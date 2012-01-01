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
 * Interface for storing and retrieving information from OpenId discovery and
 * association
 *
 * @category Brvr
 * @package Brvr_OpenId
 * @subpackage Brvr_OpenId_Consumer
 */
interface Brvr_OpenId_Consumer_Storage_Interface
{
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
    public function addAssociation($associationData);

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
    public function getAssociation($url);

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
    public function getAssociationByHandle($handle);

    /**
     * Deletes association identified by $url
     *
     * @param string $url OpenID server URL
     * @return void
     */
    public function delAssociation($url);

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
     * @return void
     */
    public function addDiscoveryInfo($discoveryData);

    /**
     * Gets information discovered from identity $id
     * Returns true if such information exists and false otherwise
     *
     * @param string $id identity
     * @return mixed Either an array with keys as for the $discoveryData
     *     parameter for
     *     {@link Brvr_OpenId_Consumer_Storage::addADiscoveryInfo()} or bool
     *     false on failure
     */
    public function getDiscoveryInfo($id);

    /**
     * Removes cached information discovered from identity $id
     *
     * @param string $id identity
     * @return bool
     */
    public function delDiscoveryInfo($id);

    /**
     * The function checks the uniqueness of openid.response_nonce
     *
     * @param string $provider openid.openid_op_endpoint field from
     *     authentication response
     * @param string $nonce openid.response_nonce field from authentication
     *     response
     * @return bool
     */
    public function isUniqueNonce($provider, $nonce);

    /**
     * Removes data from the uniqueness database that is older then given date
     *
     * @param string $date Date of expired data
     */
    public function purgeNonces($date=null);
} // Brvr_OpenId_Consumer_Storage_Interface