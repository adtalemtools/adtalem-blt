<?php

namespace Adtalem\Blt\Plugin\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Adtalem\Blt\Plugin\Helpers\CommitMessageChecker;

/**
 * A test to make sure the commit message checker matches as expected.
 *
 * To run this do:
 *   ./vendor/bin/phpunit blt/src/Blt/Plugin/Tests/Unit/
 */
class CommitMessageCheckerTest extends TestCase {

  /**
   * @param string $pattern
   *   The regex pattern for a valid message.
   */
  protected $pattern;

  public function setUp() {
    $blt_file = dirname(__FILE__) . '/../../../../../blt/blt.yml';
    $blt_config = Yaml::parseFile($blt_file);
    $this->pattern = $blt_config['git']['commit-msg']['pattern'];
  }

  /**
   * Test the pattern passes for our valid examples.
   *
   * @dataProvider providerValidMessagesPass
   */
  public function testValidMessagesPass($message) {
    $checker = new CommitMessageChecker();

    $match_result = $checker->isValid($this->pattern, $message);

    $this->assertTrue($match_result, 'Expected the valid pattern to pass the checker.');
  }

  /**
   * @return array
   */
  public function providerValidMessagesPass() {
    return [
      ['DR-123 This is a valid message'],
      ['DR-123: This is a valid message'],
      ['DR-101, DR-102 This is a valid message'],
      ['ECOMRP-123 This is a valid message'],
      ['ECOMRP-123: This is a valid message'],
      ['ECOMRP-101, ECOMRP-102 This is a valid message'],
      ['BECK-123 This is a valid message'],
      ['BECK-123: This is a valid message'],
      ['BECK-101, BECK-102 This is a valid message'],
      ['Revert "DR-123: This is a valid message"'],
      ['WT-34: This is a valid message"'],
      ['WT-34, WT-58: This is a valid message"'],
      ['WT-123 This is a valid message'],
    ];
  }

  /**
   * Test the pattern fails for our invalid examples.
   *
   * @dataProvider providerInvalidMessagesFail
   */
  public function testInvalidMessagesFail($message) {
    $checker = new CommitMessageChecker();

    $match_result = $checker->isValid($this->pattern, $message);

    $this->assertFalse($match_result, 'Expected the invalid pattern to fail the checker.');
  }

  /**
   * @return array
   */
  public function providerInvalidMessagesFail() {
    return [
      ['DR123 This is not a valid message'],
      ['This is not a valid message DR-123'],
      ['This is not a valid DR-1123 message'],
      ['DR-123DR-123 This is not a valid message'],
      ['DR-123|DR-123 This is not a valid message'],
      ['DR-123:DR-123 This is not a valid message'],
      ['WT-123:WT-123 This is not a valid message'],
      ['WT-123|WT-123 This is not a valid message'],
      ['This is not a valid message WT-123'],
    ];
  }
}
