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
 * Caching adapter implementation using Memcache as a backend
 *
 * @todo Unit testing
 *
 * @category  Brvr
 * @package   Brvr_Cache
 */
class Brvr_Cache_Adapter_Memcache implements Brvr_Cache_Adapter_Interface
{
    /**
     * Memcache object instance
     *
     * @var object Memcache
     */
    private $_memcache;
    
    /**
     * Constructor
     *
     * @param Memcache optional Memcache object to be used by adapter
     * @throws Brvr_Cache_Exception
     */
    public function __construct($memcache = null)
    {
        if ($memcache === null) {
            $memcache = new Memcache();
            $memcache->connect('localhost', 11211);
        } elseif (!($memcache instanceof Memcache)) {
            /**
             * @see Brvr_Cache_Exception
             */
            require_once 'Brvr/Cache/Exception.php';
            throw new Brvr_Cache_Exception('Method Brvr_Cache_Memcache::'
                . '__construct requires $memcache parameter be a Memcache '
                . 'instance');
        }
        $this->_memcache = $memcache;
    }
    
    /**
     * @see Memcache::connect()
     */
    public function connect()
    {
        call_user_func_array(
            array($this->_memcache, 'connect'),
            func_get_args()
            );
    }
    
    /**
     * @see Memcache::addServer()
     */
    public function addServer()
    {
        call_user_func_array(
            array($this->_memcache, 'addServer'),
            func_get_args()
            );
    }
    
    /**
     * Store variable in cache
     *
     * Variables should be checked first to ensure that they can be serialized
     *
     * Should the value boolean:false be stored, it will be converted to the
     * string 'b:0;'. This is because memcache::get() will return false on
     * failure or if an item does not exist so a stored false must be signified
     * as different from failure. It was decided null was less likely to be
     * cached than false. There is no empirical evidence for this.
     *
     * @param string $handle
     * @param mixed $value Something that can be serialized
     * @param integer $expire Expire time in seconds. Use 0 to never expire
     * @return boolean True on success or false on failure
     */
    public function set($handle, $value, $expire)
    {
        if ($value === false) {
            $value = 'b:0;'; // result of serialize(false);
        }
        /**
         * Memcache::set() behaviour is not always consistent:
         * {@link http://www.php.net/manual/en/memcache.set.php#84032}
         */
        if ($this->_memcache->replace($handle, $value, $expire) === false) {
            return $this->_memcache->set($handle, $value, $expire);
        }
        return true;
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
            return null;
        }
        
        if ($value === 'b:0;') {
            return false;
        }
        
        return $value;
    }
    
} // Class Brvr_Cache_Memcache
