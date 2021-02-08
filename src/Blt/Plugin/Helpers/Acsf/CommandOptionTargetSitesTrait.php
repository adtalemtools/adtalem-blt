<?php

namespace Adtalem\Blt\Plugin\Helpers\Acsf;

use Consolidation\SiteAlias\SiteAliasManager;
use Drush\SiteAlias\SiteAliasFileLoader;
use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Helper methods to allow target site inputs and derive the list.
 */
trait CommandOptionTargetSitesTrait {

  /**
   * The site IDs selected.
   *
   * @var array
   */
  protected $selectedSiteIds;

  /**
   * The target env selected.
   *
   * @var string
   */
  protected $targetEnv;

  /**
   * Add options for selecting sites.
   *
   * @hook option
   */
  public function addSitesCommandOptions(Command $command, AnnotationData $annotationData) {
    $command_definition = $command->getDefinition();

    $command_definition->addOption(
      new InputOption('--target-env', '', InputOption::VALUE_REQUIRED, 'The environment to perform the operation from.')
    );

    $command_definition->addOption(
      new InputOption('--normalized-sitename', '', InputOption::VALUE_OPTIONAL, 'The normalized site name to perform the command on.', '')
    );

    $command_definition->addOption(
      new InputOption('--site-ids', '', InputOption::VALUE_OPTIONAL, 'Command delimited list of site IDs to execute this command against.', '')
    );
  }

  /**
   * Process options for selecting sites.
   *
   * @hook pre-validate
   */
  public function processSitesCommandOptions(CommandData $commandData) {
    $input = $commandData->input();
    $this->selectedSiteIds = $this->processSelectedSiteIds($input->getOptions());
    $this->targetEnv = $input->getOption('target-env');

    if (empty($this->targetEnv)) {
      throw new \InvalidArgumentException("The target-env option is required.");
    }
  }

  /**
   * Get the selected sites, including meta info about each site.
   *
   * @return array
   */
  protected function getSelectedSites() {
    return $this->getSyncMaps($this->targetEnv, $this->selectedSiteIds);
  }

  /**
   * Confirm the user will continue with the operation on the selected sites.
   *
   * @param $sync_maps
   * @param $operation_names
   * @param string $type
   *
   * @return int
   */
  protected function confirmSelection($selected_sites, $operation_names, $type = 'remote-to-local') {
    return $this->promptSyncMaps($selected_sites, $operation_names, $type);
  }

  /**
   * Process the input options to get the selected site IDs.
   *
   * @param string $options
   *   The command input options.
   *
   * @return array
   *   An array of integers, each one is a site ID.
   */
  protected function processSelectedSiteIds($options) {
    if (!empty($options['normalized-sitename']) && !empty($options['site-ids'])) {
      throw new \Exception('You cannot use both --normalized-sitename and --site-ids');
    }

    $multisites = $this->getMultisiteConfig();

    $site_ids = [];
    if (!empty($options['normalized-sitename'])) {
      if (!isset($multisites[$options['normalized-sitename']])) {
        throw new \Exception('Could not find normalized site name for ' . $options['normalized-sitename']);
      }
      $site_ids[] = $multisites[$options['normalized-sitename']]['site_id'];
    }
    elseif (!empty($options['site-ids'])) {
      $site_ids = explode(',', $options['site-ids']);
    }
    else {
      foreach ($multisites as $key => $multisite) {
        if (empty($multisite['site_id'])) {
          throw new \Exception('Site ID not set for ' . $key);
        }

        $site_ids[] = $multisite['site_id'];
      }
    }

    return $site_ids;
  }

