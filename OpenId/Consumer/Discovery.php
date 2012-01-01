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
 * @see Zend_OpenId
 */
require_once 'Zend/OpenId.php';

/**
 * @see Brvr_OpenId_ConsumerComponent
 */
require_once 'Brvr/OpenId/ConsumerComponent.php';

/**
 * Class for performing discovery on a given OpenId identifier
 *
 * @category Brvr
 * @package Brvr_OpenId
 * @subpackage Brvr_OpenId_Consumer
 */
class Brvr_OpenId_Consumer_Discovery extends Brvr_OpenId_ConsumerComponent
{
    /**
     * Uri requested to be resolved
     *
     * @var string
     */
    protected $_uri;
    
    /**
     * DateTimeZone object representing UTC
     *
     * @var object DateTimeZone
     */
    protected $_timeZone;
    
    /**
     * Maximum time in seconds in the future before data stored in this class is
     * considered 'expired'
     *
     * @var integer
     */
    protected $_timeToLive = 3600;
    
    /**
     * Expiry time for service information
     *
     * Contains a string of the xs:DateTime format
     * ({@link http://www.w3.org/TR/xmlschema-2/#dateTime}) with no microseconds
     *
     * @var string
     */
    protected $_expire;
    
    /**
     * An array of services with associated proirites as they appear in the 
     * XRDS
     *
     * This will be populated with a single priority element if HTML is passed
     * to the constructor since priority cannot be specified in HTML
     *
     * @var array
     */
    protected $_rawServices = array();
     
    /**
     * An array of services
     *
     * When the array is populated these will have been sorted by priority
     *
     * @var array
     */
    protected $_services = array();
    
    /**
     * Used to implement Iterator interface
     *
     * This varible is used rather than making use of the internal pointer for
     * $this->_services since it avoids implicit changes in the pointer if
     * $this->_services is manipulated by another function or method
     *
     * @var integer
     */
    protected $_position = 0;
    
    /**
     * Magic Methods
     */
    
    /**
     * Constructor
     */
    public function __construct($storage, $config = null)
    {
        parent::__construct($storage, $config);
        
        // for iteration
        $this->_position = 0;
        
        // for expiry time functions
        $this->_timeZone = new DateTimeZone('UTC');
    }
    
    /**
     * Getter
     *
     * @throws Brvr_OpenId_Consumer_Discovery_Exception If the property
     *     requested does not exist
     * @return mixed
     */
    public function __get($property)
    {
        $current = $this->current();
        if (!array_key_exists($property, $current)) {
            return;
        }
        return $current[$property];
    }
        
    
    /**
     * Iterator interface methods
     */
    
    /**
     * Return the current element
     *
     * @link http://uk.php.net/manual/en/iterator.current.php
     * @return mixed
     */
    public function current()
    {
        if (isset($this->_services[$this->_position]) &&
            is_array($this->_services[$this->_position])) {
            $current = $this->_services[$this->_position];
        } else {
            $current = array();
        }
        $current['expire'] = $this->_expire;
        $current['uri']    = $this->_uri;
        return $current;
    }
    
    /**
     * Return the key of the current element
     *
     * @link http://uk.php.net/manual/en/iterator.key.php
     * @return integer|string
     */
    public function key()
    {
        return $this->_position;
    }
    
    /**
     * Move forward to the next element
     *
     * @link http://uk.php.net/manual/en/iterator.next.php
     */
    public function next()
    {
        $this->_position++;
    }
    
    /**
     * Rewind to the first element
     *
     * @link http://uk.php.net/manual/en/iterator.rewind.php
     */
    public function rewind() 
    {
        $this->_position = 0;
    }
    
    /**
     * Checks if the current position is valid
     *
     * @link http://uk.php.net/manual/en/iterator.valid.php
     * @return boolean
     */
    public function valid()
    {
        return isset($this->_services[$this->_position]);
    }
    
    /**
     * Perform discovery on an OpenId identifier
     *
     * @param string $identifier Url or Xri
     * @return boolean true on success, false on failure
     */
    public function resolve($identifier)
    {
        if (!Zend_OpenId::normalize($identifier)) {
            $this->setError('Normalisation of uri failed');
            return false;
        }
        
        if ($this->loadDiscoveryInfo($identifier)) {
            return true;
        }
        
        if ($identifier[0] == '=' ||
            $identifier[0] == '@' ||
            $identifier[0] == '+' ||
            $identifier[0] == '$' ||
            $identifier[0] == '!')
        {
            $response = $this->requestXri($identifier);
        } else {
            $response = $this->requestYadis($identifier);
        }
        
        if ($response === false) {
            return false;
        }
        
        if (!$this->deriveServices($response->getBody())) {
            $this->setError('No OpenId services at Identifier uri');
            return false;
        }
        $this->_uri = $identifier;
        
        if (!$this->storeDiscoveryInfo()) {
            $this->setError('Unable to store discovery info');
        }
        
        return true;
    }
    
    
    /**
     * Protected methods
     */
    
