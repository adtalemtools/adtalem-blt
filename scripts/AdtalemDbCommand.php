<?php

namespace Adtalem\Blt\Plugin\Commands;

use Adtalem\Blt\Plugin\Helpers\Acsf\Ac\AcsfAcApiClient;
use Adtalem\Blt\Plugin\Helpers\Acsf\CommandOptionTargetSitesTrait;
use Adtalem\Blt\Plugin\Helpers\Acsf\LocalBackupStorage;
use Acquia\Blt\Robo\BltTasks;
use Consolidation\AnnotatedCommand\AnnotationData;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Defines commands in the "adtalem:db" namespace.
 */
class AdtalemDbCommand extends BltTasks {

  // use CommandOptionTargetSitesTrait;

  /**
   * The Acquia Cloud API Client.
   *
   * @var \Acquia\Blt\Custom\Helpers\Acsf\Ac\AcsfAcApiClient
   */
  protected $client;

  /**
   * Process options for Acquia Cloud API.
   *
   * @hook interact
   */
  public function setupClient(InputInterface $input, OutputInterface $output, AnnotationData $annotationData) {
    $app_id = $this->getConfigValue('cloud.appId');
    $this->client = new AcsfAcApiClient($app_id, $this->logger);
  }

  /**
   * Download a backup for the site.
   *
   * @command adtalem:db:download
   * @executeInVm
   */
  public function dbDownload($options = [
    'backup-id' => '',
  ]) {
    $backup_id = $options['backup-id'];

    $selected_sites = $this->getSelectedSites();

    $operation_names = ['Downloading database backup'];
    $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-to-local');
    if (!$continue) {
      return 0;
    }

    return $this->runDbDownload($selected_sites, $backup_id);
  }

  /**
   * List available backups.
   *
   * @command adtalem:db:list
   * @executeInVm
   */
  public function dbList($options = [
    'backup-id' => '',
    'latest' => FALSE,
  ]) {
    $latest = $options['latest'];
    $backup_id = $options['backup-id'];

    $selected_sites = $this->getSelectedSites();

    $operation_names = ['Listing database backups'];
    $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-to-local');
    if (!$continue) {
      return 0;
    }

    return $this->runDbList($selected_sites, $backup_id, $latest);
  }

  /**
   * Run the site backup download logic.
   *
   * @param array $sync_maps
   *   An array of site mappings to run operations on.
   * @param int|null $backup_id
   *   The backup ID to download. Supercedes $components and $latest flags.
   *
   * @return int
   *   If 0 then all tasks completed successfully. If greater than zero, then
   *   some backups failed to generate.
   */
  protected function rundbDownload($sync_maps, $backup_id = NULL) {
    // Ensure we are only using backup_id with a specific site.
    if (count($sync_maps) > 1 && !empty($backup_id)) {
      throw new \Exception('When downloading a specific backup_id you must also specify a single site ID.');
    }

    if (!empty($backup_id)) {
      $this->say("Downloading site backup ID {$backup_id}");
    }
    elseif (empty($components)) {
      $this->say("Downloading latest site backups");
    }

    $local_storage = new LocalBackupStorage($this->getConfig(), $this->logger, $this->targetEnv);

    try {
      $return_code = 0;

      // An array of backups, keyed on the site ID.
      $download_backups = [];

      // Get backups to download.
      if ($backup_id) {
        $response = $this->client->getDatabaseBackup($sync_maps[0]['env'], $sync_maps[0]['site_dir'], $backup_id);
        if (empty($response)) {
          throw new \Exception("Failed to backups for site ID: {$sync_maps[0]['site_id']}");
        }
        $download_backups[$sync_maps[0]['site_id']] = $response;
      }
      else {
        foreach ($sync_maps as $sync_map) {
          /** @var \AcquiaCloudApi\Response\BackupsResponse $response */
          $response = $this->client->getDatabaseBackups($sync_map['env'], $sync_map['site_dir']);

          if (!$response->count()) {
            $this->logger->error("Failed to backups for site ID: {$sync_map['site_id']}");
            // Let the return code equal the number of failed sites.
            $return_code++;
            continue;
          }

          // Filter to latest.
          $latest_backup = NULL;
          foreach ($response as $backup) {
            if (empty($latest_backup) || $latest_backup->completedAt < $backup->completedAt) {
              $latest_backup = $backup;
            }
          }

          if (empty($latest_backup)) {
            throw new \Exception('No latest backup found.');
          }

          $download_backups[$sync_map['site_id']] = $latest_backup;
        }
      }

      if (empty($download_backups)) {
        $this->logger->error("No backups to download");
        return $return_code;
      }

      // Download each backup.
      foreach ($download_backups as $site_id => $backup) {
        $formatted_backup = $local_storage->formatAcBackupMetadata($site_id, $backup);

        if ($local_storage->hasAvailableBackup($formatted_backup)) {
          $this->logger->notice("Loading file from path instead of downloading backup ID {$formatted_backup['id']}");
          continue;
        }

        // Get the db_name.
        $db_name = '';
        foreach ($sync_maps as $sync_map) {
          if ($sync_map['site_id'] == $site_id) {
            $db_name = $sync_map['site_dir'];
          }
        }
        if (empty($db_name)) {
          throw new \Exception("Couldnt find db name for site id {$site_id}");
        }

        if (!$local_storage->downloadAndSaveAcBackup($this->client, $db_name, $formatted_backup)) {
          $this->logger->error("Failed to download backup ID {$formatted_backup['id']}");
          // Exit -- we assume there will be errors with the next ones too.
          return 1;
        }
      }

      return $return_code;
    } catch (\Exception $e) {
      $this->logger->error("Failed to backup sites, reason: {$e->getMessage()}");
      return 1;
    }
  }

