<?php

namespace Adtalemtools\AdtalemBlt\Blt\Plugin\Filesets;

use Acquia\Blt\Annotations\Fileset;
use Acquia\Blt\Robo\Config\ConfigAwareTrait;
use Robo\Contract\ConfigAwareInterface;
use Symfony\Component\Finder\Finder;

/**
 * Custom filesets for BLT.
 *
 * Each fileset in this class should be tagged with a @fileset annotation and
 * should return \Symfony\Component\Finder\Finder object.
 *
 * @package Acquia\Blt\Custom
 * @see \Acquia\Blt\Robo\Filesets\Filesets
 */
class Filesets implements ConfigAwareInterface {
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
