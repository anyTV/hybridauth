<?php
/*!
 * HybridAuth
 * http://hybridauth.sourceforge.net | http://github.com/hybridauth/hybridauth
 * (c) 2009-2012, HybridAuth authors | http://hybridauth.sourceforge.net/licenses.html
 */

/**
 * Hybrid_Providers_DrupalOAuth2 provider adapter based on OAuth2 protocol
 */
class Hybrid_Providers_DrupalOAuth2 extends Hybrid_Provider_Model_OAuth2
{
  // default permissions
  //public $scope = 'user_profile';
  public $scope = 'partner,profile';

  // The 'state' variable helps to prevent CSRF attacks,
  // and can also be used to identify the authentication request.
  protected $state = NULL;

  /**
   * IDp wrappers initializer
   */
  function initialize()
  {
    parent::initialize();

    $base_url = $this->config['oauth2_server'];
    $this->api->api_base_url   = $base_url;
    $this->api->authorize_url  = $base_url . '';
    $this->api->token_url      = $base_url . '';

    if (isset($this->config['redirect_uri'])) {
      //$this->api->redirect_uri = $this->config['redirect_uri'];
        $this->api->redirect_uri = 'http://freedom.academy.tm/hybridauth/endpoint?hauth.done=DrupalOAuth2';
    }

    if (isset($this->config['scope']) && $this->config['scope'] !== '') {
      $this->scope = $this->config['scope'];
    }
    if (isset($this->config['state'])) {
      $this->state = $this->config['state'];
    }

    if ($this->config['skip_ssl']) {
      $this->api->curl_ssl_verifypeer = FALSE;
      $this->api->curl_ssl_verifyhost = FALSE;
    }
    if (isset($this->config['http_proxy']) and $this->config['http_proxy']) {
      $this->api->curl_proxy = $this->config['http_proxy'];
    }
  }

  /**
   * begin login step
   */
  function loginBegin()
  {
    if (!isset($this->state)) {
      $this->state = md5(uniqid(rand(), TRUE));
    }
    $this->saveState($this->state);
    $extra_params['state'] = $this->state;

    if (isset($this->scope)) {
      $extra_params['scope'] = $this->scope;
    }

    $extra_params['roles'] = $this->scope;
    //var_dump($this->api);die();

    Hybrid_Auth::redirect($this->api->authorizeUrl($extra_params));
  }

  /**
   * finish login step
   */
  function loginFinish()
  {
    // check that the CSRF state token is the same as the one provided
    $this->checkState();

    // call the parent function
    parent::loginFinish();
  }

  /**
   * Save the given $state in session.
   */
  protected function saveState($state) {
    $session_var_name = 'state_' . $this->api->client_id;
    $_SESSION['HybridAuth']['DrupalOAuth2'][$session_var_name] = $state;
  }

  /**
   * Read the state from session.
   */
  protected function readState() {
    $session_var_name = 'state_' . $this->api->client_id;
    $state = ( isset($_SESSION['HybridAuth']['DrupalOAuth2'][$session_var_name])
	      ? $_SESSION['HybridAuth']['DrupalOAuth2'][$session_var_name]
	      : NULL );
    unset($_SESSION['HybridAuth']['DrupalOAuth2'][$session_var_name]);
    return $state;
  }

  /**
   * Check the state in the request against the one saved in session.
   */
  protected function checkState() {
    $state = $this->readState();
    if (!$state || !isset($_REQUEST['state']) || $state != $_REQUEST['state'])
      {
	throw new Exception('Authentication failed! CSRF state token does not match the one provided.');
      }
  }

  /**
   * set propper headers before posting
   */
  function post($url) {
    $this->api->curl_header =
      array(
	    'Authorization: Bearer ' . $this->api->access_token,
	    'Content-Type: application/x-www-form-urlencoded',
	    'Accept: application/json',
	    );

    $response = $this->api->post($url);
    return $response;
  }

  /*
   * @author eshlee.romero@freedom.tm
   */
  function get($url)
  {
    $response = $this->api->get($url);

    return $response;
  }

  /**
   * Load the user profile from the api client.
   */
  function getUserProfile() {
    // Refresh tokens if needed.
    $this->refreshToken();

    // Get user profile.
    //$response = $this->post('/oauth2/user/profile');
    $response = $this->get('https://accounts.freedom.tm/api/v2/user');

    if (!isset($response->user_id)) {
      throw new Exception( "User profile request failed! {$this->providerId} returned an invalid response.", 6 );
    }
    // Covert the response to an array.
    $response = json_decode(json_encode($response), true);

    // Get profile field mappings.
    // Config settings will override default settings.
    $fields = array();
    if (isset($this->config['profile_fields'])
      and is_array($this->config['profile_fields'])) {
      $fields += $this->config['profile_fields'];
    }
    $fields += array(
      'identifier' => 'user_id',
      'displayName' => 'first_name',
      'photoURL' => 'picture',
      'email' => 'email',
      'emailVerified' => 'email',
      'language' => 'language',
    );

    // Match the fields of the returned data with
    // the fields of the hybridauth profile.
    $profile = (object) array();
    foreach ($fields as $field => $field_remote) {
      if (empty($field)) continue;
      if (empty($field_remote)) continue;

      $arr_keys = explode('.', $field_remote);
      $value = $response;
      foreach ($arr_keys as $key) {
        if (isset($value[$key])) {
          $value = $value[$key];
        }
        else {
          $value = NULL;
          break;
        }
        $profile->$field = $value;
      }
    }

    // Set and return the profile.
    $this->user->profile = $profile;
    return $this->user->profile;
  }
}
