<?php

/**
 * Brvr Library
 */

/**
 * @see Brvr_Diff_Render_Interface
 */
require_once 'Brvr/Diff/Render/Interface.php';

/**
 * @see Brvr_Diff_Ops
 */
require_once 'Brvr/Diff/Ops.php';

abstract class Brvr_Diff_Render_Abstract implements Brvr_Diff_Render_Interface
{
    /**
     * Text to render
     *
     * @var string
     */
    protected $_source;
    
    /**
     * Ops to use for rendering
     *
     * @var Brvr_Diff_Ops
     */
    protected $_ops;
    
    /**
     * Constructor
     *
     * @param string $from Text to apply changes to
     * @param string $opcodes Operations to apply to text
     * @param Brvr_Diff_Render_Adapter_Interface $adapter optional
     */
    public function __construct($source, $opcodes)
    {
        $this->_source = $source;
        $this->_ops    = new Brvr_Diff_Ops($opcodes);
    }
    
    /**
     * Get source string
     *
     * @return string
     */
    public function getSource()
    {
        return $this->_source;
    }
    
    /**
     * Get opcodes
     *
     * @return string
     */
    public function getOpcodes()
    {
        return $this->_ops->getOpcodes();
    
    /**
     * Convert older (source) string to newer string using opcodes
     *
     * @return string
     */
    abstract public function render();
    
    /**
     * Convert newer string (source) to older string using opcodes
     *
     * @return string
     */
    abstract public function reverseRender();
} // class Brvr_Diff_Render