<?php

namespace Adtalem\Blt\Plugin\Commands;

use Adtalem\Blt\Custom\Helpers\Acsf\CommandOptionSourceSitesTrait;
use Adtalem\Blt\Custom\Helpers\Acsf\LocalBackupStorage;
use Acquia\Blt\Robo\BltTasks;
use Adtalem\Blt\Plugin\Helpers\Acsf\CommandOptionSourceSitesTrait;
use Adtalem\Blt\Plugin\Helpers\Acsf\LocalBackupStorage;

/**
 * Defines commands in the "adtalem:local:data" namespace.
 */
class AdtalemLocalDataCommand extends BltTasks {

  use CommandOptionSourceSitesTrait;

  /**
   * Sync data from an upstream to local.
   *
   * @command adtalem:local:data:sync
   * @executeInVm
   */
  public function localSync($options = [
    'components' => 'database,public files,private files',
  ]) {
    $components = $this->getInputComponents($options);

    $selected_sites = $this->getSelectedSites();

    $operation_names = ['Sync data of ' . join(', ', $components)];
    $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-to-local');
    if (!$continue) {
      return 0;
    }

    return $this->runLocalDataSync($selected_sites, $components);
  }

  /**
   * Restore a local site from a backup.
   *
   * @command adtalem:local:data:restore
   * @aliases adtalem:local:data:restore-and-update
   * @executeInVm
   */
  public function localRestoreAndUpdate($options = [
    'components' => 'database,public files,private files',
    'backup-id' => '',
  ]) {
    $components = $this->getInputComponents($options);
    $backup_id = $options['backup-id'];

    $selected_sites = $this->getSelectedSites();

    $operation_names = ['Restoring backups with ' . join(', ', $components)];
    $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-to-local');
    if (!$continue) {
      return 0;
    }

    return $this->runLocalDataRestoreAndUpdate($selected_sites, $components, $backup_id);
  }

  /**
   * List backups stored locally.
   *
   * @command adtalem:local:data:list
   * @executeInVm
   */
  public function localList($options = [
    'components' => '',
    'backup-id' => '',
    'latest' => FALSE,
  ]) {
    $components = $this->getInputComponents($options);
    $latest = $options['latest'];
    $backup_id = $options['backup-id'];

    $selected_sites = $this->getSelectedSites();

    if (empty($components)) {
      $operation_names = ['List backups with any component'];
    }
    else {
      $operation_names = ['List backups with ' . join(', ', $components)];
    }

    $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-to-local');
    if (!$continue) {
      return 0;
    }

    return $this->runLocalDataList($selected_sites, $components, $backup_id, $latest);
  }

  /**
   * Cleanup old backups stored locally.
   *
   * @command adtalem:local:data:cleanup
   * @executeInVm
   */
  public function localCleanup($options = [
    'components' => '',
    'backup-id' => '',
    'older-than' => '7',
  ]) {
    $components = $this->getInputComponents($options);
    $older_than = $options['older-than'];
    $backup_id = $options['backup-id'];

    $selected_sites = $this->getSelectedSites();

    $operation_names = ['Cleaning up backups with ' . join(', ', $components)];
    $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-to-local');
    if (!$continue) {
      return 0;
    }

    return $this->runLocalDataCleanup($selected_sites, $components, $backup_id, $older_than);
  }

