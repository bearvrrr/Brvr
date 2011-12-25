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
 * @see Brvr_Db_Query_WhereCondition
 */
require_once 'Brvr/Db/Query/WhereCondition.php';

/**
 * Class containing common functions for programmatically composing SQL
 * statements
 *
 * This class handles functions for generating SQL fragments common to several
 * different queries. It also has functions required for binding variables and
 * values to placeholders in the SQL statement produced.
 *
 * @todo Document this class and the rest of the module.
 * @todo Move the alias stuff to the select class.
 *
 * @category Brvr
 * @package Brvr_Db
 * @subpackage Brvr_Db_Query
 */
abstract class Brvr_Db_Query
{
    /**
     * Constants for the keys of the $_parts array
     */
    const PRIORITY = 'PRIORITY';
    const IGNORE = 'IGNORE';
    const COLUMNS = 'COLUMNS';
    const TABLE = 'TABLE';
    const FROM = 'FROM';
    const JOINS = 'JOINS';
    const WHERE = 'WHERE';
    const HAVING = 'HAVING';
    const ORDER = 'ORDER';
    const LIMIT_COUNT = 'LIMIT_COUNT';
    const LIMIT_OFFSET = 'LIMIT_OFFSET';
    
    /**
     * Constants used to pass and store join types in related functions
     */
    const JOIN_INNER = 0;
    const JOIN_LEFT = 1;
    
    /**
     * Constants to represent priority of the INSERT statement and also used to
     * set LOW_PRIORITY in UPDATE and DELETE statements
     */
    const DELAYED_PRIORITY = 0;
    const LOW_PRIORITY = 1;
    const HIGH_PRIORITY = 2;
    
    /**
     * Used to express how to bind variables
     */
    const BIND_PARAM = 'BIND_PARAM';
    const BIND_VALUE = 'BIND_VALUE';
    
    /**
     * Array to hold the fragments of the SQL statement
     *
     * @var array
     */
    protected $_parts = array();
    
    
    /**
     * Array of all aliases used in any of the SQL fragments stored in $_parts
     *
     * @var array
     */
    protected $_aliases = array();
    
    /**
     * Array to hold bound parameters and values for interpolating user input
     * into queries
     *
     * @var array
     */
    protected $_bind = array();
    
    /**
     * The parameter format for bound variables and values
     */
    protected $_bindType;
    
    /**
     * PDO Db Adapter
     *
     * @var object
     */
    protected $_adapter;
    
    /**
     * Prepared PDO statement
     *
     * @var PDOStatment
     */
    protected $_statement = null;
    
    /**
     * Public Methods
     */
    
    /**
     * Instantiate new Brvr_Db_Query_Select object
     *
     * @param PDO $dataObject A PDO instance
     * @throws Brvr_Db_Query_Exception
     */
    public function __construct($dataObject)
    {
        if (!($dataObject instanceof PDO)) {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('A PDO instance must be passed to'
                . 'the constructor');
        }
        $this->_adapter = $dataObject;
    }
    
    
    /**
     * Execute prepared statement
     */
    public function execute()
    {
        // Get the prepared statement
        $statement = $this->_prepare();
        // Binderise!
        ksort($this->_bind);
        foreach($this->_bind as $parameter => $data) {
            // Produce an array for call user func. Assumes set in correct order
            $args = array($parameter) + $data;
            unset($args['type']);
            $args = array_values($args);
            if ($data['type'] === self::BIND_PARAM) {
                call_user_func_array(array($statement, 'bindParam'), $args);
            } elseif ($data['type'] === self::BIND_VALUE) {
                call_user_func_array(array($statement, 'bindValue'), $args);
            }
            
        }
        // True on success, false on failure.
        return $this->_statement->execute();
    }
    
    /**
     * Unset prepared statement
     */
     public function flushStatement()
     {
         $this->_statement = null;
     }
    
    /**
     * SQL/Database Error Handling
     */
    
    /**
     * Fetch extended error information associated with the last query executed
     *
     * @return array Error information consisting of the following fields
     *    0: SQLSTATE error code (a five characters alphanumeric identifier
     *        defined in the ANSI SQL standard).
     *    1: Driver specific error code.
     *    2: Driver specific error message.
     * @see PDOStatement->errorInfo() for more information
     */
    public function errorInfo()
    {
        return $this->_statement->errorInfo();
    }
    
    /**
     * Protected Methods
     */
    
    /**
     * Form sql statement from the contents of the $_parts array and use this to
     * instantiate a PDOStatement prepared statement object
     *
     * @return object PDOStatement
     */
    abstract protected function _prepare();
    
    /**
     * Turn on the option for any queries that fail to do so quietly
     *
     * @return Brvr_Db_Query_Abstract
     */
    protected function _ignore()
    {
        $this->_parts[self::IGNORE] = true;
        return $this;
    }
    
