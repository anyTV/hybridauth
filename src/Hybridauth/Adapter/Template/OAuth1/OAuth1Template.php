<?php
/*!
* This file is part of the HybridAuth PHP Library(hybridauth.sourceforge.net | github.com/hybridauth/hybridauth)
*
* This branch contains work in progress toward the next HybridAuth 3 release and may be unstable.
*/

namespace Hybridauth\Adapter\Template\OAuth1;

use Hybridauth\Exception;
use Hybridauth\Http\Util;
use Hybridauth\Http\Client;

use Hybridauth\Adapter\AbstractAdapter;
use Hybridauth\Adapter\AdapterInterface;

use Hybridauth\Adapter\Template\OAuth1\Application;
use Hybridauth\Adapter\Template\OAuth1\Endpoints;
use Hybridauth\Adapter\Template\OAuth1\Tokens;

// for now Hybridauth is using OAuth lib as is
// => but should we use all of these, or should we reinvent the wheel
use Hybridauth\Adapter\Template\OAuth1\OAuthLib\OAuthConsumer;
use Hybridauth\Adapter\Template\OAuth1\OAuthLib\OAuthDataStore;
use Hybridauth\Adapter\Template\OAuth1\OAuthLib\OAuthExceptionPHP;
use Hybridauth\Adapter\Template\OAuth1\OAuthLib\OAuthRequest;
use Hybridauth\Adapter\Template\OAuth1\OAuthLib\OAuthServer;
use Hybridauth\Adapter\Template\OAuth1\OAuthLib\OAuthSignatureMethod;
use Hybridauth\Adapter\Template\OAuth1\OAuthLib\OAuthSignatureMethodHMACSHA1;
use Hybridauth\Adapter\Template\OAuth1\OAuthLib\OAuthToken;
use Hybridauth\Adapter\Template\OAuth1\OAuthLib\OAuthUtil;

class OAuth1Template extends AbstractAdapter implements AdapterInterface
{
	protected $application = null;
	protected $endpoints   = null;
	protected $tokens      = null;
	protected $httpClient  = null;

	// --------------------------------------------------------------------

	function initialize()
	{
		$this->application = new Application();
		$this->endpoints   = new Endpoints();
		$this->tokens      = new Tokens();
		$this->httpClient  = new Client();

		// http client
		if( isset( $this->hybridauthConfig["http_client"] ) && $this->hybridauthConfig["http_client"] ){
			$this->httpClient = new $this->hybridauthConfig["http_client"];
		}
		else{
			$curl_options = isset( $this->hybridauthConfig["curl_options"] ) ? $this->hybridauthConfig["curl_options"] : array();

			$this->httpClient = new Client( $curl_options );
		}

		// tokens
		$tokens = $this->getTokens();

		if( $tokens ){
			$this->tokens = $tokens;
			$this->storeTokens( $tokens );
		}
	}

	// --------------------------------------------------------------------
	
	/**
	* begin login step
	*/
	function loginBegin()
	{
		// app credentials
		if ( ! $this->getApplicationKey() || ! $this->getApplicationSecret() ){
			throw new Exception( "Application credentials are missing", Exception::MISSING_APPLICATION_CREDENTIALS );
		}

		$this->requestAuthToken();

		$parameters = $this->getEndpointAuthorizeUriAdditionalParameters();

		$url = $this->generateAuthorizeUri( $parameters );

		Util::redirect( $url );
	}
	
	// --------------------------------------------------------------------

	/**
	* finish login step
	*/
	function loginFinish($code = null, $parameters = array(), $method = 'POST')
	{
		$oauth_token    = ( array_key_exists( 'oauth_token'   , $_REQUEST ) ) ? $_REQUEST ['oauth_token']    : "";
		$oauth_verifier = ( array_key_exists( 'oauth_verifier', $_REQUEST ) ) ? $_REQUEST ['oauth_verifier'] : "";
		
		if( ! $oauth_token || ! $oauth_verifier ) {
			throw new Exception( "Authentication failed! Provider returned an invalid oauth verifier", Exception::AUTHENTIFICATION_FAILED, null, $this );
		}
		
		if($oauth_verifier) {
			$this->tokens->oauthVerifier = $oauth_verifier;
		}
		
		$this->requestAccessToken();

		// store tokens
		$this->storeTokens( $this->tokens );
	}

