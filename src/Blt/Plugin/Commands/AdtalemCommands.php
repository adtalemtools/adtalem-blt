<?php

namespace Adtalemtools\AdtalemBlt\Custom\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Commands\Sync as Sync;
use Acquia\Blt\Robo\Exceptions\BltException;
use Acquia\Blt\Robo\Config\ConfigInitializer;
use Robo\Contract\VerbosityThresholdInterface;

/**
 * Defines commands in the "sync" namespace.
 */
class AdtalemCommands extends BltTasks{

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
        $this->getConfigValue('repo.root') . '/blt/src/Helpers/Acsf/Ac/AcsfAcApiClient.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AcsfApiClient.php',
        $this->getConfigValue('repo.root') . '/blt/src/Helpers/Acsf/AcsfApiClient.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AcWrapperClient.php',
        $this->getConfigValue('repo.root') . '/blt/src/Helpers/Acsf/Ac/AcWrapperClient.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemAliasesCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Commands/AdtalemAliasesCommand.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/src/Blt/Plugin/Commands/AdtalemSyncCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Commands/AdtalemSyncCommand.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemDbCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Commands/AdtalemDbCommand.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemGitCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Commands/AdtalemGitCommand.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemLocalDataCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Commands/AdtalemLocalDataCommand.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemRefreshCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Commands/AdtalemRefreshCommand.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemSiteDataCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Commands/AdtalemSiteDataCommand.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemTestCommand.php',
        $this->getConfigValue('repo.root') . '/blt/src/Commands/AdtalemTestCommand.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/CommandOptionSourceSitesTrait.php',
        $this->getConfigValue('repo.root') . '/blt/src/Helpers/Acsf/CommandOptionSourceSitesTrait.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/CommandOptionTargetSitesTrait.php',
        $this->getConfigValue('repo.root') . '/blt/src/Helpers/Acsf/CommandOptionTargetSitesTrait.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/CommitMessageChecker.php',
        $this->getConfigValue('repo.root') . '/blt/src/Helpers/CommitMessageChecker.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/CommitMessageCheckerTest.php',
        $this->getConfigValue('repo.root') . '/blt/src/Tests/Unit/Helpers/CommitMessageCheckerTest.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/Filesets.php',
        $this->getConfigValue('repo.root') . '/blt/src/Filesets.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/LocalBackupStorage.php',
        $this->getConfigValue('repo.root') . '/blt/src/Helpers/Acsf/LocalBackupStorage.php', FALSE)
      ->stopOnFail()
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new BltException("Could not copy Adtalem files into the repository root.");
    }

    $this->say("<info>Adtalem commands and hooks were copied to your repository root.</info>");
  }
}
