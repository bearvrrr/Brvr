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
 */

/**
 * @see Zend_Loader
 */
require_once 'Zend/Loader.php';

/**
 * @see Zend_Config
 */
require_once 'Zend/Config.php';

/**
 * Class to connect to SQL databases and generating classes for prepared
 * statements.
 *
 * This class is largely a wrapper class for PDO. However, it also provides
 * methods to instantiate classes that allow programmitc construction of SQL
 * statements.
 *
 * This class will not allow anonymous connections to databases.
 *
 * @category Brvr
 * @package Brvr_Db
 *
 * @todo consider way to for static PDO storing variable so no need to connect
 *     more than once.
 * @todo Consider methods for opening and closing the connection to the database
 */
class Brvr_Db
{
    
    /**
     * Array to store configuration parameters.
     *
     * Set by the constructor
     *
     * @var array
     */
    protected $_config;
    
    /**
     * Store the PDO instance
     */
    protected $_pdo;
    
    protected static $_pdoStore = array();
    
    /**
     * Constructor
     *
     * $config is an associative array or a Zend_Config object containing
     * configuration options. The array can have 4 keys:
     * - [dsn]: Either a string or an array. The string form must be valid as
     *   for the $dsn parameter of the PDO::__construct method. 
     *   The array form must be associative and must contain the key 'prefix'
     *   with corresponding value the name of a PDO driver. The keys and
     *   values for the rest of the array must correspond to PDO DSN
     *   parameters and values. Optionally there may be a 'postfix' key whose
     *   value is added to the end of the generated DSN string
     * - [username]: name of the username for the database connection. Can be
     *   overridden by the dsn
     * - [password]: password for the databsae connection. Can be overridden by
     *   the dsn
     * - [params]: An associative array of driver options and attributes. The
     *   keys must correspond to PDO_* constants and the values must be suitable
     *   for those attributes. See the PDO documentation for more information
     *
     * The [dsn] array can have one of 3 forms. Either it contains both a
     * [prefix] key, a [uri] key or an [alias] key.
     * If it contains a [prefix] key then the other keys in the array are
     * concatencated to produce the dsn string.in the form key=value;. If there
     * is a [postfix] key then the value is added to the end of the string.
     * If it contains the [uri] key then the value must be a uri to either a
     * local or remote file
     * If it contains the [alias] key then the value should be 'name' that maps
     * to pdo.dsn.name in php.ini defining the DNS string
     *
     * @param array|Zend_Config $config. An array or Zend_Config instance
     *     having configuration data
     * @throws Brvr_Db_Exception
     */
    public function __construct($config)
    {
        /*
         * Ensure that adapter parameters are an array
         */
        if (!is_array($config)) {
            if ($config instanceof Zend_Config) {
                $config = $config->toArray();
            } else {
                /**
                 * @see Brvr_Db_Exception
                 */
                require_once 'Brvr/Db/Exception.php';
                throw new Brvr_Db_Exception('Adapter configuration parameters must be an array or a Zend_Config object');
            }
        }
        
        $c = array(); // Store parsed connection parameters in
        /*
         * Ensure the value for the DSN is a non-empty string
         */
        if (is_array($config['dsn']) && !empty($config['dsn'])) {
            // convert array to DSN string
            $dsnParts = $config['dsn'];
            if (empty($dsnParts['prefix'])) {
                /**
                 * @see Brvr_Db_Exception
                 */
                require_once 'Brvr/Db/Exception.php';
                throw new Brvr_Db_Exception('No DSN prefix specified');
            }
            
            $prefix = rtrim($dsnParts['prefix'], ": \t\n\r\0\x0B");
            unset($dsnParts['prefix']);
            
            if (array_key_exists('postfix', $dsnParts)) {
                $postfix = $dsnParts['postfix'];
                unset($dsnParts['postfix']);
            } else {
                $postfix = '';
            }
            
            $dsnParams = '';
            if (!empty($dsnParts)) {
                asort($dsnParts);
                foreach ($dsnParts as $key => $value) {
                    $dsnParams .= "$key=$value;";
                }
                $dsnParams = rtrim($dsnParams, ';');
            }
            $c['dsn'] = $prefix . ':' . $dsnParams . $postfix;
        
        } elseif (is_string($config['dsn']) && !empty($config['dsn'])) {
            $c['dsn'] = $config['dsn'];
        } else {
            /**
             * @see Brvr_Db_Exception
             */
            require_once 'Brvr/Db/Exception.php';
            throw new Brvr_Db_Exception('No DSN specified');
        }
            
        /*
         * Set a username string
         */
        if (array_key_exists('username', $config)) {
            $c['username'] = (string) $config['username'];
        } else {
            $c['username'] = '';
        }
        
        /*
         * Set a password string
         */
        if (array_key_exists('password', $config)) {
            $c['password'] = (string) $config['password'];
        } else {
            $c['password'] = '';
        }
        
        if (!empty($config['params']) && is_array($config['params'])) {
            $c['params'] = $config['params'];
        } else {
            $c['params'] = null;
        }
        
         
        $this->_config = $c; //store for debugging / unit testing
        
        try {
            //$this->_pdo = self::_getPdo($c);
            if (empty($c['params'])) {
                $pdo = new PDO($c['dsn'], $c['username'], $c['password']);
            } else {
                $pdo = new PDO($c['dsn'], 
                               $c['username'],
                               $c['password'],
                               $c['params']);
            }
        } catch (Exception $e) {
            /**
             * @see Brvr_Db_Exception
             */
            require_once 'Brvr/Db/Exception.php';
            $message = 'Unable to connect to database: ' . $e->getMessage();
            throw new Brvr_Db_Exception($message, $e->getCode());
        }
        
        $this->_pdo = $pdo;
    }
    