    /**
     * Set the table name.
     *
     * This method is to be used by classes that produce queries that only
     * operate on one table
     *
     * @param string $tableName Name of the table
     * @return object Brvr_Db_Query_Insert_Exception
     * @return object Brvr_Db_Query_Insert
     */
    protected function _setTable($tableName)
    {
        if (empty($tableName) ||
            !is_string($tableName))
        {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('Table name must not be empty');
        }
        $this->_parts[self::TABLE] = (string) $tableName;
        return $this;
    }
    
    /**
     * Check whether a string corresponds to the name of a clause that can have
     * a where condition as an argument
     *
     * @param string $target value to be checked
     * @return boolean true if $target corresponds to either self::WHERE or
     *     self::HAVING or false otherwise
     */
    private function _validateWhereConditionTarget($target)
    {
        switch ($target) {
            case self::WHERE:
            case self::HAVING:
                $isTarget = true;
                break;
            default:
                $isTarget = false;
        }
        return $isTarget;
    }
    
    /**
     * Get the where condition set to the $target index of the $_parts array
     *
     * @param string $target name of the index from which to retrieve the where
     *     condition
     * @return Brvr_Db_Query_WhereCondition
     */
    protected function _getWhereCondition($target)
    {
        if (!$this->_validateWhereConditionTarget($target)) {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('Target must be the where or ' .
                'having clause');
        }
        
        if (!($this->_parts[$target] instanceof Brvr_Db_Query_WhereCondition)) {
            return false;
        }
        
        return $this->_parts[$target];
    }
    
    /**
     * Return the where condition corresponding either to the where or the
     * HAVING clause of the query
     *
     * @param string $target name of the index of the _parts array which to set
     *     the where condition to
     * @param string|Brvr_Db_Query_WhereCondition $whereCondition where
     *     condition to set
     * @param string|null $conditionGlue Either 'AND' or 'OR'. This variable is
     *     required only if $whereCondition is a string. Its is passed to the
     *     constructor for Brvr_Db_Query_WhereCondition. See the relevant class
     *     code for more information
     */
    protected function _setWhereCondition($target, 
                                          $whereCondition,
                                          $conditionGlue = null)
    {
        if (!$this->_validateWhereConditionTarget($target)) {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('Target must be the where or ' .
                'having clause');
        }
        
        if  ($whereCondition instanceof Brvr_Db_Query_WhereCondition) {
            $this->_parts[$target] = $whereCondition;
        } elseif (is_string($whereCondition)) {
            if ($conditionGlue === null) {
                $whereConObj = new Brvr_Db_Query_WhereCondition();
            } else {
                $whereConObj = new Brvr_Db_Query_WhereCondition($conditionGlue);
            }
            $this->_parts[$target] = $whereConObj->addWhereCondition(
                                                            $whereCondition);
        } else {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('$whereCondition must be either a'
                . ' string or an instance of Brvr_Db_Query_WhereCondition');
        }
        
        return $this;
    }
    
    /**
     * Add columns to GROUP BY and ORDER by clauses
     *
     * @param string $targetClause Must be equal to an index in the parts array
     *     that stores an array as its value
     * @param string $columnOrExpr The column name to order by or an expression/
     *     function with which to sort by
     * @param boolean $sort true, the default represents ASC whilst false adds
     *     DESC
     * @return Brvr_Db_Query_Abstract
     */
    protected function _addSortClause($targetClause, 
                                      $columnOrExpr,
                                      $sort = true)
    {
        // if (!(is_string($targetClause) && !empty($targetClause))) {
        if (!array_key_exists($targetClause, $this->_parts)) {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('$targetClause parameter must not'
                . ' be empty');
        }
        
        if (!(is_string($columnOrExpr) && !empty($columnOrExpr))) {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('$columnOrExpr parameter must not'
                . ' be empty');
        }
        
        if (!is_bool($sort)) {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('orderBy method $sort parameter '
                . 'must be boolean');
        }
        
        $sortBy = trim(strval($columnOrExpr));
        $this->_parts[$targetClause][$sortBy] = $sort;
        
        return $this;
    }
    
    /**
     * Add an integer value to a limit clause
     *
     * @param string $target Name of the limit clause to set the value of. Must
     *     be equal to either 'LIMIT_COUNT' or 'LIMIT_OFFSET'
     * @param int $limitInteger Value to be set to the limit clause
     * @throws Brvr_Db_Query_Exception
     * @return Brvr_Db_Query_Abstract
     */
    protected function _setLimitClause($target, $limitInteger)
    {
        if (($target !== self::LIMIT_COUNT) &&
            ($target !== self::LIMIT_OFFSET)
        ) {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('$target must be equal to one of '
                . 'the LIMIT_* constants');
        }
        
        if (!is_numeric($limitInteger)) {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('Values assosciated with LIMIT_* '
                . 'keys in the $_parts array must be numerical');
        }
        
        $this->_parts[$target] = (integer) $limitInteger;
        
        return $this;
    }
    
