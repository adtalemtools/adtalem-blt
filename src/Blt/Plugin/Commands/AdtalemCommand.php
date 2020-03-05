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
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AcsfAcApiClient.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Helpers/Acsf/Ac/AcsfAcApiClient.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AcsfApiClient.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Helpers/Acsf/AcsfApiClient.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AcWrapperClient.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Helpers/Acsf/Ac/AcWrapperClient.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemAliasesCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemAliasesCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemDbCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemDbCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemGitCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemGitCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemHooks.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Hooks/AdtalemHooks.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemLocalDataCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemLocalDataCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemRefreshCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemRefreshCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemSiteDataCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemSiteDataCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemTestCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemTestCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/CommandOptionSourceSitesTrait.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Helpers/Acsf/CommandOptionSourceSitesTrait.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/CommandOptionTargetSitesTrait.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Helpers/Acsf/CommandOptionTargetSitesTrait.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/CommitMessageChecker.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Helpers/CommitMessageChecker.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/CommitMessageCheckerTest.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Tests/Unit/Helpers/CommitMessageCheckerTest.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/Filesets.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Filesets/Filesets.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/LocalBackupStorage.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Helpers/Acsf/LocalBackupStorage.php', FALSE)
      ->stopOnFail()
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new BltException("Could not copy example files into the repository root.");
    }

    $this->say("<info>Example commands and hooks were copied to your repository root.</info>");
  }

}
