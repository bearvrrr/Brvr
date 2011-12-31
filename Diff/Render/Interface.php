<?php

/**
 * Brvr Library
 */

interface Brvr_Diff_Render_Interface
{
    /**
     * Get source string
     *
     * @return string
     */
    public function getSource();
    
    /**
     * Get opcodes
     *
     * @return string
     */
    public function getOpcodes();
    
    /**
     * Convert older (source) string to newer string using opcodes
     *
     * @return string
     */
    public function render();
    
    /**
     * Convert newer string (source) to older string using opcodes
     *
     * @return string
     */
    public function reverseRender();
}