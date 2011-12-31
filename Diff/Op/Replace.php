<?php

/**
 * Brvr Library
 */

/**
 * @see Brvr_Diff_Op_Interface
 */
require_once 'Brvr/Diff/Op/Interface.php';

class Brvr_Diff_Op_Replace implements Brvr_Diff_Op_Interface
{
    /**
     * Text to be deleted
     *
     * @var Brvr_Diff_Op_Delete
     */
    private $_from;
    
    /**
     * Text to be inserted
     *
     * @var Brvr_Diff_Op_Insert
     */
    private $_to;
    
    /**
     * Constructor
     *
     * @param string $fromText deleted string
     * @param string $toText inserted string
     */
    public function __construct($fromText, $toText)
    {
        $this->_from = new Brvr_Diff_Op_Delete($fromText);
        $this->_to   = new Brvr_Diff_Op_Insert($toText);
    }
        
    /**
     * Get the number of characters this operation spans in the from string
     *
     * @return integer
     */
    public function getFromLen()
    {
        return $this->_from->getFromLen();
    }
    
    /**
     * Get the number of characters this operation spans in the to string
     *
     * @return integer
     */
    public function getToLen()
    {
        return $this->__to->getToLen();
    }
    
    /**
     * Get operation instruction code
     *
     * @return string
     */
    public function getOpcode()
    {
        return $this->_from->getOpCode() . $this->_to->getOpCode();
    }
    
    /**
     * Get text removed by replace operation
     *
     * @return string
     */
    public function getFromText()
    {
        return $this->_from->getText();
    }
    
    /**
     * Get text added by replace operation
     *
     * @return string
     */
    public function getToText()
    {
        return $this->_to->getText();
    }
}