    /**
     * Attempt to populate class properties from local storage
     *
     * @param string $uri xri or url from where discovery information was
     *     originally retrieved
     * @return boolean true on success, false on failure
     */
    private function loadDiscoveryInfo($uri)
    {
        $info = $this->getStorage()->getDiscoveryInfo($uri);
        if ($info === false) {
            return false;
        }
        
        $this->_uri         = $info['uri'];
        $this->_expire      = $info['expire'];
        $this->_rawServices = $info['services'];
        $this->_services    = $this->getServiceArray();
        return true;
    }
    
    /**
     * Store discovered information
     *
     * @see Brvr_OpenId_Consumer_Storage::addDiscoveryInfo
     * @return boolean true on success false on failure
     */
    private function storeDiscoveryInfo()
    {
        $discoveryInfo = array(
            'uri'      => $this->_uri,
            'expire'   => $this->_expire,
            'services' => $this->_rawServices
            );
        return $this->getStorage()->addDiscoveryInfo($discoveryInfo);
    }
    
    /**
     * Retrieve an XRDS document for a given XRI
     *
     * The xri:// scheme has not been registered with IETF. Seems to be minimal
     * uptake of i-names. {@link http://www.ltg.ed.ac.uk/~ht/xri_notes.html}
     * provides a fairly comprehensive round up. Little seems to have changed
     * by 2011
     *
     * As a result this method is pretty basic. It was considered that making
     * an xri type object for syntax checking would be useful but it was quicker
     * to just remove the query and fragment segments and pass everything to an
     * xri resolver.
     *
     * @param string $identifier Xri to perform discovery on
     * @return Zend_Http_Response
     */
    private function requestXri($identifier)
    {
        // remove any query and fragments
        if (!preg_match('/^[^?#]+/', $identifier, $matches)) {
            $this->setError("Unable to extract authority and path from given "
                . "xri '$identifier'");
            return false;
        }
        $id = $matches[0];
        
        // construct url to pass to resolver
        $url = 'http://xri.net/' . $id . '?_xrd_r=application/xrds+xml';
        
        $response = $this->getHttpClient()->setUri($url)->request('GET');
        
        if ($response->getStatus() !== 200) {
            $this->setError('Proxy Xri resolver unavailable');
            return false;
        }
        
        // check that XRI canonical ID is verified
        if (!preg_match(
                '#<[Ss]tatus\\s(?:.(?!/>|code=))*.code=(?:"|\')(\d*)#',
                $response->getBody(),
                $matches)
            )
        {
            /* No <XRD:Status> Element
               This can still be checked manually but resolver should have done
               that so treat as an error instead
             */
            $this->setError('Xri authority not verified');
            return false;
        } elseif ($matches[1] !== '100') {
            // Xri not resolved properly, canonical Id failed verification
            $this->setError("Unable to resolve xri. Status code: " . 
                "{$matches[1]}");
            return false;
        }
        
        return $response;
    }
    
