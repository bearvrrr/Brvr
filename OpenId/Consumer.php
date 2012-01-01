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
 * @copyright  Copyright 2011 (c) Andrew Bates <andrew.bates@cantab.net>
 * @version    0.1
 * @category   Brvr
 * @package    Brvr_OpenId
 * @subpackage Brvr_OpenId_Consumer
 */

/**
 * @see Zend_OpenId
 */
require_once 'Zend/OpenId.php';

/**
 * @see Zend_Http_Client
 */
require_once 'Zend/Http/Client.php';

/**
 * @see Brvr_OpenId_Consumer_Discovery
 */
require_once 'Brvr/OpenId/Consumer/Discovery.php';

/**
 * @see Brvr_OpenId_Consumer_Association
 */
require_once 'Brvr/OpenId/Consumer/Association.php';

/**
 * @see Brvr_OpenId_ConsumerComponent
 */
require_once 'Brvr/OpenId/ConsumerComponent.php';

/**
 * OpenID consumer implementation
 *
 * @category   Brvr
 * @package    Brvr_OpenId
 * @subpackage Brvr_OpenId_Consumer
 *
 * @todo support for extensions
 * @todo document config options (currently just passed to Zend Config with
 *     the exception of 'allowDumbMode'
 */
class Brvr_OpenId_Consumer extends Brvr_OpenId_ConsumerComponent
{
    /**
     * OpenId services associated with given identifier
     *
     * @var object Brvr_OpenId_Discovery
     */
    private $_services = null;
    
    /**
     * OpenId association
     *
     * @var object Brvr_OpenId_Association
     */
    private $_association = null;
    
    /**
     * OpenId extensions
     *
     * @var array of Brvr_OpenId_Consumer_Extension objects
     */
    private $_extensions = array();
    
    /**
     * Object to store session information
     *
     * @var object Zend_Session_Abstract
     */
    private $_session = null;
    
    /**
     * Whether or not requests are to be made with an association
     */
    private $_dumbMode = false;
    
    /**
     * Server response to authentication of an openid identifier
     *
     * @var null|string
     */
    private $_mode = null;
    
    /**
     * OpenId identity derived from verification of a positev authenication
     * response
     *
     * @var null|string
     */
    private $_identity = null;
    
    /**
     * Magic Methods
     */
    
    /**
     * Return class property
     *
     * Only read only properties 'mode' and 'identity' are available.
     *
     * @param string $property name of class property
     * @return string properties mode and identity SHOULD always be strings
     * @throws object Brvr_OpenId_Consumer_Exception if $property does not
     *  equal 'mode' or 'identity'
     */
    public function __get($property)
    {
        if ($property === 'mode') {
            return $this->_mode;
        }
        
        if ($property === 'identity') {
            if ($this->_mode !== null) {
                return $this->_identity;
            }
            return null;
        }
        
        /**
         * @see Brvr_OpenId_Consumer_Association_Exception
         */
        require_once 'Brvr/OpenId/Consumer/Exception.php';
        throw new Brvr_OpenId_Consumer_Exception("Property '$property' does "
            . "not exist");
    }
    /**
     * Public methods
     */
    
    /**
     * Set object to be used for performing discovery on an identifier
     *
     * @param Brvr_OpenId_Consumer_Discovery $discoveryObj
     * @throws Brvr_OpenId_Consumer_Exception
     * @return Brvr_OpenId_Consumer
     */
    public function setDiscovery($discoveryObj)
    {
        if (!($discoveryObj instanceof Brvr_OpenId_Consumer_Discovery)) {
            /**
             * @see Brvr_OpenId_Consumer_Exception
             */
            require_once 'Brvr/OpenId/Consumer/Exception.php';
            throw new Brvr_OpenId_Consumer_Exception('Paramater $discoveryObj'
                . ' must be an instance of Brvr_OpenId_Consumer_Discovery');
        }
        
        $this->_services = $discoveryObj;
        return $this;
    }
    
