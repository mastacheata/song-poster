<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * The MIT License
 *
 * Copyright (c) 2007 Andy Smith
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 * 
 * 
 * @license MIT License
 * @author Andy Smith (Responsible for all the MyOauth... classes), Benedikt Bauer (PECL:OAuth compatible ABI in Oauth_api class) 
 */


class Oauth_api {
    
    const OAUTH_AUTH_TYPE_AUTHORIZATION = 'OAUTH_AUTH_TYPE_AUTHORIZATION';
    const OAUTH_AUTH_TYPE_NONE = 'OAUTH_AUTH_TYPE_NONE';
    const OAUTH_AUTH_TYPE_URI = 'OAUTH_AUTH_TYPE_URI';
    const OAUTH_AUTH_TYPE_FORM = 'OAUTH_AUTH_TYPE_FORM';
    
    const OAUTH_SIG_METHOD_HMACSHA1 = 'OAuthSignatureMethod_HMAC_SHA1';
    const OAUTH_SIG_METHOD_HMACSHA256 = 'OAUTH_SIG_METHOD_HMACSHA256';
    const OAUTH_SIG_METHOD_RSASHA1 = 'OAUTH_SIG_METHOD_RSASHA1';
    
    public $debug;
    public $sslChecks;
    public $debugInfo;
    
    
    private $_CI;
        
    private $consumer_key;
    private $consumer_secret;
    private $token;
    private $token_secret;
    
    private $signature_method;
    private $auth_type;
    private $reqengine;
    private $timestamp;
    private $nonce;
    private $version;
    
    private $cert;
    private $ca;
    
    private $lastResponse;
    private $lastResponseHeaders = false;
    private $lastResponseInfo;
    
    private $authmethod = array
    (
        'OAUTH_AUTH_TYPE_AUTHORIZATION' => 'POST',
        'OAUTH_AUTH_TYPE_NONE' => 'POST',
        'OAUTH_AUTH_TYPE_URI' => 'GET',
        'OAUTH_AUTH_TYPE_FORM' => 'POST'
    );
    
    public function __construct(array $parameters = null) 
    {    
        $this->_CI =& get_instance();
        $this->_CI->load->helper('array');
        $this->consumer_key = $parameters[0];
        $this->consumer_secret = $parameters[1];
        $this->signature_method = new MyOAuthSignatureMethod_HMAC_SHA1();
        $this->auth_type = self::OAUTH_AUTH_TYPE_AUTHORIZATION;
        
        if (count($parameters) > 2) {
            $this->signature_method = $parameters[2];
            if (count($parameters) > 3) {
                $this->auth_type = $parameters[4];
            }
        }
    }
    
    public function __destruct ()
    {
    
    }
    
    public function disableDebug ()
    {
        $this->debug = false;
    }
    
    public function disableRedirects ()
    {
    
    }
    
    public function disableSSLChecks ()
    {
        $this->sslChecks = false;
    }
    
    public function enableDebug ()
    {
        $this->debug = true;
    }
    
    public function enableRedirects ()
    {
    
    }
    
    public function enableSSLChecks ()
    {
        $this->sslChecks = true;
    }
    
