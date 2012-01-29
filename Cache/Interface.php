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
 * Caching interface
 *
 * Interface designed to allow chaining Cache objects if for example several
 * levels of persistence/longevity need to be implemented by different backends
 *
 * @category Brvr
 * @package  Brvr_Cache
 */
interface Brvr_Cache_Interface
{
    /**
     * Used in config array as key
     *
     * If the CONFIG_SET_RES_STRICT key is true then Brvr_Cache_Interface::set()
     * will return false if storing a value in a nested cache fails
     */
    const CONFIG_SET_RES_STRICT = 'Strict set result';
    
    /**
     * Used in config array as key
     */
    const CONFIG_DEFAULT_EXPIRE_TIME = 'Default Expire';
    
    /**
     * Constructor
     *
     * @param array|Zend_Config $config Class configuration
     * @param Brvr_Cache_Interface $deeperCache
     * @throws Brvr_Cache_Exception
     */
    public function __construct($config, $deeperCache);
    
    /**
     * Store variable in cache
     *
     * @param string $handle
     * @param mixed $value
     * @param integer $expire Expire time in seconds. Use 0 to never expire
     * @throws Brvr_Cache_Exception
     * @return boolean True on success false on failure
     */
    public function set($handle, $value, $expire = null);
    
    /**
     * Retrieve cached item by handle
     *
     * @param string $handle
     * @return mixed null is returned if handle not found
     */
    public function get($handle);
} // Brvr_Cache_Interface