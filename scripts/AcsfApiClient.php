<?php

namespace Adtalem\Blt\Plugin\Helpers\Acsf;

use \Psr\Log\LoggerInterface;

/**
 * An HTTP client for communicating with ACSF API.
 */
class AcsfApiClient {

  /**
   * A logger for recording debug information.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Inject dependencies for this class.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger for recording debug information.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Set the ACSF API configurations.
   *
   * @param array $config
   *   The ACSF API configurations. It expects these keys, which take priority:
   *     acsf-api-username - Your ACSF username
   *     acsf-api-password - Your ACSF password
   *     acsf-api-base-url - The API URL, e.g.
   *   https://www.sitename.acsitefactory.com/ If these are not set it will
   *   look for these values in environment variables, which take second
   *   priority: ACSF_API_USERNAME ACSF_API_PASSWORD ACSF_API_BASE_URL For
   *   backwards compatibility it will also look for these values in the
   *   environment variables, which take third priority: ACQUIA_API_USERNAME
   *   ACQUIA_API_PASSWORD
   */
  public function setAcsfApiConfig($config = []) {
    if (!empty($config['acsf-api-username'])) {
      $this->acsfApiUsername = $config['acsf-api-username'];
    }
    elseif (!empty(getenv('ACSF_API_USERNAME'))) {
      $this->acsfApiUsername = getenv('ACSF_API_USERNAME');
    }
    elseif (!empty(getenv('ACQUIA_API_USERNAME'))) {
      $this->acsfApiUsername = getenv('ACQUIA_API_USERNAME');
    }
    else {
      throw new \Exception('The ACSF API username must be set.');
    }

    if (!empty($config['acsf-api-password'])) {
      $this->acsfApiPassword = $config['acsf-api-password'];
    }
    elseif (!empty(getenv('ACSF_API_PASSWORD'))) {
      $this->acsfApiPassword = getenv('ACSF_API_PASSWORD');
    }
    elseif (!empty(getenv('ACQUIA_API_PASSWORD'))) {
      $this->acsfApiUsername = getenv('ACQUIA_API_PASSWORD');
    }
    else {
      throw new \Exception('The ACSF API password must be set.');
    }

    if (!empty($config['acsf-api-base-url'])) {
      $this->acsfApiBaseUrl = $config['acsf-api-base-url'];
    }
    elseif (!empty(getenv('ACSF_API_BASE_URL'))) {
      $this->acsfApiBaseUrl = getenv('ACSF_API_BASE_URL');
    }
    else {
      throw new \Exception('The ACSF API base URL must be set.');
    }

    if ('/' !== substr($this->acsfApiBaseUrl, -1)) {
      throw new \Exception('The ACSF API base URL must end in a trailing forward slash.');
    }
    if ('https://' !== substr($this->acsfApiBaseUrl, 0, 8)) {
      throw new \Exception('The ACSF API base URL must begin with "https://"');
    }
  }

  /**
   * Make a request to the API v1 endpoint.
   *
   * @param string $method
   *   The HTTP method.
   * @param string $resource_url
   *   The API URL, everything after the base URL.
   * @param array $headers
   *   An array of strings, each string is a header.
   * @param string $body
   *   The HTTP body to send.
   *
   * @return array
   *   The JSON decoded HTTP response.
   */
  public function makeV1Request($method, $resource_url, $headers = [], $body = '') {
    $base_url = $this->acsfApiBaseUrl . 'api/v1';
    $url = $base_url . '/' . $resource_url;
    return $this->makeRequest($method, $url, $headers, $body);
  }

  /**
   * Make a request to the API v2 endpoint.
   *
   * @param string $method
   *   The HTTP method.
   * @param string $resource_url
   *   The API URL, everything after the base URL.
   * @param array $headers
   *   An array of strings, each string is a header.
   * @param string $body
   *   The HTTP body to send.
   *
   * @return array
   *   The JSON decoded HTTP response.
   */
  public function makeV2Request($method, $resource_url, $headers = [], $body = '') {
    $base_url = $this->acsfApiBaseUrl . 'api/v2';
    $url = $base_url . '/' . $resource_url;
    return $this->makeRequest($method, $url, $headers, $body);
  }

  /**
   * Make a request to the API.
   *
   * @param string $method
   *   The HTTP method.
   * @param string $resource_url
   *   The API URL, including the base URL.
   * @param array $headers
   *   An array of strings, each string is a header.
   * @param string $body
   *   The HTTP body to send.
   *
   * @return array
   *   The JSON decoded HTTP response.
   */
  protected function makeRequest($method, $url, $headers = [], $body = '') {
    $context_params = [
      'ssl' => [
        'verify_peer' => FALSE,
        'verify_peer_name' => FALSE,
      ],
      'http' => [
        'method' => $method,
        'header' => "Authorization: Basic " . base64_encode("$this->acsfApiUsername:$this->acsfApiPassword"),
      ],
    ];

    if (!empty($body)) {
      $context_params['http']['content'] = $body;
    }

    if (!empty($headers)) {
      $context_params['http']['header'] = join("\r\n", array_merge([$context_params['http']['header']], $headers));
    }

    $context = stream_context_create($context_params);

    $this->logger->debug("API Request: {$method} {$url}");
    $this->logger->debug("API Request Context: " . print_r($context_params, TRUE));

    $json = file_get_contents($url, FALSE, $context);
    return json_decode($json, TRUE);
  }

  /**
   * Wait for the task to complete then return.
   *
   * @param array $task_ids
   *   An array of task IDs to wait for.
   * @param int $iteration_sleep
   *   The time to wait in between each attempt to check for task completion.
   * @param int $iteration_limit
   *   The number of times to attempt waiting for the task to complete.
   *
   * @return bool
   *   If true, all tasks succeeded. Otherwise, false.
   */
  public function waitForTasksAndReturn($task_ids, $iteration_sleep = 15, $iteration_limit = 200) {
    try {
      $iteration = 0;
      $remaining_task_ids_to_check = $task_ids;
      $final_task_statuses = [];
      while ($iteration < $iteration_limit) {
        foreach ($remaining_task_ids_to_check as $key => $task_id) {
          $task_status = $this->get_task_status($task_id);

          // Print debug info.
          $this->logger->notice(print_r($task_status, TRUE));

          // If complete, print a message.
          if (!empty($task_status['wip_task']['completed'])) {
            $this->logger->notice("Task {$task_id} is completed at {$task_status['wip_task']['completed']}.");
            $final_task_statuses[] = $task_status;
            unset($remaining_task_ids_to_check[$key]);
          }
        }
        if (empty($remaining_task_ids_to_check)) {
          break;
        }
        $iteration++;
        sleep($iteration_sleep);
      }

      if ($iteration >= $iteration_limit) {
        throw new \Exception("Waited too long for task to complete. Completed {$iteration} iterations with {$iteration_sleep} seconds sleep between iterations.");
      }

      // If the status is not 16 it failed, and we should fail the script.
      $is_task_errored = FALSE;
      foreach ($final_task_statuses as $task_status) {
        if (16 != $task_status['wip_task']['status']) {
          $this->logger->warning("Task {$task_status['wip_task']['id']} failed with status code {$task_status['wip_task']['status']}.");
          $is_task_errored = TRUE;
        }
      }
      return $is_task_errored ? FALSE : TRUE;
    } catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Get the status of a task.
   *
   * @param int $task_id
   *   The task ID to get the status for.
   *
   * @return array
   *   The API response.
   */
  protected function get_task_status($task_id) {
    $resource_url = "wip/task/{$task_id}/status";
    return $this->makeV1Request('GET', $resource_url);
  }

}
