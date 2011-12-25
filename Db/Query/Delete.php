<?php

/**
 * Brvr Library
 *
 * @author Andrew Bates <andrew.bates@cantab.net>
 * @version 0.1
 * @category Brvr
 * @package Brvr_Db
 * @subpackage Brvr_Db_Query
 */

/**
 * @see Brvr_Db_Query_Abstract
 */
require_once 'Brvr/Db/Query.php';

/**
 *
 * Delete Query Class
 *
 * This class is used to construct DELETE queries that act on single tables
 * only. Since this is a class constructs SQL queries programmatically it was
 * decided that if several tables need rows deleting from them at the same time
 * then transactions should be used.
 *
 * This class makes use of the bindValue and bindParam methods of the
 * PDOStatement object. These functions allow sanitisation of user input so it
 * is advised that placeholders are used for all user input
 * @see Brvr_Db_Query_Abstract for more information
 *
 * After _prepare() has been called for the first time, alterations made to the
 * parts array will have no effect unless it is specifically unset
 *
 * @category Brvr
 * @package Brvr_Db
 * @subpackage Brvr_Db_Query
 */

class Brvr_Db_Query_Delete extends Brvr_Db_Query
{
    /**
     * Array to hold the fragments of the SQL statement
     *
     * @var array
     */
    protected $_parts = array(
        self::PRIORITY                => null,
        self::IGNORE                  => false,
        self::TABLE                   => null,
        self::WHERE                   => null,
        self::ORDER                   => array(),
        self::LIMIT_COUNT             => null
    );
    
    
    
    /**
     * Set option to make the query low priority so that the update is delayed
     * until no clients are reading the table
     *
     * @return Brvr_Db_Query_Update
     */
    public function lowPriority()
    {
        $this->_parts[self::PRIORITY] = self::LOW_PRIORITY;
        return $this;
    }
    
    /**
     * Set options for statement not to abort, even if errors occur during the
     * update
     */
    public function ignore()
    {
        return $this->_ignore();
    }
    
    /**
     * Set the table in which rows will be updated
     *
     * @param string $tableName Name of the table as it appears in the database
     */
    public function table($tableName)
    {
        return $this->_setTable($tableName);
    }
    
    /**
     * Return the Brvr_Db_Query_WhereCondition object instance representing the
     * contents of the WHERE clause
     *
     * @return Brvr_Db_Query_WhereCondition
     */
    public function getWhere()
    {
        return $this->_getWhereCondition(self::WHERE);
    }
    
    /**
     * Set the value for the WHERE condition clause
     *
     * @param string|Brvr_Db_Query_WhereCondition $whereCondition Value for
     *      the WHERE clause
     * @param string If a string is passed to $whereCondition then
     *     $conditionGlue is passed to the constructor of the
     *     Brvr_Db_Query_WhereCondition
     */
    public function setWhere($whereCondition, $conditionGlue = null)
    {
        return $this->_setWhereCondition(self::WHERE, $whereCondition,
                                                            $conditionGlue);
    }
    
    /**
     * Add column to the ORDER BY clause
     *
     * @param string $column The column name to order by or an expression/
     *     function with which to sort by
     * @param boolean $sort true, the default represents ASC whilst false adds
     *     DESC
     */
    public function orderBy($column, $sort = true)
    {
        return $this->_addSortClause(self::ORDER, $column, $sort);
    }
    
    /**
     * Set the LIMIT clause for the UPDATE query
     *
     * @param number $count The maximum number of rows to be affected by the
     *     UPDATE query
     */
    public function limit($count)
    {
        return $this->_setLimitClause(self::LIMIT_COUNT, $count);
    }
    
    /**
     * @see Brvr_Db_Query_Abstract::_bindParam
     */
    public function bindParam(
        $parameter,
        &$variable,
        $dataType = PDO::PARAM_STR,
        $length = null)
    {
        return $this->_bindParam($parameter, $variable, $dataType, $length);
    }
    
    /**
     * @see Brvr_Db_Query_Abstract::_bindValue
     */
    public function bindValue($parameter, $value, $dataType = PDO::PARAM_STR)
    {
        return $this->_bindValue($parameter, $value, $dataType);
    }
    
    
    /**
     * Protected Methods
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
            if (empty($this->_parts[self::TABLE])) {
                /**
                 * @see Brvr_Db_Query_Delete_Exception
                 */
                require_once 'Brvr/Db/Query/Delete/Exception.php';
                throw new Brvr_Db_Query_Delete_Exception('The table to delete '
                    , 'from has not been set');
            }
            
            $sql = 'DELETE ';
            
            if ($this->_parts[self::PRIORITY] === self::LOW_PRIORITY) {
                $sql .= 'LOW_PRIORITY ';
            }
            
            if ($this->_parts[self::IGNORE] === true) {
                $sql .= 'IGNORE ';
            }
            
            $sql .= 'FROM ' . $this->_parts[self::TABLE];
            
            if ($this->_parts[self::WHERE] !== null) {
                $sql .= ' WHERE ' . $this->_parts[self::WHERE];
            }
            
            if (!empty($this->_parts[self::ORDER])) {
                $sql .= ' ORDER BY '
                      . $this->_renderSortFragment($this->_parts[self::ORDER]);
            }
            
            if ($this->_parts[self::LIMIT_COUNT] !== null) {
                $sql .= ' LIMIT ' . $this->_parts[self::LIMIT_COUNT];
            }
            
            $this->_statement = $this->_adapter->prepare($sql);
        }
        return $this->_statement;
    }
} // class Brvr_Db_Query_Delete