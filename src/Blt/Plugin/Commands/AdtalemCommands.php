<?php

namespace Adtalemtools\AdtalemBlt\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Exceptions\BltException;
use Robo\Contract\VerbosityThresholdInterface;

/**
 * Defines commands in the "sync" namespace.
 */
class AdtalemCommands extends BltTasks {

  /**
   * Initializes Acquia BLT commands for the Adtalem sites.
   *
   * @command recipes:adtalem:init
   * @aliases rai adtalem:init
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  public function adtalemInit() {
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
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemAliasesCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemAliasesCommand.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemSyncCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemSyncCommand.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemDbCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemDbCommand.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemGitCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemGitCommand.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemLocalDataCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemLocalDataCommand.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemRefreshCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemRefreshCommand.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemSiteDataCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemSiteDataCommand.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemTestCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Blt/Plugin/Commands/AdtalemTestCommand.php', FALSE)
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
      throw new BltException("Could not copy Adtalem files into the repository root.");
    }

    $this->say("<info>Adtalem commands and hooks were copied to your repository root.</info>");
  }
}
