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
 * @package Brvr_Diff
 * @subpackage Brvr_Diff_Op
 */

/**
 * Interface for classes representing opcodes
 *
 * @category Brvr
 * @package Brvr_Diff
 * @subpackage Brvr_Diff_Op
 */
interface Brvr_Diff_Op_Interface
{
    /**
     * Get the number of characters this operation spans in the from string
     *
     * @return integer
     */
    public function getFromLen();
    
    /**
     * Get the number of characters this operation spans in the to string
     *
     * @return integer
     */
    public function getToLen();
    
    /**
     * Get operation instruction code
     *
     * @return string
     */
    public function getOpcode();
}