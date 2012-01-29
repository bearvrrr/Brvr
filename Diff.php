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
 */

/**
 * @see Brvr_Diff_Op_Copy
 */
require_once 'Brvr/Diff/Op/Copy.php';

/**
 * @see Brvr_Diff_Op_Delete
 */
require_once 'Brvr/Diff/Op/Delete.php';

/**
 * @see Brvr_Diff_Op_Insert
 */
require_once 'Brvr/Diff/Op/Insert.php';

/**
 * Fine granularity string DIFFing
 *
 * Largely code from fineDiff {@link http://www.raymondhill.net/finediff/}
 * ((c) Raymond Hill)
 * 
 * Usage (simplest):
 *
 *   include 'Brvr/Diff.php';
 *
 *   // for the stock stack, granularity values are:
 *   // Brvr_Diff::$lineGranularity      = paragraph/line level
 *   // Brvr_Diff::$sentenceGranularity  = sentence level
 *   // Brvr_Diff::$wordGranularity      = word level
 *   // Brvr_Diff::$characterGranularity = character level [default]
 *
 *   $opcodes = Brvr_Diff::diff($fromText, $toText[, $granularityStack = null]);
 *   // store opcodes for later use...
 *
 *   ...
 *
 *   // restore $toText from $fromText + $opcodes
 *   include 'Brvr/Diff/Render/Text.php';
 *   $toText = Brvr_Diff_Render_Text::renderForward($fromText, $opcodes);
 *
 *   ...
 *
 * Persisted opcodes (string) are a sequence of atomic opcode.
 * A single opcode can be one of the following:
 *   c | c{n} | d:{c} | d{n}:{s} | i:{c} | i{length}:{s}
 *   'c'        = copy one character from source
 *   'c{n}'     = copy n characters from source
 *   'd:{c}'    = skip one character from source
 *   'd{n}:{s}' = skip n characters from source(string s)
 *   'i:{c}     = insert character 'c'
 *   'i{n}:{s}' = insert string s, which is of length n
 *
 * These differ from the fineDiff class opcodes in that the delete opcodes
 * specify the string deleted so that opcodes can be used to roll back changes
 * from a newer version of a string to the preceeding newer version
 *
 * @category Brvr
 * @package Brvr_Diff
 * @todo test
 */
class Brvr_Diff
{
    /**
     * Stock granularity stacks and delimiters
     */
    const PARAGRAPH_DELIMITERS = "\n\r";
    public static $paragraphGranularity = array(
        self::PARAGRAPH_DELIMITERS
        );
    const SENTENCE_DELIMITERS = ".\n\r";
    public static $sentenceGranularity = array(
        self::PARAGRAPH_DELIMITERS,
        self::SENTENCE_DELIMITERS
        );
    const WORD_DELIMITERS = " \t.\n\r";
    public static $wordGranularity = array(
        self::PARAGRAPH_DELIMITERS,
        self::SENTENCE_DELIMITERS,
        self::WORD_DELIMITERS
        );
    const CHARACTER_DELIMITERS = "";
    public static $characterGranularity = array(
        self::PARAGRAPH_DELIMITERS,
        self::SENTENCE_DELIMITERS,
        self::WORD_DELIMITERS,
        self::CHARACTER_DELIMITERS
        );

    public static $textStack = array(
        ".",
        " \t.\n\r",
        ""
        );
    
    /**
     * From text
     *
     * Older, 'original' text with which to compare edits
     *
     * @var string
     */
    protected $_fromText;
    
    /**
     * To text
     *
     * Newer text with which to do comparison
     *
     * @var string
     */
    protected $_toText;
    
    /**
     * Granularity stack
     *
     * An array with values of characters to divide strings up into 'atomic'
     * segments to perform diffing with.
     *
     * @var array
     */
    protected $_granularityStack = array();
    
    /**
     * Brvr_Diff_Op_* objects
     *
     * @var array
     */
    protected $_ops = array();
    