    public function fetch ( $protected_resource_url , array $extra_parameters = NULL, $http_method = 'POST' , array $http_headers = NULL )
    {
        $consumer = new MyOAuthConsumer($this->consumer_key, $this->consumer_secret);
        $token = new MyOAuthToken($this->token, $this->token_secret);

        $parsed = parse_url($protected_resource_url);
        $params = array();
        parse_str(element('query', $parsed, ''), $params);
        
        $params = array_merge($params, $extra_parameters);
        
        $request = MyOAuthRequest::from_consumer_and_token($consumer, $token, $http_method, $protected_resource_url, $params);
        $request->sign_request($this->signature_method, $consumer, $token);
        
        $ch = curl_init($protected_resource_url);
        
        switch ($this->auth_type)
        {
            case self::OAUTH_AUTH_TYPE_AUTHORIZATION :
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $request->to_postdata());
                break;
                    
/*            case self::OAUTH_AUTH_TYPE_URI :
                curl_setopt($ch, CURLOPT_URL, $request->to_url());
                break;
        
            case self::OAUTH_AUTH_TYPE_FORM :
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $request->to_postdata());
                break;
*/        
            default:
                break;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__).'/ca-bundle.crt');

        if (($result = curl_exec($ch)) !== FALSE)
        {        
            $this->lastResponse = $result;
            $this->lastResponseInfo = curl_getinfo($ch);
            return true;
        } else {
            $oae = new MyOAuthException(curl_error($ch));
            $oae->lastResponse = curl_error($ch);
            $oae->debugInfo = curl_getinfo($ch);            
            return false;
        }
    }
    
    public function generateSignature ( $http_method , $url , mixed $extra_parameters )
    {
    
    }
    
    public function getAccessToken ( $access_token_url , $auth_session_handle = '' , $verifier_token = '' )
    {
        $consumer = new MyOAuthConsumer($this->consumer_key, $this->consumer_secret);
        $token = new MyOAuthToken($this->token, $this->token_secret);
        
        $parsed = parse_url($access_token_url);
        $params = array();
        parse_str(element('query', $parsed, ''), $params);
        
        $request = MyOAuthRequest::from_consumer_and_token($consumer, $token, $this->authmethod[$this->auth_type], $access_token_url, $params);
        $request->sign_request($this->signature_method, $consumer, $token);
        
        $ch = curl_init($access_token_url);
        
        switch ($this->auth_type)
        {
            case self::OAUTH_AUTH_TYPE_AUTHORIZATION :
                curl_setopt($ch, CURLOPT_HTTPHEADER, array($request->to_header()));
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, array('oauth_verifier' => $verifier_token));
                break;
                    
/*            case self::OAUTH_AUTH_TYPE_URI :
                curl_setopt($ch, CURLOPT_URL, $request->to_url());
                break;
        
            case self::OAUTH_AUTH_TYPE_FORM :
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $request->to_postdata());
                break;
*/        
            default:
                break;
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__).'/ca-bundle.crt');
    
        if (($result = curl_exec($ch)) !== FALSE)
        {
            return MyOAuthUtil::parse_parameters($result);
        } else {
            $oae = new MyOAuthException(curl_error($ch));
            $oae->lastResponse = curl_error($ch);
            $oae->debugInfo = curl_getinfo($ch);
                
            throw $oae;
        }
    }
    
    public function getCAPath ()
    {
        return $this->ca;
    }
    
    public function getLastResponse ()
    {
        return $this->lastResponse;
    }
    
    public function getLastResponseHeaders ()
    {
        return $this->lastResponseHeaders;
    }
    
    public function getLastResponseInfo ()
    {
        return $this->lastResponseInfo;
    }
    
    public function getRequestHeader ( $http_method , $url , $extra_parameters )
    {
    
    }
    
    public function getRequestToken ( $request_token_url , $callback_url = '' )
    {
        $consumer = new MyOAuthConsumer($this->consumer_key, $this->consumer_secret, $callback_url);
        
        $parsed = parse_url($request_token_url);
        $params = array();        
        parse_str(element('query', $parsed, ''), $params);
    
        $request = MyOAuthRequest::from_consumer_and_token($consumer, NULL, $this->authmethod[$this->auth_type], $request_token_url, $params);
        $request->sign_request($this->signature_method, $consumer, NULL);
        
        $ch = curl_init($request_token_url);

        switch ($this->auth_type)
        {
            case self::OAUTH_AUTH_TYPE_AUTHORIZATION :
                curl_setopt($ch, CURLOPT_HTTPHEADER, array($request->to_header()));
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, array('oauth_callback' => $callback_url));
                break;
/*                    
            case self::OAUTH_AUTH_TYPE_URI :
                curl_setopt($ch, CURLOPT_URL, $request->to_url());
                break;
    
            case self::OAUTH_AUTH_TYPE_FORM :
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $request->to_postdata());
                break;
*/    
            default:
                break;
        }
    
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__).'/ca-bundle.crt');

        if (($result = curl_exec($ch)) !== FALSE) 
        {
            return MyOAuthUtil::parse_parameters($result);
        } else {
            $oae = new MyOAuthException(curl_error($ch));
            $oae->lastResponse = curl_error($ch);
            $oae->debugInfo = curl_getinfo($ch);
            
            throw $oae;
        }    
    }
    
    public function setAuthType ( $auth_type )
    {
        $this->auth_type = $auth_type;
    }
    
    public function setCAPath ( $ca_path , $ca_info )
    {
        $this->ca = array('ca_path' => $ca_path, 'ca_info' => $ca_info);
    }
    
    public function setNonce ( $nonce )
    {
        $this->nonce = $nonce;
    }
    
    public function setRequestEngine ( $reqengine )
    {
        $this->reqengine = $reqengine;
    }
    
    public function setRSACertificate ( $cert )
    {
        $this->cert = $cert;
    }
    
    public function setSSLChecks ( $sslcheck )
    {
    
    }
    
    public function setTimestamp ( $timestamp )
    {
        $this->timestamp = $timestamp;
    }
    
    public function setToken ( $token , $token_secret )
    {
        $this->token = $token;
        $this->token_secret = $token_secret;
    }
    
    public function setVersion ( $version )
    {
        $this->version = $version;
    }
}

