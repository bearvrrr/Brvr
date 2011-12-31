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
 * Interface for classes rendering opcodes
 *
 * For usage {@see Brvr_Diff}
 *
 * @category Brvr
 * @package Brvr_Diff
 * @subpackage Brvr_Diff_Render
 */
interface Brvr_Diff_Render_Interface
{
    /**
     * Constructor
     *
     * @param string $source Source text to generate output from
     * @param string $opcodes Opcodes to apply to $source
     * @param boolean $forward (optional) Set to true if $source is the 'older'
     *     string to render 'forwards' or false is the 'newer' string to render
     *     backwards
     */
    public function __construct($source, $opcodes, $forward = true);
    
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
     * Output string dervied from source and opcodes
     *
     * @return string
     */
    public function render();
    
    /**
     * Render string in a 'forward' direction using opcodes
     *
     * @param string $source Source text to generate output from
     * @param string $opcodes Opcodes to apply to $source
     * @return string
     */
    public static renderForward($source, $opcodes);
    
    /**
     * Render string in a 'backward' direction using opcodes
     *
     * @param string $source Source text to generate output from
     * @param string $opcodes Opcodes to apply to $source
     * @return string
     */
    public static renderBackward($source, $opcodes);
}