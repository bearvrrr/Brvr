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
 * Show changes between two strings using html markup
 *
 * For usage {@see Brvr_Diff}
 *
 * @category Brvr
 * @package Brvr_Diff
 * @subpackage Brvr_Diff_Render
 */
class Brvr_Diff_Render_Html
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
    public static function renderForward($source, $opcodes)
    {
        $render = new Brvr_Diff_Render_Html($source, $opcodes);
        return $render->render();
    }
    
    /**
     * Render string in a 'backward' direction using opcodes
     *
     * @param string $source Source text to generate output from
     * @param string $opcodes Opcodes to apply to $source
     * @return string
     */
    public static function renderBackward($source, $opcodes)
    {
        $render = new Brvr_Diff_Render_Html($source, $opcodes, false);
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
    protected function applyOps($forward = false)
    {
        $rendered = '';
        $source = $this->getSource();
        $sourceOffset = 0;
        
        foreach ($this->_ops as $op) {
            if ($op instanceof Brvr_Diff_Op_Copy) {
                $rendered .= htmlentities(
                                substr(
                                    $source,
                                    $sourceOffset,
                                    $op->getFromLen()
                                    )
                                );
                $sourceOffset += $op->getFromLen();
            }
            elseif ($op instanceof Brvr_Diff_Op_Delete) {
                if ($forward) {
                    $rendered .= $this->deleteTags(
                                            substr(
                                                $source,
                                                $sourceOffset,
                                                $op->getFromLen()
                                                )
                                            );
                    $sourceOffset += $op->getFromLen();
                }
                else {
                    $rendered .= $this->deleteTags($op->getText());
                }
            }
            else /* if ($op instanceof Brvr_Diff_Op_Insert) */ {
                if ($forward) {
                    $rendered .= $this->insertTags($op->getText());
                }
                else {
                    $rendered .= $this->insertTags(
                                            substr(
                                                $source,
                                                $sourceOffset,
                                                $op->getToLen()
                                                )
                                            );
                    $sourceOffset += $op->getToLen();
                }
            }
        }
        return $rendered;
    }
    
    /**
     * Convert string to html delete block
     *
     * @param string $string To tag
     * @return string
     */
    protected function deleteTags($string)
    {
        return '<del>' . htmlentities($string) . '</del>';
    }
    
    /**
     * Convert string to html insert block
     *
     * @param string $string To tag
     * @return string
     */
    protected function insertTags($string)
    {
        return '<ins>' . htmlentities($string) . '</ins>';
    }
} // class Brvr_Diff_Render_Html