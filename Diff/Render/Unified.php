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
     * Convert older string ('from' string') to unified format hunks using
     * opcodes
     *
     * @return string
     */
    public function render()
    {
        /*
         * Need to ensure that ops are character granularity
         *
         * Doing this also provides some guaruntees for the copy ops. These
         * include that a delete op will never follow an insert op and that no
         * two consecutive ops will be of the same class.
         */
        if ($this->_direction) {
            $source = $this->getSource();
            $dest   = Brvr_Diff_Render_Text::renderForward($source, $opcodes);
        } else {
            $dest   = $this->getSource();
            $source = Brvr_Diff_Render_Text::renderBackward($dest, $opcodes);
        }
        $diff = new Brvr_Diff($source, $dest, Brvr_Diff::$characterGranularity);

        return $this->opsToUnified($source, $diff->getOpcodes());
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
    public static renderBackward($source, $opcodes)
    {
        $render = new Brvr_Diff_Render_Unified($source, $opcodes, false);
        return $render->render();
    }
    
    /**
     * Protected functions
     */
    
    /**
     * Convert op codes to diff unified format
     *
     * The returned string contains unified format hunks but with no header
     * since timestamps and filenames cannot be derived from the string supplied
     *
     * @param string $from Source 'from' string
     * @param string $opCodes
     * @return string
     */
    protected function opsToUnified($from, $opCodes)
    {
        $ops = new Brvr_Diff_Ops($opCodes);
        
        $fromLen = strlen($from);
        $offset = 0;
        $toLineOffset = 0;
        
        $hunkOps = '';
        $deleteBuffer = '';
        $insertBuffer = '';
        
        foreach ($ops as $op) {
            if ($op instanceof Brvr_Diff_Op_Copy) {
                /*
                 * Empty $hunkOps implies that both insert and delete buffers
                 * should be empty
                 */
                if (!empty($hunkOps)) {
                    $copyString = substr($from, $offset, $op->getFromLength());
                    $newLineCount = substr_count($copyString, "\n");
                    if ($newlineCount < 2) {
                        // add copy string to both buffers
                        $deleteBuffer .= $copyString;
                        $insertBuffer .= $copyString;
                    }
                    else {
                        // append up to first newline to both buffers.
                        $nextLinePos =  $this->nextLineFirstCharPos(
                                                                $copyString, 0);
                        $deleteBuffer .= substr($copyString, 0, $nextLinePos);
                        $insertBuffer .= substr($copyString, 0, $nextLinePos);
                        // add buffers to hunk
                        $hunkOps .= $this->insertOpChars($deleteBuffer, '-');
                        $hunkOps .= $this->insertOpChars($insertBuffer, '+');
                        
                        if ($newLineCount < 8) {
                            // add up to last newlino to hunk
                            $lastLinePos = strrpos($copyString, "\n", -2) + 1;
                            $hunkOps .= $this->insertOpChars(
                                                substr(
                                                    $copyString,
                                                    $nextLinePos,
                                                    $lastLinePos - $nextLinePos
                                                    ),
                                                ' '
                                                );
                            // populate both buffers with string after newline
                            $deleteBuffer = substr($copyString, $lastLinePos);
                            $insertBuffer = $deleteBuffer;
                        }
                        else {
                            // add three lines of context to hunk
                            $postHunkPos = $nextLinePos;
                            for ($i = 0, $i < 3, $i++) {
                                $postHunkPos = $this->nextLineFirstCharPos(
                                                            $copyString,
                                                            $postHunkPos
                                                            );
                            }
                            $hunkOps .= $this->insertOpChars(
                                                substr(
                                                    $copyString,
                                                    $nextLinePos,
                                                    $postHunkPos - $nextLinePos
                                                    ),
                                                ' '
                                                );
                            /**
                             * @todo need to find to line offset here
                             *     Consider writing function to add hunk then
                             *     another to wrap them when render returned.
                             */
                            $toLineOffset = $this->addHunk(
                                                        $hunkOps,
                                                        $hunkFrom,
                                                        $toLineOffset
                                                        );
                            $rendered .= $this->wrapHunk($hunkOps, $hunkFrom);
                            // new hunk will started next if block
                        }
                    }
                }
                
                $offset += $op->getFromLength();
                if (empty($hunkOps) && $offset < $fromLen) {
                    $curLinePos
                        =  $this->currentLineFirstCharPos($from, $offset);
                    $hunkStartPos = $curLinePos;
                    if ($hunkStartPos > 0) {
                        for ($i = 0, $i < 3, $i++) {
                            $prevLineStart = $this->prevLineFirstCharPos(
                                                        $from,
                                                        $curLinePos
                                                        );
                            if ($prevLineStart === false) {
                                break;
                            }
                            $hunkStartPos = $prevLineStart;
                        }
                        if ($hunkStartPos !== $curLinePos) {
                            $hunkOps = $this->insertOpChars(
                                            substr(
                                                $from,
                                                $hunkStartPos,
                                                $curLinePos - $hunkStartPos
                                                ),
                                            ' '
                                            );
                        }
                        
                    }
                    $hunkFrom = $this->lineNumber($string, $hunkStartPos);
                    $deleteBuffer = substr(
                                        $from,
                                        $curLinePos,
                                        $offset - $curLinePos
                                        );
                    $insertBuffer  = $deleteBuffer;
                }
            }
            elseif ($op instanceof Brvr_Diff_Op_Delete) {
                $deleteBuffer .= substr($from, $offset, $op->getFromLength());
                $offset += $op->getFromLength();
            }
            else /*if ($op instanceof Brvr_Diff_Op_Insert)*/ {
                $insertBuffer .= $op->getText();
            }
        } // end iteration through ops array
        
        if (!empty($deleteBuffer) ||
            !empty($insertBuffer) ||
            !empty($hunkOps)
        ) {
            if ($deleteBuffer !== $insertBuffer) {
                $hunkOps .= $this->insertOpChars($deleteBuffer, '-');
                $hunkOps .= $this->insertOpChars($insertBuffer, '+');
            } else {
                if (!empty($deleteBuffer)) {
                    /*
                     * Must now have characters taken from a copy operation
                     * (since both buffers are the same and non-empty)
                     */
                    $hunkOps .= $this->insertOpChars($deleteBuffer, ' ');
                }
                // check only context lines remain from trailing copy op
                // get start of last insert or delete line from $hunkOps
                $preLastCopyOpPos = max(
                                        strrpos($hunkOps, "\n-"),
                                        strrpos($hunkOps, "\n+")
                                        );
                // search forward to next "\n " then get substring
                $lastCopyOpPos = strpos($hunkOps, "\n ", $preLastCopyOpPos) + 1;
                // split up and get first 3 bits of substring
                $lastCopyOpLines = explode("\n ", substr($hunkOps, $lastCopy));
                if (count($lastCopyOpLines) > 3) {
                    // append to back of $hunkOps
                    $hunkOps = substr($hunkOps, 0, $lastCopyOpPos)
                             . implode(
                                    "\n ",
                                    array_slice($lastCopyOpLines, 0, 3)
                                    );
                }
            }
            // Add hunk
            $this->addHunk(
                        $hunkOps,
                        $hunkFrom,
                        $toLineOffset
                        );
        }
        
        return $this->getHunks();
    }
    
    /**
     * Find position of first character of current line (the line including
     * character at position of offset)
     *
     * @param string $string
     * @param int $offset Position in $string to search relative to
     * @return int
     */
    protected function currentLineFirstCharPos($string, $offset) {
        if ($offset === 0) {
            return 0;
        }
        if (substr($string, $offset, 1) === "\n") {
            $offset--;
        }
        $pos = strrpos(substr($string, 0, $offset), "\n");
        if ($pos !== false) {
            return $pos +1;
        }
        return 0;
    }
    
    /**
     * Find position of first character of next line relative to offset
     *
     * @param string $string
     * @param int $offset Position in $string to search relative to
     * @return int
     */
    protected function nextLineFirstCharPos($string, $offset) {
        $pos = strpos($string, "\n", $offset);
        
        if (($pos !== false) && ($pos + 1 < strlen($string))) {
            return $pos +1;
        }
        return false;
    }
    
    /**
     * Find position of first character of previous line relative to offset
     *
     * @param string $string
     * @param int $offset Position in $string to search relative to
     * @return int
     */
    protected function prevLineFirstCharPos($string, $offset) {
        $currentPos = $this->currentLineFirstCharPos($string, $offset);
        if ($currentPos === 0) {
            return $false;
        }
        return $this->currentLineFirstCharPos($string, $currentPos - 1);
    }
    
    protected function lineNumber($string, $offset) {
        return substr_count(substr($string, 0, $offset), "\n") + 1;
    }
    
    /**
     * Insert character between start of string or newline character and the
     * following character
     *
     * @param string $string
     * @param string $opChar Character to insert
     * @return string
     */
    protected function insertOpChars($string, $opChar)
    {
        return preg_replace('/(^|\\n)(?=.)/', '$1' . $opChar , $string);
    }
    
    /**
     * Store hunk with appropriate header
     *
     * @param string $hunkOps Unified format hunk
     * @param int $fromLineNumber
     * @param int $toLineOffset Relative line number to context in to string
     * @return int Line offset after hunk added
     */
    protected function addHunk($hunkOps, $fromLineNumber, $toLineOffset)
    {
        $hunkOps = "\n" . $hunkOps;
        $from    = $fromLineNumber;
        $to      = $from + $toLineOffset;
        
        $copyCount   = substr_count($hunkOps, "\n ");
        $deleteCount = substr_count($hunkOps, "\n-");
        $insertCount = substr_count($hunkOps, "\n+");
        
        $fromLen = $copyCount + $deleteCount;
        if ($fromLen === 1) {
            $fromLines = "-$from";
        } else {
            $fromLines = "-$from,$fromLen";
        }
        $toLen   = $copyCount + $insertCount;
        if ($toLen === 1) {
            $toLines = "-$to";
        } else {
            $toLines = "-$to,$toLen";
        }
        $this->_hunks[] = "@@ -$fromLines +$toLines @@$hunkOps";
        
        return $toLineOffset + $toLen - $fromLen;
    }
    
    /**
     * Return a string of unified format hunks
     *
     * @return string
     */
    protected function getHunks()
    {
        return implode("\n", $this->_hunks);
    }
} // class Brvr_Diff_Render_Unified