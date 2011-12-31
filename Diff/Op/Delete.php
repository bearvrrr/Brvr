<?php

/**
 * Brvr Library
 */

/**
 * @see Brvr_Diff_Op_Interface
 */
require_once 'Brvr/Diff/Op/Interface.php';

class Brvr_Diff_Op_Delete implements Brvr_Diff_Op_Interface
{
    /**
     * Text to be deleted
     */
    private $_delete;
    
    /**
     * Constructor
     *
     * @param string $fromText deleted string
     */
    public function __construct($fromText)
    {
        $this->_delete  = $fromText;
    }
        
    /**
     * Get the number of characters this operation spans in the from string
     *
     * @return integer
     */
    public function getFromLen()
    {
        return strlen($this->_delete);
    }
    
    /**
     * Get the number of characters this operation spans in the to string
     *
     * @return integer
     */
    public function getToLen()
    {
        return 0;
    }
    
    /**
     * Get operation instruction code
     *
     * @return string
     */
    public function getOpcode()
    {
        $deleteLen = strlen($this->_delete);
        if ($deleteLen === 1) {
            return "d:{$this->_delete}";
        }
        return "d{$deleteLen}:{$this->_delete}";
    }
    
    /**
     * Get text removed by delete operation
     *
     * @return string
     */
    public function getText()
    {
        return $this->_delete;
    }
}