    /**
     * Set object to be used to establish an association with an OpenId provider
     *
     * @param Brvr_OpenId_Consumer_Association $associationObj
     * @throws Brvr_OpenId_Consumer_Exception
     * @return Brvr_OpenId_Consumer
     */
    public function setAssociation($associationObj)
    {
        if (!($associationObj instanceof Brvr_OpenId_Consumer_Association)) {
            /**
             * @see Brvr_OpenId_Consumer_Exception
             */
            require_once 'Brvr/OpenId/Consumer/Exception.php';
            throw new Brvr_OpenId_Consumer_Exception('Paramater $discoveryObj'
                . ' must be an instance of Brvr_OpenId_Consumer_Association');
        }
        
        $this->_association = $associationObj;
        return $this;
    }
    
    /**
     * Set object to be used to store information between redirects
     *
     * @param Zend_Session_Abstract $sessionObj
     * @throws Brvr_OpenId_Consumer_Exception
     * @return Brvr_OpenId_Consumer
     */
    public function setSession($sessionObj)
    {
        if (!($sessionObj instanceof Zend_Session_Abstract)) {
            /**
             * @see Brvr_OpenId_Consumer_Exception
             */
            require_once 'Brvr/OpenId/Consumer/Exception.php';
            throw new Brvr_OpenId_Consumer_Exception('Paramater $sessionObj'
                . ' must be an instance of Zend_Session_Abstract');
        }
        
        $this->_session = $sessionObj;
        return $this;
    }
    
