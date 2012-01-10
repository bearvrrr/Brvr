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
 * @package Brvr_Db
 * @subpackage Brvr_Db_Query
 */

/**
 * @see Brvr_Db_Query_Abstract
 */
require_once 'Brvr/Db/Query/AlterRowAbstract.php';

/**
 *
 * Insert Query Class
 *
 * This class will not do INSERT ... SELECT type statements.
 *
 * This class is used to programmatically construct INSERT...SET SQL queries.
 * It produces INSERT...SET rather than INSERT...VALUES queries since this makes
 * the interface simpler and can also use the bindParam and bindValues methods
 * of the PDOStatement class in a more simple fashion.
 *
 * This class makes use of the bindValue and bindParam methods of the
 * PDOStatement object. These functions allow sanitisation of user input so it
 * is advised that placeholders are used for all user input
 * @see Brvr_Db_Query_Abstract for more information
 *
 * After _prepare() has been called for the first time, alterations made to the
 * parts array will have no effect unless it is specifically unset
 *
 * @todo ON_DUPLICATE_KEY_UDPATE method/syntax
 *
 * @category Brvr
 * @package Brvr_Db
 * @subpackage Brvr_Db_Query
 */
class Brvr_Db_Query_Insert extends Brvr_Db_Query_AlterRowAbstract
{
    /**
     * Constants for the keys of the $_parts array
     */
    const ON_DUPLICATE_KEY_UPDATE = 'ON_DUPLICATE_KEY_UPDATE';
    
    /**
     * Array to hold the fragments of the SQL statement
     *
     * @var array
     */
    protected $_parts = array(
        self::PRIORITY                => null,
        self::IGNORE                  => false,
        self::TABLE                    => null,
        self::COLUMNS                 => array(),
        self::ON_DUPLICATE_KEY_UPDATE => array() // Currently not used
    );
    
    /**
     * Set the priority keyword in the insert statement
     *
     * @param int $priority Corresponding to the *_PRIORITY constants of the
     *     Brvr_Db_Query_Insert class
     * @throws Brvr_Db_Query_Insert_Exception
     * @return Brvr_Db_Query_Insert
     */
    public function priority($priority)
    {
        if (($priority !== self::DELAYED_PRIORITY) &&
            ($priority !== self::LOW_PRIORITY) &&
            ($priority !== self::HIGH_PRIORITY)
        ) {
            /**
             * @see Brvr_Db_Query_Insert_Exception
             */
            require_once 'Brvr/Db/Query/Insert/Exception.php';
            $error = 'Values for priority must be equal to the '
                   . 'Brvr_Db_Query_Insert::*_PRIORITY constants';
            throw new Brvr_Db_Query_Insert_Exception($error);
        }
        $this->_parts[self::PRIORITY] = $priority;
        return $this;
    }
    
    /**
     * Add column name to which to insert data.
     *
     * @param string $columnName Name of the column as it appears in the
     *     database
     */
    public function column($columnName)
    {
        return $this->_addBindableColumn($columnName);
    }
    
    /**
     * Set the name of the table into which the row of data will be added
     *
     * @param string $tableName Name of the table
     * @return object Brvr_Db_Query_Insert_Exception
     * @return object Brvr_Db_Query_Insert
     */
    public function into($tableName)
    {
        return $this->_setTable($tableName);
    }
    
    /**
     * Returns the ID of the last inserted row or sequence value
     * @return int
     */
    public function lastInsertId()
    {
        return $this->_adapter->lastInsertId();
    }
    /**
     * Protected/Private Methods
     */
    
    /**
     * Conjugate SQL statement from the contents of the parts array and pass it
     * to the PDO object
     *
     * @return object PDOStatement
     */
    protected function _prepare()
    {
        if (empty($this->_statement)) {
            if (empty($this->_parts[self::TABLE]) ||
                empty($this->_parts[self::COLUMNS])
            ) {
                /**
                 * @see Brvr_Db_Query_Insert_Exception
                 */
                require_once 'Brvr/Db/Query/Insert/Exception.php';
                $error = 'Required parts of the Query (table or columns) have '
                       . 'not been set';
                throw new Brvr_Db_Query_Insert_Exception($error);
            }
            
            $sql = 'INSERT ';
            
            switch ($this->_parts[self::PRIORITY]) {
            case self::DELAYED_PRIORITY:
                $sql .= 'DELAYED ';
                break;
            
            case self::LOW_PRIORITY:
                $sql .= 'LOW_PRIORITY ';
                break;
            
            case self::HIGH_PRIORITY:
                $sql .= 'HIGH_PRIORITY ';
                break;
            }
            
            if ($this->_parts[self::IGNORE] === true) {
                $sql .= 'IGNORE ';
            }
            
            $sql .= "INTO {$this->_parts[self::TABLE]} SET ";
            $sql .= $this->_renderListFragment($this->_parts[self::COLUMNS]);
            
            $this->_statement = $this->_adapter->prepare($sql);
        }
        
        return $this->_statement;
    }
    
} // class Brvr_Db_Query_Insert