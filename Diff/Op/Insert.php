<?php

/**
 * Brvr Library
 */

/**
 * @see Brvr_Diff_Op_Interface
 */
require_once 'Brvr/Diff/Op/Interface.php';

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