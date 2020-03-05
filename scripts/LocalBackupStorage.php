<?php

namespace Adtalem\Blt\Plugin\Helpers\Acsf;

use AcquiaCloudApi\Response\BackupResponse;
use Consolidation\Config\ConfigInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use GuzzleHttp\Client;
use Alchemy\Zippy\Zippy;
use Alchemy\Zippy\Adapter\AdapterContainer;

/**
 * Provide local storage for backups.
 */
class LocalBackupStorage {

  /**
   * The BLT config.
   *
   * @var \Consolidation\Config\ConfigInterface
   */
  protected $config;

  /**
   * The BLT logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The environment the backups are for.
   *
   * @var string
   */
  protected $env;

  /**
   * The filesystem that stores backups.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $filesystem;

  /**
   * LocalBackupStorage constructor.
   *
   * @param \Consolidation\Config\ConfigInterface $config
   *   The BLT task config.
   * @param \Psr\Log\LoggerInterface $logger
   *   The BLT logger.
   * @param string $env
   *   The environment the backup files are being stored for.
   * @param \Symfony\Component\Filesystem\Filesystem|NULL $filesystem
   *   Optionally, a filesystem that stores backups.
   */
  public function __construct(ConfigInterface $config, LoggerInterface $logger, $env, Filesystem $filesystem = NULL) {
    $this->config = $config;
    $this->logger = $logger;
    if (empty($env)) {
      throw new \InvalidArgumentException('The environment must be passed.');
    }
    $this->env = $env;
    if (!$filesystem) {
      $filesystem = new Filesystem();
    }
    $this->filesystem = $filesystem;
  }

  /**
   * Download and save a Site Factory backup.
   *
   * @param string $url
   *   The URL to download.
   * @param array $backup
   *   The backup metadata.
   *
   * @return bool
   *   True if the file was downloaded and saved.
   */
  public function downloadAndSaveBackup($url, array $backup) {
    $file_path = $this->getBackupFilepath($backup['nid'], $this->env, $backup['file']);
    $file_path_meta = $this->getBackupFileMetadataPath($file_path);

    $this->logger->notice("Downloading backup to {$file_path}");

    $http_client = new Client();
    $response = $http_client->get($url, ['save_to' => $file_path]);

    if ($response->getStatusCode() !== 200) {
      throw new \Exception("Failed to download backup URL {$response['url']}");
    }

    $this->logger->debug("File downloaded to {$file_path}");

    if (!$this->saveFile($file_path_meta, json_encode($backup))) {
      $this->logger->debug("Failed to write file metadata to {$file_path_meta}");
      return FALSE;
    }

    $this->logger->debug("File metadata written to {$file_path_meta}");

    return TRUE;
  }

  /**
   * Download and save an Acquia Cloud backup.
   *
   * @param \Acquia\Blt\Custom\Helpers\Acsf\Ac\AcsfAcApiClient $client
   *   The HTTP client.
   * @param string $db_name
   *   The name of the database.
   * @param array $backup
   *   The backup metadata.
   *
   * @return bool
   *   True if the file was downloaded and saved.
   */
  public function downloadAndSaveAcBackup($client, $db_name, $backup) {
    $file_path = $this->getBackupFilepath($backup['nid'], $this->env, $backup['file']);
    $file_path_meta = $this->getBackupFileMetadataPath($file_path);

    $url = $client->getDatabaseBackupDownloadUrl($this->env, $db_name, $backup['id']);

    $this->logger->notice("Downloading backup to {$file_path}");
    $response = $client->getDatabaseBackupDownload($url, $file_path);

    if ($response->getStatusCode() !== 200) {
      throw new \Exception("Failed to download backup ID {$backup['id']}");
    }

    $this->logger->debug("File downloaded to {$file_path}");

    if (!$this->saveFile($file_path_meta, json_encode($backup))) {
      throw new \Exception("Failed to save backup metadata to {$file_path_meta}");
    }

    $this->logger->debug("File metadata written to {$file_path_meta}");

    return TRUE;
  }