class MyOAuthException extends Exception {
    public $debugInfo;
    public $lastResponse;
}

class MyOAuthConsumer {
    public $key;
    public $secret;
    function __construct($key, $secret, $callback_url = NULL) {
        $this->key = $key;
        $this->secret = $secret;
        $this->callback_url = $callback_url;
    }
    function __toString() {
        return "OAuthConsumer[key=$this->key,secret=$this->secret]";
    }
}

class MyOAuthToken {
    // access tokens and request tokens
    public $key;
    public $secret;

    /**
     * key = the token
     * secret = the token secret
     */
    function __construct($key, $secret) {
        $this->key = $key;
        $this->secret = $secret;
    }

    /**
     * generates the basic string serialization of a token that a server
     * would respond to request_token and access_token calls with
     */
    function to_string() {
        return "oauth_token=" . MyOAuthUtil::urlencode_rfc3986 ( $this->key ) . "&oauth_token_secret=" . MyOAuthUtil::urlencode_rfc3986 ( $this->secret );
    }
    function __toString() {
        return $this->to_string ();
    }
}

/**
 * A class for implementing a Signature Method
 * See section 9 ("Signing Requests") in the spec
 */
abstract class MyOAuthSignatureMethod {
    /**
     * Needs to return the name of the Signature Method (ie HMAC-SHA1)
     *
     * @return string
     */
    abstract public function get_name();

    /**
     * Build up the signature
     * NOTE: The output of this function MUST NOT be urlencoded.
     * the encoding is handled in OAuthRequest when the final
     * request is serialized
     *
     * @param MyOAuthRequest $request
     * @param MyOAuthConsumer $consumer
     * @param MyOAuthToken $token
     * @return string
    */
    abstract public function build_signature($request, $consumer, $token);

    /**
     * Verifies that a given signature is correct
     *
     * @param MyOAuthRequest $request
     * @param MyOAuthConsumer $consumer
     * @param MyOAuthToken $token
     * @param string $signature
     * @return bool
    */
    public function check_signature($request, $consumer, $token, $signature) {
        $built = $this->build_signature ( $request, $consumer, $token );

        // Check for zero length, although unlikely here
        if (strlen ( $built ) == 0 || strlen ( $signature ) == 0) {
            return false;
        }

        if (strlen ( $built ) != strlen ( $signature )) {
            return false;
        }

        // Avoid a timing leak with a (hopefully) time insensitive compare
        $result = 0;
        for($i = 0; $i < strlen ( $signature ); $i ++) {
            $result |= ord ( $built {$i} ) ^ ord ( $signature {$i} );
        }

        return $result == 0;
    }
}

/**
 * The HMAC-SHA1 signature method uses the HMAC-SHA1 signature algorithm as
 * defined in [RFC2104]
 * where the Signature Base String is the text and the key is the concatenated
 * values (each first
 * encoded per Parameter Encoding) of the Consumer Secret and Token Secret,
 * separated by an '&'
 * character (ASCII code 38) even if empty.
 * - Chapter 9.2 ("HMAC-SHA1")
 */
class MyOAuthSignatureMethod_HMAC_SHA1 extends MyOAuthSignatureMethod {
    function get_name() {
        return "HMAC-SHA1";
    }
    public function build_signature($request, $consumer, $token) {
        $base_string = $request->get_signature_base_string ();
        $request->base_string = $base_string;

        $key_parts = array (
                $consumer->secret,
                ($token) ? $token->secret : ""
        );

        $key_parts = MyOAuthUtil::urlencode_rfc3986 ( $key_parts );
        $key = implode ( '&', $key_parts );

        return base64_encode ( hash_hmac ( 'sha1', $base_string, $key, true ) );
    }
}

/**
 * The PLAINTEXT method does not provide any security protection and SHOULD only
 * be used
 * over a secure channel such as HTTPS.
 * It does not use the Signature Base String.
 * - Chapter 9.4 ("PLAINTEXT")
 */
class OAuthSignatureMethod_PLAINTEXT extends MyOAuthSignatureMethod {
    public function get_name() {
        return "PLAINTEXT";
    }

    /**
     * oauth_signature is set to the concatenated encoded values of the Consumer
     * Secret and
     * Token Secret, separated by a '&' character (ASCII code 38), even if
     * either secret is
     * empty.
     * The result MUST be encoded again.
     * - Chapter 9.4.1 ("Generating Signatures")
     *
     * Please note that the second encoding MUST NOT happen in the
     * SignatureMethod, as
     * OAuthRequest handles this!
     */
    public function build_signature($request, $consumer, $token) {
        $key_parts = array (
                $consumer->secret,
                ($token) ? $token->secret : ""
        );

        $key_parts = MyOAuthUtil::urlencode_rfc3986 ( $key_parts );
        $key = implode ( '&', $key_parts );
        $request->base_string = $key;

        return $key;
    }
}

