<?php

namespace Adtalem\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\EnvironmentDetector;
use Acquia\Blt\Robo\Exceptions\BltException;

/**
 * Defines commands in the "adtalem:db-scrub" namespace.
 */
class AdtalemDbScrubCommand extends BltTasks {

  /**
   * Scrub the database with options to preserve users.
   *
   * This is intended to be called from db-scrub.sh cloud hook.
   *
   * @see \Acquia\Blt\Robo\Commands\Artifact\AcHooksCommand
   *
   * @command adtalem:db-scrub
   * @hook replace-command artifact:ac-hooks:db-scrub
   *
   * @param string $site
   *   The site name, e.g., site1.
   * @param string $target_env
   *   The cloud env, e.g., dev.
   * @param string $db_name
   *   The name of the database.
   * @param string $source_env
   *   The source environment.
   *
   * @return int
   *   Returns 0 on success and 1 on failure.
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  public function dbScrub($site, $target_env, $db_name, $source_env) {
    if (!EnvironmentDetector::isAcsfEnv($site, $target_env)) {
      $this->say("BEGIN CUSTOM ADTALEM SCRUB COMMAND");
      $this->say("Scrubbing database in $target_env");
      $this->say("IMPORTANT! Passwords are not scrubbed!");
      $result = $this->taskDrush()
        ->drush("sql-sanitize --sanitize-password=no --yes")
        ->run();
      if (!$result->wasSuccessful()) {
        throw new BltException("Failed to sanitize database!");
      }
      $this->taskDrush()
        ->drush("cr")
        ->run();
    }
  }

}

