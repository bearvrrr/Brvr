<?php

/**
 * Brvr Library
 */

/**
 * @see Brvr_Diff_Op_Interface
 */
require_once 'Brvr/Diff/Op/Interface.php';

class Brvr_Diff_Op_Copy implements Brvr_Diff_Op_Interface
{
    /**
     * Length of text to be copied
     *
     * @var integer
     */
    private $_len;
    
    /**
     * Constructor
     *
     * @param integer $len
     */
    public function __construct($len)
    {
        $this->_len = $len;
    }
        
    /**
     * Get the number of characters this operation spans in the from string
     *
     * @return integer
     */
    public function getFromLen()
    {
        return $this->_len;
    }
    
    /**
     * Get the number of characters this operation spans in the to string
     *
     * @return integer
     */
    public function getToLen()
    {
        return $this->_len;
    }
    
    /**
     * Get operation instruction code
     *
     * @return string
     */
    public function getOpcode()
    {
        if ($this->_len === 1) {
            return 'c';
        }
        return "c{$this->_len}";
    }
    
    /**
     * Increase the length of the copy operation.
     *
     * This is used to fuse two adjacent copy operations
     *
     * @param integer $size Length of copy to append
     * @return integer new length of the copy operation
     */
    public function increase($size)
    {
        return $this->_len += $size;
    }
}