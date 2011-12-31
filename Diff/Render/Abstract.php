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
 * @see Brvr_Diff_Ops
 */
require_once 'Brvr/Diff/Ops.php';

/**
 * Common methods and properties for Brvr_Diff_Render_* classes
 *
 * @category Brvr
 * @package Brvr_Diff
 * @subpackage Brvr_Diff_Render
 */
abstract class Brvr_Diff_Render_Abstract
{
    /**
     * Text to render
     *
     * @var string
     */
    protected $_source;
    
    /**
     * Ops to use for rendering
     *
     * @var Brvr_Diff_Ops
     */
    protected $_ops;
    
    /**
     * Direction to render
     *
     * Boolean value. True means that the Brvr_Diff_Render_Abstract::$_source
     * will be treated as the older version and rendering will be applied in a
     * forward direction. Conversely false means that
     * Brvr_Diff_Render_Abstract::$_source is treated as the newer version and
     * changes are applied backwards (rolled back)
     *
     * @var boolean
     */
    protected $_direction = true;
    
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
    {
        $this->_source = $source;
        $this->_ops    = new Brvr_Diff_Ops($opcodes);
        if ($forward === false) {
            $this->_direction = false;
        }
    }
    
    /**
     * Get source string
     *
     * @return string
     */
    public function getSource()
    {
        return $this->_source;
    }
    
    /**
     * Get opcodes
     *
     * @return string
     */
    public function getOpcodes()
    {
        return $this->_ops->getOpcodes();
    }
} // class Brvr_Diff_Render_Abstract