<?php

namespace Adtalem\Blt\Plugin\Commands\Ac;

use Acquia\Blt\Robo\BltTasks;
use Adtalem\Blt\Plugin\Helpers\Acsf\Ac\AcsfAcApiClient;
use Adtalem\Blt\Plugin\Helpers\Acsf\CommandOptionTargetSitesTrait;
use Adtalem\Blt\Plugin\Helpers\Acsf\LocalBackupStorage;
use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Defines commands in the "adtalem:ac:site:data" namespace.
 *
 * TODO: implement media with downloads
 */
class SiteDataCommand extends BltTasks {

  use CommandOptionTargetSitesTrait;

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
   * Backup the database and files for the given sites.
   *
   * @command adtalem:ac:site:data:backup
   * @executeInVm
   */
  public function siteBackup($options = [
    'components' => 'database',
  ]) {
    $components = $this->getInputComponents($options);

    if (count($components) > 1 && $components[0] != 'database') {
      throw new \Exception('Acquia Cloud only supports database backups.');
    }

    $selected_sites = $this->getSelectedSites();

    $operation_names = ['Generate site backup of ' . join(', ', $components)];
    $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-only');
    if (!$continue) {
      return 0;
    }

    return $this->runSiteDataBackups($selected_sites, $components);
  }

  /**
   * Download a backup for the site.
   *
   * @command adtalem:ac:site:data:download
   * @executeInVm
   */
  public function siteDownload($options = [
    'components' => 'database',
    'backup-id' => '',
  ]) {
    $components = $this->getInputComponents($options);
    $backup_id = $options['backup-id'];

    if (count($components) > 1 && $components[0] != 'database') {
      throw new \Exception('Acquia Cloud only supports database backups.');
    }

    $selected_sites = $this->getSelectedSites();

    $operation_names = ['Downloading backups with ' . join(', ', $components)];
    $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-to-local');
    if (!$continue) {
      return 0;
    }

    return $this->runSiteDataDownload($selected_sites, $components, $backup_id);
  }

  /**
   * List available backups.
   *
   * @command adtalem:ac:site:data:list
   * @executeInVm
   */
  public function siteList($options = [
    'components' => '',
    'backup-id' => '',
    'latest' => FALSE,
  ]) {
    $components = $this->getInputComponents($options);
    $latest = $options['latest'];
    $backup_id = $options['backup-id'];

    if (count($components) > 1 && $components[0] != 'database') {
      throw new \Exception('Acquia Cloud only supports database backups.');
    }

    $selected_sites = $this->getSelectedSites();

    if (empty($components)) {
      $operation_names = ['Listing backups with any components'];
    }
    else {
      $operation_names = ['Listing backups with ' . join(', ', $components)];
    }

    $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-only');
    if (!$continue) {
      return 0;
    }

    return $this->runSiteDataList($selected_sites, $components, $backup_id, $latest);
  }

  /**
   * Restore the given sites from a backup.
   *
   * Note: only database is allowed on restore.
   *
   * @command adtalem:ac:site:data:restore
   * @executeInVm
   */
  public function siteRestore($options = [
    'components' => 'database',
    'backup-id' => '',
  ]) {
    $components = $this->getInputComponents($options);
    $backup_id = $options['backup-id'];

    if (count($components) > 1 && $components[0] != 'database') {
      throw new \Exception('Acquia Cloud only supports database backups.');
    }

    $selected_sites = $this->getSelectedSites();

    if (!empty($backup_id)) {
      if (empty($components)) {
        $operation_names = ["Restoring all components to site from backup ID {$backup_id}"];
      }
      else {
        $operation_names = ["Restoring " . join(', ', $components) . " to site from backup ID {$backup_id}"];
      }
    }
    elseif (empty($components)) {
      $operation_names = ["Restoring all components to site from latest backup"];
    }
    else {
      $operation_names = ["Restoring " . join(', ', $components) . " to site from latest backup"];
    }

    $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-only');
    if (!$continue) {
      return 0;
    }

    return $this->runSiteDataRestore($selected_sites, $components, $backup_id);

  }

