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
 * @see Brvr_OpenId_Consumer_Storage_Interface
 */ 
require_once 'Brvr/OpenId/Consumer/Storage/Interface.php';

/**
 * @see Zend_Http_Client
 */
require_once 'Zend/Http/Client.php';

/**
 * Base class for classes operating with an OpenId provider
 *
 * Common methods for storage, http clients, and converting responses to arrays
 *
 * @category Brvr
 * @package Brvr_OpenId
 * @subpackage Brvr_OpenId_Consumer
 */
abstract class Brvr_OpenId_ConsumerComponent
{
    private $_storage = null;
    
    protected $_config = array();
    
    private $_httpClient = null;
    
    private $_error = '';
    
    
    
    public function __construct($storage, $config = null)
    {
        if (!($storage instanceof Brvr_OpenId_Consumer_Storage_Interface)) {
            /**
             * @see Brvr_OpenId_ConsumerComponent_Exception
             */
            require_once 'Brvr/OpenId/ConsumerComponent/Exception.php';
            $error = '$storage must be an instance of '
                   . 'Brvr_OpenId_Consumer_Storage_Interface'
            throw new Brvr_OpenId_ConsumerComponent_Exception($error);
        }
        
        if ($config !== null) {
            $this->setConfig($config);
        }
        
        $this->_storage = $storage;
        
        /* Config stuff will go here */
        // ie stuff useful for http client
    }
    
    /**
     * Return the error message
     *
     * @return string
     */
    public function getError()
    {
        return $this->_error;
    }
    
    /**
     * Set the error message
     *
     * @param string $error
     */
    protected function setError($error)
    {
        $this->_error = (string) $error;
    }
    
    /**
     * Set ConsumerComponent configuration
     *
     * Child classes should accept ither an array or a Zend_Config object and
     * extract relevant configuration parameters
     *
     * @param array|Zend_Config $config
     * @throws Brvr_OpenId_ConsumerComponent_Exception
     * @return true on success, false if config has already been set
     */
    public function setConfig($config)
    {
        if (!is_array($config)) {
            if ($config instanceof Zend_Config) {
                $config = $config->toArray();
            } else {
                /**
                 * @see Brvr_OpenId_ConsumerComponent_Exception
                 */
                require_once 'Brvr/OpenId/ConsumerComponent/Exception.php';
                throw new Brvr_OpenId_ConsumerComponent_Exception(
                    '$config must be an array');
            }
        }
        
        if (!empty($this->_config)) {
            return false;
        }
        
        $this->_config = $config;
        
        return true;
    }
    
    /**
     * Set the client to use for http requests
     *
     * @param Zend_Http_Client $client
     * @throws Brvr_OpenId_ConsumerComponent_Exception when $client is not an
     *     instance of Zend_Http_Client
     */
    public function setHttpClient($client)
    {
        if (!$client instanceof Zend_Http_Client) {
            /**
             * @see Brvr_OpenId_ConsumerComponent_Exception
             */
            require_once 'Brvr/OpenId/ConsumerComponent/Exception.php';
            throw new Brvr_OpenId_ConsumerComponent_Exception('Http Client '
                . 'must be an instance of Zend_Http_Client');
        }
        $this->_httpClient = $client;
        return $this;
    }
    
    /**
     * Get the Http client being used by the class
     *
     * If no Http Client has already been instantiated, one will be instantiated
     * using config parameters
     *
     * @return object Zend_Http_Client
     */
    protected function getHttpClient()
    {
        if ($this->_httpClient === null) {
            // Should add config stuff here
            $httpConfig = $this->_config;
            $requiredParams = array(
                'maxredirects' => 4,
                'timeout'      => 15,
                'useragent'    => 'Brvr_OpenId'
                );
            
            foreach ($requiredParams as $param => $value) {
                if (!array_key_exists($param, $httpConfig)) {
                    $httpConfig[$param] = $value;
                }
            }
            
            $this->_httpClient = new Zend_Http_Client(null, $httpConfig);
        }
        
        return $this->_httpClient;
    }
    
    /**
     * Get storage adapter being used by the class
     *
     * @return object Brvr_OpenId_Consumer_Storage
     */
    protected function getStorage()
    {
        if ($this->_storage === null) {
            return false;
        }
        return $this->_storage;
    }
    
    /**
     * Produce an array of keys and values derived from the key-value form
     * used in responses to direct request
     *
     * @param string $responseString
     * @return array
     */
    protected function responseToArray($responseString)
    {
        $params = array();
        foreach(explode("\n", $responseString) as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $x = explode(':', $line, 2);
                if (is_array($x) && count($x) == 2) {
                    list($key, $value) = $x;
                    $params[trim($key)] = trim($value);
                }
            }
        }
        return $params;
    }
}
