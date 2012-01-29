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
 * @copyright Copyright 2011-2012 (c) Andrew Bates <andrew.bates@cantab.net>
 * @version   0.1
 * @category  Brvr
 * @package   Brvr_Cache
 */

/**
 * @see Brvr_Cache_Abstract
 */
require_once 'Brvr/Cache/Abstract.php';

/**
 * Pseudo-caching implementation storing variables as object properties
 *
 * This implementation of the interface will store values that are not
 * serializable since they do not need to persist between scripts
 *
 * @category Brvr
 * @package  Brvr_Cache
 */
class Brvr_Cache_Object extends Brvr_Cache_Abstract
{
    /**
     * Cached data
     *
     * @var array
     */
     protected $_cache = array();
    
    /**
     * Store variable in cache
     *
     * @param string $handle
     * @param mixed $value
     * @param integer $expire Expire time in seconds. Use 0 to never expire
     * @throws Brvr_Cache_Exception
     * @return boolean True on success false on failure
     */
    public function set($handle, $value, $expire = null)
    {
        if (!is_string($handle)) {
            /**
             * @see Brvr_Cache_Exception
             */
            require_once 'Brvr/Cache/Exception.php';
            throw new Brvr_Cache_Exception('Method Brvr_Cache::set requires '
                . '$handle be a string type');
        }
        
        /*
          Probably pissing around with expire time is worthless when only
          storing as a property...
        */
        $expireTime = $this->getExpireTime($expire);
        
        $this->_cache[$handle] = array(
                                    'expire' => $expireTime,
                                    'value'  => $value
                                    );
        
        return $this->setDeep($handle, $value, $expire);
    }
    
    
    /**
     * Retrieve cached item by handle
     *
     * @param string $handle
     * @return mixed null is returned if handle not found
     */
    public function get($handle)
    {
        if (array_key_exists($handle, $this->_cache)) {
            if (time() < $this->_cache[$handle]['expire'] ||
                                    $this->_cache[$handle]['expire'] === 0) {
                return $this->_cache[$handle]['value'];
            }
            unset($this->_cache[$handle]);
            /*
                Do not return, concurrently running script could have stored
                value in persistent cache
            */
        }
        return $this->getDeep($handle);
    }
    
    /**
     * Get Expiry time as a unix time
     *
     * @param integer $timeToLive Seconds until expiry
     * @return integer
     */
    protected function getExpireTime($timeToLive = null)
    {
        if ($timeToLive === 0) {
            // Never expire
            return $timeToLive;
        }
        
        if (!is_int($timeToLive)) {
            $timeToLive = $this->_config[self::CONFIG_DEFAULT_EXPIRE_TIME];
        }
        
        return time() + $timeToLive;
    }
} // Brvr_Cache_Object