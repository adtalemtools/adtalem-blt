<?php

namespace Acquia\Blt\Custom\Helpers;

/**
 * A class to isolate the commit message validation logic.
 *
 * Doing this allows for writing a simple unit test for the pattern.
 */
class CommitMessageChecker {

  /**
   * Check if the message is valid.
   *
   * @param string $pattern
   *   A regex string.
   * @param string $message
   *   A git commit subject line.
   *
   * @return bool
   *   If the message is invalid return false, else true.
   */
  public function isValid($pattern, $message) {
    if (!preg_match($pattern, $message)) {
      return false;
    }
    return true;
  }

}