  /**
   * Run the sync data logic.
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
  protected function runLocalDataSync($sync_maps, $components) {
    $this->say("Syncing data of " . join(', ', $components));

    if (in_array('codebase', $components)) {
      throw new \Exception('The component "codebase" is unsupported when syncing to local.');
    }
    if (in_array('themes', $components)) {
      throw new \Exception('The component "themes" is unsupported when syncing to local.');
    }

    try {
      $return_code = 0;

      foreach ($sync_maps as $sync_map) {
        $site_dir = $sync_map['site_dir'];
        if (empty($site_dir)) {
          throw new Exception('Site needs a site_dir property to sync files.');
        }

        if (in_array('public files', $components)) {
          $dest_dir = $this->getConfigValue('docroot') . "/sites/g/files/" . $site_dir . '/files';
          $this->_mkdir($dest_dir);
          $task = $this->taskDrush()
            ->alias('')
            ->uri($sync_map['local_url'])
            ->drush('rsync')
            ->arg('@' . $sync_map['remote_alias'] . ':%files/')
            ->arg($dest_dir)
            ->verbose(TRUE)
            ->option('exclude-paths', implode(':', $this->getConfigValue('sync.exclude-paths')));
          $result = $task->run();
          if (!$result->wasSuccessful()) {
            $return_code++;
            continue;
          }
        }

        if (in_array('private files', $components)) {
          $dest_dir = $this->getConfigValue('docroot') . "/../files-private/" . $site_dir;
          $this->_mkdir($dest_dir);
          $task = $this->taskDrush()
            ->alias('')
            ->uri($sync_map['local_url'])
            ->drush('rsync')
            ->arg('@' . $sync_map['remote_alias'] . ':%private/')
            ->arg($dest_dir)
            ->verbose(TRUE)
            ->option('exclude-paths', implode(':', $this->getConfigValue('sync.exclude-paths')));
          $result = $task->run();
          if (!$result->wasSuccessful()) {
            $return_code++;
            continue;
          }
        }

        if (in_array('database', $components)) {
          $i = rand();
          $task = $this->taskDrush()
            ->alias('')
            ->uri($sync_map['local_url'])
            ->drush('cache-clear drush')
            ->drush('sqlq "select database()"')
            ->drush('sql-drop')
            ->drush('sql-sync')
            ->arg('@' . $sync_map['remote_alias'])
            ->arg('@self')
            ->option('--source-dump', sys_get_temp_dir() . '/tmp.source' . $i . '.sql')
            ->option('--target-dump', sys_get_temp_dir() . '/tmp.target' . $i . '.sql.gz')
            ->option('structure-tables-key', 'lightweight')
            ->option('create-db');

          if ($this->getConfigValue('drush.sanitize')) {
            $task->drush('sql-sanitize');
          }

          $task->drush('cr');
          $task->drush('sqlq "TRUNCATE cache_entity"');

          $result = $task->run();
          if (!$result->wasSuccessful()) {
            $return_code++;
            continue;
          }
        }

        $this->say("Synced site: " . $sync_map['local_url']);
      }
      return $return_code;
    } catch (\Exception $e) {
      $this->logger->error("Failed to backup sites, reason: {$e->getMessage()}");
      return 1;
    }
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
  protected function runLocalDataRestoreAndUpdate($sync_maps, $components, $backup_id = NULL) {
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

          // Update database must be run first.
          $result = $this->_exec("drush -l {$sync_map['local_url']} updb -y");
          if (!$result->wasSuccessful()) {
            $this->logger->error("Failed to update database for site ID {$sync_map['site_id']}");
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
  protected function runLocalDataList($sync_maps, $components = [], $backup_id = NULL, $latest = FALSE) {
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
      $this->say("Listing site backups of " . join(', ', $components) . "{$say_latest}");
    }

    $local_storage = new LocalBackupStorage($this->getConfig(), $this->logger, $this->sourceEnv);

    try {
      $return_code = 0;

      foreach ($sync_maps as $sync_map) {
        $table_data = [];
        $backups = [];

        $available_backups = $local_storage->getAvailableBackups($sync_map['site_id']);
        foreach ($available_backups as $available_backup) {
          if ($backup_id) {
            if ($available_backup['id'] != $backup_id) {
              continue;
            }
            $backups[] = $available_backup;
            break 1;
          }
          else {
            if (!empty($components)) {
              $shared_components = array_intersect($components, $available_backup['componentList']);
              if (count($shared_components) !== count($available_backup['componentList'])) {
                continue;
              }
              $backups[] = $available_backup;
            }
            else {
              $backups[] = $available_backup;
            }
          }
        }

        if ($latest) {
          $backups = [end($backups)];
        }

        // Generate a table of those backups.
        foreach ($backups as $backup) {
          $table_data[$backup['id']] = print_r($backup, TRUE);
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

  /**
   * Run the site backup cleanup logic.
   *
   * @param array $sync_maps
   *   An array of site mappings to run operations on.
   * @param array $components
   *   An array of components to run operations on.
   * @param int|null $backup_id
   *   The backup ID to list. Supercedes $components and $latest flags.
   * @param int $older_than
   *   Cleanup backups older than the specified number of days.
   *
   * @return int
   *   If 0 then all tasks completed successfully. If greater than zero, then
   *   some backups failed to generate.
   */
  protected function runLocalDataCleanup($sync_maps, $components = [], $backup_id = NULL, $older_than = 3) {
    // Ensure we are only using backup_id with a specific site.
    if (count($sync_maps) > 1 && !empty($backup_id)) {
      throw new \Exception('When listing a specific backup_id you must also specify a single site ID.');
    }

    $local_storage = new LocalBackupStorage($this->getConfig(), $this->logger, $this->sourceEnv);

    try {
      $return_code = 0;
      $current_time = time();
      $older_than_seconds = ($older_than * 24 * 60 * 60);

      foreach ($sync_maps as $sync_map) {
        $table_data = [];
        $backups = [];

        $available_backups = $local_storage->getAvailableBackups($sync_map['site_id']);
        foreach ($available_backups as $available_backup) {
          if ($backup_id) {
            if ($available_backup['id'] != $backup_id) {
              continue;
            }
            $backups[] = $available_backup;
            break 1;
          }
          else {
            // Skip if newer than our "older than" threshold.
            if (($current_time - $available_backup['timestamp']) <= $older_than_seconds) {
              continue;
            }

            if (!empty($components)) {
              $shared_components = array_intersect($components, $available_backup['componentList']);
              if (count($shared_components) !== count($available_backup['componentList'])) {
                continue;
              }
              $backups[] = $available_backup;
            }
            else {
              $backups[] = $available_backup;
            }
          }
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
          if ($this->confirm("Delete these backups?", TRUE)) {
            foreach ($backups as $backup) {
              if (!$local_storage->removeLocalBackup($backup)) {
                $return_code++;
                break;
              }
            }
          }
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
