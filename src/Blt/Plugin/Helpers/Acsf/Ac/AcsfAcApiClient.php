<?php

namespace Adtalem\Blt\Plugin\Helpers\Acsf\Ac;

use AcquiaCloudApi\CloudApi\Client;
use AcquiaCloudApi\CloudApi\Connector;
use Adtalem\Blt\Plugin\Helpers\Acsf\Ac\AcWrapperClient;
use Acquia\Blt\Robo\Config\ConfigInitializer;
use Consolidation\SiteAlias\SiteAliasManager;
use Drush\SiteAlias\SiteAliasFileLoader;
use Adtalem\Blt\Plugin\Helpers\Acsf\Ac\AcConfigTrait;

/**
 * An HTTP client for communicating with ACSF API.
 */
class AcsfAcApiClient {

  use AcConfigTrait;

  /**
   * @var string
   */
  protected $appId;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The Acquia Cloud API client.
   *
   * @var \Adtalem\Blt\Plugin\Helpers\Acsf\Ac\AcWrapperClient
   */
  protected $client;

  /**
   * @param string $appId
   *   The Acquia Cloud application ID.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger for the command being run.
   */
  public function __construct($appId, $logger) {
    $this->appId = $appId;
    $this->logger = $logger;

    $api_credentials = $this->getApiConfig();
    $connector = new Connector($api_credentials);
    $this->client = AcWrapperClient::factory($connector);
  }

  /**
   * Get the Acquia connector.
   *
   * @return \AcquiaCloudApi\CloudApi\ConnectorInterface
   */
  public function getConnector() {
    return $this->client->getConnector();
  }

  /**
   * Get the Acquia Cloud app ID.
   *
   * @return string
   *   The Acquia Cloud app ID.
   */
  public function getAppId() {
    return $this->appId;
  }

  /**
   * For the given environment (e.g. 01dev) get the environment object.
   *
   * @param string $env
   *   The environment name, e.g. 01dev.
   *
   * @return \AcquiaCloudApi\Response\EnvironmentResponse|null
   */
  public function getEnvironment($env) {
    $environment = NULL;
    $environments_found = $this->client->environments($this->appId);
    foreach ($environments_found as $environment_found) {
      if ($environment_found->name == $env) {
        $environment = $environment_found;
        break;
      }
    }
    return $environment;
  }

  /**
   * Get a list of database backups.
   *
   * @param string $env
   *   The environment name, e.g. "01dev".
   * @param $db_name
   *   The name of the database.
   *
   * @return \AcquiaCloudApi\Response\BackupsResponse
   */
  public function getDatabaseBackups($env, $db_name) {
    // Get the environment UUID for the env name.
    $environment = $this->getEnvironment($env);
    if (empty($environment)) {
      throw new \Exception("Could not find environment UUID for " . $env);
    }
    return $this->client->databaseBackups($environment->uuid, $db_name);
  }

  /**
   * Get a specific database backup.
   *
   * @param string $env
   *   The environment name, e.g. "01dev".
   * @param string $db_name
   *   The name of the database.
   * @param int $backup_id
   *   The database backup ID.
   *
   * @return \AcquiaCloudApi\Response\BackupResponse
   */
  public function getDatabaseBackup($env, $db_name, $backup_id) {
    // Get the environment UUID for the env name.
    $environment = $this->getEnvironment($env);
    if (empty($environment)) {
      throw new \Exception("Could not find environment UUID for " . $env);
    }
    return $this->client->databaseBackup($environment->uuid, $db_name, $backup_id);
  }

  /**
   * Get a backup URL for downloading.
   *
   * @param string $env
   *   The environment name, e.g. "01dev".
   * @param string $db_name
   *   The name of the database.
   * @param int $backup_id
   *   The database backup ID.
   *
   * @return string
   */
  public function getDatabaseBackupDownloadUrl($env, $db_name, $backup_id) {
    $environment = $this->getEnvironment($env);
    if (empty($environment)) {
      throw new \Exception("Could not find environment UUID for " . $env);
    }
    return Connector::BASE_URI . "/environments/{$environment->uuid}/databases/{$db_name}/backups/{$backup_id}/actions/download";
  }

  /**
   * Download the given URL to the given file path.
   *
   * @param string $url
   *   The backup download URL.
   * @param string $file_path
   *   The path to save the backup to.
   */
  public function getDatabaseBackupDownload($url, $file_path) {
    $credentials = $this->getApiConfig();
    return $this->client->download($credentials, $url, $file_path);
  }

  /**
   * Create a database backup.
   *
   * @param string $env
   *   The environment name, e.g. "01dev".
   * @param string $db_name
   *   The name of the database.
   *
   * @return \AcquiaCloudApi\Response\OperationResponse
   */
  public function createDatabaseBackup($env, $db_name) {
    // Get the environment UUID for the env name.
    $environment = $this->getEnvironment($env);
    if (empty($environment)) {
      throw new \Exception("Could not find environment UUID for " . $env);
    }
    return $this->client->createDatabaseBackup($environment->uuid, $db_name);
  }

  /**
   * Wait for the notification to complete then return.
   *
   * @param array $notification_ids
   *   An array of task IDs to wait for.
   * @param int $iteration_sleep
   *   The time to wait in between each attempt to check for task completion.
   * @param int $iteration_limit
   *   The number of times to attempt waiting for the task to complete.
   *
   * @return bool
   *   If true, all tasks succeeded. Otherwise, false.
   */
  public function waitForNotificationsAndReturn($notification_ids, $iteration_sleep = 15, $iteration_limit = 200) {
    try {
      $iteration = 0;
      $remaining_notification_ids_to_check = $notification_ids;
      $final_task_statuses = [];
      while ($iteration < $iteration_limit) {
        foreach ($remaining_notification_ids_to_check as $key => $notification_id) {
          $task_status = $this->getNotificationStatus($notification_id);

          // Print debug info.
          $debug_info = $task_status;
          unset($debug_info->context);
          unset($debug_info->_links);
          unset($debug_info->_embedded);
          $this->logger->notice(print_r($debug_info, TRUE));

          // If complete, print a message.
          if (!empty($task_status->completed_at)) {
            $this->logger->notice("Task {$notification_id} is completed at {$task_status->completed_at}.");
            $final_task_statuses[] = $task_status;
            unset($remaining_notification_ids_to_check[$key]);
          }
        }
        if (empty($remaining_notification_ids_to_check)) {
          break;
        }
        $iteration++;
        sleep($iteration_sleep);
      }

      if ($iteration >= $iteration_limit) {
        throw new \Exception("Waited too long for task to complete. Completed {$iteration} iterations with {$iteration_sleep} seconds sleep between iterations.");
      }

      // If the status is not 16 it failed, and we should fail the script.
      $is_task_errored = FALSE;
      foreach ($final_task_statuses as $task_status) {
        if ('completed' != $task_status->status) {
          $this->logger->warning("Task {$task_status->uuid} failed with status code {$task_status->status}.");
          $is_task_errored = TRUE;
        }
      }

      if ($is_task_errored) {
        return FALSE;
      }

      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Get the status of a task.
   *
   * @param int $notification_id
   *   The task ID to get the status for.
   *
   * @return array
   *   The API response.
   */
  protected function getNotificationStatus($notification_id) {
    $connector = $this->getConnector();
    return $connector->request('get', "/notifications/{$notification_id}");
  }

}
