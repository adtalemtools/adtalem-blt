<?php

namespace Adtalemtools\AdtalemBlt\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Exceptions\BltException;
use Robo\Contract\VerbosityThresholdInterface;

/**
 * Defines commands related to Tugboat.
 */
class TugboatCommands extends BltTasks {

  /**
   * Initializes default Tugboat configuration for this project.
   *
   * @command recipes:adtalem:init
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  public function tugboatInit() {
    $result = $this->taskFilesystemStack()
      ->copy($this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/Makefile', $this->getConfigValue('repo.root') . '/Makefile', TRUE)
      ->copy($this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/adtalem.drushrc.aliases.php', $this->getConfigValue('repo.root') . '/drush/sites/adtalem.drushrc.aliases.php')
      ->stopOnFail()
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new BltException("Could not initialize Adtalem configuration.");
    }

    $this->say("<info>A pre-configured Tugboat Makefile was copied to your repository root.</info>");
  }

}
