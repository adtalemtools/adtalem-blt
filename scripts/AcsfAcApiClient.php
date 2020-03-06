<?php

namespace Adtalem\Blt\Plugin\Helpers\Acsf\Ac;

use AcquiaCloudApi\CloudApi\Client;
use AcquiaCloudApi\CloudApi\Connector;
use Adtalem\Blt\Plugin\Helpers\Acsf\Ac\AcWrapperClient;
use Acquia\Blt\Robo\Config\ConfigInitializer;
use Consolidation\SiteAlias\SiteAliasManager;
use Drush\SiteAlias\SiteAliasFileLoader;

/**
 * An HTTP client for communicating with ACSF API.
 */
class AcsfAcApiClient {

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

    $api_credentials = $this->getApiCredentials();
    $connector = new Connector($api_credentials);
    $this->client = AcWrapperClient::factory($connector);
  }

  protected function getApiCredentials() {
    $cloud_conf_file_path = $_SERVER['HOME'] . '/.acquia/cloud_api.conf';

    if (!file_exists($cloud_conf_file_path)) {
      throw new \Exception('Acquia cloud config file not found. Run "blt recipes:aliases:init:acquia"');
    }

    $cloud_api_config = (array) json_decode(file_get_contents($cloud_conf_file_path));

    return [
      'key' => $cloud_api_config['key'],
      'secret' => $cloud_api_config['secret'],
    ];
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
      if ($environment_found->label == $env) {
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
    $credentials = $this->getApiCredentials();
    return $this->client->download($credentials, $url, $file_path);
  }

}