  /**
   * Sync the data from the PROD env to the target env for given sites.
   *
   * @command adtalem:ac:site:data:sync
   * @executeInVm
   */
  public function siteSync($options = [
    'components' => 'database,public files,private files'
  ]) {
    $components = $this->getInputComponents($options);

    if ('prod' == $this->targetEnv) {
      throw new \Exception('You cannot sync data to the PROD environment.');
    }

    $selected_sites = $this->getSelectedSites();

    $operation_names = ['Sync from PROD env to ' . $this->targetEnv];
    $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-only');
    if (!$continue) {
      return 0;
    }

    return $this->runSiteDataSync($selected_sites, $components);
  }

  /**
   * Run the site backup logic.
   *
   * @param array $sync_maps
   *   An array of site mappings to run operations on.
   * @param array $components
   *   An array of components to run operations on.
   *
   * @return int
   *   If 0 then all tasks completed successfully. If greater than zero, then
   *   some backups failed to generate.
   */
  protected function runSiteDataBackups($sync_maps, $components) {
    $this->say("Generating site backups of " . join(', ', $components));

    try {
      $return_code = 0;

      /** @var \AcquiaCloudApi\CloudApi\Connector $connector */
      $connector = $this->client->getConnector();

      $notification_ids = [];
      foreach ($sync_maps as $sync_map) {
        $environment_uuid = $this->client->getEnvironment($sync_map['env'])->uuid;
        $db_name = $sync_map['site_dir'];
        $response = $connector->request('post',"/environments/{$environment_uuid}/databases/{$db_name}/backups");

        if (!property_exists($response, '_links') || !property_exists($response->_links, 'notification')) {
          throw new \Exception("Unable to get response status. It's likely the request failed.");
        }

        // Extract the notification ID.
        $notification_url_parts = explode('/', $response->_links->notification->href);
        $notification_id = end($notification_url_parts);

        $notification_ids[] = $notification_id;
      }

      // Since this step may take 30 mins or more, we do 200 iterations at 15
      // second intervals, which should give about 50 mins of wait time.
      $response = $this->client->waitForNotificationsAndReturn($notification_ids, 15, 200);
      if (!$response) {
        throw new \Exception("Not all tasks completed successfully.");
      }

      return $return_code;
    }
    catch (\Exception $e) {
      $this->logger->error("Failed to backup sites, reason: {$e->getMessage()}");
      return 1;
    }
  }

