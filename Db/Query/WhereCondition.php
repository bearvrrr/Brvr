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
 * @see Brvr_Db_Query_WhereCondition_Exception
 */
require_once 'Brvr/Db/Query/WhereCondition/Exception.php';

/**
 * Where condition class
 *
 * For use with where and having clauses to allow programmatic construction
 * of where conditions
 *
 * @todo Make it possible to bind variables and fetch the bound variables/values
 *    from the class
 *
 * @category Brvr
 * @package Brvr_Db
 * @subpackage Brvr_Db_Query
 */
class Brvr_Db_Query_WhereCondition
{
    /**
     * Possible values for the 'glue' for multiple conditions
     */
    const SQL_AND = 'AND';
    const SQL_OR  = 'OR';
    const SQL_XOR = 'XOR';
    
    /**
     * The relationship between the components of the where condition
     *
     * Either all where conditions within the query fragment represented by this
     * class must be satisfied (AND) or at least one must be satisfied (OR)
     *
     * @var string
     */
    private $_glue;
    
    /**
     * Whether or not to apply NOT to the contents of the where condition
     *
     * A value of true indicates the contents should be negated
     *
     * @var bool
     */
    private $_not = false;
    
    /**
     * Array to store the component where conditions of the class
     *
     * @var array
     */
    private $_components = array();
    
    /**
     * Constructor
     *
     * This is the only time where it is possible to specify the 'glue'. If no
     * value is passed then the default value of 'AND' is used.
     *
     * @param string $glue must have the value 'AND' or 'OR'
     * @throws Brvr_Db_Query_WhereCondition_Exception
     */
    public function __construct($glue = 'AND')
    {
        if (($glue !== self::SQL_AND) &&
            ($glue !== self::SQL_OR) &&
            ($glue !== self::SQL_XOR)
        ) {
            /**
             * @see Brvr_Db_Query_WhereCondition_Exception
             */
            require_once 'Brvr/Db/Query/WhereCondition/Exception.php';
            throw new Brvr_Db_Query_WhereCondition_Exception('Only \'AND\', '
                . '\'OR\' or \'XOR\' are valid for the \'glue\' parameter');
        }
        $this->_glue = $glue;
    }
      
    /**
     * Add component where conditions
     *
     * @param string|object $condition Where condition
     * @throws Brvr_Db_Query_WhereCondition_Exception
     * @return Brvr_Db_Query_WhereCondition
     */
    public function addWhereCondition($condition)
    {
        if (!is_object($condition) && !is_string($condition)) {
            /**
             * @see Brvr_Db_Query_WhereCondition_Exception
             */
             throw new Brvr_Db_Query_WhereCondition_Exception('Where conditions'
                 . 'must be either a string or an object');
        }
        
        if ($condition instanceof Brvr_Db_Query_WhereCondition) {
            if ($condition === $this || $condition->_isChild($this)) {
                /**
                 * @see Brvr_Db_Query_WhereCondition_Exception
                 */
                 throw new Brvr_Db_Query_WhereCondition_Exception('Recursion '
                     . 'detected in added WhereConditon');
            }
        }
        
        $this->_components[] = $condition;
        return $this;
    }
    
    /**
     * Get the type of glue for the where condition
     *
     * @return string value for the 'glue' either 'OR' or 'AND'
     */
    public function getType()
    {
        return $this->_glue;
    }
    
    /**
     * Apply NOT term to the contents of the Where Condition
     *
     * @return Brvr_Db_Query_WhereCondition
     */
    public function negate()
    {
        $this->_not = true;
        return $this;
    }
    
    protected function _isChild($object)
    {
        $return = false;
        foreach ($this->_components as $child) {
            if ($child instanceof Brvr_Db_Query_WhereCondition) {
                if ($child === $object) {
                    $return = true;
                    break;
                } else {
                    $return = $child->_isChild($object);
                    if ($return === true) break;
                }
            }
        }
        return $return;
    }
    
    /**
     * Produce SQL string as a single where condition involving all where
     * condition sub-components
     *
     * @return string
     */
    public function __toString()
    {
        $not = ($this->_not === true) ? 'NOT ' : '';
        return $not . '(' . implode(" $this->_glue ", $this->_components) . ')';
    }
}