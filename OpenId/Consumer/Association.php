<?php

/**
 * Brvr Library
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license send an email to
 * andrew.bates@cantab.net so we can send you a copy immediately.
 *
 * @copyright Copyright 2011 (c) Andrew Bates <andrew.bates@cantab.net>
 * @version 0.1
 * @category Brvr
 * @package Brvr_OpenId
 * @subpackage Brvr_OpenId_Consumer
 */

/**
 * @see Zend_OpenId
 */
require_once 'Zend/OpenId.php';

/**
 * @see Brvr_OpenId_ConsumerComponent
 */
require_once 'Brvr/OpenId/ConsumerComponent.php';
 
/**
 * Class representing associations with OpenId providers
 *
 * @category Brvr
 * @package Brvr_OpenId
 * @subpackage Brvr_OpenId_Consumer
 */
class Brvr_OpenId_Consumer_Association extends Brvr_OpenId_ConsumerComponent
{
    /**
     * Information regarding association
     *
     * @var array
     */
    protected $_associationData = array();
    
    /**
     * Diffie-Hellman Key
     *
     * The type of variable stored depends on extensions available
     * @see Brvr_OpenId_Consumer_Association::getDh()
     *
     * @var mixed
     */
    private $_dh = null;
    
    /**
     * Getter
     *
     * @param string $property name of property to get
     * @return string
     */
    public function __get($property)
    {
        // check whether associated
        if (!array_key_exists($property, $this->_associationData)) {
            /**
             * @see Brvr_OpenId_Consumer_Association_Exception
             */
            require_once 'Brvr/OpenId/Consumer/Association/Exception.php';
            throw new Brvr_OpenId_Consumer_Association_Exception("Property " .
                "'$property' does not exist");
        }
        return $this->_associationData[$property];
    }
    