    /**
     * Retriev an XRDS document or HTML document associated with a given url
     *
     * @link http://yadis.org/wiki/Yadis_1.0_%28HTML%29
     * @param string $identifier Url to preform discovery on
     * @return Zend_Http_Response
     */
    private function requestYadis($identifier)
    {
        // Prepare client to accept content types
        $accept = array(
            'text/plain',
            'text/html',
            'text/xml',
            'application/xrds+xml'
            );
        $httpClient = $this->getHttpClient()->setHeaders('Accept', $accept);
        
        $uri = $identifier;
        
        // Should be derived from object property so can use config and defaults
        $maxYadisRedirects = 3; 
        
        // Here are the variables used to check status inside loop...
        $foundEndpoint = false;
        $redirectCount = 0;
        
        // ...and here is the loop
        do {
            try {
                $httpClient->setUri($uri);
                $response = $httpClient->request('GET');
            } catch (Exception $e) {
                /**
                 * @todo Give more info about request (uri and method)?
                 */
                $this->setError('Bad http request');
                return false;
            }
            
            if ($response->getStatus() !== 200) {
                $this->setError('Yadis discovery failed. Unable to retrieve ' .
                    '\'' . $uri . '\' (Http code: '. $response->getStatus() .
                    ')');
                return false;
            }
            
            $headers = $response->getHeaders();
            
            // In theory 'Content-type' header could be omitted so make sure
            // that the array key exists
            if (!array_key_exists('Content-type', $headers)) {
                $headers['Content-type'] = null;
            }
            
            // On with YADIS!
            if (stripos($headers['Content-type'], 'application/xrds+xml')
                                                                    !== false ||
                stripos($headers['Content-type'], 'application/xml')
                                                                    !== false ||
                stripos($headers['Content-type'], 'text/xml')       
                                        !== false) // allow for wrong mime-type
            {
                // XRDS document found.
                $foundEndpoint = true;
            } elseif (isset($headers['X-xrds-location'])) {
                // Set uri to retrieve xrds with next iteration
                $uri = $headers['X-xrds-location'];
            } elseif (stripos($headers['Content-type'], 'text/html') 
                                                                    !== false) {
                // Look in html for xrds location
                $html = $response->getBody();
                if (empty($html)) {
                    $this->setError('Yadis discovery failed. Empty body from'
                                                        . "request to '$uri'");
                    return false;
                }
                
                // only care about a small section of the body of the response
                $headOnly = substr($html, 0, stripos($html, '<body>'));
                if ($headOnly !== false) {
                    $html = $headOnly . '</html>';
                }
                
                $dom = new DOMDocument();
                if (!@$dom->loadHTML($html)) {
                    $this->setError('Unable to parse html document');
                    return false;
                }
                
                $metaElements = $dom->getElementsByTagName('meta');
                
                $metaCount = $metaElements->length;
                $newUri = '';
                
                for ($pos = 0; $pos < $metaCount; $pos++) {
                    $node = $metaElements->item($pos);
                    if (strtolower($node->attributes->getNamedItem('http-equiv')->textContent) 
                                                        == 'x-xrds-location')
                    {
                        $newUri = $node->attributes
                                       ->getNamedItem('content')
                                       ->textContent;
                        break;
                    }
                }
                
                if (empty($newUri)) {
                    /**
                     * Yadis discovery should always end with the return of an
                     * xrds. Since if Yadis fails then discovery information
                     * is derived from HTML anyway, then content-type text/html
                     * can be returned.
                     */
                    $foundEndpoint = true;
                } else {
                    $uri = $newUri;
                }
            } else {
                $this->setError('Document type returned ('
                    . $headers['Content-type'] . ') not allowed in Yadis '
                    . 'protocol.');
                return false;
            }
        } while ($foundEndpoint === false && 
                                        $redirectCount++ < $maxYadisRedirects);
        
        if ($foundEndpoint === false) {
            $this->setError('Yadis discovery failed. Could not resolve \''
                . $identifier . '\'');
            return false;
        }
        
        return $response;
    }
    
    /**
     * Methods to extract information from Xrds/Html
     */
    
    /**
     * Populate object with information extracted from either Xrds or Html
     *
     * This method is designed to be used in when testing
     *
     * @param string $domString Xrds or Html
     * @return boolean True if OpenId services found, false if no services found
     */
    public function load($identifier, $domString)
    {
        if (!$this->deriveServices($domString)) {
            $this->setError('No OpenId services at Identifier uri');
            return false;
        }
        $this->_uri = $identifier;
        
        if (!$this->storeDiscoveryInfo()) {
            $this->setError('Unable to store discovery info');
        }
        
        return true;
    }
    
