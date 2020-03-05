<?php

namespace Acquia\Blt\Custom\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Exceptions\BltException;
use Robo\Contract\VerbosityThresholdInterface;

/**
 * Defines commands in the "adtalem:*" namespace.
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
        $this->getConfigValue('repo.root') . '/blt/src/Helpers/Acsf/Ac/AcsfAcApiClient.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AcsfApiClient.php',
        $this->getConfigValue('repo.root') . '/blt/src/Helpers/Acsf/AcsfApiClient.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AcWrapperClient.php',
        $this->getConfigValue('repo.root') . '/blt/src/Helpers/Acsf/Ac/AcWrapperClient.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemAliasesCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Commands/AdtalemAliasesCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Commands/AdtalemCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemDbCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Commands/AdtalemDbCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemGitCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Commands/AdtalemGitCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemLocalDataCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Commands/AdtalemLocalDataCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemRefreshCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Commands/AdtalemRefreshCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemSiteDataCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Commands/AdtalemSiteDataCommands.php', FALSE)
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/adtalemtools/adtalem-blt/scripts/AdtalemTestCommands.php',
        $this->getConfigValue('repo.root') . '/blt/src/Commands/AdtalemTestCommands.php', FALSE)
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
