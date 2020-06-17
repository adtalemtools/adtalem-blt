<?php

namespace Adtalem\Blt\Plugin\Helpers\Acsf;

use Consolidation\SiteAlias\SiteAliasManager;
use Drush\SiteAlias\SiteAliasFileLoader;
use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Helper methods to allow target environment inputs.
 */
trait CommandOptionTargetEnvironmentTrait {

  /**
   * The target env selected.
   *
   * @var string
   */
  protected $targetEnv;

  /**
   * Add options for selecting sites.
   *
   * @hook option
   */
  public function addSitesCommandOptions(Command $command, AnnotationData $annotationData) {
    $command_definition = $command->getDefinition();

    $command_definition->addOption(
      new InputOption('--target-env', '', InputOption::VALUE_REQUIRED, 'The environment to perform the operation from.')
    );
  }

  /**
   * Process options for selecting sites.
   *
   * @hook pre-validate
   */
  public function processSitesCommandOptions(CommandData $commandData) {
    $input = $commandData->input();
    $this->targetEnv = $input->getOption('target-env');

    if (empty($this->targetEnv)) {
      throw new \InvalidArgumentException("The target-env option is required.");
    }
  }

  /**
   * Confirm the user will continue with the operation on the selected environment.
   *
   * @param $environment
   * @param $operation_names
   * @param string $type
   *
   * @return int
   */
  protected function confirmSelection($target_env, $operation_names) {
    return $this->promptEnvironment($target_env, $operation_names);
  }

  /**
   * Print the sync maps and prompt the user to continue with an operation for the environment.
   *
   * @param string $target_env
   *   The environment to run the oeprations on.
   * @param array $operation_names
   *   The name of operations to perform.
   *
   * @return int
   *   If falsy, do not continue. Otherwise, continue.
   *
   * @throws \Exception
   */
  protected function promptEnvironment($target_env, $operation_names) {
    if (empty($operation_names)) {
      throw new \Exception('You have not selected any operations to perform!');
    }

    $this->say("Operations to be performed:");
    foreach ($operation_names as $operation_name) {
      $this->say("  * <info>{$operation_name}</info>");
    }

    $this->say("Environment to perform the operations on:");
    $this->say("  * <comment>{$target_env}</comment>");

    return $this->confirm("Continue?", TRUE);
  }

}