/**
 * The RSA-SHA1 signature method uses the RSASSA-PKCS1-v1_5 signature algorithm
 * as defined in
 * [RFC3447] section 8.2 (more simply known as PKCS#1), using SHA-1 as the hash
 * function for
 * EMSA-PKCS1-v1_5.
 * It is assumed that the Consumer has provided its RSA public key in a
 * verified way to the Service Provider, in a manner which is beyond the scope
 * of this
 * specification.
 * - Chapter 9.3 ("RSA-SHA1")
 */
abstract class MyOAuthSignatureMethod_RSA_SHA1 extends MyOAuthSignatureMethod {
    public function get_name() {
        return "RSA-SHA1";
    }

    // Up to the SP to implement this lookup of keys. Possible ideas are:
    // (1) do a lookup in a table of trusted certs keyed off of consumer
    // (2) fetch via http using a url provided by the requester
    // (3) some sort of specific discovery code based on request
    //
    // Either way should return a string representation of the certificate
    protected abstract function fetch_public_cert(&$request);

    // Up to the SP to implement this lookup of keys. Possible ideas are:
    // (1) do a lookup in a table of trusted certs keyed off of consumer
    //
    // Either way should return a string representation of the certificate
    protected abstract function fetch_private_cert(&$request);
    public function build_signature($request, $consumer, $token) {
        $base_string = $request->get_signature_base_string ();
        $request->base_string = $base_string;

        // Fetch the private key cert based on the request
        $cert = $this->fetch_private_cert ( $request );

        // Pull the private key ID from the certificate
        $privatekeyid = openssl_get_privatekey ( $cert );

        // Sign using the key
        $ok = openssl_sign ( $base_string, $signature, $privatekeyid );

        // Release the key resource
        openssl_free_key ( $privatekeyid );

        return base64_encode ( $signature );
    }
    public function check_signature($request, $consumer, $token, $signature) {
        $decoded_sig = base64_decode ( $signature );

        $base_string = $request->get_signature_base_string ();

        // Fetch the public key cert based on the request
        $cert = $this->fetch_public_cert ( $request );

        // Pull the public key ID from the certificate
        $publickeyid = openssl_get_publickey ( $cert );

        // Check the computed signature against the one passed in the query
        $ok = openssl_verify ( $base_string, $decoded_sig, $publickeyid );

        // Release the key resource
        openssl_free_key ( $publickeyid );

        return $ok == 1;
    }
}

class MyOAuthRequest {
    protected $parameters;
    protected $http_method;
    protected $http_url;
    // for debug purposes
    public $base_string;
    public static $version = '1.0';
    public static $POST_INPUT = 'php://input';
    function __construct($http_method, $http_url, $parameters = NULL) {
        $parameters = ($parameters) ? $parameters : array ();
        $parameters = array_merge ( MyOAuthUtil::parse_parameters ( parse_url ( $http_url, PHP_URL_QUERY ) ), $parameters );
        $this->parameters = $parameters;
        $this->http_method = $http_method;
        $this->http_url = $http_url;
    }

    /**
     * attempt to build up a request from what was passed to the server
     */
    public static function from_request($http_method = NULL, $http_url = NULL, $parameters = NULL) {
        $scheme = (! isset ( $_SERVER ['HTTPS'] ) || $_SERVER ['HTTPS'] != "on") ? 'http' : 'https';
        $http_url = ($http_url) ? $http_url : $scheme . '://' . $_SERVER ['SERVER_NAME'] . ':' . $_SERVER ['SERVER_PORT'] . $_SERVER ['REQUEST_URI'];
        $http_method = ($http_method) ? $http_method : $_SERVER ['REQUEST_METHOD'];

        // We weren't handed any parameters, so let's find the ones relevant to
        // this request.
        // If you run XML-RPC or similar you should use this to provide your own
        // parsed parameter-list
        if (! $parameters) {
            // Find request headers
            $request_headers = MyOAuthUtil::get_headers ();
                
            // Parse the query-string to find GET parameters
            $parameters = MyOAuthUtil::parse_parameters ( $_SERVER ['QUERY_STRING'] );
                
            // It's a POST request of the proper content-type, so parse POST
            // parameters and add those overriding any duplicates from GET
            if ($http_method == "POST" && isset ( $request_headers ['Content-Type'] ) && strstr ( $request_headers ['Content-Type'], 'application/x-www-form-urlencoded' )) {
                $post_data = MyOAuthUtil::parse_parameters ( file_get_contents ( self::$POST_INPUT ) );
                $parameters = array_merge ( $parameters, $post_data );
            }
                
            // We have a Authorization-header with OAuth data. Parse the header
            // and add those overriding any duplicates from GET or POST
            if (isset ( $request_headers ['Authorization'] ) && substr ( $request_headers ['Authorization'], 0, 6 ) == 'OAuth ') {
                $header_parameters = MyOAuthUtil::split_header ( $request_headers ['Authorization'] );
                $parameters = array_merge ( $parameters, $header_parameters );
            }
        }

        return new MyOAuthRequest ( $http_method, $http_url, $parameters );
    }