  /**
   * Run the site backup download logic.
   *
   * @param array $sync_maps
   *   An array of site mappings to run operations on.
   * @param array $components
   *   An array of components to run operations on.
   * @param int|null $backup_id
   *   The backup ID to download. Supercedes $components and $latest flags.
   *
   * @return int
   *   If 0 then all tasks completed successfully. If greater than zero, then
   *   some backups failed to generate.
   */
  protected function runSiteDataDownload($sync_maps, $components = [], $backup_id = NULL) {
    // Ensure we are only using backup_id with a specific site.
    if (count($sync_maps) > 1 && !empty($backup_id)) {
      throw new \Exception('When downloading a specific backup_id you must also specify a single site ID.');
    }

    if (!empty($backup_id)) {
      $this->say("Downloading site backup ID {$backup_id}");
    }
    elseif (empty($components)) {
      $this->say("Downloading latest site backups of any component list");
    }
    else {
      $this->say("Downloading latest site backups of " . join(', ', $components));
    }

    $local_storage = new LocalBackupStorage($this->getConfig(), $this->logger, $this->targetEnv);
    /** @var \AcquiaCloudApi\CloudApi\Connector $connector */
    $connector = $this->client->getConnector();

    try {
      $return_code = 0;

      // An array of backups, keyed on the site ID.
      $download_backups = [];

      // Get backups to download.
      if ($backup_id) {
        $environment_uuid = $this->client->getEnvironment($sync_maps[0]['env'])->uuid;
        $db_name = $sync_maps[0]['site_dir'];
        $response = $connector->makeRequest('get',"/environments/{$environment_uuid}/databases/{$db_name}/backups/{$backup_id}");
        if ($response->getStatusCode() >= 400) {
          throw new \Exception('Invalid API response code: ' . $response->getStatusCode());
        }
        $response_body = json_decode($response->getBody(), TRUE);

        if (empty($response_body['_links']['download'])) {
          throw new \Exception('Unexpected API response.');
        }

        $download_backups[$sync_maps[0]['site_id']] = $local_storage->formatRawAcBackupMetadata($sync_maps[0]['site_id'], $response_body);
      }
      else {
        foreach ($sync_maps as $sync_map) {
          $environment_uuid = $this->client->getEnvironment($sync_map['env'])->uuid;
          $db_name = $sync_map['site_dir'];
          $response = $connector->makeRequest('get', "/environments/{$environment_uuid}/databases/{$db_name}/backups");
          if ($response->getStatusCode() >= 400) {
            throw new \Exception("API returned an invalid status code: " . $response->getStatusCode());
          }

          $response_body = json_decode($response->getBody(), TRUE);

          if (empty($response_body) || !isset($response_body['total'])) {
            $this->logger->error('Unexpected API response.');
            // Let the return code equal the number of failed sites.
            $return_code++;
            continue;
          }

          // Filter to latest.
          $latest_backup = NULL;
          foreach ($response_body['_embedded']['items'] as $backup) {
            $latest_backup_completed_at = strtotime($latest_backup['completed_at'] );
            $backup_completed_at = strtotime($backup['completed_at']);
            if (empty($latest_backup) || $latest_backup_completed_at < $backup_completed_at) {
              $latest_backup = $backup;
            }
          }
          $download_backups[$sync_map['site_id']] = $local_storage->formatRawAcBackupMetadata($sync_map['site_id'], $latest_backup);
        }
      }

      if (empty($download_backups)) {
        $this->logger->error("No backups to download");
        return 1;
      }

      // Download each backup.
      foreach ($download_backups as $site_id => $backup) {
        if ($local_storage->hasAvailableBackup($backup)) {
          $this->logger->notice("Loading file from path instead of downloading backup ID {$backup['id']}");
          continue;
        }

        if (!$local_storage->downloadAndSaveAcBackup($this->client, $backup['response']['database']['name'], $backup)) {
          $this->logger->error("Failed to download backup ID {$backup['id']}");
          // Exit -- we assume there will be errors with the next ones too.
          return 1;
        }
      }

      return $return_code;
    }
    catch (\Exception $e) {
      $this->logger->error("Failed to backup sites, reason: {$e->getMessage()}");
      return 1;
    }
  }

