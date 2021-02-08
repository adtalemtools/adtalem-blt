<?php

namespace Adtalem\Blt\Plugin\Commands\Ac;

use Acquia\Blt\Robo\BltTasks;
use Adtalem\Blt\Plugin\Helpers\Acsf\Ac\AcsfAcApiClient;
use Adtalem\Blt\Plugin\Helpers\Acsf\CommandOptionTargetEnvironmentTrait;
use Consolidation\AnnotatedCommand\CommandData;

/**
 * Defines commands in the "adtalem:ac:code" namespace that involve changing code.
 */
class CodeWriteCommand extends BltTasks {

  use CommandOptionTargetEnvironmentTrait;

  /**
   * The Acquia Cloud API Client.
   *
   * @var \Adtalem\Blt\Plugin\Helpers\Acsf\Ac\AcsfAcApiClient
   */
  protected $client;

  /**
   * Process options for Acquia Cloud API.
   *
   * @hook pre-validate
   */
  public function setupClient(CommandData $commandData) {
    $app_id = $this->getConfigValue('cloud.appId');
    $this->client = new AcsfAcApiClient($app_id, $this->logger);
  }

  /**
   * Checkout a gitref on the environment.
   *
   * @command adtalem:ac:code:checkout
   * @executeInVm
   */
  public function codeCheckout($options = [
    'gitref' => '',
  ]) {
    if (empty($options['gitref'])) {
      throw new \Exception('You must specify a gitref.');
    }
    return $this->runCodeCheckout($options['gitref']);
  }

  /**
   * Run the code checkout logic.
   *
   * @param string $gitref
   *   The gitref to checkout.
   *
   * @return int
   *   If 0 then all tasks completed successfully. If greater than zero, then
   *   some backups failed to generate.
   */
  protected function runCodeCheckout($gitref) {
    $this->say("Checkout out {$gitref} on {$this->targetEnv}");

    try {
      /** @var \AcquiaCloudApi\CloudApi\Connector $connector */
      $connector = $this->client->getConnector();

      $target_env = $this->client->getEnvironment($this->targetEnv);

      $options = [
        'form_params' => [
          'branch' => $gitref,
        ],
      ];
      $response = $connector->makeRequest('post',"/environments/{$target_env->uuid}/code/actions/switch", [], $options);

      if ($response->getStatusCode() >= 400) {
        $this->logger->error("API returned an invalid status code: " . $response->getStatusCode());
        return 1;
      }
      $response_body = json_decode($response->getBody(), TRUE);
      if (!isset($response_body['_links']['notification'])) {
        $this->logger->error("Unable to get response status. It's likely the request failed.");
        return 1;
      }
      // Extract the notification ID.
      $notification_url_parts = explode('/', $response_body['_links']['notification']['href']);
      $notification_id = end($notification_url_parts);
      $notification_ids[] = $notification_id;

      // Since this step may take 30 mins or more, we do 200 iterations at 15
      // second intervals, which should give about 50 mins of wait time.
      $response = $this->client->waitForNotificationsAndReturn($notification_ids, 15, 200);
      if (!$response) {
        throw new \Exception("Not all tasks completed successfully.");
      }

      return 0;
    }
    catch (\Exception $e) {
      $this->logger->error("Failed to checkout gitref, reason: {$e->getMessage()}");
      return 1;
    }
  }

}

