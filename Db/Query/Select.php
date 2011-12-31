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
require_once 'Brvr/Db/Query.php';

/**
 *
 * Select Query Class
 *
 * This class allows the programmatic construction of SELECT queries. It does
 * not allow for the construction of SELECT...UNION queries.
 *
 * This class makes use of the bindValue and bindParam methods of the
 * PDOStatement object. These functions allow sanitisation of user input so it
 * is advised that placeholders are used for all user input
 * @see Brvr_Db_Query_Abstract for more information
 *
 * After _prepare() has been called for the first time, alterations made to the
 * parts array will have no effect unless it is specifically unset
 *
 * @todo collect all select only code back into this class
 * @todo method to generate an object ot be passed to SELECT..UNION and
 *     INSERT..SELECT query objects for forming their SQL and binding variables
 *
 * @category Brvr
 * @package Brvr_Db
 * @subpackage Brvr_Db_Query
 */
class Brvr_Db_Query_Select extends Brvr_Db_Query
{
    /**
     * Constants for the keys of the $_parts array specific to the SELECT
     * statement
     */
    const DISTINCT = 'DISTINCT';
    const GROUP = 'GROUP';
    const GROUP_ROLLUP = 'GROUP_ROLLUP';
    const LOCK_TYPE = 'LOCK_TYPE';
    
    /**
     * Use to store type of lock to be used on results of select statement
     */
    const FOR_UPDATE = 0;
    const SHARE_MODE = 1;
    
    /**
     * PDO Db Adapter
     *
     * @var object
     */
    protected $_adapter;
    
    /**
     * Array to hold the fragments of the SQL statement
     *
     * @var array
     */
    protected $_parts = array(
        self::DISTINCT     => false,
        self::COLUMNS      => array(),
        self::FROM         => array(),
        self::JOINS        => array(),
        self::WHERE        => null,
        self::GROUP        => array(),
        self::GROUP_ROLLUP => false,
        self::HAVING       => null,
        self::ORDER        => array(),
        self::LIMIT_OFFSET => null,
        self::LIMIT_COUNT  => null,
        self::LOCK_TYPE    => null
    );
    
    
    
    /**
     * Set option to remove duplicate rows from the result set
     *
     * @return Brvr_Db_Query_Select
     */
    public function distinct()
    {
        $this->_parts[self::DISTINCT] = true;
        return $this;
    }
    
    /**
     * Specify columns to be retrieved by the select statement
     *
     * @param string $columnExpression Column name or SQL function to be
     *     retrived as part of the result set
     * @param string|null $columnAlias Alias to be used to refer to data
     *     retrieved from $columnExpression in the result set. If null then no
     *     alias set
     * @return Brvr_Db_Query_Select
     */
    public function column($columnExpression, $columnAlias = null)
    {
        /**
          * @todo Validate the $columnExpression variable
          * Perhaps have this variable as a column name string or a Query
          * expression object if functions are required.
          */
        if ($columnAlias !== null) {
            $aliasString = $this->_makeAliasString($columnAlias);
        } else {
            $aliasString = '';
        }
        $this->_parts[self::COLUMNS][] = $columnExpression . $aliasString;
        
        return $this;
    }
    
    /**
     * Specify tables from which to retrieve data
     *
     * @param string $tableName Name of table
     * @param string|null Alias for table to be referred to in the query and
     *     result set
     * @return Brvr_Db_Query_Select
     */
    public function from($tableName, $tableAlias = null)
    {
        if ($tableAlias !== null) {
            $aliasString = $this->_makeAliasString($tableAlias);
        } else {
            $this->_reserveAlias($tableName);
            $aliasString = '';
        }
        $this->_parts[self::FROM][] = $tableName . $aliasString;
        
        return $this;
    }
    
    
    /**
     * Join Functions
     * These allow you to add tables for inner and left join clauses in an SQL
     * query.
     *
     * There is no functionality for straight joins. If this level of control is
     * required then the Brvr_Db_Query_Statement class should be used instead.
     */
    
    /**
     * Add a inner join clause to the SELECT statement
     *
     * @param string $condition An expression that specifies a relationship
     *     (such as equality) between two columns with which to make the join
     * @param string $tableName name of the table to join to those already
     *     included in the SELECT statement
     * @param string $tableAlias optional alias for the table
     * @return Brvr_Db_Query_Select
     */
    public function joinInner($condition, $tableName, $tableAlias = null )
    {
        return $this->_join(self::JOIN_INNER, $condition, $tableName,
                                                                $tableAlias);
    }
    
