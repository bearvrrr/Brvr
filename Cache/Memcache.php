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
 * @see Brvr_Cache_Abstract
 */
require_once 'Brvr/Cache/Abstract.php';

/**
 * Caching adapter implementation using Memcache as a backend
 *
 * @category  Brvr
 * @package   Brvr_Cache
 */
class Brvr_Cache_Memcache extends Brvr_Cache_Abstract
{
    /**
     * Constant use to specify array of server configuration parameters
     */
    const CONFIG_SERVERS = 'Memcache servers';
    /**
     * Constants used to specify configuration parameters
     */
    const CONFIG_SERVER_HOST               = 'Host';
    const CONFIG_SERVER_PORT               = 'Port';
    const CONFIG_SERVER_PERSISTENT         = 'Persistent';
    const CONFIG_SERVER_DEFAULT_PERSISTENT = 'Default persistent';
    const CONFIG_SERVER_WEIGHT             = 'Weight';
    
    /**
     * Memcache object instance
     *
     * @var object Memcache
     */
    private $_memcache;
    
    /**
     * Store variable in cache
     *
     * Variables that cannot be stored will not be stored and the method will
     * return false
     *
     * Should the value boolean:false be stored, it will be converted to the
     * string 'b:0;'. This is because memcache::get() will return false on
     * failure or if an item does not exist so a stored false must be signified
     * as different from failure. It was decided null was less likely to be
     * cached than false. There is no empirical evidence for this.
     *
     * @param string $handle
     * @param mixed $value
     * @param integer $expire Expire time in seconds. Use 0 to never expire
     * @throws Brvr_Cache_Exception
     * @return boolean True on success or false on failure
     */
    public function set($handle, $value, $expire = null)
    {
        if (!$this->isSerializable($value)) {
            return false;
        }
        
        if ($value === false) {
            $value = 'b:0;'; // result of serialize(false);
        }
        if ($expire === null) {
            $expire = $this->_config[self::CONFIG_DEFAULT_EXPIRE_TIME];
        }
        
        /**
         * Memcache::set() behaviour is not always consistent:
         * {@link http://www.php.net/manual/en/memcache.set.php#84032}
         */
        $replaced = $this->_memcache->replace($handle, $value, false, $expire);
        if (!$replaced) {
            return $this->_memcache->set($handle, $value, false, $expire);
        }
        return $this->setDeep($handle, $value, $expire);
    }
    
    /**
     * Retrive cached item by handle
     *
     * @param string $handle
     * @return mixed null is returned if handle not found or a failure occurs
     */
    public function get($handle)
    {
        $value = $this->_memcache->get($handle);
        
        if ($value === false) {
            return $this->getDeep($handle);
        }
        
        if ($value === 'b:0;') {
            return false;
        }
        
        return $value;
    }
    
    /**
     * Set class configuration and apply defaults to required values that are
     * not specified
     *
     * @param array $params
     * @throws Brvr_Cache_Exception
     */
    protected function setConfig($params)
    {
        parent::setConfig($params);
        if (!isset($this->_config[self::CONFIG_SERVER_DEFAULT_PERSISTENT])) {
            $this->_config[self::CONFIG_SERVER_DEFAULT_PERSISTENT] = false;
        }
        if (!isset($this->_config[self::CONFIG_SERVERS])) {
            $this->_config[self::CONFIG_SERVERS] = array(
                array(
                    self::CONFIG_SERVER_HOST => 'localhost',
                    self::CONFIG_SERVER_PORT => 11211
                    )
                );
        }
        if (!is_array($this->_config[self::CONFIG_SERVERS])) {
            /**
             * @see Brvr_Cache_Exception
             */
            require_once 'Brvr/Cache/Exception.php';
            $message = 'Configuration parameter ' . self::CONFIG_SERVERS
                     . ' is expected to be an array';
            throw new Brvr_Cache_Exception($message);
        }
        
        $servers = $this->_config[self::CONFIG_SERVERS];
        $memcache = new Memcache();
        foreach ($servers as $server) {
            if (!is_array($server) ||
                !isset($server[self::CONFIG_SERVER_HOST]) ||
                !isset($server[self::CONFIG_SERVER_PORT])
            ) {
                /**
                 * @see Brvr_Cache_Exception
                 */
                require_once 'Brvr/Cache/Exception.php';
                $message = 'Memcache server configuration is expected to '
                         . 'be at least an array with the keys \''
                         . self::CONFIG_SERVER_HOST . '\' and \''
                         . self::CONFIG_SERVER_PORT . '\' set';
                throw new Brvr_Cache_Exception($message);
            }
            if (!isset($server[self::CONFIG_SERVER_PERSISTENT])) {
                $server[self::CONFIG_SERVER_PERSISTENT] = 
                        $this->_config[self::CONFIG_SERVER_DEFAULT_PERSISTENT];
            }
            
            if (isset($server[self::CONFIG_SERVER_WEIGHT])) {
                $addStatus = $memcache->addServer(
                    $server[self::CONFIG_SERVER_HOST],
                    $server[self::CONFIG_SERVER_PORT],
                    $server[self::CONFIG_SERVER_PERSISTENT],
                    $server[self::CONFIG_SERVER_WEIGHT]
                    );
            }
            else {
                $addStatus = $memcache->addServer(
                    $server[self::CONFIG_SERVER_HOST],
                    $server[self::CONFIG_SERVER_PORT],
                    $server[self::CONFIG_SERVER_PERSISTENT]
                    );
            }
            
            if (!$addStatus) {
                /**
                 * @see Brvr_Cache_Exception
                 */
                require_once 'Brvr/Cache/Exception.php';
                $message = 'Memcache configuration failed: Failed to add '
                         . 'server \'' . $server[self::CONFIG_SERVER_HOST]
                         . ':' . $server[self::CONFIG_SERVER_PORT] . '\'';
                throw new Brvr_Cache_Exception($message);
            }
        }
        $this->_memcache = $memcache;
    }
} // Class Brvr_Cache_Memcache
