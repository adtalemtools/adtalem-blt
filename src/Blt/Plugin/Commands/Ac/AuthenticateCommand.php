<?php

namespace Adtalem\Blt\Plugin\Commands\Ac;

use Acquia\Blt\Robo\BltTasks;
use AcquiaCloudApi\CloudApi\Client;
use AcquiaCloudApi\CloudApi\Connector;
use Adtalem\Blt\Plugin\Helpers\Acsf\Ac\AcConfigTrait;

/**
 * Defines commands in the "adtalem:ac:authentication" namespace.
 */
class AuthenticateCommand extends BltTasks {

  use AcConfigTrait;

  /**
   * @var string
   */
  protected $appId;

  /**
   * @command adtalem:ac:authentication:set
   */
  public function setAcAuthentication($options = [
    'api-key' => '',
    'api-secret' => '',
  ]) {
    // TODO: refactor to prompt for app ID here
    $this->setAppId();
    $this->say("You may generate new API tokens at <comment>https://cloud.acquia.com/app/profile/tokens</comment>");
    if (!empty($options['api-key'])) {
      $key = $options['api-key'];
    }
    else {
      $key = $this->askRequired('Please enter your Acquia cloud API key:');
    }
    if (!empty($options['api-secret'])) {
      $secret = $options['api-secret'];
    }
    else {
      $secret = $this->askRequired('Please enter your Acquia cloud API secret:');
    }

    $config = [
      'key' => $key,
      'secret' => $secret,
    ];
    $this->validateApiConfigWithMessage($config, "You must specify both the api-key and api-secret arguments");

    $this->writeApiConfigToFile($config);

    $this->say("<info>Successfully saved configuration!</info>");
  }

  /**
   * @command adtalem:ac:authentication:get
   */
  public function getAcAuthentication() {
    $config = $this->getApiConfig();
    if (empty($config)) {
      $this->say("<error>You are not authenticated to Acquia Cloud!</error>");
    }
    $this->say(json_encode($config));
    return 0;
  }

  /**
   * @command adtalem:ac:authentication:check
   */
  public function checkAcAuthentication($options = [
    'api-key' => '',
    'api-secret' => '',
  ]) {
    $config = $this->getApiConfig($options);
    if (empty($config)) {
      $this->say("<error>You are not authenticated to Acquia Cloud!</error>");
      return 1;
    }
    // TODO: refactor setAppId so it doesn't prompt here.
    $this->setAppId();
    $connector = new Connector(array(
      'key' => $config['key'],
      'secret' => $config['secret'],
    ));
    $cloud_api = Client::factory($connector);

    // We must call some method on the client to test authentication.
    try {
      $cloud_api->applications();
    } catch (\Exception $e) {
      $this->say("<error>Failed to authenticate! Message: {$e->getMessage()}</error>");
      return 1;
    }

    $this->say("<info>Successfully authenticated!</info>");
    return 0;
  }

  /**
   * Sets the Acquia application ID from config and prompt.
   */
  protected function setAppId() {
    if ($app_id = $this->getConfigValue('cloud.appId')) {
      $this->appId = $app_id;
    }
    else {
      $this->say("<info>To generate an alias for the Acquia Cloud, BLT require's your Acquia Cloud application ID.</info>");
      $this->say("<info>See https://docs.acquia.com/acquia-cloud/manage/applications.</info>");
      $this->appId = $this->askRequired('Please enter your Acquia Cloud application ID');
      $this->writeAppConfig($this->appId);
    }
  }

}
