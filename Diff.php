<?php
/**
 * FINE granularity DIFF
 *
 * Computes a set of instructions to convert the content of
 * one string into another.
 *
 * Copyright (c) 2011 Raymond Hill (http://raymondhill.net/blog/?p=441)
 *
 * Licensed under The MIT License
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @copyright Copyright 2011 (c) Raymond Hill (http://raymondhill.net/blog/?p=441)
 * @link http://www.raymondhill.net/finediff/
 * @version 0.6
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
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
 */

/**
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
 */

/**
 * String diffing class
 *
 *
 * @TODO: Document
 * @todo license
 * @todo packaging information
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
        if (is_string($granularityStack) {
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
        return $this->_granularity;
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
        $diff = new Brvr_Diff($from, $to, $granularityStack = null);
        return $diff->getOpcodes();
    }
    
    /**
     * Protected methods
     */
     
    /**
     * This is the recursive function which is responsible for
     * handling/increasing granularity.
     *
     * Incrementally increasing the granularity is key to compute the
     * overall diff in a very efficient way.
     *
     * @param string $fromText
     * @param string $toText
     * @param array $granularityStack
     */
    private function processGranularity(
        $fromSegment,
        $toSegment,
        $granularityStack
        )
    {
        $fromOffset = 0;
        $edits = array();
        $delimiters = array_shift($granularityStack);
        $hasNextStage = !empty($granularityStack);
        foreach (
            self::doFragmentDiff($fromSegment, $toSegment, $delimiters)
            as $fragmentEdit
        ) {
            // increase granularity
            if ($fragmentEdit instanceof Brvr_Diff_Op_Replace && 
                $hasNextStage
            ) {
                $edits = array_merge(
                    $edits,
                    $this->processGranularity(
                        $fragmentEdit->getFromText(),
                        $fragmentEdit->getToText(),
                        $granularityStack
                        )
                    );
            }
            // fuse copy ops whenever possible
            elseif ($fragmentEdit instanceof Brvr_Diff_Op_Copy &&
                    $edits[count($edits)-1] instanceof Brvr_Diff_Op_Copy
            ) {
                $edits[count($edits)-1]->increase(
                                                $fragmentEdit->getFromLen());
            }
            else {
                $edits[] = $fragmentEdit;
            }
            $fromOffset += $fragmentEdit->getFromLen();
        }
        return $edits;
    }

    /**
     * Perform diff at granularity determined by delimiters
     *
     * This is the core algorithm which actually perform the diff itself,
     * fragmenting the strings as per specified delimiters.
     *
     * This function is naturally recursive, however for performance purpose
     * a local job queue is used instead of outright recursivity.
     *
     * This is a long function that may benefit from refactoring, however
     * hopefully the comments are informative
     *
     * @param string $fromText
     * @param string $toText
     * @param string $delimiters Boundary characters for fragments
     */
    private static function doFragmentDiff($fromText, $toText, $delimiters)
    {
        /*
         * Empty delimiter means character-level diffing.
         * In such case, use code path optimized for character-level
         * diffing.
         */
        if (empty($delimiters)) {
            return self::doCharDiff($fromText, $toText);
        }

        $result = array();

        $fromTextLen = strlen($fromText);
        $toTextLen = strlen($toText);
        $fromFragments = self::extractFragments($fromText, $delimiters);
        $toFragments = self::extractFragments($toText, $delimiters);
        
        $jobs = array(array(0, $fromTextLen, 0, $toTextLen));
        
        $cachedArrayKeys = array();
        
        while ($job = array_pop($jobs)) {
            list(
                $fromSegmentStart,
                $fromSegmentEnd,
                $toSegmentStart,
                $toSegmentEnd
                ) = $job;
            
            /*
             * Catch easy cases: if either the from or to segment are empty then
             * it will be an insert or delete operation respectively. If both
             * are empty then just skip to the next job
             */
            $fromSegmentLength = $fromSegmentEnd - $fromSegmentStart;
            $toSegmentLength = $toSegmentEnd - $toSegmentStart;
            if (!$fromSegmentLength || !$toSegmentLength) {
                if ($fromSegmentLength) {
                    $result[$fromSegmentStart * 4]
                        = new Brvr_Diff_Op_Delete(
                            substr(
                                $fromText,
                                $fromSegmentStart,
                                $fromSegmentLength
                                )
                            );
                }
                elseif ($toSegmentLength) {
                    $result[$fromSegmentStart * 4 + 1]
                        = new Brvr_Diff_Op_Insert(
                            substr($toText, $toSegmentStart, $toSegmentLength)
                            );
                }
                continue;
            }
            
            /*
             * Only copy or replace ops possible. Determine which by finding
             * longest copy operation for the current segments
             */
            $bestCopyLength = 0;
            $fromBaseFragmentIndex = $fromSegmentStart;
            $cachedArrayKeysForCurrentSegment = array();
            
            while ( $fromBaseFragmentIndex < $fromSegmentEnd ) {
                $fromBaseFragment = $fromFragments[$fromBaseFragmentIndex];
                $fromBaseFragmentLength = strlen($fromBaseFragment);
                
                /*
                 * Get the character indices of all those 'to fragments' that
                 * match the current from fragment
                 */
                // performance boost: cache array keys
                if (!isset(
                        $cachedArrayKeysForCurrentSegment[$fromBaseFragment])
                ) {
                    if (!isset($cachedArrayKeys[$fromBaseFragment])) {
                        $toAllFragmentIndices
                            = $cachedArrayKeys[$fromBaseFragment]
                            = array_keys($toFragments, $fromBaseFragment, true);
                    }
                    else {
                        $toAllFragmentIndices
                            = $cachedArrayKeys[$fromBaseFragment];
                    }
                    
                    // get only indices which falls within current segment
                    if ( $toSegmentStart > 0 || $toSegmentEnd < $toTextLen ) {
                        $toFragmentIndices = array();
                        foreach ($toAllFragmentIndices as $toFragmentIndex) {
                            if ( $toFragmentIndex < $toSegmentStart ) {
                                continue;
                            }
                            if ( $toFragmentIndex >= $toSegmentEnd ) { 
                                break;
                            }
                            $toFragmentIndices[] = $toFragmentIndex;
                        }
                        $cachedArrayKeysForCurrentSegment[$fromBaseFragment]
                                                        = $toFragmentIndices;
                    }
                    else {
                        $toFragmentIndices = $toAllFragmentIndices;
                    }
                }
                else {
                    $toFragmentIndices
                        = $cachedArrayKeysForCurrentSegment[$fromBaseFragment];
                }
                
                /*
                 * $toFragmentIndices will be empty if no fragments in the
                 * current segment match (i.e. no execution of foreach block)
                 */
                foreach ($toFragmentIndices as $toBaseFragmentIndex) {
                    $fragmentIndexOffset = $fromBaseFragmentLength;
                    // iterate until no more match
                    for (;;) {
                        /*
                         * Check whether the end of the to or from segment has
                         * been reached by comparing index for the next fragment
                         * to the index of the segment end
                         */
                        $fragmentFromIndex
                                = $fromBaseFragmentIndex + $fragmentIndexOffset;
                        if ($fragmentFromIndex >= $fromSegmentEnd) {
                            break;
                        }
                        $fragmentToIndex
                                = $toBaseFragmentIndex + $fragmentIndexOffset;
                        if ($fragmentToIndex >= $toSegmentEnd) {
                            break;
                        }
                        
                        /*
                         * Check whether next from and to fragments match
                         */
                        if ($fromFragments[$fragmentFromIndex] !==
                                            $toFragments[$fragmentToIndex]
                        ) {
                            break;
                        }
                        
                        /*
                         * Fragments match so get length of current matching
                         * series of fragments
                         */
                        $fragmentLength
                                = strlen($fromFragments[$fragmentFromIndex]);
                        $fragmentIndexOffset += $fragmentLength;
                    }
                    
                    /*
                     * Assumption is that longest matching portion of the string
                     * is the part that has not been changed between the from
                     * and to strings
                     */
                    if ($fragmentIndexOffset > $bestCopyLength) {
                        $bestCopyLength = $fragmentIndexOffset;
                        $bestFromStart = $fromBaseFragmentIndex;
                        $bestToStart = $toBaseFragmentIndex;
                    }
                }
                
                $fromBaseFragmentIndex += strlen($fromBaseFragment);
                
                /*
                 * no point to keep looking if what is left is less than
                 * current best match
                 */
                if ($bestCopyLength >= $fromSegmentEnd - $fromBaseFragmentIndex
                ) {
                    break;
                }
            } // end of while to find $bestCopyLength

            if ($bestCopyLength) {
                /*
                 * Job to diff segment between current segment start and start
                 * of longest found matching string
                 */
                $jobs[] = array(
                    $fromSegmentStart,
                    $bestFromStart,
                    $toSegmentStart,
                    $bestToStart
                    );
                $result[$bestFromStart * 4 + 2]
                                    = new Brvr_Diff_Op_Copy($bestCopyLength);
                /*
                 * Job to diff segment after current found longest matching
                 * string and end of current segment
                 */
                $jobs[] = array(
                    $bestFromStart + $bestCopyLength,
                    $fromSegmentEnd,
                    $bestToStart + $bestCopyLength,
                    $toSegmentEnd
                    );
                }
            /*
             * If there are no matching segments then a replace operation is
             * required
             */
            else {
                $result[$fromSegmentStart * 4 ]
                    = new Brvr_Diff_Op_Replace(
                        $fromSegmentLength,
                        substr($toText, $toSegmentStart, $toSegmentLength)
                        );
            }
        } // end of jobs while loop
        ksort($result, SORT_NUMERIC);
        return array_values($result);
    }

    /**
    * Perform a character-level diff.
    *
    * The algorithm is quite similar to doFragmentDiff(), except that
    * the code path is optimized for character-level diff -- strpos() is
    * used to find out the longest common subequence of characters.
    *
    * We try to find a match using the longest possible subsequence, which
    * is at most the length of the shortest of the two strings, then incrementally
    * reduce the size until a match is found.
    *
    * I still need to study more the performance of this function. It
    * appears that for long strings, the generic doFragmentDiff() is more
    * performant. For word-sized strings, doCharDiff() is somewhat more
    * performant.
    *
    * @param string $fromText
    * @param string $toText
    * @return array
    */
    private static function doCharDiff($fromText, $toText) {
        $result = array();
        $jobs = array(array(0, strlen($fromText), 0, strlen($toText)));
        while ( $job = array_pop($jobs) ) {
            list(
                $fromSegmentStart,
                $fromSegmentEnd,
                $toSegmentStart,
                $toSegmentEnd
                ) = $job;
            
            /*
             * Catch easy cases: if either the from or to segment are empty then
             * it will be an insert or delete operation respectively. If both
             * are empty then just skip to the next job
             */
            $fromSegmentLen = $fromSegmentEnd - $fromSegmentStart;
            $toSegmentLen = $toSegmentEnd - $toSegmentStart;
            if (!$fromSegmentLen || !$toSegmentLen) {
                if ($fromSegmentLen) {
                    $result[$fromSegmentStart * 4 + 0]
                        = new Brvr_Diff_Op_Delete($fromSegmentLen);
                }
                elseif ($toSegmentLen) {
                    $result[$fromSegmentStart * 4 + 1]
                        = new Brvr_Diff_Op_Insert(
                            substr($toText, $toSegmentStart, $toSegmentLen)
                            );
                }
                continue;
            }
            
            if ($fromSegmentLen >= $toSegmentLen) {
                /*
                 * Start with looking for best possible match then decrease
                 * length of 'needle' search string until all possible needles
                 * searched for or a match is found
                 */
                $copyLen = $toSegmentLen;
                while ($copyLen) {
                    $toCopyStart = $toSegmentStart;
                    $toCopyStartMax = $toSegmentEnd - $copyLen;
                    
                    /*
                     * Check each possible string of length $copyLen
                     */
                    while ($toCopyStart <= $toCopyStartMax) {
                        $fromCopyStart = strpos(
                                            substr(
                                                $fromText, 
                                                $fromSegmentStart,
                                                $fromSegmentLen
                                                ),
                                            substr(
                                                $toText,
                                                $toCopyStart,
                                                $copyLen
                                                )
                                            );
                        if ($fromCopyStart !== false  {
                            $fromCopyStart += $fromSegmentStart;
                            break 2;
                        }
                        $toCopyStart++;
                    }
                    $copyLen--;
                }
            }
            else {
                /*
                 * Start with looking for best possible match then decrease
                 * length of 'needle' search string until all possible needles
                 * searched for or a match is found
                 */
                $copyLen = $fromSegmentLen;
                while ($copyLen) {
                    $fromCopyStart = $fromSegmentStart;
                    $fromCopyStartMax = $fromSegmentEnd - $copyLen;
                    
                    /*
                     * Check each possible string of length $copyLen
                     */
                    while ($fromCopyStart <= $fromCopyStartMax) {
                        $toCopyStart = strpos(
                                            substr(
                                                $toText,
                                                $toSegmentStart,
                                                $toSegmentLen),
                                            substr($fromText,
                                                $fromCopyStart,
                                                $copyLen
                                                )
                                            );
                        if ($toCopyStart !== false) {
                            $toCopyStart += $toSegmentStart;
                            break 2;
                        }
                        $fromCopyStart++;
                    }
                    $copyLen--;
                }
            }
            // match found
            if ($copyLen) {
                /*
                 * Job to diff segment between current segment start and start
                 * of longest found matching string
                 */
                $jobs[] = array(
                    $fromSegmentStart,
                    $fromCopyStart,
                    $toSegmentStart,
                    $toCopyStart
                    );
                $result[$fromCopyStart * 4 + 2]
                    = new Brvr_Diff_Op_Copy($copyLen);
                /*
                 * Job to diff segment after current found longest matching
                 * string and end of current segment
                 */
                $jobs[] = array(
                    $fromCopyStart + $copyLen,
                    $fromSegmentEnd,
                    $toCopyStart + $copyLen,
                    $toSegmentEnd
                    );
                }
            /*
             * If there are no matching segments then a replace operation is
             * required
             */
            else {
                $result[$fromSegmentStart * 4]
                    = new Brvr_Diff_Op_Replace(
                        $fromSegmentLen,
                        substr($toText, $toSegmentStart, $toSegmentLen)
                        );
            }
        } // end of jobs while loop
        ksort($result, SORT_NUMERIC);
        return array_values($result);
    }

    /**
     * Efficiently fragment the text into an array according to specified
     * delimiters.
     *
     * No delimiters means fragment into single character.
     *
     * The array indices are the offset of the fragments into the input string.
     * A sentinel empty fragment is always added at the end. Its index is the
     * length of the string
     *
     * Careful: No check is performed as to the validity of the
     * delimiters.
     *
     * @param string $text
     * @param string $delimiters characters at which to split the $text
     * @return array
     */
    private static function extractFragments($text, $delimiters)
    {
        // special case: split into characters
        if ( empty($delimiters) ) {
            $chars = str_split($text, 1);
            $chars[strlen($text)] = '';
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
            $fragments[$start] = substr($text, $start, $end - $start);
            $start = $end;
        }
        $fragments[$start] = '';
        return $fragments;
    }
}