    /**
     * pretty much a helper function to set up the request
     */
    public static function from_consumer_and_token($consumer, $token, $http_method, $http_url, $parameters = NULL) {
        $parameters = ($parameters) ? $parameters : array ();
        $defaults = array (
                "oauth_version" => MyOAuthRequest::$version,
                "oauth_nonce" => MyOAuthRequest::generate_nonce (),
                "oauth_timestamp" => MyOAuthRequest::generate_timestamp (),
                "oauth_consumer_key" => $consumer->key
        );
        if ($token)
            $defaults ['oauth_token'] = $token->key;

        $parameters = array_merge ( $defaults, $parameters );

        return new MyOAuthRequest ( $http_method, $http_url, $parameters );
    }
    public function set_parameter($name, $value, $allow_duplicates = true) {
        if ($allow_duplicates && isset ( $this->parameters [$name] )) {
            // We have already added parameter(s) with this name, so add to the
            // list
            if (is_scalar ( $this->parameters [$name] )) {
                // This is the first duplicate, so transform scalar (string)
                // into an array so we can add the duplicates
                $this->parameters [$name] = array (
                        $this->parameters [$name]
                );
            }
                
            $this->parameters [$name] [] = $value;
        } else {
            $this->parameters [$name] = $value;
        }
    }
    public function get_parameter($name) {
        return isset ( $this->parameters [$name] ) ? $this->parameters [$name] : null;
    }
    public function get_parameters() {
        return $this->parameters;
    }
    public function unset_parameter($name) {
        unset ( $this->parameters [$name] );
    }

    /**
     * The request parameters, sorted and concatenated into a normalized string.
     *
     * @return string
     */
    public function get_signable_parameters() {
        // Grab all parameters
        $params = $this->parameters;

        // Remove oauth_signature if present
        // Ref: Spec: 9.1.1 ("The oauth_signature parameter MUST be excluded.")
        if (isset ( $params ['oauth_signature'] )) {
            unset ( $params ['oauth_signature'] );
        }

        return MyOAuthUtil::build_http_query ( $params );
    }

    /**
     * Returns the base string of this request
     *
     * The base string defined as the method, the url
     * and the parameters (normalized), each urlencoded
     * and the concated with &.
     */
    public function get_signature_base_string() {
        $parts = array (
                $this->get_normalized_http_method (),
                $this->get_normalized_http_url (),
                $this->get_signable_parameters ()
        );

        $parts = MyOAuthUtil::urlencode_rfc3986 ( $parts );

        return implode ( '&', $parts );
    }

    /**
     * just uppercases the http method
     */
    public function get_normalized_http_method() {
        return strtoupper ( $this->http_method );
    }

    /**
     * parses the url and rebuilds it to be
     * scheme://host/path
     */
    public function get_normalized_http_url() {
        $parts = parse_url ( $this->http_url );

        $scheme = (isset ( $parts ['scheme'] )) ? $parts ['scheme'] : 'http';
        $port = (isset ( $parts ['port'] )) ? $parts ['port'] : (($scheme == 'https') ? '443' : '80');
        $host = (isset ( $parts ['host'] )) ? strtolower ( $parts ['host'] ) : '';
        $path = (isset ( $parts ['path'] )) ? $parts ['path'] : '';

        if (($scheme == 'https' && $port != '443') || ($scheme == 'http' && $port != '80')) {
            $host = "$host:$port";
        }
        return "$scheme://$host$path";
    }

    /**
     * builds a url usable for a GET request
     */
    public function to_url() {
        $post_data = $this->to_postdata ();
        $out = $this->get_normalized_http_url ();
        if ($post_data) {
            $out .= '?' . $post_data;
        }
        return $out;
    }

    /**
     * builds the data one would send in a POST request
     */
    public function to_postdata() {
        return MyOAuthUtil::build_http_query ( $this->parameters );
    }

