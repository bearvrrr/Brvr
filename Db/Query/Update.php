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
 * @see Brvr_Db_Query_Abstract
 */
require_once 'Brvr/Db/Query/AlterRowAbstract.php';

/**
 *
 * Update Query Class
 *
 * This class is allows programmatic construction of UDPATE statemnts using the
 * single table syntax. If updating several tables at the same time is required
 * then transactions should be used. The single table syntax is followed to
 * provide a simple interface to make use of the PDOStatement bindValue and
 * bindParam methods.
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

class Brvr_Db_Query_Update extends Brvr_Db_Query_AlterRowAbstract
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
        self::COLUMNS                 => array(),
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
     * Add column name to which to alter data.
     *
     * @param string $columnName Name of the column as it appears in the
     *     database
     */
    public function column($columnName)
    {
        return $this->_addBindableColumn($columnName);
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
            if (empty($this->_parts[self::TABLE]) || 
                empty($this->_parts[self::COLUMNS])
            ) {
                /**
                 * @see Brvr_Db_Query_Update_Exception
                 */
                require_once 'Brvr/Db/Query/Update/Exception.php';
                throw new Brvr_Db_Query_Update_Exception('Required parts of the'
                    . 'Query (table or columns) have not been set');
            }
            
            $sql = 'UPDATE ';
            
            if ($this->_parts[self::PRIORITY] === self::LOW_PRIORITY) {
                $sql .= 'LOW_PRIORITY ';
            }
            
            if ($this->_parts[self::IGNORE] === true) {
                $sql .= 'IGNORE ';
            }
            
            $sql .= "{$this->_parts[self::TABLE]} SET ";
            $sql .= $this->_renderListFragment($this->_parts[self::COLUMNS]);
            
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
} // class Brvr_Db_Query_Update