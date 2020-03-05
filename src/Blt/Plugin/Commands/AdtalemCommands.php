<?php

namespace Adtalemtools\AdtalemBlt\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Acquia\Blt\Robo\Commands\Sync as Sync;
use Acquia\Blt\Robo\Exceptions\BltException;
use Acquia\Blt\Robo\Config\ConfigInit;
use Acquia\Blt\Custom\Helpers\Acsf\Ac\AcsfAcApiClient;
use Acquia\Blt\Custom\Helpers\Acsf\CommandOptionTargetSitesTrait;
use Acquia\Blt\Custom\Helpers\Acsf\LocalBackupStorage;
use Consolidation\AnnotatedCommand\AnnotationData;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Defines commands in the "adtalem" namespace.
 */
class AdtalemCommands extends BltTasks {

  /**
   * Print "Hello world!" to the console.
   *
   * @command adtalem:hello
   * @description This is an example command.
   */
  public function hello() {
    $this->say("Hello world!");
  }

  /**
   * This will be called before the `adtalem:hello` command is executed.
   *
   * @hook command-event adtalem:hello
   */
  public function preExampleHello(ConsoleCommandEvent $event) {
    $command = $event->getCommand();
    $this->say("preCommandMessage hook: The {$command->getName()} command is about to run!");
  }

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
   * This will be called before the `adtalem:sync:all-sites` command is executed.
   *
   * @hook command-event adtalem:sync:all-sites
   */
  public function preExampleAllSites(ConsoleCommandEvent $event) {
    $command = $event->getCommand();
    $this->say("preCommandMessage hook: The {$command->getName()} command isnt about to run!");
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
   * This will be called before the `adtalem:sync:site` command is executed.
   *
   * @hook command-event adtalem:sync:site
   */
  public function preExampleSync(ConsoleCommandEvent $event) {
    $command = $event->getCommand();
    $this->say("preCommandMessage hook: The {$command->getName()} command isnt about to run!");
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
   * This will be called before the `adtalem:sync:truncate` command is executed.
   *
   * @hook command-event adtalem:sync:truncate
   */
  public function preExampleTruncate(ConsoleCommandEvent $event) {
    $command = $event->getCommand();
    $this->say("preCommandMessage hook: The {$command->getName()} command isnt about to run!");
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


}
