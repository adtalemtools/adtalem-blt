<?php

namespace Adtalem\Blt\Plugin\Helpers\Acsf\Ac;

trait AcConfigTrait {

  /**
   * Get Acquia Cloud API config from various ways in priority order.
   *
   * Priority order:
   *   1. Explicit values provided, e.g. via CLI args.
   *   2. Environment variable.
   *   3. File on disk.
   *
   * @return array
   *   The config with keys "key" and "secret" if either are found, else an empty array.
   * @throws \Exception If config are provided but are invalid.
   */
  public function getApiConfig(array $options = []) {
    $config = $this->getApiConfigFromOptions($options);
    if (!empty($config)) {
      $this->validateApiConfigWithMessage($config, "You must specify both the api-key and api-secret arguments.");
      return $config;
    }

    $config = $this->getApiConfigFromEnvironment();
    if (!empty($config)) {
      $this->validateApiConfigWithMessage($config, "You must specify both the AC_API_KEY and AC_API_SECRET variables.");
      return $config;
    }

    $config = $this->getApiConfigFromFile();
    if (!empty($config)) {
      $this->validateApiConfigWithMessage($config, "You must specify both the key and secret in the API config file.");
      return $config;
    }

    return [];
  }

  /**
   * Get API config from the options to a command.
   *
   * @param array $options
   *   The options passed to a command.
   * @return array
   *   The config with keys "key" and "secret" if either are found, else an empty array.
   */
  public function getApiConfigFromOptions(array $options = []) {
    $key = !empty($options['api-key']) ? $options['api-key'] : '';
    $secret = !empty($options['api-secret']) ? $options['api-secret'] : '';
    if (empty($key) && empty($secret)) {
      return [];
    }
    return [
      'key' => $key,
      'secret' => $secret,
    ];
  }

  /**
   * Get API config from the environment variables AC_API_KEY and AC_API_SECRET.
   *
   * @return array
   *   The config with keys "key" and "secret" if either are found, else an empty array.
   */
  public function getApiConfigFromEnvironment() {
    $key = getenv('AC_API_KEY');
    $secret = getenv('AC_API_SECRET');
    if (empty($key) && empty($secret)) {
      return [];
    }
    return [
      'key' => $key,
      'secret' => $secret,
    ];
  }

  /**
   * Get API config from a file on disk: ~/.acquia/cloud_api.conf.
   *
   * Note: this file follows the convention of other BLT and drush commands.
   *
   * @return array
   *   The config with keys "key" and "secret" if either are found, else an empty array.
   * @throws \Exception
   */
  public function getApiConfigFromFile() {
    $cloud_conf_file_path = $_SERVER['HOME'] . '/.acquia/cloud_api.conf';

    if (!file_exists($cloud_conf_file_path)) {
      throw new \Exception('Acquia cloud config file not found. Run "blt recipes:aliases:init:acquia"');
    }

    $cloud_api_config = (array) json_decode(file_get_contents($cloud_conf_file_path));

    if (empty($cloud_api_config)) {
      return [];
    }

    return [
      'key' => $cloud_api_config['key'],
      'secret' => $cloud_api_config['secret'],
    ];
  }

  /**
   * Validate the API config are in the expected format for use.
   *
   * @param array $config
   * @param string $message
   *
   * @return bool
   *   Return TRUE if valid, else throw exception using the given message.
   *
   * @throws \Exception
   */
  public function validateApiConfigWithMessage(array $config, $message) {
    if (empty($config['key']) || empty($config['secret'])) {
      throw new \Exception($message);
    }
    return TRUE;
  }

  /**
   * Writes configuration to local file.
   *
   * @param array $config
   *   An array of CloudAPI configuraton.
   */
  public function writeApiConfigToFile(array $config) {
    $acquia_dir = $_SERVER['HOME'] . '/.acquia';

    if (!is_dir($acquia_dir)) {
      mkdir($acquia_dir);
    }
    $cloud_conf_file_path = $acquia_dir . '/cloud_api.conf';

    file_put_contents($cloud_conf_file_path, json_encode($config));
    $this->say("Config was written to {$cloud_conf_file_path}.");
  }

}
