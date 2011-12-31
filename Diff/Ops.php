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
 * Iterable collection of opcode objects
 *
 * The opcodes objects are derived from parsing an opcode string
 *
 * @category Brvr
 * @package Brvr_Diff
 */
class Brvr_Diff_Ops implements IteratorAggregate
{
    /**
     * Operations objects
     *
     * @var array
     */
    private $_ops = array();
    
    /**
     * Opcodes
     *
     * @var string
     */
    private $_opcodes;
    
    /**
     * Constructor
     */
    public function __construct($opcodes)
    {
        $ops = $this->parseOpcodes($opcodes);
        if ($ops === false) {
            /**
             * @see Brvr_Diff_Ops_Exception
             */
            require_once 'Brvr/Diff/Ops/Exception.php';
            throw new Brvr_Diff_Ops_Exception('Opcodes supplied are invalid');
        }
        $this->_ops     = $ops;
        $this->_opcodes = $opcodes;
    }
    
    /**
     * @see IteratorAggregate
     */
    public function getIterator()
    {
        return new ArrayIterator($this->_ops);
    }
    
    /**
     * Get Ops objects
     *
     * @return array
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
        return $this->_opcodes;
    }
    
    /**
     * Convert opcodes to an array of ops objects
     *
     * @param string $opcodes
     * @return array|boolean false is returned on failure
     */
    protected function parseOpcodes($opcodes)
    {
        $parsed = array();
        $opcodesOffset = 0;
        $opcodesLen = strlen($opcodes);
        while ($opcodesOffset <  $opcodesLen) {
            $opcode = substr($opcodes, $opcodesOffset, 1);
            $opcodesOffset++;
            $n = intval(substr($opcodes, $opcodesOffset));
            if ($n) {
                $opcodesOffset += strlen(strval($n));
            }
            else {
                $n = 1;
            }
            if ($opcode === 'c') { // copy n characters from source
                $parsed[] = new Brvr_Diff_Op_Copy($n);
            }
            elseif ($opcode === 'd') { // delete n characters from source
                $parsed[] = new Brvr_Diff_Op_Delete(
                                substr($opcodes, $opcodesOffset + 1, $n)
                                );
                $opcodesOffset += 1 + $n;
            }
            elseif ($opcode === 'i') { // insert n characters from opcodes
                $parsed[] = new Brvr_Diff_Op_Insert(
                                substr($opcodes, $opcodesOffset + 1, $n)
                                );
                $opcodesOffset += 1 + $n;
            }
            else { // opcodes string is invalid
                return false;
            }
        }
        return $parsed;
    }
} // class Brvr_Diff_Ops