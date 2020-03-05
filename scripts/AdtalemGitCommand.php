<?php

namespace Adtalem\Blt\Plugin\Commands;

use Adtalem\Blt\Plugin\Helpers\CommitMessageChecker;
use Acquia\Blt\Robo\Commands\Git\GitCommand;

/**
 * Defines commands in the "adtalem:git:*" namespace.
 */
class AdtalemGitCommand extends GitCommand {

  /**
   * Validates a git commit message.
   *
   * @command adtalem:git-hook:execute:commit-msgs
   *
   * @param string $branch
   *   The branch you are checking the commit messages on.
   * @param string $branch_compare
   *   The branch you are comparing against to know which commits to check.
   *
   * @return int
   *   Returns 0 on success and 1 on failure.
   */
  public function commitMsgsHook($branch, $branch_compare) {
    $this->say('Getting a list of git messages...');
    exec("git log \"{$branch}\" --pretty=format:\"%h:%p:%s\" --not \"{$branch_compare}\"", $messages_list, $exit_code);
    if ($exit_code) {
      $this->logger->error("Could not get a list of commits!");
      return 1;
    }

    $this->say('Checking if there are merge commits...');
    exec("git log --merges \"{$branch}\" \"^{$branch_compare}\"", $has_merge_commits, $exit_code);
    if ($exit_code) {
      $this->logger->error("Could check if there are merge commits!");
      return 1;
    }

    // Get configuration values.
    $pattern = $this->getConfigValue('git.commit-msg.pattern');
    $help_description = $this->getConfigValue('git.commit-msg.help_description');
    $example = $this->getConfigValue('git.commit-msg.example');

    // Iterate over messages and check them.
    $invalid_messages = [];
    $has_merges = FALSE;
    $checker = new CommitMessageChecker();
    foreach ($messages_list as $output_line) {
      // Parse the output, in format "commit_hash:parents:commit_subject".
      list($commit_hash, $parents, $commit_subject) = explode(':', $output_line, 3);

      // Check if there are merge commits.
      // We do this by checking if there is a space in the parents string. See
      // the %p format for git-log. Examples are like so:
      //
      // A non-merge commit:
      // 31508b018f46da1fa5b5506d1249569e3fe14a91:26d81631:[DR-1223] add normal link button to richtext editor
      //
      // A merge commit:
      // 26d81631871c8629414a017e1b73ef9050766912:f875f0e6 cf09c6fe:Merge pull request #723 from D41079942/DR-906
      if (FALSE !== strpos($parents, ' ')) {
        $has_merges = TRUE;

        // This is a merge commit, so we don't check formatting.
        continue;
      }

      if (!$checker->isValid($pattern, $commit_subject)) {
        $invalid_messages[] = $commit_hash . ' ' . $commit_subject;
      }
    }

    // Print the response.
    if (!empty($invalid_messages)) {
      $this->logger->error("Invalid commit messages!");

      foreach ($invalid_messages as $invalid_message) {
        $this->logger->warning($invalid_message);
      }

      $this->say("Commit messages must conform to the regex $pattern");
      if (!empty($help_description)) {
        $this->say("$help_description");
      }
      if (!empty($example)) {
        $this->say("Example: $example");
      }

      if ($has_merges) {
        $this->say("Since the branch has merge commits it will be difficult to rename commits. If you are good with rebasing and resolving conflicts, try rebasing onto develop:");
        $this->say("  git rebase -i develop");
        $this->say("After resolving any conflicts, force push your branch:");
        $this->say("  git push origin +{$branch}");
        $this->say("Otherwise, work with the technical lead or release manager to proceed with merging this PR.");
      }
      else {
        $number_of_commits = count($messages_list);
        $this->say("Please fix the commit messages by rebasing and following the prompts:");
        $this->say("  git rebase -i HEAD~{$number_of_commits}");
        $this->say("After renaming the commits, force push your branch:");
        $this->say("  git push origin +{$branch}");
      }
      $this->logger->notice("See https://confluence.atlassian.com/bitbucket/use-smart-commits-298979931.html for details on using smart commit messages.");

      return 1;
    }

    $this->say("No commit message errors found.");
    return 0;
  }

}

