<?php

namespace Adtalemtools\AdtalemBlt\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Exceptions\BltException;
use Robo\Contract\VerbosityThresholdInterface;

/**
 * Defines commands in the "examples:*" namespace.
 */
class AdtalemCommand extends BltTasks {

  /**
   * Generates example files for writing adtalem commands and hooks.
   *
   * @command recipes:adtalem:init
   *
   * @aliases rai adtalem:init
   */
  public function init() {
    $result = $this->taskFilesystemStack()
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/scripts/blt/examples/Commands/AdtalemAliasesCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemAliasesCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemCommand.php', FALSE)
      ->copy(
        $this->getConfigValue('blt.root') . '/scripts/blt/examples/Filesets/AdtalemFilesets.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Filesets/AdtalemFilesets.php', FALSE)
      ->stopOnFail()
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new BltException("Could not copy example files into the repository root.");
    }

    $this->say("<info>Example commands and hooks were copied to your repository root.</info>");
  }

}
