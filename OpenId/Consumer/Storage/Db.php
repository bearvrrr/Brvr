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
 * @package Brvr_OpenId
 * @subpackage Brvr_OpenId_Consumer
 */

/**
 * @see Brvr_OpenId_Consumer_Storage_Interface
 */
require_once 'Brvr/OpenId/Consumer/Storage/Interface.php';
    
/**
 * @see Brvr_Db
 */
require_once 'Brvr/Db.php';

// Zend_Config is required by Brvr_Db

/**
 * Store and retrieve OpenId discovery and association data in a database
 *
 * @category Brvr
 * @package Brvr_OpenId
 * @subpackage Brvr_OpenId_Consumer
 */
class Brvr_OpenId_Consumer_Storage_Db 
    implements Brvr_OpenId_Consumer_Storage_Interface
{
    const MYSQL_DATETIME = 'Y-m-d H:i:s';
    
    /**
     * Database adapter
     *
     * @var Brvr_Db
     */
    private $_dbdapter;
    
    /**
     * Table name prefix
     *
     * @var string
     */
    private $_prefix = '';
    
    /**
     * Constructor
     *
     * This object has only a single configuration value specified by the key
     * 'prefix'. This is a string that is prepended to all table names in
     * queries to the database.
     *
     * All other configuration parameters are passed to the constructor of
     * Brvr_Db {@see Brvr_Db::__construct()}
     *
     * @param array|Zend_Config
     * @throws Brvr_OpenId_Consumer_Storage_Db_Exception
     */
    public function __construct($config)
    {
        if (!is_array($config)) {
            if ($config instanceof Zend_Config) {
                $config = $config->toArray();
            } else {
                /**
                 * @see Brvr_OpenId_Consumer_Storage_Db_Exception
                 */
                require_once 'Brvr/OpenId/Consumer/Storage/Db/Exception.php';
                throw new Brvr_OpenId_Consumer_Storage_Db_Exception(
                    'The $config parameter must be an array or instance of ' .
                    'Zend_Config');
            }
        }
        
        if (isset($config['prefix'])) {
            if (!is_string($config['prefix'])) {
                /**
                 * @see Brvr_OpenId_Consumer_Storage_Db_Exception
                 */
                require_once 'Brvr/OpenId/Consumer/Storage/Db/Exception.php';
                throw new Brvr_OpenId_Consumer_Storage_Db_Exception(
                    'The prefix configuration parameter must be a string');
            }
            $this->_prefix = $config['prefix'];
            unset($config['prefix']);
        }
        
        $this->_dbAdapter = new Brvr_Db($config);
    }
    
    /**
     * Stores information about association identified by url/handle
     *
     * @param array $associationData An array with the following keys:
     *    'url'     => string OpenID server URL
     *    'version' => float OpenID protoctol version
     *    'handle'  => string assiciation handle
     *    'macFunc' => string HMAC function (sha1 or sha256)
     *    'secret'  => string shared secret
     *    'expire'  => string expiration ISO 8601 date
     * @return bool true on success, false on failure
     */
    public function addAssociation($associationData)
    {
        if (!is_array($associationData)) {
            return false;
        }
        $associationData = $associationData;
        
        if (empty($associationData['url']) ||
            empty($associationData['version']) ||
            empty($associationData['handle']) ||
            empty($associationData['macFunc']) ||
            empty($associationData['secret']) ||
            empty($associationData['expire'])) {
            return false;
        }
        
        if (!is_string($associationData['url']) ||
            !is_numeric($associationData['version']) ||
            !is_string($associationData['handle'])) {
            return false;
        }
        
        if ($associationData['macFunc'] != 'sha1' &&
            $associationData['macFunc'] != 'sha256') {
            return false;
        }
        
        try {
            $expire = new DateTime($associationData['expire']);
            $expire->setTimezone(new DateTimeZone('UTC'));
            $expireString = $expire->format(self::MYSQL_DATETIME);
        } catch (Exception $e) {
            return false;
        }
        
        $tableName = $this->getPrefix() . 'association';
        
        try {
            $query = $this->getDb()->prepare('INSERT INTO ' . $tableName .
                ' (url, version, handle, mac_func, secret, expire) ' .
                'VALUES (:u, :v, :h, :m, :s, :e)');
            $query->bindValue(':u', $associationData['url']);
            $query->bindValue(':v', (string) $associationData['version']);
            $query->bindValue(':h', $associationData['handle']);
            $query->bindValue(':m', $associationData['macFunc']);
            $query->bindValue(':s', $associationData['secret'], PDO::PARAM_LOB);
            $query->bindValue(':e', $expireString);
            return $query->execute();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Gets information about association identified by $url
     * Returns an array if given association found and not expired and false
     * otherwise
     *
     * @param string $url OpenID server URL
     * @return mixed Either an array with keys as for the $associationData
     *     parameter for {@link Brvr_OpenId_Consumer_Storage::addAssociation()}
     *     or bool false on failure
     */
    public function getAssociation($url)
    {
        return $this->_getAssociationBy('url', $url);
    }
    /**
     * Gets information about association identified by $handle
     * Returns an array if given association found and not expired and false
     * otherwise
     *
     * @param string $handle association handle
     * @return mixed Either an array with keys as for the $associationData
     *     parameter for {@link Brvr_OpenId_Consumer_Storage::addAssociation()}
     *     or bool false on failure
     */
    public function getAssociationByHandle($handle)
    {
        return $this->_getAssociationBy('handle', $handle);
    }
    
    /**
     * Deletes association identified by $url
     *
     * @param string $url OpenID server URL
     * @return void
     */
    public function delAssociation($url)
    {
        if (!is_string($url)) {
            return false;
        }
        
        $tableName = $this->getPrefix() . 'association';
        try {
            $query = $this->getDb()->prepare('DELETE FROM ' . $tableName .
                ' WHERE url = :u');
            if (!$query->bindValue(':u', $url)->execute()) {
                return false;
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Stores information discovered from identity $id
     *
     * The paramater $discoveryDate is a nested array with top level keys:
     *     'uri' => string Given OpenId identity
     *     'expire' => string expiration ISO 8601 date
     *     'services' => array with keys corresponding to priority of each
     *         service and values an non-associative array of arrays
     *
     * The lowest level arrays each MUST have the keys:
     *     'version' => float OpenID protocol version
     *     'endpoint' => string uri to which to make authenitication requests to
     *
     * The lowest level arrays each MAY have the keys:
     *     'localID' => string local identifier for claimed Ids
     *     'identifier' => string 'claimed'|'OP'
     *
     * @param array $discoveryData
     * @return bool true on success, false on failure
     */
    public function addDiscoveryInfo($discoveryData)
    {
        if (!is_array($discoveryData)) {
            return false;
        }
        if (!isset($discoveryData['uri']) ||
            !isset($discoveryData['expire']) ||
            !isset($discoveryData['services']) ||
            !is_string($discoveryData['uri']) ||
            !is_string($discoveryData['expire']) ||
            !is_array($discoveryData['services'])) {
            return false;
        }
        
        try {
            $expire = new DateTime($discoveryData['expire']);
            $expireString = $expire->format(self::MYSQL_DATETIME);
        } catch (Exception $e) {
            return false;
        }
        
        if ($expireString === false) {
            return false;
        }
        
        $services = serialize($discoveryData['services']);
        $tableName = $this->getPrefix() . 'discovery_info';
        
        try {
            $query = $this->getDb()->prepare('INSERT INTO ' . $tableName .
                ' (uri, expire, services) VALUES (:u, :e, :s)');
            $query->bindValue(':u', $discoveryData['uri']);
            $query->bindValue(':e', $expireString);
            $query->bindValue(':s', $services);
            return $query->execute();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Gets information discovered from identity $id
     * An array with keys as for the $discoveryData parameter for
     * {@link Brvr_OpenId_Consumer_Storage::addADiscoveryInfo()} if such 
     * information exists and has not expired and false otherwise
     *
     * If expired discovery information is found then this is deleted
     *
     * @param string $id identity
     * @return mixed Either an array or bool false on failure
     *     
     */
    public function getDiscoveryInfo($id)
    {
        if (!is_string($id)) {
            return false;
        }
        
        $tableName = $this->getPrefix() . 'discovery_info';
        try {
            $query = $this->getDb()->prepare('SELECT * FROM ' . $tableName .
                ' WHERE uri = :u');
            if (!$query->bindValue(':u', $id)->execute()) {
                return false;
            }
            $results = $query->fetch();
        } catch (Exception $e) {
            return false;
        }
        
        if ($results === false) {
            return false;
        }
            
        $discoveryData = array(
            'uri'      => $results['uri'],
            'expire'   => $results['expire'],
            'services' => unserialize($results['services'])
            );
        
        if ($this->_dateHasPassed($discoveryData['expire'])) {
            $this->delDiscoveryInfo($id);
            return false;
        }
        
        return $discoveryData;
    }
    
    /**
     * Removes cached information discovered from identity $id
     *
     * @param string $id identity
     * @return bool
     */
    public function delDiscoveryInfo($id)
    {
        if (!is_string($id)) {
            return false;
        }
        
        $tableName = $this->getPrefix() . 'discovery_info';
        try {
            $query = $this->getDb()->prepare('DELETE FROM ' . $tableName .
                ' WHERE uri = :u');
            if (!$query->bindValue(':u', $id)->execute()) {
                return false;
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * The function checks the uniqueness of openid.response_nonce
     *
     * @param string $provider openid.openid_op_endpoint field from
     *     authentication response
     * @param string $nonce openid.response_nonce field from authentication
     *     response
     * @return bool
     */
    public function isUniqueNonce($provider, $nonce)
    {
        if (!is_string($provider) ||
            !is_string($nonce)) {
            return null;
        }
        
        $tableName = $this->getPrefix() . 'nonce';
        
        try {
            $query = $this->getDb()->prepare('SELECT COUNT(*) AS n FROM ' .
                $tableName . ' WHERE provider = :p AND nonce =:n');
            $query->bindValue(':p', $provider);
            $query->bindValue(':n', $nonce);
            if (!$query->execute()) {
                return null;
            }
            $result = $query->fetch();
            if (!empty($result['n'])) {
                return false;
            }
            
            $creationTime = new DateTime('now', new DateTimeZone('UTC'));
            $creationTimeString = $creationTime->format(self::MYSQL_DATETIME);
            $query = $this->getDb()->prepare('INSERT INTO ' . $tableName .
                ' (provider, nonce, creation_time) VALUES (:p, :n, :c)');
            $query->bindValue(':p', $provider);
            $query->bindValue(':n', $nonce);
            $query->bindValue(':c', $creationTimeString);
            
            if (!$query->execute()) {
                return null;
            }
            
            return true;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Removes data from the uniqueness database that is older then given date
     *
     * @param string $date Date of expired data
     * @return boolean
     */
    public function purgeNonces($date = null)
    {
        if ($date === null) {
            $date = 'now';
        }
        
        try {
            $dateTime = new DateTime($date, new DateTimeZone('UTC'));
            $dateTimeString = $dateTime->format(self::MYSQL_DATETIME);
            
            $query = $this->getDb()->prepare('DELETE FROM ' . $tableName .
                ' WHERE creation_time < :c');
            $query->bindValue(':c', $dateTimeString);
            
            return $query->execute();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Retrieve association data either by matching the handle or the url for 
     * the association
     *
     * $searchColumn should be either 'url' or 'handle', if not then false is
     * returned. $match is the url or the handle to search for
     *
     * @param string $searchColumn
     * @param string $match 
     * @return mixed array|boolean False is returned if no asociation data is
     *     matched
     */
    private function _getAssociationBy($searchColumn, $match)
    {
        $searchColumn = strtolower($searchColumn);
        
        if (!is_string($match) ||
            ($searchColumn != 'url' && $searchColumn != 'handle')) {
            return false;
        }
        
        $tableName = $this->getPrefix() . 'association';
        
        try {
            $query = $this->getDb()->prepare('SELECT url, version, handle, ' .
                'mac_func AS macFunc, secret, expire FROM ' . $tableName .
                ' WHERE '. $searchColumn . ' = :m');
            if (!$query->bindValue(':m', $match)->execute()) {
                return false;
            }
            $results = $query->fetch();
            
            if ($results === false) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
        
        $aData = $results;
        
        try {
            $expire = new DateTime($aData['expire'], new DateTimeZone('UTC'));
            $expireString = $expire->format('c');
            $aData['expire'] = $expireString;
        } catch (Exception $e) {
            return false;
        }
        
        if ($this->_dateHasPassed($expireString)) {
            $this->delAssociation($aData['url']);
            return false;
        }
        
        return $aData;
    }
    
    /**
     * Determine whether a datetime is in the past or not
     *
     * @param string $isotime ISO 8601 date
     * @return boolean|null True if $isotime is in the past, false if $isotime
     *     in the future, Null if an error occurs
     */
    private function _dateHasPassed($isoTime)
    {
        try {
            $utc = new DateTimeZone('UTC');
            
            $now    = new DateTime('now'   , $utc);
            $expire = new DateTime($isoTime, $utc);
            
            if ($now->diff($expire)->format('%R') == '-') {
                // Has expired
                return true;
            } else {
                // Yet to expire
                return false;
            }
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get the prefix used for SQL tablenames
     *
     * @return string
     */
    private function getPrefix()
    {
        return $this->_prefix;
    }
    
    /**
     * Get current instance of database adapter
     *
     * @return Brvr_Db
     */
    private function getDb()
    {
        return $this->_dbAdapter;
    }
        
}