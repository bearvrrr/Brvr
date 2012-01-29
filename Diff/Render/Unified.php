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
 * @see Brvr_Diff
 */
require_once 'Brvr/Diff.php';

/**
 * @see Brvr_Diff_Ops
 */
require_once 'Brvr/Diff/Ops.php';

/**
 * @see Brvr_Diff_Render_Text
 */
require_once 'Brvr/Diff/Render/Text.php';

/**
 * @see Brvr_Diff_Render_Abstract
 */
require_once 'Brvr/Diff/Render/Abstract.php';

/**
 * @see Brvr_Diff_Render_Interface
 */
require_once 'Brvr/Diff/Render/Interface.php';

/**
 * Show changes between two strings using the unified type output used by the
 * GNU diff program
 *
 * This class produces an output for a different diffing paradigm. Due to
 * not all information required for this not being stored by the opcodes the
 * orginal strings must be reconstructed. Thus this is pretty inefficient.
 *
 * @link http://www.gnu.org/software/diffutils/manual/html_node/Unified-Format.html
 *
 * For usage {@see Brvr_Diff}
 *
 * @category Brvr
 * @package Brvr_Diff
 * @subpackage Brvr_Diff_Render
 */
class Brvr_Diff_Render_Unified 
    extends Brvr_Diff_Render_Abstract
    implements Brvr_Diff_Render_Interface
{
    /**
     * Store for parsed unified format hunks
     *
     * @var array
     */
    protected $_hunks = array();
    
    /**
     * Convert older string ('from' string') to unified format hunks using
     * opcodes
     *
     * @return string
     */
    public function render()
    {
        $opcodes = $this->_ops->getOpcodes();
        /*
         * Need to ensure that ops are character granularity
         *
         * Doing this also provides some guaruntees for the copy ops. These
         * include that a delete op will never follow an insert op and that no
         * two consecutive ops will be of the same class.
         */
        if ($this->_direction) {
            $from = $this->getSource();
            $to   = Brvr_Diff_Render_Text::renderForward($from, $opcodes);
        } else {
            $to   = $this->getSource();
            $from = Brvr_Diff_Render_Text::renderBackward($to, $opcodes);
        }
        return $this->diffUnified($from, $to);
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
        $render = new Brvr_Diff_Render_Unified($source, $opcodes);
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
        $render = new Brvr_Diff_Render_Unified($source, $opcodes, false);
        return $render->render();
    }
    
    /**
     * Protected functions
     */
    
    /**
     * Produced unified format ops string
     *
     * @param string $from
     * @param string $to
     * @return string
     */
    protected function diffUnified($from, $to)
    {
        $old = explode("\n", $from);
        $new = explode("\n", $to);
        
        // special cases of empty from or to here
        if (empty($from) || empty($to)) {
            if (!empty($from)) {
                return $this->renderSingleOpHunk($old, '-');
            }
            if (!empty($to)) {
                return $this->renderSingleOpHunk($new, '+');
            }
            return '';
        }
        
        $oldIndex = $oldHunkStart = 0;
        $newIndex = $newHunkStart = 0;
        $rendered = array();
        $currentHunk = array();
        $currentContext = array();
        foreach (Brvr_Diff::arrayDiff($old, $new) as $op) {
            if (is_array($op)) {
                if (empty($currentHunk)) {
                    $oldHunkStart = $oldIndex + 1 - count($currentContext);
                    $newHunkStart = $newIndex + 1 - count($currentContext);
                }
                if (!empty($currentContext)) {
                    $currentHunk = array_merge($currentHunk, $currentContext);
                    $currentContext = array();
                }
                foreach ($op['d'] as $delete) {
                    $oldIndex++;
                    $currentHunk[] = '-' . $delete;
                }
                foreach ($op['i'] as $insert) {
                    $newIndex++;
                    $currentHunk[] = '+' . $insert;
                }
                
            }
            else {
                $oldIndex++;
                $newIndex++;
                $currentContext[] = ' ' . $op;
                if (empty($currentHunk)) {
                    if (count($currentContext) > 3) {
                        array_shift($currentContext);
                        //$currentContext = array_slice($currentContext, -3);
                    }
                }
                elseif (count($currentContext) > 6) {
                    $currentHunk = array_merge(
                                        $currentHunk,
                                        array_slice($currentContext, 0, 3)
                                        );
                    $rendered[] = $this->renderHunk(
                                            $currentHunk,
                                            $oldHunkStart,
                                            $oldIndex - $oldHunkStart - 3,
                                            $newHunkStart,
                                            $newIndex - $newHunkStart - 3
                                            );
                    $currentContext = array_slice($currentContext, -3);
                    $currentHunk = array();
                }
            }
        }
        
        if (!empty($currentHunk)) {
            $lengthAdjust = 0;
            if (!empty($currentContext)) {
                $endContext = array_slice($currentContext, 0, 3);
                $lengthAdjust = count($currentContext) - count($endContext);
                $currentHunk = array_merge($currentHunk, $endContext);
            }
            $rendered[] = $this->renderHunk(
                                $currentHunk,
                                $oldHunkStart,
                                $oldIndex - $oldHunkStart - $lengthAdjust + 1,
                                $newHunkStart,
                                $newIndex - $newHunkStart - $lengthAdjust + 1
                                );
        }
        return implode("\n", $rendered);
    }
    
    /**
     * Render hunk ops as string prefixed with line numbers
     *
     * @param array $hunkOps
     * @param integer $oldStart
     * @param integer $oldLength
     * @param integer $newStart
     * @param integer $newLength
     * @return string
     */
    protected function renderHunk(
        $hunkOps,
        $oldStart,
        $oldLength,
        $newStart,
        $newLength
        )
    {
        if ($oldLength === 1) {
            $oldIndices = "$oldStart";
        }
        else {
            $oldIndices = "$oldStart,$oldLength";
        }
        if ($newLength === 1) {
            $newIndices = "$newStart";
        }
        else {
            $newIndices = "$newStart,$newLength";
        }
        return "@@ -$oldIndices +$newIndices @@\n" . implode("\n", $hunkOps);
    }
    
    /**
     * Render single op hunk (i.e. when either delete all or insert all
     *
     * @param array $hunkLines
     * @param string $opPrefix '-' or '+' (delete or insert respectively)
     * @return string
     */
    protected function renderSingleOpHunk($hunkLines, $opPrefix)
    {
        $length = count($hunkLines);
        
        if ($opPrefix === '-') {
            $oldStart = 1;
            $oldLength = $length;
            $newStart = 0;
            $newLength = 0;
        }
        else {
            $oldStart = 0;
            $oldLength = 0;
            $newStart = 1;
            $newLength = $length;
        }
        return $this->renderHunk(
            array_map(
                function ($line, $opPrefix)
                {
                    return $opPrefix . $line;
                },
                $hunkLines,
                array_fill(0, $length, $opPrefix)
                ),
            $oldStart,
            $oldLength,
            $newStart,
            $newLength
            );
    }
} // class Brvr_Diff_Render_Unified