  /**
   * Run the site backup list logic.
   *
   * @param array $sync_maps
   *   An array of site mappings to run operations on.
   * @param array $components
   *   An array of components to run operations on.
   * @param int|null $backup_id
   *   The backup ID to list. Supercedes $components and $latest flags.
   * @param bool $latest
   *   If true, only print the latest backup. Complies with $components param.
   *
   * @return int
   *   If 0 then all tasks completed successfully. If greater than zero, then
   *   some backups failed to generate.
   */
  protected function runSiteDataList($sync_maps, $components = [], $backup_id = NULL, $latest = FALSE) {
    // Ensure we are only using backup_id with a specific site.
    if (count($sync_maps) > 1 && !empty($backup_id)) {
      throw new \Exception('When listing a specific backup_id you must also specify a single site ID.');
    }

    $say_latest = $latest ? ", only showing latest" : "";
    if (!empty($backup_id)) {
      $this->say("Listing site backup ID {$backup_id}");
    }
    elseif (empty($components)) {
      $this->say("Listing site backups of any component list{$say_latest}");
    }
    else {
      if ($components !== ['database']) {
        throw new \Exception('We do not have support for components except for database.');
      }
      $this->say("Listing site backups of " . join(', ', $components) . "{$say_latest}");
    }

    try {
      $return_code = 0;

      /** @var \AcquiaCloudApi\CloudApi\Connector $connector */
      $connector = $this->client->getConnector();

      foreach ($sync_maps as $sync_map) {
        $environment_uuid = $this->client->getEnvironment($sync_map['env'])->uuid;
        $db_name = $sync_map['site_dir'];
        $response = $connector->makeRequest('get',"/environments/{$environment_uuid}/databases/{$db_name}/backups");

        if ($response->getStatusCode() >= 400) {
          throw new \Exception("API returned an invalid status code: " . $response->getStatusCode());
        }

        $response_body = json_decode($response->getBody(), TRUE);

        if (empty($response_body) || !isset($response_body['total'])) {
          throw new \Exception('Received an unexpected API response.');
        }

        $backups = [];
        $table_data = [];

        $this->say("Backups for site ID: {$sync_map['site_id']}");

        if (!empty($backup_id)) {
          foreach ($response_body['_embedded']['items'] as $backup) {
            if ($backup['id'] == $backup_id) {
              $backups[] = $backup;
              break;
            }
          }
          if (empty($backups)) {
            throw new \Exception("Could not find backup with ID: " . $backup_id);
          }
        }
        elseif ($latest) {
          // Filter to latest.
          $latest_backup = NULL;
          foreach ($response_body['_embedded']['items'] as $backup) {
            $latest_backup_completed_at = strtotime($latest_backup['completed_at'] );
            $backup_completed_at = strtotime($backup['completed_at']);
            if (empty($latest_backup) || $latest_backup_completed_at < $backup_completed_at) {
              $latest_backup = $backup;
            }
          }
          $backups = [$latest_backup];
        }
        else {
          $backups = $response_body['_embedded']['items'];
        }

        // Generate a table of those backups.
        foreach ($backups as $backup) {
          $table_data[$backup['id']] = print_r($backup, TRUE);
        }

        if (empty($table_data)) {
          $this->say("No backups found matching parameters.");
        }
        else {
          $this->printArrayAsTable($table_data, ['Backup ID', 'Data']);
        }
      }

      return $return_code;
    }
    catch (\Exception $e) {
      $this->logger->error("Failed to list backups, reason: {$e->getMessage()}");
      return 1;
    }
  }