    /**
     * Extract service information from XRDS or HTML and set to class properties
     *
     * @param string $domString XRDS or HTML
     * @return boolean true on success, false on failure
     */
    private function deriveServices($domString)
    {
        if (empty($domString)) {
            return false;
        }
        
        if (stripos($domString, '<html') !== false) {
            return $this->populateFromHtml($domString);
        } else {
            return $this->populateFromXrds($domString);
        }
    }
    
    
    /**
     * Populate class properties using html source string
     *
     * @param string $htmlString String
     * @return bool true on success, false on failure
     */
    private function populateFromHtml($htmlString)
    {
        $service = array();
        /**
         * The following code is virtually a vertabrim copy from
         * Zend_OpenId_Consumer version 20096 2010-01-06 02:05:09Z bkarwin
         */
         if (preg_match(
                '/<link[^>]*rel=(["\'])[ \t]*(?:[^ \t"\']+[ \t]+)*?openid2.provider[ \t]*[^"\']*\\1[^>]*href=(["\'])([^"\']+)\\2[^>]*\/?>/i',
                $htmlString,
                $r)) {
            $service['version'] = 2.0;
            $service['endpoint'] = $r[3];
        } else if (preg_match(
                '/<link[^>]*href=(["\'])([^"\']+)\\1[^>]*rel=(["\'])[ \t]*(?:[^ \t"\']+[ \t]+)*?openid2.provider[ \t]*[^"\']*\\3[^>]*\/?>/i',
                $htmlString,
                $r)) {
            $service['version'] = 2.0;
            $service['endpoint'] = $r[2];
        } else if (preg_match(
                '/<link[^>]*rel=(["\'])[ \t]*(?:[^ \t"\']+[ \t]+)*?openid.server[ \t]*[^"\']*\\1[^>]*href=(["\'])([^"\']+)\\2[^>]*\/?>/i',
                $htmlString,
                $r)) {
            $service['version'] = 1.1;
            $service['endpoint'] = $r[3];
        } else if (preg_match(
                '/<link[^>]*href=(["\'])([^"\']+)\\1[^>]*rel=(["\'])[ \t]*(?:[^ \t"\']+[ \t]+)*?openid.server[ \t]*[^"\']*\\3[^>]*\/?>/i',
                $htmlString,
                $r)) {
            $service['version'] = 1.1;
            $service['endpoint'] = $r[2];
        } else {
            return false;
        }
        if ($service['version'] >= 2.0) {
            if (preg_match(
                    '/<link[^>]*rel=(["\'])[ \t]*(?:[^ \t"\']+[ \t]+)*?openid2.local_id[ \t]*[^"\']*\\1[^>]*href=(["\'])([^"\']+)\\2[^>]*\/?>/i',
                    $htmlString,
                    $r)) {
                $service['localId'] = $r[3];
            } else if (preg_match(
                    '/<link[^>]*href=(["\'])([^"\']+)\\1[^>]*rel=(["\'])[ \t]*(?:[^ \t"\']+[ \t]+)*?openid2.local_id[ \t]*[^"\']*\\3[^>]*\/?>/i',
                    $htmlString,
                    $r)) {
                $service['localId'] = $r[2];
            }
        } else {
            if (preg_match(
                    '/<link[^>]*rel=(["\'])[ \t]*(?:[^ \t"\']+[ \t]+)*?openid.delegate[ \t]*[^"\']*\\1[^>]*href=(["\'])([^"\']+)\\2[^>]*\/?>/i',
                    $htmlString,
                    $r)) {
                $service['localId'] = $r[3];
            } else if (preg_match(
                    '/<link[^>]*href=(["\'])([^"\']+)\\1[^>]*rel=(["\'])[ \t]*(?:[^ \t"\']+[ \t]+)*?openid.delegate[ \t]*[^"\']*\\3[^>]*\/?>/i',
                    $htmlString,
                    $r)) {
                $service['localId'] = $r[2];
            }
        }
        
        $this->_rawServices[0][] = $service;
        $this->_services[] = $service;
        $this->_expire = $this->expireTime();
        
        return true;
    }
    
