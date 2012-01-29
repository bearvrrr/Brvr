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
 * Caching adapter dummy with no backend for use with testing
 *
 * @category  Brvr
 * @package   Brvr_Cache
 */
class Brvr_Cache_None extends Brvr_Cache_Abstract
{
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
        return false;
    }
    
    /**
     * Retrive cached item by handle
     *
     * @param string $handle
     * @return mixed null is returned if handle not found or a failure occurs
     */
    public function get($handle)
    {
        return null;
    }
} // Brvr_Cache_None