<?php

namespace Adtalem\Blt\Plugin\Commands\Ac;

use Acquia\Blt\Robo\BltTasks;
use Adtalem\Blt\Plugin\Helpers\Acsf\Ac\AcsfAcApiClient;
use Consolidation\AnnotatedCommand\CommandData;

/**
 * Defines commands in the "adtalem:ac:code" namespace that involve reading code.
 */
class CodeReadCommand extends BltTasks {

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
   * List code branches and tags in the environment.
   *
   * @command adtalem:ac:code:list
   * @executeInVm
   */
  public function codeList($options = []) {
    return $this->runCodeList();
  }

  /**
   * Find a branch or tag in the environment.
   *
   * @command adtalem:ac:code:find
   * @executeInVm
   */
  public function codeFind($options = [
    'gitref' => '',
  ]) {
    if (empty($options['gitref'])) {
      throw new \Exception('You must specify a gitref.');
    }
    return $this->runCodeFind($options['gitref']);
  }

  /**
   * Run the code list logic.
   *
   * @return int
   *   If 0 then all tasks completed successfully. If greater than zero, then
   *   some backups failed to generate.
   */
  protected function runCodeList() {
    $this->say("Listing code on the environment");

    try {
      $return_code = 0;

      /** @var \AcquiaCloudApi\CloudApi\Connector $connector */
      $connector = $this->client->getConnector();

      $app_id = $this->client->getAppId();

      $response = $connector->makeRequest('get',"/applications/{$app_id}/code");
      if ($response->getStatusCode() >= 400) {
        throw new \Exception('An incorrect HTTP status code was returned: ' . $response->getStatusCode());
      }

      $response_body = json_decode($response->getBody(), TRUE);
      if (empty($response_body) || !isset($response_body['_embedded']) || !isset($response_body['_embedded']['items'])) {
        $this->logger->error('Unexpected API response.');
        $return_code++;
        return $return_code;
      }

      // Extract the the list of tags and branches.
      $gitrefs = $response_body['_embedded']['items'];
      $this->say("Available gitrefs:");
      foreach ($gitrefs as $gitref) {
        $this->say("    {$gitref['name']}");
      }

      return $return_code;
    }
    catch (\Exception $e) {
      $this->logger->error("Failed to list gitrefs, reason: {$e->getMessage()}");
      return 1;
    }
  }

  /**
   * Run the code find logic.
   *
   * @param string $gitref
   *   The gitref to find.
   *
   * @return int
   *   If 0 then all tasks completed successfully. If greater than zero, then
   *   some backups failed to generate.
   */
  protected function runCodeFind($gitref) {
    $this->say("Finding {$gitref}");

    try {
      /** @var \AcquiaCloudApi\CloudApi\Connector $connector */
      $connector = $this->client->getConnector();

      $app_id = $this->client->getAppId();

      $response = $connector->makeRequest('get',"/applications/{$app_id}/code");
      if ($response->getStatusCode() >= 400) {
        throw new \Exception('An incorrect HTTP status code was returned: ' . $response->getStatusCode());
      }

      $response_body = json_decode($response->getBody(), TRUE);
      if (empty($response_body) || !isset($response_body['_embedded']) || !isset($response_body['_embedded']['items'])) {
        $this->logger->error('Unexpected API response.');
        $return_code++;
        return $return_code;
      }

      // Extract the the list of tags and branches.
      $available_gitrefs = $response_body['_embedded']['items'];
      foreach ($available_gitrefs as $available_gitref) {
        if ($available_gitref['name'] == $gitref) {
          $this->say("Gitref {$gitref} is found!");
          return 0;
        }
      }

      $this->say("Could not find the gitref {$gitref}");
      return 1;
    }
    catch (\Exception $e) {
      $this->logger->error("Failed to find gitref, reason: {$e->getMessage()}");
      return 1;
    }
  }

}