    /**
     * Populate class properties using XRDS source string
     *
     * @param string $xrdsString String
     * @return bool true on success, false on failure
     */
    private function populateFromXrds($xrdsString)
    {
        $serviceOrder = $this->servicesFromXrds($xrdsString);
        
        if (!empty($serviceOrder)) {
            $this->_rawServices = $serviceOrder;
            $this->_services = $this->getServiceArray();
            
            $this->_expire = $this->expireFromXrds($xrdsString);
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Wrapper method to generate a DOMDocument from an XRDS string
     *
     * The purpose of this wrapper function is to suppress errors and warnings
     * when the DOMDocument parses the string so that they are not output
     * directly.
     *
     * @param string $xrdsString XRDS document
     * @throws object Brvr_OpenId_ServiceDocument_Exception
     * @return object DOMDocument
     */
    private function domDocumentFromXrds($xrdsString)
    {
        $xrdsRoot = new DOMDocument();
        
        // Allow parsing of poorly formed XRS documents
        $xrdsRoot->recover = true;
        
        // Be very sure to suppress warnings and error reports...
        if (!(@$xrdsRoot->loadXML($xrdsString, LIBXML_NOERROR))) {
                /**
                 * @see Brvr_OpenId_ServiceDocument_Exception
                 */
                require_once 'Brvr/OpenId/ServiceDocument/Exception.php';
                throw new Brvr_OpenId_ServiceDocument_Exception('Unable to '
                    . 'create DOMDocument from Xrds supplied');
        }
        return $xrdsRoot;
    }
    
    /**
     * Generate an array of OpenId services from an XRDS document
     *
     * XRDS documents may list more than one OpenId service. Along with an
     * OP endpoint, they may also provide OpenId extension urls and a localID
     *
     * @param string $xrdsString XRDS document
     * @return array An array of services fonud within the document. Will be
     *     empty if no valid services found.
     */
    private function servicesFromXrds($xrdsString)
    {
        $serviceOrder = array();
        
        // create DOMDocument
        /**
         * Creating the DOMDocument will throw an exception if the XML of the
         * XRDS is malformed. This a more appropriate than returning an empty
         * array since there may be valid services that it is not possible to
         * extract
         */
        $xrdsRoot = $this->domDocumentFromXrds($xrdsString);
        
        // Service node list
        $serviceNodeList = $xrdsRoot->getElementsByTagName('Service');
        $serviceNodeCount = $serviceNodeList->length;
        
        // Iterate through each element
        for ($i = 0; $i < $serviceNodeCount; $i++) {
            $serviceNode = $serviceNodeList->item($i);
            // For each element extract all types
            $typeNodeList = $serviceNode->getElementsByTagName('Type');
            $typeNodeCount = $typeNodeList->length;
            
            $extensions = array();
            $currentService = array('version' => false);
            
            for ($j = 0; $j < $typeNodeCount; $j++) {
                
                $typeUrl = strtolower(trim(
                                        $typeNodeList->item($j)->textContent));
                // check type for an open id endpoint url, set version
                if ('http://specs.openid.net/auth/2.0/server' === $typeUrl) {
                    $currentService['version'] = 2.0;
                    $currentService['identifier'] = 'OP';
                } elseif ('http://specs.openid.net/auth/2.0/signon'
                                                                === $typeUrl) {
                    $currentService['version'] = 2.0;
                    $currentService['identifier'] = 'Claimed';
                    
                    $localNode = $serviceNode->getElementsByTagName('LocalID')
                                             ->item(0);
                    if ($localNode === null) {
                        $localNode = $xrdsRoot->getElementsByTagName('LocalID')
                                              ->item(0);
                    }
                    if ($localNode !== null) {
                        $currentService['localId'] = $localNode->textContent;
                    }
                } elseif ('http://openid.net/signon/1.0' === $typeUrl ||
                          'http://openid.net/signon/1.1' === $typeUrl) { 
                    $currentService['version'] = (float) substr($typeUrl, -3);
                    $currentService['identifier'] = 'Claimed';
                    
                    $localNode = $serviceNode->getElementsByTagName('Delegate')
                                             ->item(0);
                    if ($localNode !== null) {
                        $currentService['localId'] = $localNode->textContent;
                    }
                    
                    $localNode = $serviceNode->getElementsByTagName('LocalID')
                                             ->item(0);
                    if ($localNode !== null) {
                        $currentService['localId'] = $localNode->textContent;
                    }
                } else {
                    $extensions[] = $typeUrl;
                }
            } // end of loop through type elements
            
            // check version set or not, rest of loop in if block
            if ($currentService['version'] !== false) {
                $uriNode = $serviceNode->getElementsByTagName('URI')->item(0);
                if ($uriNode !== null) {
                    // All required elements present. Add to serviceOrder array
                    $currentService['endpoint'] = $uriNode->textContent;
                    if (!empty($extensions)) {
                        $currentService['extensions'] = $extensions;
                    }
                    
                    // Find the prioity
                    if (($serviceAttributes = $serviceNode->attributes)
                                                                    !== null) {
                        $priorityAttribute = 
                                $serviceAttributes->getNamedItem('priority');
                        if ($priorityAttribute !== null) {
                            $priority = $priorityAttribute->textContent;
                        } else { 
                            $priority = 'last';
                        } 
                    }
                    
                    $serviceOrder[$priority][] = $currentService;
                }
            }
        } // end of looping thourgh service elements
        
        return $serviceOrder;
    }
    
    /**
     * Extract expiry datetime from an xrds document string
     *
     * This method searches for the first valid timestamp within an <Expires>
     * tag. If none is found then a timestamp is returned for a default time in
     * the future
     *
     * @uses Brvr_OpenId_ServiceDocument::expireTime() This is used to create
     *     default timestamp
     * @param string $xrdsString Xrds string to search for timestamp
     * @return string xs:DateTime formatted timestamp
     */
    private function expireFromXrds($xrdsString)
    {
        preg_match(
            '#<\\s*[Ee]xpires\\s*>\\s*(\\d{4,4}-\\d\\d-\\d\\dT\\d\\d:\\d\\d:\\d\\d)(?:.\\d*)?([-+]\\d\\d:\\d\\d)?#',
            $xrdsString,
            $matches);
        
        if (empty($matches[1])) {
            // No expiry time specified
            return $this->expireTime();
        } else {
            $expireTimestamp = $matches[1];
        }
        
        if (!empty($matches[2])) {
            $zoneOffset = $matches[2];
            if ($zoneOffset[0] === '-' || $zoneOffset[0] === '+') {
                $expireTimestamp .= $zoneOffset;
            }
        }
        
        try {
            // If there is an offset, $this->_timeZone will be ignored
            $expireDateTime  = new DateTime($expireTimestamp, $this->_timeZone);
            $currentDateTime = new DateTime('now',            $this->_timeZone);
            
            // Ensure timezone UTC
            $expireDateTime->setTimeZone($this->_timeZone);
            
            $timeDiff = $currentDateTime->diff($expireDateTime);
            
            if ($timeDiff->invert === 0) {
                // get difference in seconds
                $secondsDiff = ($timeDiff->days * 24 * 60 * 60) +
                               ($timeDiff->h    * 60 * 60) +
                               ($timeDiff->i    * 60) +
                               ($timeDiff->s);
                
                if ($secondsDiff > $this->_timeToLive) {
                    // Time to expiry is greater than time to live
                    return $this->expireTime();
                }
            }
            // Difference in time is less than time to live or the given expiry
            // time is in the past.
            return $expireDateTime->format(DateTime::W3C);
        } catch (Exception $e) {
            // Fallback to default expiry time.
            return $this->expireTime();
        }
    }
    
    /**
     * Get a 0-indexed array of non-nested arrays containing data about
     * discovered services.
     *
     * This is generated from the $_rawServices array which is nested so that
     * services of the same priority are stored at the same index. This method
     * randomises the order of the elements which have a shared priority
     *
     * @return array
     */
    private function getServiceArray()
    {
        $priorityArray = $this->_rawServices;
        
        if (!empty($priorityArray['last'])) {
            $last = $priorityArray['last'];
            unset($priorityArray['last']);
        }
        
        ksort($priorityArray);
        
        $ordered = array();
        
        foreach($priorityArray as $serviceArray) {
            if (is_array($serviceArray)) {
                $ordered = $this->appendShuffledArray($ordered, $serviceArray);
            }
        }
        
        if (isset($last) && is_array($last)) {
            $ordered = $this->appendShuffledArray($ordered, $last);
        }
        
        return $ordered;
    }
    
    /**
     * Shuffle the contents of an array and append it to another array
     *
     * @param array $reciever The array to which to append shuffled array to
     * @param array $shuffleAppend The array to be shuffled
     * @throws Brvr_OpenId_ServiceDocument
     * @return array
     */
    private function appendShuffledArray($reciever, $shuffleAppend)
    {
        if (!is_array($reciever) || !is_array($shuffleAppend)) {
           /**
             * @see Brvr_OpenId_ServiceDocument_Exception
             */
            require_once 'Brvr/OpenId/ServiceDocument/Exception.php';
            throw new Brvr_OpenId_ServiceDocument_Exception('Non-array passed to _appendShuffledArray method');
        }
        
        $returnArray = $reciever;
        
        shuffle($shuffleAppend);
        foreach ($shuffleAppend as $value) {
            $returnArray[] = $value;
        }
        
        return $returnArray;
    }
    
    /**
     * Generate a timestamp for the time of $this->_timeToLive from now. 
     * Use as default expiry time.
     *
     * @return string Of xs:dateTime format
     */
    protected function expireTime()
    {
        $expire = new DateTime('now', $this->_timeZone);
        $expire->add(new DateInterval('PT' . $this->_timeToLive . 'S'));
        return $expire->format(DateTime::W3C);
    }
    
}