  /**
   * Remove a backup from local filesystem.
   *
   * @param array $backup
   *   The backup metadata.
   *
   * @return bool
   *   True if the backup was removed, otherwise false.
   */
  public function removeLocalBackup(array $backup) {
    $site_id = $backup['nid'];
    $env = $this->env;
    $backup_dir = $this->getBackupFiledir($site_id, $env);
    $file_prefix = substr($backup['file'], 0, -7);
    $backup_file_compressed = $backup_dir . '/' . $backup['file'];
    $backup_file_extracted_dir = $backup_dir . '/' . $file_prefix;
    $backup_file_metadata = $backup_file_compressed . '.json';

    if (is_dir($backup_file_extracted_dir)) {
      if (!$this->removefile($backup_file_extracted_dir)) {
        $this->logger->debug("Failed to delete extracted dir {$backup_file_extracted_dir}");
        return FALSE;
      }
    }

    if (file_exists($backup_file_compressed)) {
      if (!$this->removefile($backup_file_compressed)) {
        $this->logger->debug("Failed to delete compressed file {$backup_file_compressed}");
        return FALSE;
      }
    }

    if (file_exists($backup_file_metadata)) {
      if (!$this->removefile($backup_file_metadata)) {
        $this->logger->debug("Failed to delete metadata {$backup_file_metadata}");
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Extract a backup on the local filesystem.
   *
   * @param array $backup
   *   The backup metadata.
   *
   * @return bool
   *   True if the backup was extracted, otherwise false.
   */
  public function extractBackup($backup) {
    $backup_file = $this->getBackupFilepath($backup['nid'], $this->env, $backup['file']);
    $extraction_dir = $this->getBackupFileExtractionDir($backup_file);

    if (!file_exists($backup_file)) {
      throw new \Exception('File note found: ' . $backup_file);
    }

    // Check if it has been extracted already.
    if (!is_dir($extraction_dir)) {
      $this->logger->notice("Extracting backup to {$extraction_dir}");

      if (!$this->mkdir($extraction_dir)) {
        return FALSE;
      }

      if (isset($backup['backup_api']) && $backup['backup_api'] == 'ac') {
        $file_name = $backup_file;
        $out_file_name = $extraction_dir . '/database.sql';

        $buffer_size = 4096;
        $file = gzopen($file_name, 'rb');
        $out_file = fopen($out_file_name, 'wb');
        while (!gzeof($file)) {
          fwrite($out_file, gzread($file, $buffer_size));
        }
        fclose($out_file);
        gzclose($file);
      }
      else {
        $zippy_container = AdapterContainer::load();
        $zippy = Zippy::load($zippy_container);
        $archive = $zippy->open($backup_file);
        $archive->extract($extraction_dir);
      }
    }
    return TRUE;
  }

  /**
   * Check if the backup is available locally.
   *
   * @param array $backup
   *   The backup metadata.
   *
   * @return bool
   *   True if the backup is available locally, otherwise false.
   */
  public function hasAvailableBackup($backup) {
    $file_path = $this->getBackupFilepath($backup['nid'], $this->env, $backup['file']);
    $file_path_meta = $this->getBackupFileMetadata($file_path);

    if (empty($file_path_meta)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Get backups available on the local filesystem.
   *
   * @param int $site_id
   *   The site to get backups for.
   *
   * @return array
   *   An array of arrays of backup metadata.
   */
  public function getAvailableBackups($site_id) {
    $backups = [];

    $backup_dir = $this->getBackupFiledir($site_id, $this->env);

    if (!is_dir($backup_dir)) {
      return [];
    }

    $finder = new Finder();
    // We assume the name reverse sorted is equal to descend sorting by
    // date of backup.
    $finder->files()->in($backup_dir)->name('*.json')->sortByName();

    $backup_meta_files = $finder;

    if (!$backup_meta_files) {
      return $backups;
    }

    foreach ($backup_meta_files as $backup_meta_file) {
      $metadata = json_decode(file_get_contents($backup_meta_file), TRUE);
      if ($metadata) {
        $backups[] = $metadata;
      }
    }

    return $backups;
  }

  /**
   * Get the file path to a backup, regardless of whether it already exists.
   *
   * @param int $site_id
   *   The site ID.
   * @param string $env
   *   The environment name, e.g. 01dev.
   * @param $filename
   *   The name of the file, backup-2019-01-01-15-23-krnc123-15329.sql.gz.
   *
   * @return string
   *   The full path to the backup file on disk.
   */
  public function getBackupFilepath($site_id, $env, $filename) {
    $dir = $this->getBackupFiledir($site_id, $env);
    if (!$this->filesystem->exists($dir)) {
      $this->mkdir($dir);
    }
    return $dir . '/' . $filename;
  }

  /**
   * Get the directory path the backup file is extracted to, regardless of
   * whether it exists.
   *
   * @param string $backup_file
   *   The full path to the compressed backup file.
   *
   * @return bool|string
   *   The full path to the backup file extraction directory on disk.
   */
  public function getBackupFileExtractionDir($backup_file) {
    return substr($backup_file, 0, -7);
  }

  /**
   * Format metadata from Acquia Cloud to look like that from ACSF.
   *
   * @param int $site_id
   *   The ACSF site ID.
   * @param \AcquiaCloudApi\Response\BackupResponse $backup
   *   An Acquia Cloud backup response.
   *
   * @return array
   *   The backup metadata in a formatted array.
   */
  public function formatAcBackupMetadata($site_id, BackupResponse $backup) {
    $format = [
      'backup_api' => 'ac',
      'site_id' => $site_id,
      'id' => $backup->id,
      'nid' => $site_id,
      'status' => 1,
      'uid' => 56,
      'timestamp' => $backup->completedAt,
      'bucket' => '',
      'directory' => '',
      'file' => 'backup-' . date_format(new \DateTime($backup->completedAt), 'Y-m-d-H-i') . '-' . $backup->database->name . '-' . $backup->id . '.sql.gz',
      'label' => '',
      'codebase' => 0,
      'componentList' => [
        'database',
      ],
      'response' => $backup,
    ];
    return $format;
  }

  protected function getBackupFiledir($site_id, $env) {
    $backup_dir = $this->config->get('acsf.backup_dir', '/tmp');
    return $backup_dir . '/' . $site_id . '/' . $env;
  }

  protected function getBackupFileMetadataPath($file) {
    return $file . '.json';
  }

  protected function getBackupFileMetadata($backup_file) {
    $metadata_file = $this->getBackupFileMetadataPath($backup_file);
    if (!file_exists($metadata_file)) {
      return NULL;
    }
    return json_decode(file_get_contents($metadata_file), TRUE);
  }

  protected function saveFile($file, $data) {
    try {
      $this->filesystem->dumpFile($file, $data);
      return TRUE;
    } catch (\Exception $e) {
      $this->logger->error("Remove file failed with: " . $e->getMessage());
      return FALSE;
    }
  }

  protected function removeFile($file) {
    if (!$this->filesystem->exists($file)) {
      return TRUE;
    }
    try {
      $this->filesystem->remove($file);
      return TRUE;
    } catch (\Exception $e) {
      $this->logger->error("Remove file failed with: " . $e->getMessage());
      return FALSE;
    }
  }

  protected function mkdir($dir) {
    if ($this->filesystem->exists($dir)) {
      return TRUE;
    }
    try {
      $this->filesystem->mkdir($dir);
      return TRUE;
    } catch (\Exception $e) {
      $this->logger->error("Mkdir failed with: " . $e->getMessage());
      return FALSE;
    }
  }

}