	// --------------------------------------------------------------------

	function generateAuthorizeUri( $parameters = array() )
	{
		$defaults = array(
			"oauth_token" => $this->getTokens()->requestToken 
		);

		$parameters = array_merge( $defaults, (array) $parameters );

		return $this->endpoints->authorizeUri . "?" . http_build_query( $parameters );
	}

	// --------------------------------------------------------------------

	function isAuthorized()
	{
		return $this->getTokens()->accessToken != null;
	}

	// --------------------------------------------------------------------

	function requestAuthToken( $parameters = array(), $method = 'GET' )
	{
		$defaults = array(
			'oauth_callback' => $this->endpoints->redirectUri 
		);

		$parameters = array_merge( $defaults,( array ) $parameters );

		$response = $this->signedRequest( $this->endpoints->requestTokenUri, $method, $parameters );

		$tokens = OAuthUtil::parse_parameters( $response );

		if( ! isset( $tokens['oauth_token'] ) ){
			throw new
				Exception(
					"Provider returned and error (" . $this->httpClient->getResponseError() . ", " . $this->httpClient->getResponseStatus() . ")",
					Exception::AUTHENTIFICATION_FAILED,
					null,
					$this
				);
		}

		$this->tokens->requestToken       = $tokens['oauth_token'];
		$this->tokens->requestSecretToken = $tokens['oauth_token_secret'];

		$this->storeTokens( $this->tokens );
	}
	
	// --------------------------------------------------------------------

	function requestAccessToken($parameters = array(), $method = 'GET')
	{
		$defaults = array();

		if( $this->tokens->oauthVerifier ) {
			$defaults['oauth_verifier'] = $this->tokens->oauthVerifier;
		}

		$parameters = array_merge( $defaults,( array ) $parameters );

		$request = $this->signedRequest( $this->endpoints->accessTokenUri, $method, $parameters );

		$tokens = OAuthUtil::parse_parameters( $request );

		if( ! isset( $tokens['oauth_token'] ) ){
			throw new
				Exception(
					"Provider returned and error (" . $this->httpClient->getResponseError() . ", " . $this->httpClient->getResponseStatus() . ")",
					Exception::AUTHENTIFICATION_FAILED,
					null,
					$this
				);
		}

		$this->tokens->accessToken       = $tokens ['oauth_token'];
		$this->tokens->accessSecretToken = $tokens ['oauth_token_secret'];

		$this->storeTokens( $this->tokens );
	}

	// --------------------------------------------------------------------

	function signedRequest( $uri, $method = 'GET', $parameters = array(), $authHeader = true /* <= fixme! */ )
	{
		// oauth1 lib
		$oauthLibTokens     = null;
		$oauthLibSha1Method = new OAuthSignatureMethodHMACSHA1();
		$oauthLibConsumer   = new OAuthConsumer( $this->getApplicationKey(), $this->getApplicationSecret() );

		if( $this->getTokens()->accessToken ){
			$oauthLibTokens = new OAuthConsumer( $this->getTokens()->accessToken, $this->getTokens()->accessSecretToken );
		}
		elseif( $this->getTokens()->requestToken ){
			$oauthLibTokens = new OAuthConsumer( $this->getTokens()->requestToken, $this->getTokens()->requestSecretToken );
		}

		if ( strrpos($uri, 'http://') !== 0 && strrpos($uri, 'https://') !== 0 ){
			$uri = $this->endpoints->baseUri . $uri;
		}

		$request = OAuthRequest::from_consumer_and_token( $oauthLibConsumer, $oauthLibTokens, $method, $uri, $parameters );
		$request->sign_request( $oauthLibSha1Method, $oauthLibConsumer, $oauthLibTokens );

		switch ($method) {
			case   'GET': $this->httpClient->get ( $request->to_url() ); break;
			case 'POST' : $this->httpClient->post( $request->get_normalized_http_url(), $method, $request->to_postdata(), $request->to_header() ) ; break;
		}

		return $this->httpClient->getResponseBody();
	}
}