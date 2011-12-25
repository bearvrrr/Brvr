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
 * @see Brvr_Db_Query
 */
require_once 'Brvr/Db/Query.php';

/**
 *
 * General SQL Statement Class
 *
 * This class provides a consistent interface within the library for SQL
 * statements written externally to the class to have variables/values to be
 * bound to them then executed.
 *
 * The class inherits a retarded amount of constants and methods that are not
 * used. This is because PHP will not let abstract objects inherit abstract
 * methods so all the Brvr_Db_Query_* classes inherit from just one abstract
 * class.
 *
 * This class makes use of the bindValue and bindParam methods of the
 * PDOStatement object. These functions allow sanitisation of user input so it
 * is advised that placeholders are used for all user input
 * @see Brvr_Db_Query_Abstract for more information
 *
 * @category Brvr
 * @package Brvr_Db
 * @subpackage Brvr_Db_Query
 */
class Brvr_Db_Query_Statement extends Brvr_Db_Query
{
    /**
     * Used to store externally generated SQL
     */
    protected $_sql;
    
    /**
     * Public Methods
     */
    
    /**
     * Set the SQL query to be evaluated
     *
     * @param string $sql SQL query to be evaluated
     * @throws object Brvr_Db_Query_Statement_Exception
     * @return object Brvr_Db_Query_Statement
     */
    public function prepare($sql)
    {
        if (!is_string($sql)) {
            /**
             * @see Brvr_Db_Query_Statement_Exception
             */
            require_once 'Brvr/Db/Query/Statement/Exception.php';
            throw new Brvr_Db_Query_Statement_Exception('Parameter $sql should '
                . 'be a string');
        }
        $this->_sql = $sql;
        return $this;
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
     * @see PDOStatement->fetchColumn()
     */
    public function fetchColumn($columnNumber = 0)
    {
        return $this->_statement->fetchColumn($columnNumber);
    }
    
    /**
     * Protected methods
     */
    
    /**
     * Instantiate a PDOStatement object using $_sql property.
     *
     * @throws object Brvr_Db_Query_Statement_Exception
     * @return object PDOStatement
     */
    protected function _prepare()
    {
        if (empty($this->_statement)) {
            if (empty($this->_sql)) {
                /**
                 * @see Brvr_Db_Query_Statement_Exception
                 */
                require_once 'Brvr/Db/Query/Statement/Exception.php';
                throw new Brvr_Db_Query_Statement_Exception('There is no SQL '
                    . 'query to prepare');
            }
            
            $this->_statement = $this->_adapter->prepare($this->_sql);
        }
        
        return $this->_statement;
    }
        
    
} // Brvr_Db_Query_Statement