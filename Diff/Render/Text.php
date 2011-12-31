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
 * @subpackage Brvr_Diff_Render
 */

/**
 * @see Brvr_Diff_Render_Abstract
 */
require_once 'Brvr/Diff/Render/Abstract.php';

/**
 * @see Brvr_Diff_Render_Interface
 */
require_once 'Brvr/Diff/Render/Interface.php';

/**
 * Convert one string to another using opcodes
 *
 * For usage {@see Brvr_Diff}
 *
 * @category Brvr
 * @package Brvr_Diff
 * @subpackage Brvr_Diff_Render
 */
class Brvr_Diff_Render_Text 
    extends Brvr_Diff_Render_Abstract
    implements Brvr_Diff_Render_Interface
{
    /**
     * Convert older (source) string to newer string using opcodes
     *
     * @return string
     */
    public function render()
    {
        return $this->applyOps($this->_direction);
    }
    
    /**
     * Render string in a 'forward' direction using opcodes
     *
     * @param string $source Source text to generate output from
     * @param string $opcodes Opcodes to apply to $source
     * @return string
     */
    public static renderForward($source, $opcodes)
    {
        $render = new Brvr_Diff_Render_Text($source, $opcodes);
        return $render->render();
    }
    
    /**
     * Render string in a 'backward' direction using opcodes
     *
     * @param string $source Source text to generate output from
     * @param string $opcodes Opcodes to apply to $source
     * @return string
     */
    public static renderBackward($source, $opcodes)
    {
        $render = new Brvr_Diff_Render_Text($source, $opcodes, false);
        return $render->render();
    }
    
    /**
     * Apply ops to source string
     *
     * @param boolean $forward Set true to proceed in a forward direction ie
     *     the source string is the older version of a string and the newer
     *     version is rendered
     * @return string
     */
    protected function applyOps($forward = true)
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
                if ($forward) {
                    $sourceOffset += $op->getFromLen();
                }
                else {
                    $rendered .= $op->getText();
                }
            }
            else /* if ($op instanceof Brvr_Diff_Op_Insert) */ {
                if ($forward) {
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