  /**
   * Get the maps for syncing from remote to local.
   *
   * @param string $target_env
   *   The environment to run the command on, if an empty string use the
   *   default for the site in blt/blt.yml.
   *
   * @param array $selected_site_ids
   *   The sites to get a sync map for.
   *
   * @return array
   *   An array of arrays, each nested array has:
   *     - remote_alias
   *     - remote_is_default
   *     - remote_url
   *     - local_url
   *     - site_dir
   *     - normalized_sitename
   *     - site_id
   *     - env
   *
   * @throws \Exception
   */
  protected function getSyncMaps($target_env, $selected_site_ids) {
    // Get the multisites from blt/blt.yml.
    $multisites = $this->getMultisiteConfig();
    $sync_maps = [];

    // Setup the drush alias manager.
    $alias_file_loader = new SiteAliasFileLoader();
    $paths = [$this->getConfigValue('drush.alias-dir')];
    $alias_manager = new SiteAliasManager($alias_file_loader);
    $alias_manager->addSearchLocations($paths);

    // Build the sync maps.
    $found_site_ids = [];
    foreach ($multisites as $key => $multisite) {
      if (!in_array($multisite['site_id'], $selected_site_ids)) {
        continue;
      }

      $input_remote_alias_parts = explode('.', $multisite['remote']);

      if (2 !== count($input_remote_alias_parts)) {
        throw new \Exception('The remote alias should be in the format: mysite.envname, e.g. mysite.01live');
      }

      // Determine the environment.
      if (empty($target_env)) {
        $target_env = $input_remote_alias_parts[1];
      }

      // Determine if the environment is default.
      $remote_is_default = ($input_remote_alias_parts[1] == $target_env) ? TRUE : FALSE;

      // Build the parts of the alias:
      //   0 - Alias name, e.g. "mysite"
      //   1 - Period "." which separates site name and env
      //   2 - Environment name, e.g. "01live"
      $remote_alias_parts = [
        $input_remote_alias_parts[0],
        '.',
        $target_env,
      ];

      // Form the remote alias.
      $remote_alias = implode($remote_alias_parts);

      // Validate the alias exists.
      $source_record = $alias_manager->get('@' . $remote_alias);
      if (!$source_record) {
        throw new \Exception("Could not find alias, it's possible the alias exists but under a different drush alias file than the default. Please ensure that if the default environment has a custom domain set that all environments have one set due to how ACSF handles drush aliases.");
      }

      // Setup the remote URL, always assuming HTTPS.
      $remote_url = 'https://' . $source_record->get('uri');

      $sync_maps[] = [
        'remote_alias' => $remote_alias,
        'remote_is_default' => $remote_is_default,
        'remote_url' => $remote_url,
        'local_url' => $multisite['local'],
        'site_dir' => $multisite['site_dir'],
        'normalized_sitename' => $key,
        'site_id' => $multisite['site_id'],
        'env' => $target_env,
      ];
      $found_site_ids[] = $multisite['site_id'];
    }

    // Validate we found all the sync maps.
    if (count($found_site_ids) != count($selected_site_ids)) {
      $not_found_site_ids = [];
      foreach ($selected_site_ids as $site_id) {
        if (!in_array($site_id, $found_site_ids)) {
          $not_found_site_ids[] = $site_id;
        }
      }
      if (!empty($not_found_site_ids)) {
        throw new \Exception('Could not find sync map for site IDs: ' . implode(', ', $not_found_site_ids));
      }
    }

    return $sync_maps;
  }

  /**
   * Print the sync maps and prompt the user to continue with all sites.
   *
   * @param array $sync_maps
   *   The array of sync maps.
   * @param array $operation_names
   *   The name of operations to perform.
   * @param string $type
   *   The type of operation: remote-to-local, remote-only, or local-only.
   *
   * @return int
   *   If falsy, do not continue. Otherwise, continue.
   *
   * @throws \Exception
   */
  protected function promptSyncMaps($sync_maps, $operation_names, $type = 'remote-to-local') {
    if (empty($operation_names)) {
      throw new \Exception('You have not selected any operations to perform!');
    }

    $this->say("Operations be performed:");
    foreach ($operation_names as $operation_name) {
      $this->say("  * <info>{$operation_name}</info>");
    }

    $this->say("Sites to perform the operations on:");

    if ($type == 'remote-to-local') {
      foreach ($sync_maps as $sync_map) {
        if ($sync_map['remote_is_default']) {
          $this->say("  * <comment>{$sync_map['remote_alias']}</comment> => <comment>{$sync_map['local_url']}</comment>");
        }
        else {
          $this->say("  * <comment>{$sync_map['remote_alias']}</comment> => <comment>{$sync_map['local_url']}</comment> | <bg=yellow;options=bold>[warning]</> <options=bold>Operating from non-default environment.</>");
        }
      }
    }
    elseif ($type == 'local-to-remote') {
      foreach ($sync_maps as $sync_map) {
        if ($sync_map['remote_is_default']) {
          $this->say("  * <comment>{$sync_map['local_url']}</comment> => <comment>{$sync_map['remote_alias']}</comment> | <bg=yellow;options=bold>[warning]</> <options=bold>Operating on default environment.</");
        }
        else {
          $this->say("  * <comment>{$sync_map['local_url']}</comment> => <comment>{$sync_map['remote_alias']}</comment>>");
        }
      }
    }
    elseif ($type == 'remote-only') {
      foreach ($sync_maps as $sync_map) {
        $this->say("  * <comment>{$sync_map['remote_alias']}</comment>");
      }
    }
    elseif ($type == 'local-only') {
      foreach ($sync_maps as $sync_map) {
        $this->say("  * <comment>{$sync_map['local_url']}</comment>");
      }
    }
    else {
      throw new \Exception('Incorrect type in promptSyncMaps: ' . $type);
    }

    $this->say("To modify the set of aliases for syncing, set the values for multisites in blt/blt.yml");

    return $this->confirm("Continue?", TRUE);
  }

  /**
   * Print the sync maps and prompt the user to continue with an operation for
   * the environment.
   *
   * @param string $target_env
   *   The environment to run the oeprations on.
   * @param array $operation_names
   *   The name of operations to perform.
   *
   * @return int
   *   If falsy, do not continue. Otherwise, continue.
   *
   * @throws \Exception
   */
  protected function promptEnvironment($target_env, $operation_names) {
    if (empty($operation_names)) {
      throw new \Exception('You have not selected any operations to perform!');
    }

    $this->say("Sync operations be performed:");
    foreach ($operation_names as $operation_name) {
      $this->say("  * <info>{$operation_name}</info>");
    }

    $this->say("Environment to perform the operations on:");
    $this->say("  * <comment>{$target_env}</comment>");

    return $this->confirm("Continue?", TRUE);
  }

  protected function getMultisiteConfig() {
    return $this->getConfigValue('adtalem_multisites');
  }

}
