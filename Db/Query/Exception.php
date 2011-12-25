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
 * @author Andrew Bates <andrew.bates@cantab.net>
 * @version 0.1
 * @category Brvr
 * @package Brvr_Db
 * @subpackage Brvr_Db_Query
 */

/**
 * @see Brvr_Exception
 */
require_once 'Brvr/Exception.php';

/**
 * Exceptions to be thrown by Brvr_Db_Query
 *
 * @todo Consider adding error definitions here if they are reqiured elsewhere.
 *     The error codes created should ideally only be codes that can be
 *     applicable to all descendants of the Brvr_Db_Query_Abstract class
 *
 * @category Brvr
 * @package Brvr_Db
 * @subpackage Brvr_Db_Query
 */
class Brvr_Db_Query_Exception extends Brvr_Exception
{
}