    /**
     * builds the Authorization: header
     */
    public function to_header($realm = null) {
        $first = true;
        if ($realm) {
            $out = 'Authorization: OAuth realm="' . MyOAuthUtil::urlencode_rfc3986 ( $realm ) . '"';
            $first = false;
        } else
            $out = 'Authorization: OAuth';

        $total = array ();
        foreach ( $this->parameters as $k => $v ) {
            if (substr ( $k, 0, 5 ) != "oauth")
                continue;
            if (is_array ( $v )) {
                throw new MyOAuthException ( 'Arrays not supported in headers' );
            }
            $out .= ($first) ? ' ' : ',';
            $out .= MyOAuthUtil::urlencode_rfc3986 ( $k ) . '="' . MyOAuthUtil::urlencode_rfc3986 ( $v ) . '"';
            $first = false;
        }
        return $out;
    }
    public function __toString() {
        return $this->to_url ();
    }
    public function sign_request($signature_method, $consumer, $token) {
        $this->set_parameter ( "oauth_signature_method", $signature_method->get_name (), false );
        $signature = $this->build_signature ( $signature_method, $consumer, $token );
        $this->set_parameter ( "oauth_signature", $signature, false );
    }
    public function build_signature($signature_method, $consumer, $token) {
        $signature = $signature_method->build_signature ( $this, $consumer, $token );
        return $signature;
    }

    /**
     * util function: current timestamp
     */
    private static function generate_timestamp() {
        return time ();
    }

    /**
     * util function: current nonce
     */
    private static function generate_nonce() {
        $mt = microtime ();
        $rand = mt_rand ();

        return md5 ( $mt . $rand ); // md5s look nicer than numbers
    }
}

class MyOAuthServer {
    protected $timestamp_threshold = 300; // in seconds, five minutes
    protected $version = '1.0'; // hi blaine
    protected $signature_methods = array ();
    protected $data_store;
    function __construct($data_store) {
        $this->data_store = $data_store;
    }
    public function add_signature_method($signature_method) {
        $this->signature_methods [$signature_method->get_name ()] = $signature_method;
    }

    // high level functions

    /**
     * process a request_token request
     * returns the request token on success
     */
    public function fetch_request_token(&$request) {
        $this->get_version ( $request );

        $consumer = $this->get_consumer ( $request );

        // no token required for the initial token request
        $token = NULL;

        $this->check_signature ( $request, $consumer, $token );

        // Rev A change
        $callback = $request->get_parameter ( 'oauth_callback' );
        $new_token = $this->data_store->new_request_token ( $consumer, $callback );

        return $new_token;
    }

    /**
     * process an access_token request
     * returns the access token on success
     */
    public function fetch_access_token(&$request) {
        $this->get_version ( $request );

        $consumer = $this->get_consumer ( $request );

        // requires authorized request token
        $token = $this->get_token ( $request, $consumer, "request" );

        $this->check_signature ( $request, $consumer, $token );

        // Rev A change
        $verifier = $request->get_parameter ( 'oauth_verifier' );
        $new_token = $this->data_store->new_access_token ( $token, $consumer, $verifier );

        return $new_token;
    }

    /**
     * verify an api call, checks all the parameters
     */
    public function verify_request(&$request) {
        $this->get_version ( $request );
        $consumer = $this->get_consumer ( $request );
        $token = $this->get_token ( $request, $consumer, "access" );
        $this->check_signature ( $request, $consumer, $token );
        return array (
                $consumer,
                $token
        );
    }

    // Internals from here
    /**
     * version 1
     */
    private function get_version(&$request) {
        $version = $request->get_parameter ( "oauth_version" );
        if (! $version) {
            // Service Providers MUST assume the protocol version to be 1.0 if
            // this parameter is not present.
            // Chapter 7.0 ("Accessing Protected Ressources")
            $version = '1.0';
        }
        if ($version !== $this->version) {
            throw new MyOAuthException ( "OAuth version '$version' not supported" );
        }
        return $version;
    }

    /**
     * figure out the signature with some defaults
     */
    private function get_signature_method($request) {
        $signature_method = $request instanceof MyOAuthRequest ? $request->get_parameter ( "oauth_signature_method" ) : NULL;

        if (! $signature_method) {
            // According to chapter 7 ("Accessing Protected Ressources") the
            // signature-method
            // parameter is required, and we can't just fallback to PLAINTEXT
            throw new MyOAuthException ( 'No signature method parameter. This parameter is required' );
        }

        if (! in_array ( $signature_method, array_keys ( $this->signature_methods ) )) {
            throw new MyOAuthException ( "Signature method '$signature_method' not supported " . "try one of the following: " . implode ( ", ", array_keys ( $this->signature_methods ) ) );
        }
        return $this->signature_methods [$signature_method];
    }