  /**
   * Run the site restore logic.
   *
   * @param array $sync_maps
   *   An array of site mappings to run operations on.
   * @param array $components
   *   An array of components to run operations on.
   * @param int|null $backup_id
   *   The backup ID to list. Supercedes $components and $latest flags.
   * @param bool $latest
   *   If true, only print the latest backup. Complies with $components param.
   *
   * @return int
   *   If 0 then all tasks completed successfully. If greater than zero, then
   *   some backups failed to generate.
   */
  protected function runSiteDataRestore($sync_maps, $components = [], $backup_id = NULL) {
    // Ensure we are only using backup_id with a specific site.
    if (count($sync_maps) > 1 && !empty($backup_id)) {
      throw new \Exception('When restoring from a specific backup_id you must also specify a single site ID.');
    }

    if (count($components) > 1 && $components[0] != 'database') {
      throw new \Exception('Acquia Cloud only supports database backups.');
    }

    try {
      $return_code = 0;

      /** @var \AcquiaCloudApi\CloudApi\Connector $connector */
      $connector = $this->client->getConnector();

      $notification_ids = [];
      foreach ($sync_maps as $sync_map) {
        // Get specified backup or latest backup.
        if ($backup_id) {
          $environment_uuid = $this->client->getEnvironment($sync_map['env'])->uuid;
          $db_name = $sync_map['site_dir'];
          $response = $connector->makeRequest('get', "/environments/{$environment_uuid}/databases/{$db_name}/backups/{$backup_id}");

          if ($response->getStatusCode() >= 400) {
            throw new \Exception("API returned an invalid status code: " . $response->getStatusCode());
          }

          $response_body = json_decode($response->getBody(), TRUE);

          if (empty($response_body)) {
            throw new \Exception('Received an unexpected API response.');
          }

          if ($response_body['id'] != $backup_id) {
            throw new \Exception('Received unexpected backup ID from API.');
          }

          $restore_backup = $response_body;
        }
        else {
          $environment_uuid = $this->client->getEnvironment($sync_map['env'])->uuid;
          $db_name = $sync_map['site_dir'];
          $response = $connector->makeRequest('get', "/environments/{$environment_uuid}/databases/{$db_name}/backups");

          if ($response->getStatusCode() >= 400) {
            throw new \Exception("API returned an invalid status code: " . $response->getStatusCode());
          }

          $response_body = json_decode($response->getBody(), TRUE);

          if (empty($response_body) || !isset($response_body['total'])) {
            throw new \Exception('Received an unexpected API response.');
          }

          $restore_backup = NULL;

          // Filter to latest.
          $latest_backup = NULL;
          foreach ($response_body['_embedded']['items'] as $backup) {
            $latest_backup_completed_at = strtotime($latest_backup['completed_at']);
            $backup_completed_at = strtotime($backup['completed_at']);
            if (empty($latest_backup) || $latest_backup_completed_at < $backup_completed_at) {
              $latest_backup = $backup;
            }
          }

          $restore_backup = $latest_backup;
        }

        // Run restore
        if (empty($restore_backup)) {
          $this->logger->error('No backup to restore for site');
          // Let the return code equal the number of failed sites.
          $return_code++;
          continue;
        }
        $response = $connector->makeRequest('post',"/environments/{$environment_uuid}/databases/{$db_name}/backups/{$restore_backup['id']}/actions/restore");

        if ($response->getStatusCode() >= 400) {
          $this->logger->error("API returned an invalid status code: " . $response->getStatusCode());
          // Let the return code equal the number of failed sites.
          $return_code++;
          continue;
        }

        $response_body = json_decode($response->getBody(), TRUE);


        if (!isset($response_body['_links']['notification'])) {
          $this->logger->error("Unable to get response status. It's likely the request failed.");
          // Let the return code equal the number of failed sites.
          $return_code++;
          continue;
        }

        // Extract the notification ID.
        $notification_url_parts = explode('/', $response_body['_links']['notification']['href']);
        $notification_id = end($notification_url_parts);

        $notification_ids[] = $notification_id;
      }

      // Since this step may take 30 mins or more, we do 200 iterations at 15
      // second intervals, which should give about 50 mins of wait time.
      $response = $this->client->waitForNotificationsAndReturn($notification_ids, 15, 200);
      if (!$response) {
        throw new \Exception("Not all tasks completed successfully.");
      }

      return $return_code;
    }
    catch (\Exception $e) {
      $this->logger->error("Failed to restore sites, reason: {$e->getMessage()}");
      return 1;
    }
  }

