<?php

namespace Adtalem\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Adtalem\Blt\Plugin\Helpers\Acsf\CommandOptionTargetSitesTrait;
use Adtalem\Blt\Plugin\Helpers\Acsf\LocalBackupStorage;
use Adtalem\Blt\Plugin\Helpers\Acsf\AcsfApiClient;
use Consolidation\AnnotatedCommand\AnnotationData;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;


/**
 * Defines commands in the "adtalem:site:data" namespace.
 */
class AdtalemSiteDataCommand extends BltTasks {

   use CommandOptionTargetSitesTrait;

  /**
   * The ACSF API Client.
   *
   * @var \Adtalem\Blt\Plugin\Helpers\Acsf\AcsfApiClient
   */
  protected $client;

  /**
   * Set the ACSF API client.
   *
   * @param array $options
   *   The arguments passed to the command.
   */
  protected function setupAcsfApiClient($options) {
    $this->client = new AcsfApiClient($this->logger);
    $acsf_name = $this->getConfigValue('acsf.name');
    switch ($this->targetEnv) {
      case '01live':
        $options['acsf-api-base-url'] = "https://www.{$acsf_name}.acsitefactory.com/";
        break;

      case '01test':
        $options['acsf-api-base-url'] = "https://www.test-{$acsf_name}.acsitefactory.com/";
        break;

      case '01dev':
        $options['acsf-api-base-url'] = "https://www.dev-{$acsf_name}.acsitefactory.com/";
        break;

      default:
        throw new \InvalidArgumentException("Unknown environment set: " . $this->targetEnv);
    }
    $this->client->setAcsfApiConfig($options);
  }

  /**
   * Add options for ACSF API.
   *
   * @hook option
   */
  public function addAcsfApiCommandOptions(Command $command, AnnotationData $annotationData) {
    $command_definition = $command->getDefinition();

    $command_definition->addOption(
      new InputOption('--acsf-api-username', '', InputOption::VALUE_REQUIRED, 'Acquia Cloud Site Factory API username.', '')
    );

    $command_definition->addOption(
      new InputOption('--acsf-api-password', '', InputOption::VALUE_OPTIONAL, 'Acquia Cloud Site Factory API password.', '')
    );

    $command_definition->addOption(
      new InputOption('--acsf-api-base-url', '', InputOption::VALUE_OPTIONAL, 'Acquia Cloud Site Factory API base URL with trailing slash, e.g. https://www.mysite.acsitefactory.com/', '')
    );
  }

  /**
   * Process options for ACSF API.
   *
   * @hook interact
   */
  public function processAcsfApiommandOptions(InputInterface $input, OutputInterface $output, AnnotationData $annotationData) {
    $this->targetEnv = $input->getOption('target-env');

    if (empty($this->targetEnv)) {
      throw new \InvalidArgumentException("The target-env option is required.");
    }
    $this->setupAcsfApiClient($input->getOptions());
  }

  /**
   * Backup the database and files for the given sites.
   *
   * @command adtalem:site:data:backup
   * @executeInVm
   */
  public function siteBackup($options = [
    'components' => 'codebase,database,public files,private files,themes',
    'label' => '',
    'autolabel' => '',
  ]) {
    $components = $this->getInputComponents($options);
    $label = $options['label'];
    $autolabel = $options['autolabel'];

    if (!empty($label) && !empty($autolabel)) {
      throw new \Exception('You cannot use label and autolabel at the same time.');
    }

    if (!empty($autolabel) && !in_array($autolabel, [
        'predeploy',
        'prerollback',
      ])) {
      throw new \Exception('You must specify an autolabel strategy; accepted values: predeploy, prerollback');
    }

    if (!empty($autolabel)) {
      $current_gitref = $this->getDeployedGitRef();
      $label = $current_gitref . '-' . $autolabel;
    }

    $selected_sites = $this->getSelectedSites();

    $with_label = !empty($label) ? ' with label "' . $label . '"' : '';
    $operation_names = ['Generate site backup of ' . join(', ', $components) . $with_label];
    $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-only');
    if (!$continue) {
      return 0;
    }

    return $this->runSiteDataBackups($selected_sites, $components, $label);
  }

