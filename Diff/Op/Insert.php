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
 * @see Brvr_Diff_Op_Interface
 */
require_once 'Brvr/Diff/Op/Interface.php';

/**
 * Class to represent insert ('i') opcode
 *
 * @category Brvr
 * @package Brvr_Diff
 * @subpackage Brvr_Diff_Op
 */
class Brvr_Diff_Op_Insert implements Brvr_Diff_Op_Interface
{
    /**
     * Text to be inserted
     *
     * @var string
     */
    private $_insert;
    
    /**
     * Constructor
     *
     * @param string $toText inserted string
     */
    public function __construct($toText)
    {
        $this->_insert  = $toText;
    }
        
    /**
     * Get the number of characters this operation spans in the from string
     *
     * @return integer
     */
    public function getFromLen()
    {
        return 0;
    }
    
    /**
     * Get the number of characters this operation spans in the to string
     *
     * @return integer
     */
    public function getToLen()
    {
        return strlen($this->_insert);
    }
    
    /**
     * Get operation instruction code
     *
     * @return string
     */
    public function getOpcode()
    {
        $insertLen = strlen($this->_insert);
        if ($insertLen === 1) {
            return "i:{$this->_insert}";
        }
        return "i{$insertLen}:{$this->_insert}";
    }
    
    /**
     * Get text added by insert operation
     *
     * @return string
     */
    public function getText()
    {
        return $this->_insert;
    }
}