    /**
     * try to find the consumer for the provided request's consumer key
     */
    private function get_consumer($request) {
        $consumer_key = $request instanceof MyOAuthRequest ? $request->get_parameter ( "oauth_consumer_key" ) : NULL;

        if (! $consumer_key) {
            throw new MyOAuthException ( "Invalid consumer key" );
        }

        $consumer = $this->data_store->lookup_consumer ( $consumer_key );
        if (! $consumer) {
            throw new MyOAuthException ( "Invalid consumer" );
        }

        return $consumer;
    }

    /**
     * try to find the token for the provided request's token key
     */
    private function get_token($request, $consumer, $token_type = "access") {
        $token_field = $request instanceof MyOAuthRequest ? $request->get_parameter ( 'oauth_token' ) : NULL;

        $token = $this->data_store->lookup_token ( $consumer, $token_type, $token_field );
        if (! $token) {
            throw new MyOAuthException ( "Invalid $token_type token: $token_field" );
        }
        return $token;
    }

    /**
     * all-in-one function to check the signature on a request
     * should guess the signature method appropriately
     */
    private function check_signature($request, $consumer, $token) {
        // this should probably be in a different method
        $timestamp = $request instanceof MyOAuthRequest ? $request->get_parameter ( 'oauth_timestamp' ) : NULL;
        $nonce = $request instanceof MyOAuthRequest ? $request->get_parameter ( 'oauth_nonce' ) : NULL;

        $this->check_timestamp ( $timestamp );
        $this->check_nonce ( $consumer, $token, $nonce, $timestamp );

        $signature_method = $this->get_signature_method ( $request );

        $signature = $request->get_parameter ( 'oauth_signature' );
        $valid_sig = $signature_method->check_signature ( $request, $consumer, $token, $signature );

        if (! $valid_sig) {
            throw new MyOAuthException ( "Invalid signature" );
        }
    }

    /**
     * check that the timestamp is new enough
     */
    private function check_timestamp($timestamp) {
        if (! $timestamp)
            throw new MyOAuthException ( 'Missing timestamp parameter. The parameter is required' );
            
        // verify that timestamp is recentish
        $now = time ();
        if (abs ( $now - $timestamp ) > $this->timestamp_threshold) {
            throw new MyOAuthException ( "Expired timestamp, yours $timestamp, ours $now" );
        }
    }

    /**
     * check that the nonce is not repeated
     */
    private function check_nonce($consumer, $token, $nonce, $timestamp) {
        if (! $nonce)
            throw new MyOAuthException ( 'Missing nonce parameter. The parameter is required' );
            
        // verify that the nonce is uniqueish
        $found = $this->data_store->lookup_nonce ( $consumer, $token, $nonce, $timestamp );
        if ($found) {
            throw new MyOAuthException ( "Nonce already used: $nonce" );
        }
    }
}

class MyOAuthDataStore {
    function lookup_consumer($consumer_key) {
        // implement me
    }
    function lookup_token($consumer, $token_type, $token) {
        // implement me
    }
    function lookup_nonce($consumer, $token, $nonce, $timestamp) {
        // implement me
    }
    function new_request_token($consumer, $callback = null) {
        // return a new token attached to this consumer
    }
    function new_access_token($token, $consumer, $verifier = null) {
        // return a new access token attached to this consumer
        // for the user associated with this token if the request token
        // is authorized
        // should also invalidate the request token
    }
}

class MyOAuthUtil {
    public static function urlencode_rfc3986($input) {
        if (is_array ( $input )) {
            return array_map ( array (
                    'MyOAuthUtil',
                    'urlencode_rfc3986'
            ), $input );
        } else if (is_scalar ( $input )) {
            return str_replace ( '+', ' ', str_replace ( '%7E', '~', rawurlencode ( $input ) ) );
        } else {
            return '';
        }
    }

    // This decode function isn't taking into consideration the above
    // modifications to the encoding process. However, this method doesn't
    // seem to be used anywhere so leaving it as is.
    public static function urldecode_rfc3986($string) {
        return urldecode ( $string );
    }

    // Utility function for turning the Authorization: header into
    // parameters, has to do some unescaping
    // Can filter out any non-oauth parameters if needed (default behaviour)
    // May 28th, 2010 - method updated to tjerk.meesters for a speed
    // improvement.
    // see http://code.google.com/p/oauth/issues/detail?id=163
    public static function split_header($header, $only_allow_oauth_parameters = true) {
        $params = array ();
        if (preg_match_all ( '/(' . ($only_allow_oauth_parameters ? 'oauth_' : '') . '[a-z_-]*)=(:?"([^"]*)"|([^,]*))/', $header, $matches )) {
            foreach ( $matches [1] as $i => $h ) {
                $params [$h] = MyOAuthUtil::urldecode_rfc3986 ( empty ( $matches [3] [$i] ) ? $matches [4] [$i] : $matches [3] [$i] );
            }
            if (isset ( $params ['realm'] )) {
                unset ( $params ['realm'] );
            }
        }
        return $params;
    }