    /**
     * Constructor
     *
     * The $granularityStack allows object to be configurable so that a
     * particular stack tailored to the specific content of a document can
     * be passed.
     */
    public function __construct(
        $fromText = '',
        $toText = '',
        $granularityStack = null
    ) {
        if (is_string($granularityStack)) {
            $this->_granularityStack = array($granularityStack);
        }
        elseif (is_array($granularityStack) && !empty($granularityStack)) {
            $iter = new RecursiveIteratorIterator(
                        new RecursiveArrayIterator($granularityStack)
                        );
            foreach ($iter as $v) {
                $flatStack[] = $v;
            }
            $this->_granularityStack = $flatStack;
        }
        else {
            $this->_granularityStack = self::$characterGranularity;
        }
        
        $this->_fromText = $fromText;
        $this->_toText   = $toText;
        $this->_ops = $this->processGranularity(
                                $fromText,
                                $toText,
                                $this->getGranularity()
                                );
    }
    
    /**
     * Get granularity stack used for diff
     *
     * @return array
     */
    public function getGranularity()
    {
        return $this->_granularityStack;
    }
    
    /**
     * Get array of Brvr_Diff_Op_* objects
     *
     * @return array containing objects implementing Brvr_Diff_Op_Interface
     */
    public function getOps()
    {
        return $this->_ops;
    }
    
    /**
     * Get opcodes
     *
     * @return string
     */
    public function getOpcodes()
    {
        $opcodes = array();
        foreach ($this->_ops as $op) {
            $opcodes[] = $op->getOpcode();
        }
        return implode('', $opcodes);
    }
    
    /**
     * Static diffing function
     *
     * @return string opcodes
     */
    public static function diff($from, $to, $granularityStack = null)
    {
        $diff = new Brvr_Diff($from, $to, $granularityStack);
        return $diff->getOpcodes();
    }
    
    /**
     * Protected methods
     */
     
    /**
     * This is the recursive function which is responsible for
     * handling/increasing granularity.
     *
     * @param string $fromText
     * @param string $toText
     * @param array $granularityStack
     */
    private function processGranularity($from, $to, $granularityStack)
    {
        $delimiters = array_shift($granularityStack);
        $hasNextStage = !empty($granularityStack);
        $old = $this->extractFragments($from, $delimiters);
        $new = $this->extractFragments($to,   $delimiters);
        
        $ops = array();
        foreach (self::arrayDiff($old, $new) as $fragment) {
            if (is_array($fragment)) {
                $oldString = implode('', $fragment['d']);
                $newString = implode('', $fragment['i']);
                if ($hasNextStage) {
                    $ops = $this->appendOps(
                                        $ops,
                                        $this->processGranularity(
                                                    $oldString,
                                                    $newString,
                                                    $granularityStack
                                                    )
                                        );
                }
                else {
                    if (!empty($oldString)) {
                        $ops[] = new Brvr_Diff_Op_Delete($oldString);
                    }
                    if (!empty($newString)) {
                        $ops[] = new Brvr_Diff_Op_Insert($newString);
                    }
                }
            }
            else {
                if (empty($ops)) {
                    $ops[] = new Brvr_Diff_Op_Copy(strlen($fragment));
                }
                else {
                    $lastOp = $ops[count($ops) - 1];
                    if ($lastOp instanceof Brvr_Diff_Op_Copy) {
                        $lastOp->increase(strlen($fragment));
                    }
                    else {
                        $ops[] = new Brvr_Diff_Op_Copy(strlen($fragment));
                    }
                }
            }
        }
        return $ops;
    }
    