  /**
   * Run the site backup list logic.
   *
   * @param array $sync_maps
   *   An array of site mappings to run operations on.
   * @param int|null $backup_id
   *   The backup ID to list. Supercedes $components and $latest flags.
   * @param bool $latest
   *   If true, only print the latest backup. Complies with $components param.
   *
   * @return int
   *   If 0 then all tasks completed successfully. If greater than zero, then
   *   some backups failed to generate.
   */
  protected function runDbList($sync_maps, $backup_id = NULL, $latest = FALSE) {
    // Ensure we are only using backup_id with a specific site.
    if (count($sync_maps) > 1 && !empty($backup_id)) {
      throw new \Exception('When listing a specific backup_id you must also specify a single site ID.');
    }

    $say_latest = $latest ? ", only showing latest" : "";
    if (!empty($backup_id)) {
      $this->say("Listing database backup ID {$backup_id}");
    }
    else {
      $this->say("Listing database backups{$say_latest}");
    }

    $local_storage = new LocalBackupStorage($this->getConfig(), $this->logger, $this->targetEnv);

    try {
      $return_code = 0;

      foreach ($sync_maps as $sync_map) {
        /** @var \AcquiaCloudApi\Response\BackupResponse[] $backups */
        $backups = [];
        $table_data = [];

        if (!empty($backup_id)) {
          $response = $this->client->getDatabaseBackup($sync_map['env'], $sync_map['site_dir'], $backup_id);
          if (empty($response)) {
            throw new \Exception("Could not get backup ID {$backup_id}");
          }
          $backups[] = $local_storage->formatAcBackupMetadata($sync_map['site_id'], $response);
        }
        else {
          $response = $this->client->getDatabaseBackups($sync_map['env'], $sync_map['site_dir']);
          if (empty($response)) {
            throw new \Exception("Could not get backup ID {$backup_id}");
          }
          foreach ($response as $backup_response) {
            $backups[] = $local_storage->formatAcBackupMetadata($sync_map['site_id'], $backup_response);
          }
        }

        // Filter to latest.
        if ($latest) {
          $latest_backup = NULL;
          foreach ($backups as $backup) {
            if (empty($latest_backup) || $latest_backup['response']->completedAt < $backup['response']->completedAt) {
              $latest_backup = $backup;
            }
          }
          $backups = [$latest_backup];
        }

        // Generate a table of those backups.
        foreach ($backups as $backup) {
          $table_data[$backup['response']->id] = print_r($backup, TRUE);
        }

        if (empty($table_data)) {
          $this->say("No backups found matching parameters.");
        }
        else {
          $this->say("Database backups for site {$sync_map['site_id']}");
          $this->printArrayAsTable($table_data, ['Backup ID', 'Data']);
        }
      }

      return $return_code;
    } catch (\Exception $e) {
      $this->logger->error("Failed to list backups, reason: {$e->getMessage()}");
      return 1;
    }
  }

}