    /**
     * Store references to variables along with placeholders to which to bind
     * the variables to a placeholder within the SQL query.
     *
     * The arguments for this function are stored and later used when calling
     * PDOStatement->bindParam. As such documentation for the parameters is
     * taken from that method
     *
     * @param string|integer $parameter Parameter identifier. For a prepared 
     *     statement using named placeholders, this will be a parameter name of
     *     the form :name. For a prepared statement using question mark
     *     placeholders, this will be the 1-indexed position of the parameter.
     * @param mixed $variable reference to the variable to bind to SQL statement
     *     parameter
     * @param integer $dataType Explicit data type for the parameter using the
     *     PDO::PARAM_* constants
     * @param integer $length Length of the data type. To indicate that a
     *     parameter is an OUT parameter from a stored procedure, you must
     *     explicitly set the length. 
     */
    protected function _bindParam($parameter,
                                  &$variable, 
                                  $dataType = PDO::PARAM_STR,
                                  $length = null)
    {
        if (!is_int($dataType) ||
           (!is_int($length) && $length !== null))
        {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('Variables of incrorrect type '
                . 'passed method bindParam');
        }
        $this->_validateBindParameter($parameter);
        
        $this->_bind[$parameter] = array(
            'type' => self::BIND_PARAM,
            'variable' => &$variable,
            'dataType' => $dataType
            );
        if ($length !== null) {
            $this->_bind[$parameter]['length'] = $length;
        }
        
        return $this;
    }
    
    /**
     * Store values along with placeholders to which to bind the values to a
     * placeholder within the SQL query.
     *
     * The arguments for this function are stored and later used when calling
     * PDOStatement->bindValue. As such documentation for the parameters is
     * taken from that method
     *
     * @param string|integer $parameter Parameter identifier. For a prepared 
     *     statement using named placeholders, this will be a parameter name of
     *     the form :name. For a prepared statement using question mark
     *     placeholders, this will be the 1-indexed position of the parameter.
     * @param mixed $variable The value to bind to the parameter
     * @param integer $dataType Explicit data type for the parameter using the
     *     PDO::PARAM_* constants
     */
    protected function _bindValue($parameter, 
                                  $value,
                                  $dataType = PDO::PARAM_STR)
    {
        if (!is_int($dataType)) {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('Variables of incrorrect type '
                . 'passed method bindValue');
        }
        
        $this->_validateBindParameter($parameter);
        
        $this->_bind[$parameter] = array(
            'type' => self::BIND_VALUE,
            'variable' => $value,
            'dataType' => $dataType
            );
        
        return $this;
    }
    
    /**
     * Returns an array as a comma seperated list for use in building SQL
     * statements
     *
     * @param array $source Array to be used to produce comma seperated value
     *     list
     * @throws Brvr_Db_Query_Exception
     * @return string
     */
    protected function _renderListFragment($source)
    {
        if (!is_array($source)) {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('Parameter must be an array');
        }
        
        return implode(', ', $source);
    }
    
    /**
     * Produce string to use for GROUP BY or ORDER BY clauses of the select
     * statement
     *
     * @param array $source Should be of the form 'column/expression/alias to
     *     sort by' => boolean where true represents the default ASC sort and
     *     false represents the DESC sort
     * @throws Brvr_Db_Query_Exception
     * @return string
     */
    protected function _renderSortFragment($source)
    {
        if (!is_array($source)) {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('Parameter must be an array');
        }
        
        $sortList = '';
        if (!empty($source)) {
            foreach($source as $sortItem => $direction) {
                $sortList .= ", $sortItem";
                if ($direction === false) $sortList .= ' DESC';
            }
            $sortList = substr($sortList, 2);
        }
        return $sortList;
    }
    
    /**
     * Check that the parameter name passed to binding functions corresponds to
     * the type previously passed to bind functions.
     * 
     * For example, if named parameters are used then all subsequent parameter
     * names are of the form ':name' and if question mark place holders are used
     * then all subsequent parameters are integers.
     *
     * @param mixed $parameter Variable to validate
     * @throws Brvr_Db_Query_Exception
     */
    protected function _validateBindParameter($parameter)
    {
        if (!is_int($parameter) && !is_string($parameter)) {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('Parameter of incorrect type');
        }
        
        if (is_numeric($parameter)) {
            if ($this->_bindType === true) { // bind type ':name' format
                /**
                 * @see Brvr_Db_Query_Exception
                 */
                require_once 'Brvr/Db/Query/Exception.php';
                throw new Brvr_Db_Query_Exception('Incompatible parameter types'
                    . ' used with bind functions');
            } else {
                $this->_bindType = false;
            }
        } else { // therefore must be a string (see above)
            if ($this->_bindType === false) { // bind type '?' format
                /**
                 * @see Brvr_Db_Query_Exception
                 */
                require_once 'Brvr/Db/Query/Exception.php';
                throw new Brvr_Db_Query_Exception('Incompatible parameter types'
                    . ' used with bind functions');
            } else {
                $this->_bindType = true;
            }
        }
    }
    
} // class Brvr_Db_Query