<?php

namespace Acquia\Blt\Custom\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Commands\Sync as Sync;
use Acquia\Blt\Robo\Exceptions\BltException;
use Acquia\Blt\Robo\Config\ConfigInitializer;

/**
 * Defines commands in the "sync" namespace.
 */
class AdtalemCommand extends BltTasks{

  /**
   * Synchronize each multisite.
   *
   * @command adtalem:sync:all-sites
   * @aliases adtalem:sync:all
   * @executeInVm
   */
  public function allSites() {
    $multisites = $this->getConfigValue('adtalem_multisites');
    $this->printSyncMap($multisites);
    $continue = $this->confirm("Continue?");
    if (!$continue) {
      return 0;
    }
    foreach ($multisites as $key => $multisite) {
      $this->getConfig()->set('drush.uri', $multisite['local']);
      $this->say("Refreshing site <comment>$key</comment>...");
      $this->syncDbSite($multisite);
    }
  }

  /**
   * Synchronize an individual multisite.
   *
   * @command adtalem:sync:site
   * @aliases adtalem:sync
   * @executeInVm
   */
  public function sync($options = [
    'sync-files' => FALSE
  ]) {
    $multisite = $this->promptSite();
    $this->syncDbSite($multisite);

  }

  /**
   * Synchronize an individual multisite by environment.
   *
   * @command adtalem:sync:site:env
   * @aliases adtalem:env
   * @executeInVm
   */
  public function syncEnv($options = [
    'sync-files' => FALSE,
    'normalized-envname' => '',
    'normalized-sitename' => '',
  ]) {

    if (empty($options['normalized-sitename'])) {
      $multisite_config = $this->promptSite();
    } else {
      $multisite_config = $this->promptSite($options['normalized-sitename']);
    }

    if (empty($options['normalized-envname'])) {
      $env_config = $this->promptEnv();
    } else {
      $env_config = $this->promptEnv($options['normalized-envname']);
    }

    $result = $this->syncDbSiteEnv($multisite_config, $env_config);
    return $result;
  }

  /**
   * Truncate database for multisite.
   *
   * @command adtalem:sync:truncate
   * @aliases adtalem:truncate
   * @executeInVm
   */
  public function truncate() {
    $multisite = $this->promptSite();

    $task = $this->taskDrush()
      ->alias('')
      ->drush('cache-clear drush')
      ->uri($multisite['local'])
      ->drush('sql-drop');

  }
    /**
   * @param $multisites
   *
   * @return mixed
   */
  protected function printSyncMap($multisites) {
    $this->say("Sync operations be performed for the following drush aliases:");
    foreach ($multisites as $key => $multisite) {
      $this->say("  * <comment>" . $key . "</comment> | <comment>" . $multisite['remote'] . "</comment> => <comment>" . $multisite['local'] . "</comment>");
    }
    $this->say("To modify the set of aliases for syncing, set the values for multisites in blt/blt.yml");
  }

  /**
   * @param $multisites
   * @param $env
   *
   * @return mixed
   */
  protected function printSyncMapEnv($multisites, $env) {
    $this->say("Sync operations be performed for the following drush aliases:");
    foreach ($multisites as $key => $multisite) {
      $remote_alias = $multisite['remote'];
      $stub_alias = substr($remote_alias, 0, -4);
      $env_name = $env['envname'];
      $env_alias = '@' . $stub_alias.$env_name;
      $this->say("  * <comment>" . $key . "</comment> | <comment>" . $env_alias . "</comment> => <comment>" . $multisite['local'] . "</comment>");
    }
    $this->say("To modify the set of aliases for syncing, set the values for multisites in blt/blt.yml");
  }

  /**
   * Synchronize files for an individual multisite.
   *
   * @command adtalem:sync:files
   * @executeInVm
   */
  public function syncFiles() {
    $multisite = $this->promptSite();

    $remote_alias = '@' . $multisite['remote'];
    $site_dir = $multisite['site_dir'];

    if(empty($site_dir)){
      throw new Exception('Site needs a site_dir property to sync files.');
    }

    $dest_dir = $this->getConfigValue('docroot') . "/sites/g/files/" . $site_dir .'/files';
    $this->_mkdir($dest_dir);
    $task = $this->taskDrush()
      ->alias('')
      ->uri('')
      ->drush('rsync')
      ->arg($remote_alias . ':%files/')
      ->arg($dest_dir)
      ->verbose(TRUE)
      ->option('exclude-paths', implode(':', $this->getConfigValue('sync.exclude-paths')));
    $result = $task->run();
    if (!$result->wasSuccessful()) {
        return $result;
    }

    $dest_dir = $this->getConfigValue('docroot') . "/../files-private/" . $site_dir;
    $this->_mkdir($dest_dir);
    $task = $this->taskDrush()
      ->alias('')
      ->uri('')
      ->drush('rsync')
      ->arg($remote_alias . ':%private/')
      ->arg($dest_dir)
      ->verbose(TRUE)
      ->option('exclude-paths', implode(':', $this->getConfigValue('sync.exclude-paths')));
    $result = $task->run();

    return $result;
  }