    // helper to try to sort out headers for people who aren't running apache
    public static function get_headers() {
        if (function_exists ( 'apache_request_headers' )) {
            // we need this to get the actual Authorization: header
            // because apache tends to tell us it doesn't exist
            $headers = apache_request_headers ();
                
            // sanitize the output of apache_request_headers because
            // we always want the keys to be Cased-Like-This and arh()
            // returns the headers in the same case as they are in the
            // request
            $out = array ();
            foreach ( $headers as $key => $value ) {
                $key = str_replace ( " ", "-", ucwords ( strtolower ( str_replace ( "-", " ", $key ) ) ) );
                $out [$key] = $value;
            }
        } else {
            // otherwise we don't have apache and are just going to have to hope
            // that $_SERVER actually contains what we need
            $out = array ();
            if (isset ( $_SERVER ['CONTENT_TYPE'] ))
                $out ['Content-Type'] = $_SERVER ['CONTENT_TYPE'];
            if (isset ( $_ENV ['CONTENT_TYPE'] ))
                $out ['Content-Type'] = $_ENV ['CONTENT_TYPE'];
                
            foreach ( $_SERVER as $key => $value ) {
                if (substr ( $key, 0, 5 ) == "HTTP_") {
                    // this is chaos, basically it is just there to capitalize
                    // the first
                    // letter of every word that is not an initial HTTP and
                    // strip HTTP
                    // code from przemek
                    $key = str_replace ( " ", "-", ucwords ( strtolower ( str_replace ( "_", " ", substr ( $key, 5 ) ) ) ) );
                    $out [$key] = $value;
                }
            }
        }
        return $out;
    }

    // This function takes a input like a=b&a=c&d=e and returns the parsed
    // parameters like this
    // array('a' => array('b','c'), 'd' => 'e')
    public static function parse_parameters($input) {
        if (! isset ( $input ) || ! $input)
            return array ();

        $pairs = explode ( '&', $input );

        $parsed_parameters = array ();
        foreach ( $pairs as $pair ) {
            $split = explode ( '=', $pair, 2 );
            $parameter = MyOAuthUtil::urldecode_rfc3986 ( $split [0] );
            $value = isset ( $split [1] ) ? MyOAuthUtil::urldecode_rfc3986 ( $split [1] ) : '';
                
            if (isset ( $parsed_parameters [$parameter] )) {
                // We have already recieved parameter(s) with this name, so add
                // to the list
                // of parameters with this name

                if (is_scalar ( $parsed_parameters [$parameter] )) {
                    // This is the first duplicate, so transform scalar (string)
                    // into an array
                    // so we can add the duplicates
                    $parsed_parameters [$parameter] = array (
                            $parsed_parameters [$parameter]
                    );
                }

                $parsed_parameters [$parameter] [] = $value;
            } else {
                $parsed_parameters [$parameter] = $value;
            }
        }
        return $parsed_parameters;
    }
    public static function build_http_query($params) {
        if (! $params)
            return '';
            
        // Urlencode both keys and values
        $keys = MyOAuthUtil::urlencode_rfc3986 ( array_keys ( $params ) );
        $values = MyOAuthUtil::urlencode_rfc3986 ( array_values ( $params ) );
        $params = array_combine ( $keys, $values );

        // Parameters are sorted by name, using lexicographical byte value
        // ordering.
        // Ref: Spec: 9.1.1 (1)
        uksort ( $params, 'strcmp' );

        $pairs = array ();
        foreach ( $params as $parameter => $value ) {
            if (is_array ( $value )) {
                // If two or more parameters share the same name, they are
                // sorted by their value
                // Ref: Spec: 9.1.1 (1)
                // June 12th, 2010 - changed to sort because of issue 164 by
                // hidetaka
                sort ( $value, SORT_STRING );
                foreach ( $value as $duplicate_value ) {
                    $pairs [] = $parameter . '=' . $duplicate_value;
                }
            } else {
                $pairs [] = $parameter . '=' . $value;
            }
        }
        // For each parameter, the name is separated from the corresponding
        // value by an '=' character (ASCII code 61)
        // Each name-value pair is separated by an '&' character (ASCII code 38)
        return implode ( '&', $pairs );
    }
}