    /**
     * Associate with an OpenId provider and store association data
     *
     * @param string $url
     * @param string $version Version of OpenId protocol to be used to make
     *     association requests
     * @param string $privKey for testing only
     * @return boolean True on successful association, false otherwise
     */
    public function resolve($url, $version, $privKey = null)
    {
        if ($this->loadAssociation($url)) {
            return true;
        }
        
        $this->_associationData = array(
            'url'     => $url,
            'version' => $version
            );
        
        if ($version >= 2.0) {
            $params = array(
                'openid.ns'           => Zend_OpenId::NS_2_0,
                'openid.mode'         => 'associate',
                'openid.assoc_type'   => 'HMAC-SHA256',
                'openid.session_type' => 'DH-SHA256',
            );
        } else {
            $params = array(
                'openid.mode'         => 'associate',
                'openid.assoc_type'   => 'HMAC-SHA1',
                'openid.session_type' => 'DH-SHA1',
            );
        }

        $dh = $this->getDh($privKey);
        $dhDetails = Zend_OpenId::getDhKeyDetails($dh);
        
        $params['openid.dh_modulus']         = base64_encode(
            Zend_OpenId::btwoc($dhDetails['p']));
        $params['openid.dh_gen']             = base64_encode(
            Zend_OpenId::btwoc($dhDetails['g']));
        $params['openid.dh_consumer_public'] = base64_encode(
            Zend_OpenId::btwoc($dhDetails['pub_key']));
        
        $httpClient = $this->getHttpClient();
        $httpClient->setHeaders('Accept', false);
        $httpClient->setUri($url);
        
        while (true) {
            try {
                $httpClient->resetParameters();
                $httpClient->setMethod(Zend_Http_Client::POST);
                $httpClient->setParameterPost($params);
                $response = $httpClient->request();
            } catch (Exception $e) {
                $this->setError('HTTP Request failed: ' . $e->getMessage());
                return false;
            }
            if ($response->getStatus() != 200) {
                $this->setError('HTTP request failed: The server responded ' .
                    'with status code: ' . $response->getStatus());
                return false;
            }
            
            $rParams = $this->responseToArray($response->getBody());
            
            if (isset($rParams['error_code'])) {
                if ($rParams['error_code'] != 'unsupported-type') {
                    $error = 'The openid provider responded with' . 
                        $rParams['error_code'];
                    if (isset($rParams['error'])) {
                        $error .= ':' . $rParams['error'];
                    }
                    $this->setError($error);
                    return false;
                }
                
                if (isset($rParams['session_type']) &&
                    $rParams['session_type'] == 'no-encryption' &&
                    $params['openid.session_type'] != 'no-encryption') {
                    $params['openid.session_type'] = 'no-encryption';
                    if (isset($rParams['assoc_type'])) {
                        $params['openid.assoc_type'] = $rParams['assoc_type'];
                    } else {
                        $params['openid.assoc_type'] = 'HMAC-SHA256';
                    }
                } else if ($params['openid.session_type'] == 'DH-SHA256') {
                    $params['openid.session_type'] = 'DH-SHA1';
                    $params['openid.assoc_type']   = 'HMAC-SHA1';
                } else if ($params['openid.session_type'] == 'DH-SHA1') {
                    $params['openid.session_type'] = 'no-encryption';
                    $params['openid.assoc_type']   = 'HMAC-SHA256';
                } else if ($params['openid.session_type'] == 'no-encryption' &&
                    $params['openid.assoc_type'] == 'HMAC-SHA256') {
                    $params['openid.assoc_type'] = 'HMAC-SHA1';
                } else {
                    $this->setError('Unsupported session and assoc types');
                    return false;
                }
                
                if ($params['openid.session_type'] == 'no-encryption' &&
                    substr(trim($url), 0, 8) !== 'https://') {
                
                    if (substr(trim($url), 0, 5) === 'http:') {
                        // Don't use plain requests if DH encryption not used
                        $url = 'https' . substr($url, 4);
                    } else {    
                        $this->setError('Must not use no-encryption session ' .
                            'type without using SSL/TLS protocol');
                        return false;
                    }
                }
            } else {
                break;
            }
        } // end while
        
        if (!$this->validateCommonParams($rParams)) {
            return false;
        }
        
        
        if ($rParams['assoc_type'] == 'HMAC-SHA1') {
            $macFunc = 'sha1';
        } else if ($rParams['assoc_type'] == 'HMAC-SHA256' &&
            $this->_associationData['version'] >= 2.0) {
            $macFunc = 'sha256';
        } else {
            $this->setError('Unsupported assoc_type');
            return false;
        }
        
        
        $secret = $this->deriveSharedSecret($rParams, $macFunc);
        if ($secret === false) {
            return false;
        }
        
        try {
            $expire = new DateTime('now', new DateTimeZone('UTC'));
            $expire->add(new DateInterval('PT'.
                                            (int) $rParams['expires_in'] .'S'));
        } catch (Exception $e) {
            $this->setError('expire_time invalid: ' . $e->getMessage());
            return false;
        }
        
        // Successful association respose is valid if we got this far
        $this->_associationData['handle']  = $rParams['assoc_handle'];
        $this->_associationData['macFunc'] = $macFunc;
        $this->_associationData['secret']  = $secret;
        $this->_associationData['expire']  = $expire->format('c');
        
        $this->addAssociation($this->_associationData);
        
        return true;
    }
    
    /**
     * Get Diffie-Hellman key used for association.
     *
     * The variable type to represent the key depends on extensions installed
     * @see Zend_OpenId::createDhKey()
     *
     * @return mixed
     */
    private function getDh($privKey = null)
    {
        if ($this->_dh === null) {
            $this->_dh = Zend_OpenId::createDhKey(pack('H*', Zend_OpenId::DH_P),
                                                  pack('H*', Zend_OpenId::DH_G),
                                                  $privKey);
        }
        
        return $this->_dh;
    }
    
