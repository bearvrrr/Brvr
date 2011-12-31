<?php

/**
 * Brvr Library
 */

/**
 * @see Brvr_Diff_Render_Abstract
 */
require_once 'Brvr/Diff/Render/Abstract.php';

class Brvr_Diff_Render_Text extends Brvr_Diff_Render_Abstract
{
    /**
     * Convert older (source) string to newer string using opcodes
     *
     * @return string
     */
    public function render()
    {
        return $this->applyOps();
    }
    
    /**
     * Convert newer string (source) to older string using opcodes
     *
     * @return string
     */
    public function reverseRender()
    {
        return $this->applyOps(true);
    }
    
    /**
     * Apply ops to source string
     *
     * @param boolean $reverse Set false to proceed in a forward direction ie
     *     the source string is the older version of a string and the newer
     *     version is rendered
     * @return string
     */
    protected function applyOps($reverse = false)
    {
        $rendered = '';
        $source = $this->getSource();
        $sourceOffset = 0;
        
        foreach ($this->_ops as $op) {
            if ($op instanceof Brvr_Diff_Op_Copy) {
                $rendered .= substr($source, $sourceOffset, $op->getFromLen());
                $sourceOffset += $op->getFromLen;
            }
            elseif ($op instanceof Brvr_Diff_Op_Delete) {
                if (!$reverse) {
                    $sourceOffset += $op->getFromLen();
                }
                else {
                    $rendered .= $op->getText();
                }
            }
            else /* if ($op instanceof Brvr_Diff_Op_Insert) */ {
                if (!$reverse) {
                    $rendered .= $op->getText();
                }
                else {
                    $sourceOffset += $op->getToLen();
                }
            }
        }
        return $rendered;
    }
} // class Brvr_Diff_Render_Text