    /**
     * Get instance of a PDO based on config array
     *
     * This method uses an internal static array as a cache for PDO instances
     *
     * This method rhas the stench of a factory and smells of premature
     * optimisation but here it is anyway.
     */
    protected static function _getPdo($config)
    {
        if (!is_array($config)) {
            /**
             * @see Brvr_Db_Exception
             */
            require_once 'Brvr/Db/Exception.php';
            $error = 'Parameter \'$config\' must be an array';
            throw new Brvr_Db_Exception($error);
        }
        
        $configString = serialize($config);
        
        if (array_key_exists($configString, self::$_pdoStore) &&
            is_array(self::$pdoStore[$configString]) &&
            $pdoStore[self::$configString] instanceof PDO
        ) {
            return $pdoStore[$configString];
        }
        
        if (empty($config['params'])) {
            $pdo = new PDO($config['dsn'],
                           $config['username'],
                           $config['password']);
        } else {
            $pdo = new PDO($config['dsn'], 
                           $config['username'],
                           $config['password'],
                           $config['params']);
        }
        
        self::$_pdoStore[$configString] = $pdo;
        return $pdo;
    }
    
    
    
    /**
     * Transaction methods
     */
    
    /**
     * Turn off autocommit mode and begin transaction
     *
     * @return Brvr_Db
     */
    public function beginTransaction() {
        try {
            $this->_pdo->beginTransaction();
        } catch (PDOException $e) {
            /**
             * @see Brvr_Db_Exception
             */
            require_once 'Brvr/Db/Exception.php';
            throw new Brvr_Db_Exception($e->getMessage(), $e->getCode());
        }
        return $this;
    }
    
    /**
     * Commit a transaction and revert to autocommit mode
     *
     * @return Brvr_Db
     */
    public function commit() {
        try {
            $this->_pdo->commit();
        } catch (PDOException $e) {
            /**
             * @see Brvr_Db_Exception
             */
            require_once 'Brvr/Db/Exception.php';
            throw new Brvr_Db_Exception($e->getMessage(), $e->getCode());
        }
        return $this;
    }
    
    /**
     * Roll back a transaction and revert to autocommit mode
     *
     * @return Brvr_Db
     */
    public function rollBack() {
        try {
            $this->_pdo->rollBack();
        } catch (PDOException $e) {
            /**
             * @see Brvr_Db_Exception
             */
            require_once 'Brvr/Db/Exception.php';
            throw new Brvr_Db_Exception($e->getMessage(), $e->getCode());
        }
        return $this;
    }
    
    /**
     * SQL Statement methods
     */
    
    /**
     * Prepare an SQL statement
     * 
     * @param string $sqlStatement An SQL statement.
     * @return object Brvr_Db_Query_Statement
     */
    public function prepare($sqlStatement)
    {
        Zend_Loader::loadClass('Brvr_Db_Query_Statement');
        $preparedStatement = new Brvr_Db_Query_Statement($this->_pdo);
        return $preparedStatement->prepare($sqlStatement);
    }
    
    /**
     * Get a {@see Brvr_Db_Query_Select} to begin constructed a SELECT query
     */
    public function select()
    {
        Zend_Loader::loadClass('Brvr_Db_Query_Select');
        return new Brvr_Db_Query_Select($this->_pdo);
    }
    
    /**
     * Get a {@see Brvr_Db_Query_Insert} to begin constructed a INSERT query
     */
    public function insert()
    {
        Zend_Loader::loadClass('Brvr_Db_Query_Insert');
        return new Brvr_Db_Query_Insert($this->_pdo);
    }
    
    /**
     * Get a {@see Brvr_Db_Query_Insert} to begin constructed a UPDATE query
     */
    public function update()
    {
        Zend_Loader::loadClass('Brvr_Db_Query_Update');
        return new Brvr_Db_Query_Update($this->_pdo);
    }
    
    /**
     * Get a {@see Brvr_Db_Query_Delete} to begin constructed a DELETE query
     */
    public function delete()
    {
        Zend_Loader::loadClass('Brvr_Db_Query_Delete');
        return new Brvr_Db_Query_Delete($this->_pdo);
    }
    
    /**
     * Database status methods
     */
    
    /**
     * Get extended error information associated with the last query executed
     *
     * @return array Error information consisting of the following fields
     * - 0: SQLSTATE error code (a five characters alphanumeric identifier
     *   defined in the ANSI SQL standard).
     * - 1: Driver specific error code.
     * - 2: Driver specific error message.
     *
     * @see PDO->errorInfo() for more information
     */
    public function errorInfo()
    {
        return $this->_pdo->errorInfo();
    }
    
    /**
     * Get the SQLSTATE associated with the last operation on the database
     * @return string
     */
    public function errorCode()
    {
        return strval($this->_pdo->errorCode());
    }
    
    /**
     * Returns the ID of the last inserted row or sequence value
     * @return int
     */
    public function lastInsertId()
    {
        return $this->_pdo->lastInsertId();
    }
    
    
} // Brvr_Db