  /**
   * Download a backup for the site.
   *
   * @command adtalem:site:data:download
   * @executeInVm
   */
  public function siteDownload($options = [
    'components' => 'codebase,database,public files,private files,themes',
    'backup-id' => '',
  ]) {
    $components = $this->getInputComponents($options);
    $backup_id = $options['backup-id'];

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
   * @command adtalem:site:data:list
   * @executeInVm
   */
  public function siteList($options = [
    'components' => '',
    'backup-id' => '',
    'latest' => FALSE,
    'label' => '',
  ]) {
    $components = $this->getInputComponents($options);
    $latest = $options['latest'];
    $backup_id = $options['backup-id'];
    $label = $options['label'];

    $selected_sites = $this->getSelectedSites();

    if (empty($components)) {
      $operation_names = ['Listing backups with any components'];
    }
    else {
      $operation_names = ['Listing backups with ' . join(', ', $components)];
    }
    if (!empty($label)) {
      $operation_names[] = 'Filtering by label ' . $label;
    }
    $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-only');
    if (!$continue) {
      return 0;
    }

    return $this->runSiteDataList($selected_sites, $components, $backup_id, $latest, $label);
  }

  /**
   * Restore the given sites from a backup.
   *
   * Note: codebase is not allowed when restoring a site.
   *
   * @command adtalem:site:data:restore
   * @executeInVm
   */
  public function siteRestore($options = [
    'components' => 'database,public files,private files,themes',
    'backup-id' => '',
    'label' => '',
    'autolabel' => '',
  ]) {
    $components = $this->getInputComponents($options);
    $backup_id = $options['backup-id'];
    $label = $options['label'];
    $autolabel = $options['autolabel'];

    if (!empty($label) && !empty($autolabel)) {
      throw new \Exception('You cannot use label and autolabel at the same time.');
    }

    if (!empty($autolabel) && !in_array($autolabel, [
        'predeploy',
        'prerollback',
      ])) {
      throw new \Exception('You must specify an autolabel strategy; accepted values: predeploy, prerollback');
    }

    if (!empty($autolabel)) {
      $current_gitref = $this->getDeployedGitRef();
      $label = $current_gitref . '-' . $autolabel;
    }

    $selected_sites = $this->getSelectedSites();

    if (!empty($label) && !empty($backup_id)) {
      throw new \Exception("You cannot use the label flag with the backup-id flag at the same time.");
    }

    // If using a label, we must first find the backup with that label.
    if (!empty($label)) {
      $operation_names = ["Finding backup with the label {$label}"];
      $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-only');
      if (!$continue) {
        return 0;
      }
      $backups = $this->getBackupsByLabel($selected_sites, $components, $label);
      $backups = array_filter($backups);

      if (count($backups) !== count($selected_sites)) {
        throw new \Exception("Could not find backup for a site using label {$label}");
      }

      $operation_names = ['Restoring sites from backups'];
      $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-only');
      if (!$continue) {
        return 0;
      }

      $exit_code = $this->bulkSiteDataRestore($backups, $components);

      return $exit_code;
    }
    else {
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
  }

  /**
   * Sync the data from the PROD env to the target env for given sites.
   *
   * @command adtalem:site:data:sync
   * @executeInVm
   */
  public function siteSync($options = []) {
    if ('01live' == $this->targetEnv) {
      throw new \Exception('You cannot sync data to the PROD environment.');
    }

    $selected_sites = $this->getSelectedSites();

    $operation_names = ['Sync from PROD env to ' . $target_env];
    $continue = $this->confirmSelection($selected_sites, $operation_names, 'remote-to-local');
    if (!$continue) {
      return 0;
    }

    return $this->runSiteDataSync($selected_sites);
  }

  /**
   * Run the site backup logic.
   *
   * @param array $sync_maps
   *   An array of site mappings to run operations on.
   * @param array $components
   *   An array of components to run operations on.
   * @param string $label
   *   The label for the backups (optional).
   *
   * @return int
   *   If 0 then all tasks completed successfully. If greater than zero, then
   *   some backups failed to generate.
   */
  protected function runSiteDataBackups($sync_maps, $components, $label = '') {
    $this->say("Generating site backups of " . join(', ', $components));

    try {
      $return_code = 0;

      $task_ids = [];
      foreach ($sync_maps as $sync_map) {
        $resource_uri = "sites/{$sync_map['site_id']}/backup";
        $headers = [
          'Content-Type: application/json',
        ];
        $body = [
          'components' => $components,
        ];
        if (!empty($label)) {
          $body['label'] = $label;
        }
        $body = json_encode($body);
        $response = $this->client->makeV1Request('POST', $resource_uri, $headers, $body);

        if (!empty($response)) {
          $this->say("Backup started for site ID {$sync_map['site_id']}. Task ID: {$response['task_id']}.");
          $task_ids[] = $response['task_id'];
        }
        else {
          $this->logger->error("Failed to back up site ID: {$sync_map['site_id']}");
          // Let the return code equal the number of failed sites.
          $return_code++;
        }
      }

      // Since this step may take 30 mins or more, we do 200 iterations at 15
      // second intervals, which should give about 50 mins of wait time.
      $response = $this->client->waitForTasksAndReturn($task_ids, 15, 200);
      if (!$response) {
        throw new \Exception("Not all tasks completed successfully.");
      }

      return $return_code;
    } catch (\Exception $e) {
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

    try {
      $return_code = 0;

      // An array of backups, keyed on the site ID.
      $download_backups = [];

      // Get backups to download.
      if ($backup_id) {
        $sync_map = $sync_maps[0];
        $resource_uri = "sites/{$sync_map['site_id']}/backups/{$backup_id}";
        $response = $this->client->makeV1Request('GET', $resource_uri);

        if (!empty($response)) {
          $download_backups[$sync_maps[0]['site_id']] = $response['backups'][0];
        }
        else {
          $this->logger->error("Failed to backups for site ID: {$sync_maps[0]['site_id']}");
          // Let the return code equal the number of failed sites.
          $return_code++;
        }
      }
      else {
        foreach ($sync_maps as $sync_map) {
          $resource_uri = "sites/{$sync_map['site_id']}/backups";
          $response = $this->client->makeV1Request('GET', $resource_uri);

          if (!empty($response)) {
            $backups = [];

            // Filter to only backups that have all components.
            foreach ($response['backups'] as $backup) {
              if (count($components) !== count($backup['componentList'])) {
                continue;
              }
              $shared_components = array_intersect($components, $backup['componentList']);
              if (count($shared_components) !== count($backup['componentList'])) {
                continue;
              }
              $backups[] = $backup;
            }

            if (empty($backups)) {
              $this->logger->error("Unable to find backup for site ID: {$sync_map['site_id']}");
              continue;
            }

            // Filter to latest.
            $latest_backup = NULL;
            foreach ($backups as $backup) {
              if (empty($latest_backup) || $latest_backup['timestamp'] < $backup['timestamp']) {
                $latest_backup = $backup;
              }
            }

            $download_backups[$sync_map['site_id']] = $latest_backup;
          }
          else {
            $this->logger->error("Failed to backups for site ID: {$sync_map['site_id']}");
            // Let the return code equal the number of failed sites.
            $return_code++;
          }
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

        $resource_uri = "sites/{$site_id}/backups/{$backup['id']}/url";
        $response = $this->client->makeV1Request('GET', $resource_uri);

        if (!empty($response)) {
          if (!$local_storage->downloadAndSaveBackup($response['url'], $backup)) {
            $this->logger->error("Failed to download backup ID {$backup['id']}");
            // Exit -- we assume there will be errors with the next ones too.
            return 1;
          }
        }
        else {
          $this->logger->error("Failed to get download URL for backup ID {$backup['id']}");
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
   * @param array $components
   *   An array of components to run operations on.
   * @param int|null $backup_id
   *   The backup ID to list. Supercedes $components and $latest flags.
   * @param bool $latest
   *   If true, only print the latest backup. Complies with $components param.
   * @param string $label
   *   Filter backups by those with this label.
   *
   * @return int
   *   If 0 then all tasks completed successfully. If greater than zero, then
   *   some backups failed to generate.
   */
  protected function runSiteDataList($sync_maps, $components = [], $backup_id = NULL, $latest = FALSE, $label = '') {
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

    try {
      $return_code = 0;

      foreach ($sync_maps as $sync_map) {
        $resource_uri = "sites/{$sync_map['site_id']}/backups";
        $response = $this->client->makeV1Request('GET', $resource_uri);
        $backups = [];
        $table_data = [];

        if (!empty($response)) {
          $this->say("Backups for site ID: {$sync_map['site_id']}");

          if (!empty($backup_id)) {
            foreach ($response['backups'] as $backup) {
              if ($backup['id'] == $backup_id) {
                $backups[] = $backup;
                break;
              }
            }
          }
          elseif (empty($components)) {
            // Filter to latest.
            if ($latest) {
              $latest_backup = NULL;
              foreach ($response['backups'] as $backup) {
                if (empty($latest_backup) || $latest_backup['timestamp'] < $backup['timestamp']) {
                  $latest_backup = $backup;
                }
              }
              $backups = [$latest_backup];
            }
            else {
              $backups = $response['backups'];
            }
          }
          else {
            // Filter to component match.
            foreach ($response['backups'] as $backup) {
              if (count($components) !== count($backup['componentList'])) {
                continue;
              }
              $shared_components = array_intersect($components, $backup['componentList']);
              if (count($shared_components) !== count($backup['componentList'])) {
                continue;
              }
              $backups[] = $backup;
            }
            // Filter to latest.
            if ($latest) {
              $latest_backup = NULL;
              foreach ($backups as $backup) {
                if (empty($latest_backup) || $latest_backup['timestamp'] < $backup['timestamp']) {
                  $latest_backup = $backup;
                }
              }
              $backups = [$latest_backup];
            }
          }

          // Filter by label.
          if (!empty($label)) {
            $old_backups = $backups;
            $backups = [];
            foreach ($old_backups as $old_backup) {
              if ($old_backup['label'] == $label) {
                $backups[] = $old_backup;
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
          }
        }
        else {
          $this->logger->error("Failed to get backup list for site ID: {$sync_map['site_id']}");
          // Let the return code equal the number of failed sites.
          $return_code++;
        }
      }

      return $return_code;
    } catch (\Exception $e) {
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

    try {
      $return_code = 0;

      $task_ids = [];
      foreach ($sync_maps as $sync_map) {
        $resource_uri = "sites/{$sync_map['site_id']}/restore";
        $headers = [
          'Content-Type: application/json',
        ];
        // TODO: support passing target id, callback_url, callback_method, caller_data
        $params = [
          'target_site_id' => $sync_map['site_id'],
        ];
        if (!empty($components)) {
          $params['components'] = $components;
        }
        if (!empty($backup_id)) {
          $params['backup_id'] = $backup_id;
        }

        $body = json_encode($params);
        $response = $this->client->makeV1Request('POST', $resource_uri, $headers, $body);

        if (!empty($response)) {
          $this->say("Restore started for site ID {$sync_map['site_id']}. Task ID: {$response['task_id']}.");
          $task_ids[] = $response['task_id'];
        }
        else {
          $this->logger->error("Failed to restore site ID: {$sync_map['site_id']}");
          // Let the return code equal the number of failed sites.
          $return_code++;
        }
      }

      // Since this step may take 30 mins or more, we do 200 iterations at 15
      // second intervals, which should give about 50 mins of wait time.
      $response = $this->client->waitForTasksAndReturn($task_ids, 15, 200);
      if (!$response) {
        throw new \Exception("Not all tasks completed successfully.");
      }

      return $return_code;
    } catch (\Exception $e) {
      $this->logger->error("Failed to restore sites, reason: {$e->getMessage()}");
      return 1;
    }
  }

  /**
   * Run the site sync logic.
   *
   * @param array $sync_maps
   *   An array of site mappings to run operations on.
   *
   * @return int
   *   If 0 then all tasks completed successfully. If greater than zero, then
   *   some backups failed to sync.
   */
  protected function runSiteDataSync($sync_maps) {
    $this->say("Syncing sites from PROD to target environment");

    try {
      $return_code = 0;

      $resource_url = "stage";

      $site_ids = [];
      foreach ($sync_maps as $sync_map) {
        $site_ids[] = $sync_map['site_id'];
      }

      // TODO: is there a better way to handle map from 01{env} to {env}?
      $options = $this->input()->getOptions();
      if ($options['target-env'] == '01dev') {
        $to_env = 'dev';
      }
      elseif ($options['target-env'] == '01test') {
        $to_env = 'test';
      }
      else {
        throw new \Exception('Unknown environment passed.');
      }

      $headers = [
        'Content-Type: application/json',
      ];

      // Set request body parameters.
      // TODO: support these as parameters to the command
      $json_data = [
        'to_env' => $to_env,
        'sites' => $site_ids,
        // Do NOT wipe the env, this will drop all configured domains.
        'wipe_target_environment' => FALSE,
        // To increase speed of deployment, don't syncronize users.
        'synchronize_all_users' => FALSE,
        // Use detailed_status to get emails when task is complete.
        'detailed_status' => FALSE,
      ];

      // TODO: have some check that makes sure the base API URL is pointing to PROD; it will error otherwise.
      $response = $this->client->makeV2Request('POST', $resource_url, $headers, json_encode($json_data));

      if (!isset($response['task_id']) || empty($response['task_id'])) {
        throw new \Exception('Unable to sync from PROD to lower env, response was: ' . print_r($response, TRUE));
      }
      $task_ids = [$response['task_id']];

      // Since this step may take 30 mins or more, we do 200 iterations at 15
      // second intervals, which should give about 50 mins of wait time.
      $response = $this->client->waitForTasksAndReturn($task_ids, 15, 200);
      if (!$response) {
        throw new \Exception("Not all tasks completed successfully.");
      }

      return $return_code;
    } catch (\Exception $e) {
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

  protected function getBackupsByLabel($sync_maps, $components, $label) {
    try {
      $return_backups = [];

      foreach ($sync_maps as $sync_map) {
        $resource_uri = "sites/{$sync_map['site_id']}/backups";
        $response = $this->client->makeV1Request('GET', $resource_uri);

        if (!empty($response)) {
          $backups = $response['backups'];

          // Filter to the label.
          $new_backups = [];
          foreach ($backups as $backup) {
            if ($backup['label'] == $label) {
              $new_backups[] = $backup;
            }
          }
          $backups = $new_backups;

          // Filter to component match.
          if (!empty($components)) {
            $new_backups = [];
            foreach ($backups as $backup) {
              $shared_components = array_intersect($components, $backup['componentList']);
              if (count($shared_components) !== count($components)) {
                continue;
              }
              $new_backups[] = $backup;
            }
            $backups = $new_backups;
          }

          // Filter to latest.
          if (count($backups)) {
            $latest_backup = NULL;
            foreach ($backups as $backup) {
              if (empty($latest_backup) || $latest_backup['timestamp'] < $backup['timestamp']) {
                $latest_backup = $backup;
              }
            }
            $backups = [$latest_backup];
          }

          $return_backups[$sync_map['site_id']] = $backups;
        }
        else {
          $this->logger->error("Failed to get backup list for site ID: {$sync_map['site_id']}");
          // Let the return code equal the number of failed sites.
          $return_backups = [];
        }
      }
    } catch (\Exception $e) {
      $this->logger->error("Failed to list backups, reason: {$e->getMessage()}");
    }

    return $return_backups;
  }

  protected function bulkSiteDataRestore($backups, $components = []) {
    try {
      $return_code = 0;

      $task_ids = [];
      foreach ($backups as $site_id => $backup) {
        $resource_uri = "sites/{$site_id}/restore";
        $headers = [
          'Content-Type: application/json',
        ];
        $params = [
          'target_site_id' => $site_id,
        ];
        if (!empty($components)) {
          $params['components'] = $components;
        }
        if (!empty($backup_id)) {
          $params['backup_id'] = $backup_id;
        }

        $body = json_encode($params);
        $response = $this->client->makeV1Request('POST', $resource_uri, $headers, $body);

        if (!empty($response)) {
          $this->say("Restore started for site ID {$site_id}. Task ID: {$response['task_id']}.");
          $task_ids[] = $response['task_id'];
        }
        else {
          $this->logger->error("Failed to restore site ID: {$site_id}");
          // Let the return code equal the number of failed sites.
          $return_code++;
        }
      }

      // Since this step may take 30 mins or more, we do 200 iterations at 15
      // second intervals, which should give about 50 mins of wait time.
      $response = $this->client->waitForTasksAndReturn($task_ids, 15, 200);
      if (!$response) {
        throw new \Exception("Not all tasks completed successfully.");
      }

      return $return_code;
    } catch (\Exception $e) {
      $this->logger->error("Failed to restore sites, reason: {$e->getMessage()}");
      return 1;
    }
  }

  protected function getDeployedGitref() {
    $response = $this->client->makeV1Request('GET', 'vcs?type=sites');
    if (empty($response['current'])) {
      throw new \Exception("Could not get current deployed tag.");
    }
    return $response['current'];
  }

}