    /**
     * Merge arrays of opcodes
     *
     * Adjacent Brvr_Diff_Op_Copy objects will become merged into a single op
     *
     * @param array $ops of Brvr_Diff_Op_Interface objects
     * @param array $opsToAppend of Brvr_Diff_Op_Interface objects
     * @return array
     */
    protected function appendOps($ops, $opsToAppend)
    {
        if (empty($ops) || empty($opsToAppend)) {
            if (!empty($ops)) return $ops;
            if (!empty($opsToAppend)) return $opsToAppend;
            return array();
        }
        $lastOp          = array_pop($ops);
        $firstOpToAppend = array_shift($opsToAppend);
        if ($lastOp instanceof Brvr_Diff_Op_Copy &&
            $firstOpToAppend instanceof Brvr_Diff_Op_Copy
        ) {
            //
            $lastOp->increase($firstOpToAppend->getFromLen());
            $ops[] = $lastOp;
            return array_merge($ops, $opsToAppend);
        }
        $ops[] = $lastOp;
        $ops[] = $firstOpToAppend;
        return array_merge($ops, $opsToAppend);
    }
    
    /**
     * Find the difference between two arrays
     *
     * This function is slighty altered version of 'Paul's simple diff
     * algorithm' {@link https://raw.github.com/paulgb/simplediff/5bfe1d2a8f967c7901ace50f04ac2d9308ed3169/simplediff.php}
     * which is distributed under the zlib/libpng license (C) 2007. 
     *
     * Example input/output:
     *
     * $old = array('copy', 'delete', 'copy');
     * $new = array('copy', 'insert', 'copy');
     * var_dump($old, $new);
     * 
     * Would produce:
     * array(3) {
     *   [0]=>
     *   string(4) "copy"
     *   [1]=>
     *   array(2) {
     *     ["d"]=>
     *       array(1) {
     *     [0]=>
     *     string(6) "delete"
     *     }
     *     ["i"]=>
     *     array(1) {
     *       [0]=>
     *       string(6) "insert"
     *     }
     *   }
     *   [2]=>
     *   string(4) "copy"
     * }
     *
     * @param array $old
     * @param array $new
     * @return array
     */
    public static function arrayDiff($old, $new)
    {
        if (empty($old) && empty($new)) {
            return array();
        }
        $maxlen = 0;
        $matrix = array();
        foreach ($old as $oindex => $ovalue) {
            $nkeys = array_keys($new, $ovalue);
            foreach ($nkeys as $nindex) {
                $matrix[$oindex][$nindex] 
                    = isset($matrix[$oindex - 1][$nindex - 1]) ?
                                    $matrix[$oindex - 1][$nindex - 1] + 1 : 1;
                if ($matrix[$oindex][$nindex] > $maxlen) {
                    $maxlen = $matrix[$oindex][$nindex];
                    $omax = $oindex + 1 - $maxlen;
                    $nmax = $nindex + 1 - $maxlen;
                }
            }
        }
        if ($maxlen == 0) return array(array('d' => $old, 'i' => $new));
        return array_merge(
            self::arrayDiff(
                    array_slice($old, 0, $omax),
                    array_slice($new, 0, $nmax)
                    ),
            array_slice($new, $nmax, $maxlen),
            self::arrayDiff(
                array_slice($old, $omax + $maxlen),
                array_slice($new, $nmax + $maxlen)
                )
            );
    }
    
    /**
     * Efficiently fragment the text into an array according to specified
     * delimiters.
     *
     * No delimiters means fragment into single character.
     *
     * Careful: No check is performed as to the validity of the
     * delimiters.
     *
     * @param string $text
     * @param string $delimiters characters at which to split the $text
     * @return array
     */
    protected function extractFragments($text, $delimiters)
    {
        // special case: split into characters
        if (empty($delimiters)) {
            $chars = str_split($text, 1);
            return $chars;
        }
        $fragments = array();
        $start = $end = 0;
        for (;;) {
            $end += strcspn($text, $delimiters, $end);
            $end += strspn($text, $delimiters, $end);
            if ($end === $start) {
                break;
            }
            $fragments[] = substr($text, $start, $end - $start);
            $start = $end;
        }
        return $fragments;
    }
}