    /**
     * Specify not to use an association as part of the authentication request
     *
     * This only affects the authentication request. The OpenId provider may
     * invalidate an association in which case direct verification will be
     * necessary anyway.
     *
     * @return boolean True on success, false if using dumb mode prohibited
     */
    public function useDumbMode()
    {
        if ($this->_config['allowDumbMode'] === true) {
            $this->_dumbMode = true;
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Authenticate user with possible user interaction with OpenId provider
     *
     * This is the first step of OpenID authentication process.
     * On success the function does not return (it does HTTP redirection to
     * server and exits). On failure it returns false.
     *
     * @param string $uri OpenId identifier. Either a claimed id or an OpenId
     *     provider identifier. May have a url or xri form.
     * @param string $returnTo Uri to return end user to after authentication
     *     with OpenId provider
     * @param string $realm Uri pattern for sites for which the authentication
     *     is to be valid. See {@link
     *     http://openid.net/specs/openid-authentication-2_0.html#realms} for
     *     more information
     * @param Zend_Controller_Response_Abstract $response an optional response
     *     object to perform HTTP or HTML form redirection
     * @return boolean false on failure
     */
    public function authLogin(
        $uri,
        $returnTo = null,
        $realm = null,
        Zend_Controller_Response_Abstract $response = null)
    {
        return $this->authRequest(false, $uri, $returnTo, $realm, $response);
    }
    
    /**
     * Authenticate user without user interaction with OpenId provider
     *
     * This is the first step of OpenID authentication process.
     * On success the function does not return (it does HTTP redirection to
     * server and exits). On failure it returns false.
     *
     * @param string $uri OpenId identifier. Either a claimed id or an OpenId
     *     provider identifier. May have a url or xri form.
     * @param string $returnTo Uri to return end user to after authentication
     *     with OpenId provider
     * @param string $realm Uri pattern for sites for which the authentication
     *     is to be valid. See {@link
     *     http://openid.net/specs/openid-authentication-2_0.html#realms} for
     *     more information
     * @param Zend_Controller_Response_Abstract $response an optional response
     *     object to perform HTTP or HTML form redirection
     * @return boolean false on failure
     */
    public function authCheck(
        $uri,
        $returnTo = null,
        $realm = null,
        Zend_Controller_Response_Abstract $response = null)
    {
        return $this->authRequest(true, $uri, $returnTo, $realm, $response);
    }
    
    /**
     * Check the response from an authentication request to an OpenID provider
     *
     * Returns either a string containing the servers reposne (derived from
     * openid.mode) or boolean false if the verification process failed.
     *
     * Three valid server responses are defined:
     * id_res - successful user authentication
     * setup_needed - Following an immediate request, the user must provide
     *     credentials to the OpenID provider for authentication to proceed
     * cancel - Authentication was unsuccessful and the user must be treated as
     *     non-authenticated
     *
     * @param array $params associative array returned from server via the user
     *      to be verified
     * @return mixed
     * @todo ste identity variable to class property?
     */
    public function verify($params)
    {
        $this->setError('');

        $this->setIdentity($params);
        
        if (empty($params['openid_mode'])) {
            $this->setError('Verification failed: Missing openid.mode');
            return false;
        }
        
        /* Check OpenId mode and stop verification if not id_res */
        if ($params['openid_mode'] != 'id_res') {
            if ($params['openid_mode'] === 'error') {
                $error = 'Verification failed: Indirect request error';
                if (!empty($params['openid_error'])) {
                    $error .= '(' . $params['openid_error'] . ')';
                }
                $this->setError($error);
                return false;
            } elseif ($params['openid_mode'] === 'setup_needed') {
                $this->setError('Verification failed: mode=setup_needed');
                return $this->_mode = 'setup_needed';
            } elseif ($params['openid_mode'] === 'cancel') {
                $this->setError('Verification failed: mode=cancel');
                return $this->_mode = 'cancel';
            }
            $this->setError('Wrong openid.mode \'' .$params['openid_mode'] .
                '\' != \'id_res\'');
            return false;
        }
        
        
        if (empty($params['openid_return_to'])) {
            $this->setError('Verification failed: Missing openid.return_to');
            return false;
        }
        if (empty($params['openid_signed'])) {
            $this->setError('Verification failed: Missing openid.signed');
            return false;
        }
        if (empty($params['openid_sig'])) {
            $this->setError('Verification failed: Missing openid.sig');
            return false;
        }
        
        if (empty($params['openid_assoc_handle'])) {
            $this->setError('Verification failed: Missing ' 
                           . 'openid.assoc_handle');
            return false;
        }
        
        /* OpenID 2.0 (11.1 Verifying the return url) */
        if (!$this->verifyAssertionReturnTo($params)) {
            return false;
        }
        
        if ($this->getVersion($params) >= 2.0) {
            if (empty($params['openid_response_nonce'])) {
                $this->setError('Verification failed: Missing '
                               . 'openid.response_nonce');
                return false;
            }
            if (empty($params['openid_op_endpoint'])) {
                $this->setError('Verification failed: Missing '
                               . 'openid.op_endpoint');
                return false;
            /* OpenID 2.0 (11.3) Checking the Nonce */
            } elseif (!$this->getStorage()->isUniqueNonce(
                $params['openid_op_endpoint'], 
                $params['openid_response_nonce'])) {
                $this->setError('Duplicate openid.response_nonce');
                return false;
            }
        }
        
        
        if (!empty($params['openid_invalidate_handle'])) {
            $invalidAssociation = $this->storage->getAssociationByHandle(
                $params['openid_invalidate_handle']);
            if ($invalidAssociation !== false) {
                $this->_storage->delAssociation($invalidAssociation['url']);
                unset($invalidAssociation);
            }
        }
        
        /* Verify the signed fields */
        $assoc = $this->getStorage()
                      ->getAssociationByHandle($params['openid_assoc_handle']);
        
        if ($assoc !== false) {
            if (!$this->verifySignatureByAssociation($params,
                                                     $assoc['url'],
                                                     $assoc['macFunc'],
                                                     $assoc['secret'])
            ) {
                return false;
            }
            if (!$this->verifyDiscoveredInformation($params)) {
                return false;
            }
        } else {
            if (!$this->verifySignatureDirect($params)) {
                return false;
            }
        }
        
        /* Verify parameters used by extensions */
        // Would go here
        
        return $this->_mode = 'id_res';
    }
    
    /**
     * Protected/ private methods
     */
    
    /**
     * Authenticate user with or without user interaction with OpenId provider
     *
     * This is the first step of OpenID authentication process.
     * On success the function does not return (it does HTTP redirection to
     * server and exits). On failure it returns false.
     *
     * @param boolean $immediate True attempt authentication without user
     *     interaction.
     * @param string $uri OpenId identifier. Either a claimed id or an OpenId
     *     provider identifier. May have a url or xri form.
     * @param string $returnTo Uri to return end user to after authentication
     *     with OpenId provider
     * @param string $realm Uri pattern for sites for which the authentication
     *     is to be valid. See {@link
     *     http://openid.net/specs/openid-authentication-2_0.html#realms} for
     *     more information
     * @param Zend_Controller_Response_Abstract $response an optional response
     *     object to perform HTTP or HTML form redirection
     * @return boolean false on failure
     */
    protected function authRequest(
        $immediate,
        $uri,
        $returnTo = null,
        $realm = null,
        Zend_Controller_Response_Abstract $response = null)
    {
        $this->setError('');
        
        if (!Zend_OpenId::normalize($uri)) {
            $this->setError('Normalisation failed');
            return false;
        }
        

        $service = $this->discover($uri);
        if ($service === false) {
            $this->setError('Discovery failed: ' . $this->getError());
            return false;
        }
        
        $service->rewind();
        
        if (!$this->_dumbMode) {
            $associationError = array();
            while ($service->valid()) {
                $association = $this->associate($service->endpoint,
                    $service->version);
                
                if ($association !== false) {
                    break;
                }
                $associationError[] = 'Unable to resolve \''
                                    . $service->endpoint . '\' ('
                                    . $this->getError() . ')';
                
                $service->next();
            }
            
            if ($association === false) {
                if ($this->useDumbMode()) {
                    $service->rewind();
                } else {
                    $this->setError('Association failed, cannot fall back to '
                                   . 'dumb mode ('
                                   . implode(', ', $associationError)
                                   . ')');
                    return false;
                }
            }
            //unset($association);
            unset($associationError);
        }
        
        $params = array();
        if ($service->version >= 2.0) {
            $params['openid.ns'] = Zend_OpenId::NS_2_0;
        }

        $params['openid.mode'] = $immediate ?
            'checkid_immediate' : 'checkid_setup';
        
        $params['openid.identity'] = $service->localId;
        if (empty($params['openid.identity'])) {
            /*
                $uri must be an OpenId provider identifier since no local id
                is given. An identifier must be selected during authentication
                with OpenId provider
            */
            $params['openid.identity'] = 'http://specs.openid.net/auth/2.0/'
                                       . 'identifier_select';
            $params['openid.claimed_id'] = $params['openid.identity'];
        } else {
            $params['openid.claimed_id'] = $uri;
        }
        
        /*
            In versions prior to 2.0 no claimed_id key was specified. Since it
            cannot be relied upon in the authentication response, it must be
            stored..
        */
        if ($service->version <= 2.0) {
            if ($this->_session !== null) {
                $this->_session->identity = $params['openid.identity'];
                $this->_session->claimed_id = $params['openid.claimed_id'];
            } else if (defined('SID')) {
                $_SESSION['zend_openid'] = array(
                    'identity' => $params['openid.identity'],
                    'claimed_id' => $params['openid.claimed_id']);
            } else {
                require_once 'Zend/Session/Namespace.php';
                $this->_session = new Zend_Session_Namespace('zend_openid');
                $this->_session->identity = $params['openid.identity'];
                $this->_session->claimed_id = $params['openid.claimed_id'];
            }
        }

        if (!$this->_dumbMode) {
            $params['openid.assoc_handle'] = $association->handle;
        }

        $params['openid.return_to'] = Zend_OpenId::absoluteUrl($returnTo);
        if ($this->_session !== null) {
            $this->_session->returnTo = $params['openid.return_to'];
        } else if (defined('SID')) {
            if (array_key_exists($_SESSION['zend_openid'])){
                $_SESSION['zend_openid']['returnTo'] = 
                                                    $params['openid.return_to'];
            } else {
                $_SESSION['zend_openid'] = array(
                'returnTo' => $params['openid.returnTo']);
            }
        } else {
            require_once 'Zend/Session/Namespace.php';
            $this->_session = new Zend_Session_Namespace('zend_openid');
            $this->_session->identity = $service->localId;
            $this->_session->claimed_id = $claimedId;
        }

        if (empty($realm)) {
            $realm = Zend_OpenId::selfUrl();
            if ($realm[strlen($realm)-1] != '/') {
                $realm = dirname($realm);
            }
        }
        if ($service->version >= 2.0) {
            $params['openid.realm'] = $realm;
        } else {
            $params['openid.trust_root'] = $realm;
        }
        
        /**
         * @todo Extension stuff
         */
        /*
        if (!Zend_OpenId_Extension::forAll($extensions, 'prepareRequest', $params)) {
            $this->setError("Extension::prepareRequest failure");
            return false;
        }*/

        Zend_OpenId::redirect($service->endpoint, $params, $response);
        return true;
    }
    
    /**
     * Get a list of services associated with an identifier
     *
     * @param $url OpenId identifier to perform discovery on
     * @return Brvr_OpenId_Consumer_Discovery|boolean: An iterator object 
     *     containing a list of services on success or boolean false on failure
     */
    protected function discover($url)
    {
        if ($this->_services === null) {
            $this->_services = new Brvr_OpenId_Consumer_Discovery(
                                        $this->getStorage(),
                                        $this->_config
                                        );
            
            $this->_services->setHttpClient($this->getHttpClient());
        }
        
        if (!$this->_services->resolve($url)) {
            $this->setError($this->_services->getError());
            return false;
        }
        
        return $this->_services;
    }
    
    /**
     * Establish an association with an OpenId provider
     *
     * @param string $url OpenId endpoint
     * @version float $version Open protocor version to use.
     * @return mixed Brvr_OpenId_Consumer_Association|boolean: false is returned
     *      failure to associate
     */
    protected function associate($url, $version)
    {
        if ($this->_association === null) {
            $this->_association = new Brvr_OpenId_Consumer_Association(
                                            $this->getStorage()
                                            $this->_config
                                            );
            
            $this->_association->setHttpClient($this->getHttpClient());
        } 
        
        if (!$this->_association->resolve($url, $version)) {
            $this->setError($this->_association->getError());
            return false;
        }
        
        return $this->_association;
    }
    
    /**
     * Deriwe the identifier about which a positve assertion is being made from
     * the OpenId provider response
     *
     * @param array $params response params from server
     */
    protected function setIdentity($params)
    {
        $version = $this->getVersion($params);

        if (isset($params['openid_claimed_id'])) {
            $identity = $params['openid_claimed_id'];
        } else if (isset($params['openid_identity'])){
            $identity = $params['openid_identity'];
        } else {
            $identity = null;
            // Can only verify extensions at this point
        }
        
        if ($version < 2.0 && !isset($params['openid_claimed_id'])) {
            if ($this->_session !== null) {
                if ($this->_session->identity === $identity) {
                    $identity = $this->_session->claimed_id;
                }
            } else if (defined('SID')) {
                if (isset($_SESSION['zend_openid']['identity']) &&
                    isset($_SESSION['zend_openid']['claimed_id']) &&
                    $_SESSION['zend_openid']['identity'] === $identity
                ) {
                    $identity = $_SESSION['zend_openid']['claimed_id'];
                }
            } else {
                require_once 'Zend/Session/Namespace.php';
                $this->_session = new Zend_Session_Namespace('zend_openid');
                if ($this->_session->identity === $identity) {
                    $identity = $this->_session->claimed_id;
                }
            }
        }
        
        $this->_identity = $identity;
    }
    
    protected function getVersion($params)
    {
        $version = 1.1;
        if (isset($params['openid_ns']) &&
            $params['openid_ns'] == Zend_OpenId::NS_2_0) {
            $version = 2.0;
        }
        
        return $version;
    }
    
    /**
     * Get identifier that is being verified
     *
     * This method is designed to be used by methods involved in verification
     * which expect that $this->_indentity be set os returns false and sets an
     * error if is not
     *
     * @return string|boolean
     */ 
    protected function getVerifyId()
    {
        if (empty($this->_identity)) {
            $this->setError('Missing openid.claimed_id and openid.identity');
            return false;
        }
        
        return $this->_identity;
    }
    
    /**
     * Check that the correct return_to url specified by OpenId provider matches
     * expected/current url
     *
     * If the return_to url is correct then true is returned. Otherwise false is
     * return on failure
     *
     * @param array $params response parameters from server
     * @return boolean
     */
    protected function verifyAssertionReturnTo($params)
    {
        /*
            11.1.  Verifying the Return URL
            
            To verify that the "openid.return_to" URL matches the URL that is
            processing this assertion:
            
                * The URL scheme, authority, and path MUST be the same between
                  the two URLs.
                * Any query parameters that are present in the
                  "openid.return_to" URL MUST also be present with the same
                  values in the URL of the HTTP request the RP received.
        */
        if ($this->_session !== null) {
            $requestReturnTo = $this->_session->returnTo;
        } else if (defined('SID')) {
            if (isset($_SESSION['zend_openid']['returnTo'])) {
                $requestReturnTo = $_SESSION['zend_openid']['returnTo'];
            } else {
                $requestReturnTo = null;
            }
        } else {
            require_once 'Zend/Session/Namespace.php';
            $this->_session = new Zend_Session_Namespace('zend_openid');
            $requestReturnTo = $this->_session->returnTo;
        }
        
        $currentUrl = Zend_OpenId::selfUrl();
        if ($params['openid_return_to'] !== $currentUrl) {
            if (!$this->verifyUrl($requestReturnTo, $currentUrl)) {
                // include any error message?
                $this->setError('Verification failed: Wrong openid.return_to '
                               . '\'' . $params['openid_return_to'] . '\' != \''
                               . Zend_OpenId::selfUrl() . '\' ('
                               . $this->getError() . ')');
                return false;
            }
        }
        if ($params['openid_return_to'] !== $requestReturnTo) {
            $this->setError('Verification failed: Wrong openid.return_to '
                           . '\'' . $params['openid_return_to'] . '\' != \''
                           . $requestReturnTo . '\' (request return url)');
            return false;
        }
        
        return true;
    }
    
    /**
     * Check whether two urls match
     *
     * When comparing the query fragment of the url, any OpenId parameters are
     * ignored since if they are transferred using a get request, they will have
     * necessarily been added.
     *
     * @param string $expectedUrl
     * @param string $actualUrl
     * @return boolean True if urls match, false otherwise
     */
    protected function verifyUrl($expectedUrl, $actualUrl)
    {
        try {
            $eUrl = Zend_Uri::factory($expectedUrl);
            $aUrl = Zend_Uri::factory($actualUrl);
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
        
        // check host
        if ($eUrl->getHost() !== $aUrl->getHost()) {
            $this->setError('Host names do not match');
            return false;
        }
        
        // check query
        $eQuery = $eUrl->getQueryAsArray();
        $aQuery = $aUrl->getQueryAsArray();
        
        // Remove all parameters using the OpenId namespace
        if ($aQuery !== false) {
            $aQueryKeys = array_keys($aQuery);
            foreach ($aQueryKeys as $key) {
                if (strpos($key, 'openid.') === 0) {
                    unset($aQuery[$key]);
                }
            }
        }
        
        if (empty($aQuery)) {
            $aQuery = false;
        };
        
        if ($eQuery !== $aQuery) {
            $this->setError('Queries do not match');
            return false;
        }
        
        return true;
    }
    
    /**
     * Check that OpenId provider making assertion has the authority to make
     * assertions for identitier being verified
     *
     * If the assertion can be valid then true is returned. Otherwise false is
     * return on failure
     *
     * @param array $params response parameters from server
     * @return boolean
     */
    protected function verifyDiscoveredInformation($params)
    {
        /**
         * @todo ensure this function does not break openid 1.*
         */
        /*
            11.2.  Verifying Discovered Information
            
            If the Claimed Identifier in the assertion is a URL and contains a
            fragment, the fragment part and the fragment delimiter character 
            "#" MUST NOT be used for the purposes of verifying the discovered
            information.
            
            If the Claimed Identifier is included in the assertion, it MUST have
            been discovered (Discovery) by the Relying Party and the information
            in the assertion MUST be present in the discovered information. The
            Claimed Identifier MUST NOT be an OP Identifier.
            
            If the Claimed Identifier was not previously discovered by the
            Relying Party (the "openid.identity" in the request was
            "http://specs.openid.net/auth/2.0/identifier_select" or a different
            Identifier, or if the OP is sending an unsolicited positive
            assertion), the Relying Party MUST perform discovery on the Claimed
            Identifier in the response to make sure that the OP is authorized to
            make assertions about the Claimed Identifier.
            
            If no Claimed Identifier is present in the response, the assertion
            is not about an identifier and the RP MUST NOT use the User-supplied
            Identifier associated with the current OpenID authentication
            transaction to identify the user. Extension information in the
            assertion MAY still be used.
        */
        $id = $this->getVerifyId();
        if ($id === false) {
            $this->setError('Verificiation failed: ' . $this->getError());
            return false;
        }
        
        $discovery = $this->discover($id);
        if ($discovery === false) {
            $this->setError('Verification failed: Discovery failed: ' .
                                                            $this->getError());
            return false;
        }
        
        $verified = false;
        while ($discovery->valid()) {
            if (!empty($params['openid_op_endpoint']) &&
                $params['openid_op_endpoint'] == $discovery->endpoint
            ) {
                $verified = true;
                break;
            }
            $discovery->next();
        }
        
        if ($verified === false) {
            $this->setError('Verification failed: OP cannot make assertions '
                                                    . 'about this claimed id');
            return false;
        }
        
        /**
         * @todo check version
         */
        
        return true;
    }
    
    /**
     * Verify authentication response signature using a stroed association
     *
     * @param array $params Authentication respnse parameters
     * @param string $url Endpoint for association
     * @param string $macFunc name of selected hashing algorithm (sha1, sha256)
     * @param string $secret shared secret key used for generating the HMAC
     *    variant of the message digest
     * @return boolean
     */
    protected function verifySignatureByAssociation(
        $params,
        $url,
        $macFunc,
        $secret)
    {
        $signed = explode(',', $params['openid_signed']);
        $data = '';
        /* Generate key value form encoded string from list of signed fields */
        foreach ($signed as $key) {
            $paramKey = 'openid_' . strtr($key,'.','_');
            if (!array_key_exists($paramKey, $params)) {
                $this->setError('Verification failed: Signature check failed'
                    . ' (expected signed keys missing from response)');
                return false;
            }
            $data .= "$key:{$params[$paramKey]}\n";
        }
        
        /* Check the message signature against hashed key-value form */
        if (!(base64_decode($params['openid_sig']) ==
                                Zend_OpenId::hashHmac($macFunc, $data, $secret))
        ) {
            $this->getStorage()->delAssociation($url);
            $this->setError('Verification failed: Signature check failed');
            return false;
        }
        
        return true;
    }
    
    /**
     * Verify authentication response by making a direct request to OpenId
     * provider endpoint
     *
     * @uses _verifyDiscoveredInformation
     * @param array $params Authentication response parameters
     * @return boolean
     */
    protected function verifySignatureDirect($params)
    {
        /*
            Must verify discovery information since a direct request must be
            made to an OP
        */
        if (!$this->verifyDiscoveredInformation($params)) {
            return false;
        }
        
        $verificiationParams = array();
        foreach ($params as $key => $val) {
            if (strpos($key, 'openid_ns_') === 0) {
                $key = 'openid.ns.' . substr($key, strlen('openid_ns_'));
            } else if (strpos($key, 'openid_sreg_') === 0) {
                $key = 'openid.sreg.' . substr($key, strlen('openid_sreg_'));
            } else if (strpos($key, 'openid_') === 0) {
                $key = 'openid.' . substr($key, strlen('openid_'));
            }
            $verificiationParams[$key] = $val;
        }
        $verificiationParams['openid.mode'] = 'check_authentication';
        
        
        try {
            $httpClient = $this->getHttpClient();
            $httpClient->setHeaders('Accept', false)
                       ->setUri($params['openid_op_endpoint'])
                       ->resetParameters()
                       ->setMethod(Zend_Http_Client::POST)
                       ->setParameterPost($verificiationParams);
            $response = $httpClient->request();
        } catch (Exception $e) {
            
            $this->setError('HTTP Request failed: ' . $e->getMessage());
            return false;
        }
        if ($response->getStatus() != 200) {
            $this->setError('Verification Failed: direct signature '
                . 'verification HTTP request failed');
            return false;
        }
        
        $rParams = $this->responseToArray($response->getBody());
        
        if (!empty($ret['invalidate_handle'])) {
            $assoc = $this->getStorage()->getAssociationByHandle(
                                                $rParams['invalidate_handle']);
            if ($assoc !== false) {
                $this->getStorage()->delAssociation($assoc['url']);
            }
        }
        
        if (!(isset($rParams['is_valid']) && $rParams['is_valid'] == 'true')) {
            $this->setError('Verification failed: Signature invalid');
            return false;
        }
        
        return true;
    }
} // Brvr_OpenId_Consumer