<?php

/**
 * @file
 * Contains ProductivityGithubAuthAuthentication.
 */

class ProductivityGithubAuthAuthentication extends \RestfulAccessTokenAuthentication {

  /**
   * Overrides \RestfulBase::controllersInfo().
   */
  public static function controllersInfo() {
    return array(
      '' => array(
        // Get or create a new user.
        \RestfulInterface::POST => 'getUser',
      ),
    );
  }

  /**
   * Get a user from GitHub.
   *
   * @todo: Deal with the case that an existing user has revoked Github access
   * so we need to re-set their key.
   *
   * @return array
   *   Array from RESTful token authentication resource.
   *
   * @throws \RestfulUnauthorizedException
   */
  protected function getUser() {
    $request = $this->getRequest();
    if (empty($request['code'])) {
      throw new \RestfulUnauthorizedException('code property is missing.');
    }

    $options = array(
      'method' => 'POST',
      'data' => http_build_query(array(
        'client_id' => variable_get('github_public'),
        'client_secret' => variable_get('github_secret'),
        'code' => $request['code'],
      )),
    );


    // Pantheon has a nasty bug that causes http_build_query() to build the
    // query incorrectly.
    $options['data'] = 'client_id=' . variable_get('github_public') . '&client_secret=' . variable_get('github_secret') . '&code=' . $request['code'];

    // Allow mocking the login to Github.
    $url = variable_get('productivity_github_api_login_url', 'https://github.com/login/oauth/access_token');
    $result = drupal_http_request($url, $options);
    productivity_github_check_response_http_error($url, $result);

    $access_token = productivity_github_get_data_from_http_result($result);

    $options = array(
      'headers' => array(
        'Authorization' => 'token ' . $access_token,
      ),
    );

    $data = productivity_github_http_request('user', $options);
    $data = $data['data'];
    $github_username = $data['login'];

    // Get the email from Github and compare with ours.
    $query = new EntityFieldQuery();
    $result = $query
      ->entityCondition('entity_type', 'user')
      ->fieldCondition('field_github_username', 'value', $github_username)
      ->range(0, 1)
      ->execute();

    if (!$this->checkMembership($data, $options)) {
      throw new \RestfulUnauthorizedException('You are not authorized to access this organization.');
    }

    if (empty($result['user'])) {
      // Create a new user.
      $account = $this->createUser($data, $options, $access_token);
    }
    else {
      $id = key($result['user']);
      $account = user_load($id);

      // Make sure GitHub's access token is updated.
      $wrapper = entity_metadata_wrapper('user', $id);
      if ($wrapper->field_github_access_token->value() != $access_token) {
        $wrapper->field_github_access_token->set($access_token);
        $wrapper->save();
      }
    }

    if ($account->status == FALSE) {
      // User is blocked.
      throw new \RestfulUnauthorizedException('You are blocked from the site.');
    }

    $this->setAccount($account);
    return $this->getOrCreateToken();
  }

  /**
   * Create a new user.
   *
   * @param array $data
   *   Array with the user's data from GitHub
   * @param array $options
   *   Options array as passed to drupal_http_request().
   * @param string $access_token
   *   The GitHub access token.
   *
   * @return \stdClass
   *   The newly saved user object.
   */
  protected function createUser($data, $options, $access_token) {
    $fields = array(
      'name' => $data['login'],
      'mail' => $this->getEmailFromGithub($options),
      'pass' => user_password(8),
      'status' => TRUE,
      'roles' => array(
        DRUPAL_AUTHENTICATED_RID => 'authenticated user',
      ),
      // Pipe our data so implementing modules amy use it for example in
      // hook_user_preasve().
      '_github' => array(
        'access_token' => $access_token,
        'data' => $data
      ),
    );

    // The first parameter is left blank so a new user is created.
    $account = user_save('', $fields);
    return $account;
  }

  /**
   * Get user's primary email.
   *
   * @param array $options
   *   Options array as passed to drupal_http_request().
   *
   * @return string
   *   The user's email.
   */
  protected function getEmailFromGithub($options) {
    $data = productivity_github_http_request('user/emails', $options);
    foreach ($data['data'] as $row) {
      if (empty($row['primary'])) {
        // Not a primary email.
        continue;
      }

      return $row['email'];
    }
  }

  /**
   * Checks if the user is the member of the managed organization.
   *
   * @param array $data
   *   Array with the user's data from GitHub.
   * @param array $options
   *   Options array as passed to drupal_http_request().
   *
   * @return bool
   *   TRUE only if the user is member of the organization.
   */
  protected function checkMembership($data, $options) {
    try {
      productivity_github_http_request('orgs/' . variable_get('productivity_github_organization', 'Gizra') . '/members/' . $data['login'], array(), FALSE);
    }
    catch (RestfulException $e) {
      // https://developer.github.com/v3/orgs/members/#check-membership
      // @see productivity_github_check_response_http_error()
      // If we have a 2XX status code, it's a member, otherwise not.
      return FALSE;
    }
    return TRUE;
  }
}
