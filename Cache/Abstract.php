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
 * @version   0.1
 * @category  Brvr
 * @package   Brvr_Cache
 */

/**
 * @see Brvr_Cache_Interface
 */
require_once 'Brvr/Cache/Interface.php';

/**
 * Caching implementation
 *
 * @category Brvr
 * @package  Brvr_Cache
 */
abstract class Brvr_Cache_Abstract implements Brvr_Cache_Interface
{
    /**
     * Nested Brvr_Cache_Interface instance
     *
     * @var Brvr_Cache_Interface
     */
    protected $_deeperCache;
    
    /**
     * Cache configuration
     *
     * @var array
     */
    protected $_config = array();
    
    /**
     * Constructor
     *
     * @param array|Zend_Config $config
     * @param Brvr_Cache_Interface $deeperCache (optional)
     * @throws Brvr_Cache_Exception
     */
    public function __construct($config, $deeperCache = null)
    {
        $this->setConfig($config);
        if ($deeperCache !== null &&
            !($deeperCache instanceof Brvr_Cache_Interface)
        ) {
            /**
             * @see Brvr_Cache_Exception
             */
            require_once 'Brvr/Cache/Exception.php';
            $message = 'Parameter $deeperCache expected to be an instance of '
                     . 'Brvr_Cache_Interface';
            throw new Brvr_Cache_Exception($message);
        }
        $this->_deeperCache = $deeperCache;
    }
    
    /**
     * Protected methods
     */
    
    /**
     * Set class configuration and apply defaults to required values that are
     * not specified
     *
     * @param array $params
     * @throws Brvr_Cache_Exception
     */
    protected function setConfig($params)
    {
        if (!empty($this->_config)) {
            /**
             * @see Brvr_Cache_Exception
             */
            require_once 'Brvr/Cache/Exception.php';
            $message = 'Class configuration has already been set';
            throw new Brvr_Cache_Exception($message);
        }
        
        if ($params instanceof Zend_Config) {
            $params = $config->toArray();
        }
        if (!is_array($params)) {
            /**
             * @see Brvr_Cache_Exception
             */
            require_once 'Brvr/Cache/Exception.php';
            $message = 'Parameter $config expected to be an array or instance '
                     . 'of Zend_Config';
            throw new Brvr_Cache_Exception($message);
        }
        
        $this->_config      = $params;
        
        // Default expiry
        if (!isset($this->_config[self::CONFIG_DEFAULT_EXPIRE_TIME])) {
            $this->_config[self::CONFIG_DEFAULT_EXPIRE_TIME] = 300; // 5 minutes
        }
        
        // Set method will not fail if nested cache fails to store data
        if (!isset($this->_config[self::CONFIG_SET_RES_STRICT])) {
            $this->_config[self::CONFIG_SET_RES_STRICT] = false;
        }
    }
    
    /**
     * Store value in nested cache object
     *
     * @param string $handle
     * @param mixed $value
     * @param integer $expire Expire time in seconds. Use 0 to never expire
     * @throws Brvr_Cache_Exception
     * @return boolean False if nested cache setting fails and config option
     *     self::CONFIG_SET_RES_STRICT is set to true
     */
    protected function setDeep($handle, $value, $expire = null)
    {
        if ($this->_deeperCache === null) {
            return true;
        }
        if (!$this->_deeperCache->set($handle, $value, $expire) &&
            $this->getConfig(self::CONFIG_SET_RES_STRICT) === true
        ) {
            return false;
        }
        return true;
    }
    
    /**
     * Retrieve cached item from nested cache object
     *
     * @param string $handle
     * @return mixed null is returned if handle not found
     */
    public function getDeep($handle)
    {
        if ($this->_deeperCache === null) {
            return null;
        }
        return $this->_deeperCache->get($handle);
    }
    
    /**
     * Determine whether a variable can be serialized
     *
     * According to {@link http://php.net/manual/en/function.serialize.php},
     * serialize will accept all type except resource types. However passing a
     * resource to this function will just produced the serialized string for
     * an integer of value 0, with no error even with E_ALL error reporting.
     * This rules out the use of calling the serialize function and checking for
     * errors.
     *
     * This function will call itself recursively for array variables.
     *
     * With objects, it tests to see whether they implement the Serializable
     * interface, returning false if not.
     *
     * @param mixed $var
     * @param array $parents Array of parent arrays for use recursively
     * @return boolean True if the variable is serializable
     */
    protected function isSerializable($var)
    {
        if (is_resource($var)) {
            return false;
        }
        
        if (is_object($var) && !($var instanceof Serializable)) {
            return false;
        }
        
        if (is_array($var) && !empty($var)) {
            foreach ($var as $key => $value) {
                $var[$key] = null; // break self reference recursion
                if (!$this->isSerializable($value)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get ocnfig parameter by name
     *
     * @param string|int $param
     * @return mixed Null is returned if the named parameter has not been set
     */
    protected function getConfig($param)
    {
        if (!isset($this->_config[$param])) {
            return null;
        }
        return $this->_config[$param];
    }
} // Brvr_Cache