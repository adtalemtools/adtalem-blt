<?php

namespace Adtalemtools\AdtalemBlt\Blt\Plugin\Commands;

use Acquia\Blt\Robo\Commands\Tests\BehatCommand;
use Consolidation\SiteAlias\SiteAliasManager;
use Drush\SiteAlias\SiteAliasFileLoader;
use Robo\Task\Testing\Behat;

/**
 * Runs test suites for Adtalem sites.
 */
class AdtalemTestCommands extends BehatCommand {

  /**
   * The current BEHAT_PARAMS value to use.
   *
   * @var string
   */
  protected $currentBehatParams = '';

  /**
   * Executes all behat tests for each site.
   *
   * @param array $options
   *   The command options.
   *
   * @command adtalem:tests:behat:run
   * @description Executes all behat tests setting the correct base URL for the site. This optionally launch PhantomJS or Selenium prior to execution.
   * @usage
   *   Executes all configured tests.
   * @usage -D behat.paths=${PWD}/tests/behat/features/Examples.feature
   *   Executes scenarios in the Examples.feature file.
   * @usage -D behat.paths=${PWD}/tests/behat/features/Examples.feature:4
   *   Executes only the scenario on line 4 of Examples.feature.
   *
   * @aliases adtalem:tests:behat
   *
   * @interactGenerateSettingsFiles
   * @interactConfigureBehat
   * @interactInstallDrupal
   * @launchWebServer
   * @executeInVm
   *
   * @throws \Exception
   *   Only in extreme failure will it throw an exception.
   */
  public function behat($options = [
    'target-env' => '',
  ]) {
    // TODO: accept site name as a parameter.

    // Get the target environment.
    if (empty($options['target-env'])) {
      throw new \Exception('The target-env parameter must be set, e.g. 01dev, 01test, 01live.');
    }
    $target_env = $options['target-env'];
    if (!in_array($target_env, ['01dev', '01test', '01live'])) {
      throw new \Exception('The target-env parameter must be one of: 01dev, 01test, or 01live.');
    }

    // Get a list of all sites for the given environment.
    $maps = $this->getSiteMaps($target_env);

    $original_behat_tags = $this->getConfigValue('behat.tags');

    // For each site, run the automated tests.
    $result = 0;
    foreach ($maps as $map) {
      $key = $map['normalized_sitename'];
      $this->say("Running test suite for <comment>$key</comment>...");

      // Log config for debugging purposes.
      $this->logConfig($this->getConfigValue('behat'), 'behat');
      $this->logConfig($this->getInspector()->getLocalBehatConfig()->export());
      $this->createReportsDir();

      try {
        $this->launchWebDriver();

        // Determine the base URL.
        $base_url = $map['remote_url'];

        // Limit to tests tagged with the site. This lets us annotate each test
        // to be for a specific site or multiple sites, e.g. @adtalem or
        // @adtalem @chamberlain.
        $config = $this->getConfig();
        $behat_tags = empty($original_behat_tags) ? $key : $original_behat_tags . '&&' . $key;
        // Also run any tests that use the @all tag.
        $behat_tags = $behat_tags . ',all';
        $config->set('behat.tags', $behat_tags);

        // Dynamically set the base URL.
        $this->currentBehatParams = '{"extensions" : {"Behat\\\\MinkExtension" : {"base_url" : "' . $base_url . '"}}}';

        $current_result = $this->executeBehatTests();
        if (!$current_result) {
          $result = 1;
        }
        $this->killWebDriver();
      }
      catch (\Exception $e) {
        // Kill web driver a server to prevent Pipelines from hanging after fail.
        $this->killWebDriver();
        throw $e;
      }
    }

    // TODO: figure out how to get BLT to exit with the correct status.
    exit($result);
  }

  /**
   * To avoid the execution exiting on the first failure we capture exceptions and continue.
   *
   * @return bool
   *   If true, the command succeeded.
   */
  protected function executeBehatTests() {
    try {
      parent::executeBehatTests();
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * We need to set the BEHAT_PARAMS argument on every task run so that the base_url changes.
   *
   * @param null|string $pathToBehat
   *
   * @return \Robo\Task\Testing\Behat|\Robo\Collection\CollectionBuilder
   */
  protected function taskBehat($pathToBehat = null) {
    return $this->task(Behat::class, $pathToBehat)->env('BEHAT_PARAMS', $this->currentBehatParams);
  }

  /**
   * Get the site maps for the given environment and site list.
   *
   * @param string $target_env
   *   The environment to run the task against, if an empty string use the default for the site in blt/blt.yml.
   * @param array $selected_site_ids
   *   The sites to get a sync map for.
   *
   * @return array
   *   An array of arrays, each nested array has:
   *     - remote_alias
   *     - remote_is_default
   *     - local_url
   *     - site_dir
   *     - normalized_sitename
   *     - site_id
   *     - env
   *
   * @throws \Exception
   */
  protected function getSiteMaps($target_env = '', $selected_site_ids = []) {
    // Get the multisites from blt/blt.yml.
    $multisites = $this->getConfigValue('adtalem_multisites');
    $sync_maps = [];

    // Setup the drush alias manager.
    $alias_file_loader = new SiteAliasFileLoader();
    $paths = [$this->getConfigValue('drush.alias-dir')];
    $alias_manager = new SiteAliasManager($alias_file_loader);
    $alias_manager->addSearchLocations($paths);

    // Build the sync maps.
    $found_site_ids = [];
    foreach ($multisites as $key => $multisite) {
      // TODO: Support filtering by specific sites.
//      if (!in_array($multisite['site_id'], $selected_site_ids)) {
//        continue;
//      }

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
      //   0 - @
      //   1 - Alias name, e.g. "mysite"
      //   2 - Period "." which separates site name and env
      //   3 - Environment name, e.g. "01live"
      $remote_alias_parts = [
        '@',
        $input_remote_alias_parts[0],
        '.',
        $target_env,
      ];

      // Form the remote alias.
      $remote_alias = implode($remote_alias_parts);

      // Validate the alias exists.
      $source_record = $alias_manager->get($remote_alias);
      if (!$source_record) {
        // TODO: consider looking up in the sites.json for the environment what the alias is.
        throw new \Exception("Could not find alias, it's possible the alias exists but under a different drush alias file than the default. Please ensure that if the default environment has a custom domain set that all environments have  one set due to how ACSF handles drush aliases.");
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
//        'site_id' => $multisite['site_id'],
        'env' => $target_env,
      ];
//      $found_site_ids[] = $multisite['site_id'];
    }
    /* TODO: support filtering by a list of sites
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
    */

    return $sync_maps;
  }

}
