<?php

namespace Adtalem\Blt\Plugin\Commands;

use Adtalem\Blt\Plugin\Helpers\Acsf\Ac\AcsfAcApiClient;
use Adtalem\Blt\Plugin\Helpers\Acsf\CommandOptionSourceSitesTrait;
use Adtalem\Blt\Plugin\Helpers\Acsf\LocalBackupStorage;
use Acquia\Blt\Robo\BltTasks;
use Consolidation\AnnotatedCommand\AnnotationData;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Defines commands in the "adtalem:refresh" namespace.
 */
class AdtalemRefreshCommand extends BltTasks {

   use CommandOptionSourceSitesTrait;

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
   * Set default values.
   *
   * Note: the only reason this works is because it gets called before the
   * interact hook in the trait.
   *
   * @hook interact
   */
  public function setDefaultEnv(InputInterface $input, OutputInterface $output, AnnotationData $annotationData) {
    // If the source environment was not provided, use "01live".
    $source_env = $this->sourceEnv = $input->getOption('source-env');
    if (empty($source_env)) {
      $input->setOption('source-env', '01live');
    }
  }

  /**
   * Download and restore a backup for the site.
   *
   * @command adtalem:refresh:db
   * @executeInVm
   */
  public function refreshDb($options = [
    'backup-id' => '',
  ]) {
    $components = ['database'];
    $backup_id = $options['backup-id'];

    $selected_sites = $this->getSelectedSites();

    $operation_names = ['Downloading database backup'];
    $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-to-local');
    if (!$continue) {
      return 0;
    }

    $exit_code = $this->runDbDownload($selected_sites, $backup_id);
    if ($exit_code) {
      return $exit_code;
    }

    $operation_names = ['Restoring backups with ' . join(', ', $components)];
    $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-to-local');
    if (!$continue) {
      return 0;
    }

    return $this->runLocalDataRestore($selected_sites, $components, $backup_id);
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

    $local_storage = new LocalBackupStorage($this->getConfig(), $this->logger, $this->sourceEnv);

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
    } catch (\Exception $e) {
      $this->logger->error("Failed to backup sites, reason: {$e->getMessage()}");
      return 1;
    }

    return $return_code;
  }

  /**
   * Run the data restore logic.
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
  protected function runLocalDataRestore($sync_maps, $components, $backup_id = NULL) {
    // Ensure we are only using backup_id with a specific site.
    if (count($sync_maps) > 1 && !empty($backup_id)) {
      throw new \Exception('When downloading a specific backup_id you must also specify a single site ID.');
    }

    if (in_array('codebase', $components)) {
      throw new \Exception('The component "codebase" is unsupported when restoring to local.');
    }
    if (in_array('themes', $components)) {
      throw new \Exception('The component "themes" is unsupported when restoring to local.');
    }

    $local_storage = new LocalBackupStorage($this->getConfig(), $this->logger, $this->sourceEnv);

    try {
      $return_code = 0;

      foreach ($sync_maps as $sync_map) {
        $available_backups = $local_storage->getAvailableBackups($sync_map['site_id']);
        $backup = NULL;

        foreach ($available_backups as $available_backup) {
          if ($backup_id) {
            if ($available_backup['id'] != $backup_id) {
              continue;
            }
            $shared_components = array_intersect($components, $available_backup['componentList']);
            if (count($shared_components) !== count($components)) {
              $this->logger->error("Backup ID {$backup_id} does not contain all components requested for restore");
              $return_code++;
              break;
            }
            $backup = $available_backup;
            break;
          }
          else {
            $shared_components = array_intersect($components, $available_backup['componentList']);
            if (count($shared_components) !== count($components)) {
              continue;
            }
            $backup = $available_backup;
          }
        }

        if (empty($backup)) {
          $this->logger->error("Could not find backup for site {$sync_map['site_id']}");
          $return_code++;
          continue;
        }

        if (!$local_storage->extractBackup($backup)) {
          $this->logger->error("Could not extract backup {$backup['id']}");
          $return_code++;
          continue;
        }

        $backup_file = $local_storage->getBackupFilepath($sync_map['site_id'], $sync_map['env'], $backup['file']);
        $backup_file_extracted_dir = $local_storage->getBackupFileExtractionDir($backup_file);

        if (in_array('public files', $components)) {
          $source_dir = $backup_file_extracted_dir . '/docroot/sites/default/files/';
          $dest_dir = $this->getConfigValue('docroot') . "/sites/g/files/" . $sync_map['site_dir'] . '/files';
          $this->_mkdir($dest_dir);
          $result = $this->_exec("drush -v --no-interaction --ansi -l {$sync_map['local_url']} rsync {$source_dir} {$dest_dir} --exclude-paths='" . implode(':', $this->getConfigValue('sync.exclude-paths')) . "' -- --no-group");
          if (!$result->wasSuccessful()) {
            $this->logger->error("Failed to restore public files for site {$sync_map['site_id']}, reason: " . $result->getMessage());
            $return_code++;
            continue;
          }
        }

        if (in_array('private files', $components)) {
          $source_dir = $backup_file_extracted_dir . '/docroot/sites/default/files-private/';
          $dest_dir = $this->getConfigValue('docroot') . "/../files-private/" . $sync_map['site_dir'];
          $this->_mkdir($dest_dir);
          $result = $this->_exec("drush -v --no-interaction --ansi -l {$sync_map['local_url']} rsync {$source_dir} {$dest_dir} --exclude-paths='" . implode(':', $this->getConfigValue('sync.exclude-paths')) . "' -- --no-group");
          if (!$result->wasSuccessful()) {
            $this->logger->error("Failed to restore private files for site {$sync_map['site_id']}, reason: " . $result->getMessage());
            $return_code++;
            continue;
          }
        }

        if (in_array('database', $components)) {
          if (!file_exists("{$backup_file_extracted_dir}/database.sql")) {
            $this->logger->error("Backup file for site {$sync_map['site_id']} doesn't exist: {$backup_file_extracted_dir}/database.sql");
            $return_code++;
            continue;
          }

          // Drop database.
          $task = $this->taskDrush()
            ->alias('')
            ->uri($sync_map['local_url'])
            ->drush('cache-clear drush')
            ->drush('sql-drop');
          $result = $task->run();
          if (!$result->wasSuccessful()) {
            $this->logger->error("Failed to cache clear drush and drop database for site ID {$sync_map['site_id']}");
            $return_code++;
            continue;
          }

          // Import database from file.
          $result = $this->_exec("drush -l {$sync_map['local_url']} sqlc < {$backup_file_extracted_dir}/database.sql");
          if (!$result->wasSuccessful()) {
            $this->logger->error("Could not import backup file for site ID {$sync_map['site_id']}: {$backup_file_extracted_dir}/database.sql");
            $return_code++;
            continue;
          }

          // Sanitize and clear cache.
          $task = $this->taskDrush()
            ->alias('')
            ->uri($sync_map['local_url']);
          if ($this->getConfigValue('drush.sanitize')) {
            $task->drush('sql-sanitize');
          }
          $task->drush('cr');
          $task->drush('sqlq "TRUNCATE cache_entity"');
          $result = $task->run();
          if (!$result->wasSuccessful()) {
            $this->logger->error("Failed to sanitize and clear cache for site ID {$sync_map['site_id']}");
            $return_code++;
            continue;
          }
        }

        $this->say("Restored site: " . $sync_map['local_url']);
      }
      return $return_code;
    } catch (\Exception $e) {
      $this->logger->error("Failed to restore sites, reason: {$e->getMessage()}");
      return 1;
    }
  }

}

