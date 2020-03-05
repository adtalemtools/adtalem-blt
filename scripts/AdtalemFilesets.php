<?php

namespace Adtalemtools\AdtalemBlt\Blt\Plugin\Filesets;

// Do not remove this, even though it appears to be unused.
// @codingStandardsIgnoreLine
use Acquia\Blt\Annotations\Fileset;
use Acquia\Blt\Robo\Config\ConfigAwareTrait;
use Robo\Contract\ConfigAwareInterface;
use Symfony\Component\Finder\Finder;

/**
 * Class Filesets.
 *
 * Each fileset in this class should be tagged with a @fileset annotation and
 * should return \Symfony\Component\Finder\Finder object.
 *
 * @package Acquia\Blt\Custom
 * @see \Acquia\Blt\Robo\Filesets\Filesets
 */
class AdtalemFilesets implements ConfigAwareInterface {
  use ConfigAwareTrait;

  /**
   * Travis YAML files.
   *
   * @fileset(id="files.yaml.travis")
   */
  public function getTravisYaml() {
    $yaml = Finder::create()
      ->files()
      ->name('.travis.yml')
      ->ignoreDotFiles(false)
      ->depth('<1')
      ->in($this->getConfigValue('repo.root'));
    return $yaml;
  }


}
