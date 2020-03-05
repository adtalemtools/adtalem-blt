<?php

namespace Adtalemtools\AdtalemBlt\Blt\Plugin\Hooks;

use Acquia\Blt\Robo\BltTasks;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

/**
 * This class defines example hooks.
 */
class AdtalemHooks extends BltTasks {

  /**
   * This will be called before the `recipes:adtalem:init` command is executed.
   *
   * @hook command-event recipes:adtalem:init
   */
  public function preExampleInit(ConsoleCommandEvent $event) {
    $command = $event->getCommand();
    $this->say("preCommandMessage hook: The {$command->getName()} command is about to run!");
  }

  /**
   * This will be called before the `adtalem:sync:all-sites` command is executed.
   *
   * @hook command-event adtalem:sync:all-sites
   */
  public function preExampleAllSites(ConsoleCommandEvent $event) {
    $command = $event->getCommand();
    $this->say("preCommandMessage hook: The {$command->getName()} command is about to run!");
  }

  /**
   * This will be called before the `adtalem:sync:site` command is executed.
   *
   * @hook command-event adtalem:sync:site
   */
  public function preExampleSync(ConsoleCommandEvent $event) {
    $command = $event->getCommand();
    $this->say("preCommandMessage hook: The {$command->getName()} command is about to run!");
  }

  /**
   * This will be called before the `adtalem:sync:truncate` command is executed.
   *
   * @hook command-event adtalem:sync:truncate
   */
  public function preExampleTruncate(ConsoleCommandEvent $event) {
    $command = $event->getCommand();
    $this->say("preCommandMessage hook: The {$command->getName()} command is about to run!");
  }

  /**
   * This will be called before the `adtalem:aliases:generate` command is executed.
   *
   * @hook command-event adtalem:aliases:generate
   */
  public function preExampleGenerate(ConsoleCommandEvent $event) {
    $command = $event->getCommand();
    $this->say("preCommandMessage hook: The {$command->getName()} command is about to run!");
  }

}