  /**
   * Run the site sync logic.
   *
   * @param array $sync_maps
   *   An array of site mappings to run operations on.
   * @param array $components
   *   An array of components to run operations on.
   *
   * @return int
   *   If 0 then all tasks completed successfully. If greater than zero, then
   *   some backups failed to sync.
   */
  protected function runSiteDataSync($sync_maps, $components = []) {
    $this->say("Syncing sites from PROD to target environment");

    if (empty($components)) {
      throw new \Exception('You must provide components to sync.');
    }

    // If copying files we must copy both public and private.
    $has_public_files = in_array('public files', $components);
    $has_private_files = in_array('private files', $components);
    if (($has_public_files && !$has_private_files) || (!$has_public_files && $has_private_files)) {
      throw new \Exception('If syncing files you must sync both public and private.');
    }
    // Verify the components are allowed. We only support those listed here.
    $not_allowed_components = array_diff($components, ['database', 'public files', 'private files']);
    if (count($not_allowed_components)) {
      throw new \Exception('These components are not supported: ' . join(',', $not_allowed_components));
    }

    try {
      $return_code = 0;

      $options = $this->input()->getOptions();
      $to_env = $options['target-env'];

      $prod_env = $this->client->getEnvironment('prod');
      $target_env = $this->client->getEnvironment($to_env);

      $connector = $this->client->getConnector();

      $notification_ids = [];

      // Copy databases.
      $databases = [];
      foreach ($sync_maps as $sync_map) {
        $databases[] = $sync_map['site_dir'];
      }
      foreach ($databases as $database) {
        $options = [
          'form_params' => [
            'name' => $database,
            'source' => $prod_env->uuid,
          ],
        ];
        $response = $connector->makeRequest('post', "/environments/{$target_env->uuid}/databases", [], $options);
        if ($response->getStatusCode() >= 400) {
          $this->logger->error("API returned an invalid status code: " . $response->getStatusCode());
          // Let the return code equal the number of failed sites.
          $return_code++;
          continue;
        }
        $response_body = json_decode($response->getBody(), TRUE);
        if (!isset($response_body['_links']['notification'])) {
          $this->logger->error("Unable to get response status. It's likely the request failed.");
          // Let the return code equal the number of failed sites.
          $return_code++;
          continue;
        }
        // Extract the notification ID.
        $notification_url_parts = explode('/', $response_body['_links']['notification']['href']);
        $notification_id = end($notification_url_parts);
        $notification_ids[] = $notification_id;
      }

      // Copy files.
      $options = [
        'form_params' => [
          'source' => $prod_env->uuid,
        ],
      ];
      $response = $connector->makeRequest('post', "/environments/{$target_env->uuid}/files", [], $options);
      if ($response->getStatusCode() >= 400) {
        $this->logger->error("API returned an invalid status code: " . $response->getStatusCode());
        // Let the return code equal the number of failed sites.
        $return_code++;
      }
      else {
        $response_body = json_decode($response->getBody(), TRUE);
        if (!isset($response_body['_links']['notification'])) {
          $this->logger->error("Unable to get response status. It's likely the request failed.");
          // Let the return code equal the number of failed sites.
          $return_code++;
        }
        else {
          // Extract the notification ID.
          $notification_url_parts = explode('/', $response_body['_links']['notification']['href']);
          $notification_id = end($notification_url_parts);
          $notification_ids[] = $notification_id;
        }
      }

      // Since this step may take 30 mins or more, we do 200 iterations at 15
      // second intervals, which should give about 50 mins of wait time.
      $response = $this->client->waitForNotificationsAndReturn($notification_ids, 15, 200);
      if (!$response) {
        throw new \Exception("Not all tasks completed successfully.");
      }

      return $return_code;
    }
    catch (\Exception $e) {
      $this->logger->error("Failed to sync sites, reason: {$e->getMessage()}");
      return 1;
    }
  }

  /**
   * Get the components provided by the user.
   *
   * @param $options
   *   The options passed to the command.
   *
   * @return array
   *   An array of components.
   */
  protected function getInputComponents($options) {
    $allowed_components = [
      'codebase',
      'database',
      'public files',
      'private files',
      'themes',
    ];
    $components = array_filter(explode(',', $options['components']));
    $not_allowed_components = [];
    foreach ($components as $component) {
      if (!in_array($component, $allowed_components)) {
        $not_allowed_components[] = $component;
      }
    }
    if (!empty($not_allowed_components)) {
      throw new \Exception('These components are not allowed: ' . join(', ', $not_allowed_components));
    }
    return $components;
  }

}