  /**
   * Synchronize files for all sites.
   *
   * @command adtalem:sync:all-files
   * @executeInVm
   */
  public function syncAllFiles() {
    $return_result = 0;
    $multisites = $this->getConfigValue('adtalem_multisites');
    $this->printSyncMap($multisites);
    $continue = $this->confirm("Continue?");
    if (!$continue) {
      return $return_result;
    }
    foreach ($multisites as $key => $multisite) {
      $remote_alias = '@' . $multisite['remote'];
      $site_dir = $multisite['site_dir'];
      if(empty($site_dir)){
        throw new Exception('Site needs a site_dir property to sync files.');
      }

      $dest_dir = $this->getConfigValue('docroot') . "/sites/g/files/" . $site_dir .'/files';
      $this->_mkdir($dest_dir);
      $task = $this->taskDrush()
        ->alias('')
        ->uri('')
        ->drush('rsync')
        ->arg($remote_alias . ':%files/')
        ->arg($dest_dir)
        ->verbose(TRUE)
        ->option('exclude-paths', implode(':', $this->getConfigValue('sync.exclude-paths')));
      $result = $task->run();
      if (!$result->wasSuccessful()) {
        $return_result = $result;
      }

      $dest_dir = $this->getConfigValue('docroot') . "/../files-private/" . $site_dir;
      $this->_mkdir($dest_dir);
      $task = $this->taskDrush()
        ->alias('')
        ->uri('')
        ->drush('rsync')
        ->arg($remote_alias . ':%private/')
        ->arg($dest_dir)
        ->verbose(TRUE)
        ->option('exclude-paths', implode(':', $this->getConfigValue('sync.exclude-paths')));
      $result = $task->run();
      if (!$result->wasSuccessful()) {
        $return_result = $result;
      }
    }
    return $return_result;
  }

  /**
   * Syncs single db for a site
   */
  public function syncDbSite($multisite) {
    $local_alias = '@self';
    $local_url =  $multisite['local'];
    $remote_alias = '@' . $multisite['remote'];
    $i = rand();

    $task = $this->taskDrush()
      ->alias('')
      ->drush('cache-clear drush')
      ->drush('sql-drop')
      ->drush('sql-sync')
      ->arg($remote_alias)
      ->arg($local_alias)
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

    print "Synced site: http://" . $multisite['local'] . "\r\n";

    return $result;
  }

  /**
   * Syncs single db for a site by environment
   *
   * @param array
   *   The BLT config for the chosen site.
   */
  public function syncDbSiteEnv($multisite, $env) {
    $local_alias = '@self';
    $local_url =  $multisite['local'];
    $remote_alias = $multisite['remote'];
    $stub_alias = substr($remote_alias, 0, -4);
    $env_name = $env['envname'];
    $env_alias = '@' . $stub_alias.$env_name;
    $i = rand();

    $task = $this->taskDrush()
      ->alias('')
      ->drush('cache-clear drush')
      ->drush('sql-drop')
      ->drush('sql-sync')
      ->arg($env_alias)
      ->arg($local_alias)
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
    if ($result->wasSuccessful()) {
      print "Synced site: https://" . $multisite['local'] . "\r\n";
    }

    return $result;
  }

  /**
   * Update current database to reflect the state of the Drupal file system.
   *
   * @command adtalem:drupal:update
   * @executeInVm
   */
  public function update() {
    $multisite = $this->promptSite();
    $this->invokeCommands(['drupal:config:import', 'drupal:toggle:modules']);
    print "Updated site: http://" . $multisite['local'] . "\r\n";
  }

  /**
   * Helper function for choosing site to sync.
   * @return array with site that was chosen.
   */
  private function promptSite(){
    $multisites = $this->getConfigValue('adtalem_multisites');
    $site_list = array_keys($multisites);
    $site = $this->askChoice('What site would you like to use?', $site_list);
    $multisite = $multisites[$site];
    $this->getConfig()->set('drush.uri', $multisite['local']);
    return $multisite;
  }

  /**
   * Helper function for choosing environment to sync.
   *
   * @param string $normalized_envname
   *   The normalized envname in the BLT config, if already known.
   *
   * @return array
   *   The BLT site configuration for the chosen environment.
   *
   * @throws \Exception
   */
  protected function promptEnv($normalized_envname = '') {
    $envs = $this->getConfigValue('adtalem_envs');
    $env_list = array_keys($envs);
    if (empty($normalized_envname)) {
      $normalized_envname = $this->askChoice('What environment would you like to use?', $env_list);
    }
    if (!isset($envs[$normalized_envname])) {
      throw new \Exception("Could not find site configuration for the selected environment!");
    }
    $env = $envs[$normalized_envname];
    return $env;
  }


}
