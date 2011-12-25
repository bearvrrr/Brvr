<?php

/**
 * Brvr Library
 */

/**
 * @see Brvr_Db_Query_Abstract
 */
require_once 'Brvr/Db/Query.php';

/**
 *
 * Alter Row Abstract Query Class
 *
 * The class supplies methods common to the INSERT and UPDATE queries
 *
 */
abstract class Brvr_Db_Query_AlterRowAbstract extends Brvr_Db_Query
{
    /**
     * Array to cross reference column names and valid placeholders for
     * prepared statements
     *
     * @var array
     */
    protected $_placeholders = array();
    
    
    /**
     * Turn on the option for any queries that fail to do so quietly
     *
     * @return Brvr_Db_Query_AlterRowAbstract
     */
    public function ignore()
    {
        return $this->_ignore();
    }
    
    /**
     * Bind a variable with a column name.
     *
     * This will not add the column name to the $_parts array and thus will not
     * include the column name in the SQL statement automatically.
     *
     * @param string $columnName The name of the column to which to bind the
     *     variable. It is passed by reference and evaluated late.
     * @param mixed &$variable The variable type should correspond to the
     *     PDO::PARAM_* constant.
     * @param int $dataType Corresponds to one of the PDO::PARAM_* constants.
     *     Review the PDO documentation for more information
     * @param int $length Specify the length of the $variable. For further
     *     information review the PDO documentation
     * @throws object Brvr_Db_Query_Exception
     * @return object Brvr_Db_Query_AlterRowAbstract
     */
    public function bindColumnParam(
        $columnName,
        &$variable,
        $dataType = PDO::PARAM_STR,
        $length = null)
    {
        if (empty($columnName)) {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('Column name must not be empty');
        }
        $parameter = $this->_uniquePlaceholder($columnName);
        return $this->_bindParam($parameter, &$variable, $dataType, $length);
    }
    
    /**
     * Bind a value with a column name.
     *
     * This will not add the column name to the $_parts array and thus will not
     * include the column name in the SQL statement automatically.
     *
     * @param string $columnName The name of the column to which to bind the
     *     variable. It is passed by reference and evaluated late.
     * @param mixed $value The data type should correspond to the
     *     PDO::PARAM_* constant.
     * @param int $dataType Corresponds to one of the PDO::PARAM_* constants.
     *     Review the PDO documentation for more information
     * @throws object Brvr_Db_Query_Exception
     * @return object Brvr_Db_Query_AlterRowAbstract
     */
    public function bindColumnValue(
        $columnName,
        $value,
        $dataType = PDO::PARAM_STR)
    {
        if (empty($columnName)) {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('Column name must not be empty');
        }
        $parameter = $this->_uniquePlaceholder($columnName);
        return $this->_bindValue($parameter, $value, $dataType);
    }
    
    /**
     * Add column name to which to insert data.
     *
     * @param string $columnName column name as it appears in the database
     * @throws object Brvr_Db_Query_Exception
     * @return object Brvr_Db_Query_Insert
     */
    protected function _addBindableColumn($columnName)
    {
        if (empty($columnName) || !is_string($columnName)) {
            /**
             * @see Brvr_Db_Query_Exception
             */
            require_once 'Brvr/Db/Query/Exception.php';
            throw new Brvr_Db_Query_Exception('Column name must not be empty');
        }
        if (!array_key_exists($columnName, $this->_parts[self::COLUMNS])) {
            $placeholder = $this->_uniquePlaceholder($columnName);
            $this->_parts[self::COLUMNS][$columnName] = "`$columnName` = "
                                                      . $placeholder;
        }
        return $this;
    }
    
    /**
     * Return a placeholder string based on the column name of the format
     * :name where 'name' consists only of lower case letters.
     *
     * @param string $columnName Name of the column as it appears in the
     *     database
     * @return string
     */
    protected function _uniquePlaceholder($columnName)
    {
        if (!array_key_exists($columnName, $this->_placeholders)) {
            $placeholder = ':'
                         . preg_replace('/[^a-z]+/', '',
                                                     strtolower($columnName));
            
            while (empty($placeholder) ||
                in_array($placeholder, $this->_placeholders)
            ) {
                $placeholder .= chr(mt_rand(97, 122));
            }
            
            $this->_placeholders[$columnName] = $placeholder;
        }
        
        return $this->_placeholders[$columnName];
    }
    
} // Brvr_Db_Query_AlterRowAbstract