    /**
     * Extract shared secret from a successful association response
     *
     * @param array $params Association response parameters
     * @param string $macFunc Name of hashing algorithm ('sha1' or 'sha256')
     * @return string|boolean Shared secret on success or false on failure
     */
    private function deriveSharedSecret($params, $macFunc)
    {
        if ((empty($params['session_type']) ||
             ($this->getVersion() >= 2.0 && 
                 $params['session_type'] == 'no-encryption')) &&
             isset($params['mac_key'])) {
            $secret = base64_decode($params['mac_key']);
        } else if (isset($params['session_type']) &&
            $params['session_type'] == 'DH-SHA1' &&
            !empty($params['dh_server_public']) &&
            !empty($params['enc_mac_key'])) {
            $dhFunc = 'sha1';
        } else if (isset($params['session_type']) &&
            $params['session_type'] == 'DH-SHA256' &&
            $this->_associationData['version'] >= 2.0 &&
            !empty($params['dh_server_public']) &&
            !empty($params['enc_mac_key'])) {
            $dhFunc = 'sha256';
        } else {
            $this->setError('Unsupported session type');
            return false;
        }
        
        if (isset($dhFunc)) {
            $serverPub = base64_decode($params['dh_server_public']);
            $dhSec = Zend_OpenId::computeDhSecret($serverPub, $this->getDh());
            if ($dhSec === false) {
                $this->setError('DH secret computation failed');
                return false;
            }
            
            $sec = Zend_OpenId::digest($dhFunc, $dhSec);
            if ($sec === false) {
                $this->setError('Could not create digest');
                return false;
            }
            $secret = $sec ^ base64_decode($params['enc_mac_key']);
        }
        if ($macFunc == 'sha1') {
            if (Zend_OpenId::strlen($secret) != 20) {
                $this->setError('The length of the sha1 secret must be 20');
                return false;
            }
        } else if ($macFunc == 'sha256') {
            if (Zend_OpenId::strlen($secret) != 32) {
                $this->setError('The length of the sha256 secret must be 32');
                return false;
            }
        } else {
            $this->setError("Shared secret validation failed. '$macFunc' is "
                          . "not a valid hashing algorithm");
        }
        
        return $secret;
    }
    
    /**
     * Get the version of the OpenId protocol being used
     *
     * @return number|boolean Version number if set, false if version not set
     */
    private function getVersion()
    {
        if (isset($this->_associationData['version'])) {
            return $this->_associationData['version'];
        }
        return false;
    }
    
    /**
     * Check OpenId authentiaction reponse parameters common to all response
     * message types
     *
     * @param array $params
     * @return boolean True if valid, false otherwise
     */
    private function validateCommonParams($params)
    {
        $expected = array('assoc_type', 'assoc_handle', 'expires_in');
        if ($this->getVersion() >= 2.0) {
            $expected[] = 'ns';
        }
        
        $missing = array();
        
        foreach ($expected as $x) {
            if (!array_key_exists($x, $params)) {
                $missing[] = $x;
            }
        }
        
        if (!empty($missing)) {
            $this->setError('Missing required data from provider (' .
                                                implode(', ', $missing) . ')');
            return false;
        }
        
        if ($params['ns'] != Zend_OpenId::NS_2_0) {
            $this->setError('Wrong namespace definition in provider response');
            return false;
        }
        
        return true;
    }
    
    /**
     * Populate class properties with association data for an OpenId provider
     * url
     *
     * @see Brvr_OpenId_Consumer_Storage::getAssociation()
     *
     * @param string $url
     * @return boolean True on success, false otherwise
     */
    private function loadAssociation($url)
    {
        $associationParams = $this->getStorage()->getAssociation($url);
        if ($associationParams === false) {
            return false;
        }
        $this->_associationData = $associationParams;
        return true;
    }
    
    /**
     * Store association data
     *
     * @see Brvr_OpenId_Consumer_Storage::addAssociation()
     *
     * @param array $assocArray
     * @return boolean
     */
    private function addAssociation($assocArray)
    {
        return $this->getStorage()->addAssociation($assocArray);
    }
}