    /**
     * Add a left join clause to the SELECT statement
     *
     * @param string $condition An expression that specifies a relationship
     *     (such as equality) between two columns with which to make the join
     * @param string $tableName name of the table to join to those already
     *     included in the SELECT statement
     * @param string $tableAlias optional alias for the table
     * @return Brvr_Db_Query_Select
     */
    public function joinLeft($condition, $tableName, $tableAlias = null)
    {
        return $this->_join(self::JOIN_LEFT, $condition, $tableName,
                                                                $tableAlias);
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
     *     the WHERE clause
     * @param string If a string is passed to $whereCondition then
     *     $conditionGlue is passed to the constructor of the
     *     Brvr_Db_Query_WhereCondition
     * @return Brvr_Db_Query_Select
     */
    public function setWhere($whereCondition, $conditionGlue = null)
    {
        return $this->_setWhereCondition(self::WHERE, $whereCondition,
                                                            $conditionGlue);
    }
    
    /**
     * Add column to the GROUP BY clause
     *
     * @param string $columnOrExpr The column name to group by or an expression/
     *     function with which to group by
     * @param boolean $sort true, the default represents ASC whilst false adds
     *     DESC
     * @return Brvr_Db_Query_Select
     */
    public function groupBy($columnOrExpr, $sort = true)
    {
        return $this->_addSortClause(self::GROUP, $columnOrExpr, $sort);
    }
    
    public function groupByRollup($rollup = true)
    {
        $this->_parts[self::GROUP_ROLLUP] = $rollup;
        return $this;
    }
    
    /**
     * Return the Brvr_Db_Query_WhereCondition object instance representing the
     * contents of the HAVING clause
     *
     * @return Brvr_Db_Query_WhereCondition
     */
    public function getHaving()
    {
        return $this->_getWhereCondition(self::HAVING);
    }
    
    /**
     * Set the value for the HAVING condition clause
     *
     * @param string|Brvr_Db_Query_WhereCondition $whereCondition Value for
     *     the WHERE clause
     * @param string If a string is passed to $whereCondition then
     *     $conditionGlue is passed to the constructor of the
     *     Brvr_Db_Query_WhereCondition
     * @return Brvr_Db_Query_Select
     */
    public function setHaving($whereCondition, $conditionGlue = null)
    {
        return $this->_setWhereCondition(self::HAVING, $whereCondition,
                                                            $conditionGlue);
    }
    
    /**
     * Add column to the ORDER BY clause
     *
     * @param string $columnOrExpr The column name to order by or an expression/
     *     function with which to sort by
     * @param boolean $sort true, the default represents ASC whilst false adds
     *     DESC
     * @return Brvr_Db_Query_Select
     */
    public function orderBy($columnOrExpr, $sort = true)
    {
        return $this->_addSortClause(self::ORDER, $columnOrExpr, $sort);
    }
    
    /**
     * Set the limit clause for the SELECT query
     *
     * @param number $rowCount The maximum number of rows to be returned by the
     *     SELECT statement
     * @param number $offset Specify the offset for the rows returned in the
     *     result set.
     * @return Brvr_Db_Query_Select
     */
    public function limit($rowCount, $offset = 0)
    {
        $this->_setLimitClause(self::LIMIT_OFFSET, $offset);
        $this->_setLimitClause(self::LIMIT_COUNT, $rowCount);
        
        return $this;
    }
    
    /**
     * Add lock to result set of a the select query
     *
     * This function is to be used as part of a transaction where this
     * functionality is supported by the flavour of SQL RDMS used.
     *
     * @param integer $lockType The type of lock permitted. The value must
     *     either be equivalent to self::FOR_UPDATE or self::SHARE_MODE
     * @return Brvr_Db_Query_Select
     */
    public function lock($lockType)
    {
        if (($lockType !== self::FOR_UPDATE) &&
            ($lockType !== self::SHARE_MODE)
        ) {
            /**
             * @see Brvr_Db_Query_Select_Exception
             */
            require_once 'Brvr/Db/Query/Select/Exception.php';
            throw new Brvr_Db_Query_Select_Exception('Values for lockType must '
                . "be either '{self::FOR_UPDATE}' or '{self::SHARE_MODE}'");
        }
        
        $this->_parts[self::LOCK_TYPE] = $lockType;
        return $this;
    }
    
    /**
     * Binding functions
     */
    
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
     * Fetching functions
     */
    
    /**
     * @see PDOStatement->fetch()
     */
    public function fetch($fetchStyle = PDO::FETCH_ASSOC)
    {
        return $this->_statement->fetch($fetchStyle);
    }
    
    /**
     * @see PDOStatement->fetchAll()
     */
    public function fetchAll($fetchStyle = PDO::FETCH_ASSOC)
    {
        return $this->_statement->fetchAll($fetchStyle);
    }
    
    /**
     * Protected methods
     */
    
    /**
     * Use generated SQL statement to instantiate a PDOStatement prepared
     * statement object
     *
     * @return object PDOStatement
     */
    protected function _prepare()
    {
        if (empty($this->_statement)) {
            $this->_statement = $this->_adapter->prepare(
                                                $this->_generateStatement());
        }
        return $this->_statement;
    }
    
    /**
     * Form SQL statement from the contents of the $_parts array
     *
     * @return string
     */
    protected function _generateStatement()
    {
        $sql = 'SELECT ';
        if ($this->_parts[self::DISTINCT] === true) $sql .= ' DISTINCT ';
        
        if (!empty($this->_parts[self::COLUMNS])) {
            $sql .= $this->_renderListFragment($this->_parts[self::COLUMNS]);
        } else {
            $sql .= '*';
        }
        
        $sql .= ' FROM '
              . $this->_renderListFragment($this->_parts[self::FROM])
              . $this->_renderJoinsFragment();
        
        if ($this->_parts[self::WHERE] !== null) {
            $sql .= " WHERE {$this->_parts[self::WHERE]}";
        }
        
        if (!empty($this->_parts[self::GROUP])) {
            $sql .= ' GROUP BY '
                  . $this->_renderSortFragment($this->_parts[self::GROUP]);
            if ($this->_parts[self::GROUP_ROLLUP] === true) {
                $sql .= ' WITH ROLLUP';
            }
        }
        
        if ($this->_parts[self::HAVING] !== null) {
            $sql .= " HAVING {$this->_parts[self::HAVING]}";
        }
        
        if (!empty($this->_parts[self::ORDER])) {
            $sql .= ' ORDER BY '
                  . $this->_renderSortFragment($this->_parts[self::ORDER]);
        }
        
        // limit
        if ($this->_parts[self::LIMIT_COUNT] !== null) {
            $limit = ' LIMIT ';
            if ($this->_parts[self::LIMIT_OFFSET] !== null) {
                $limit .= $this->_parts[self::LIMIT_OFFSET] . ', ';
            }
            $sql .= $limit . $this->_parts[self::LIMIT_COUNT];
        }
        
        // lock
        if ($this->_parts[self::LOCK_TYPE] === self::FOR_UPDATE) {
            $sql .= ' FOR UPDATE';
        } elseif ($this->_parts[self::LOCK_TYPE] === self::SHARE_MODE) {
            $sql .= ' LOCK IN SHARE MODE';
        }
        
        return $sql;
    }
    
    /**
     * Add join fragment of SQL query to array of join fragments
     *
     * @param integer $type The type of join to be performed. Only inner or left
     *     joins are permitted.(For argument for this see above)
     * @param string $condition Condition to be satisfied in order to make the
     *     join
     * @param string $tableName Table to be joined to other tables in from or
     *     join clauses of the SQL query
     * @param string $tableAlias Alias to refer to the tabled joined in other
     *     parts of the query
     * @return Brvr_Db_Query_Select
     */
    protected function _join($type, $condition, $tableName, $tableAlias = null)
    {
        /**
         * @todo Add some form of validation for the $condition variable. Having
         * some form of condition object is planned
         */
        
        /*
          Here is a clumsy way to check the type. It allows to the function to
          carry on quietly if there is an error for the $type variable.
          It will need to be rewritten if there is ever more than two join
          types
        */
        if ($type !== self::JOIN_LEFT) {
            $type = self::JOIN_INNER;
        }
        
        if ($tableAlias !== null) {
            $aliasString = $this->_makeAliasString($tableAlias);
        } else {
            $this->_reserveAlias($tableName);
            $aliasString = '';
        }
        
        $joinString = $tableName . $aliasString . ' ON ' . $condition;
        
        $join = array(
            'type' => $type,
            'join' => $joinString
        );
        
        $this->_parts[self::JOINS][] = $join;
        return $this;
    }
    
    /**
     * Return string expressing joins to be performed by the SQL statement
     *
     * @return string
     */
    protected function _renderJoinsFragment()
    {
        $joins = '';
        if (!empty($this->_parts[self::JOINS])) {
            foreach($this->_parts[self::JOINS] as $fragment) {
                if ($fragment['type'] === self::JOIN_LEFT) {
                    $joins .= ' LEFT';
                } else {
                    $joins .= ' INNER';
                }
                $joins .= " JOIN {$fragment['join']}";
            }
        }
        
        return $joins;
    }
    
    /**
     * Check that an alias is not already in use then produce a string to be
     * incooporated into table name or column identifying clauses
     *
     * @param string $alias To be checked and returned in an alias clause.
     * @return string formatted as " AS $alias"
     */
    protected function _makeAliasString($alias)
    {
        $this->_reserveAlias($alias);
        return " AS $alias";
    }
    
    /**
     * Check an alias is a valid type and is not already in use then add it to
     * list of used aliases so it cannot be used twice
     *
     * @param string $alias To be checked
     * @throws Brvr_Db_Query_Select_Exception
     * @return string formatted as " AS $alias"
     */
    protected function _reserveAlias($alias)
    {
        if (empty($alias) || !is_string($alias)) {
            /**
              * @see Brvr_Db_Query_Select_Exception
              */
            require_once 'Brvr/Db/Query/Select/Exception.php';
            throw new Brvr_Db_Query_Select_Exception('Alias must be a '
                . 'non-empty string');
        }
        
        if (in_array($alias, $this->_aliases)) {
            /**
              * @see Brvr_Db_Query_Select_Exception
              */
            require_once 'Brvr/Db/Query/Select/Exception.php';
            throw new Brvr_Db_Query_Select_Exception("Alias '$alias' has already "
                . "been defined");
        }
        
        $this->_aliases[] = $alias;
        
        return true;
    }
    
    /**
     * Get the contents of the bound parameter array
     *
     * @return array
     */
    protected function _getBound()
    {
        return $this->_bind;
    }
    
} // Brvr_Db_Query_Select