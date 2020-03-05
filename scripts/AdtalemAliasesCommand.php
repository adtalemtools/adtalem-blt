<?php
/**
 * Created by PhpStorm.
 * User: matthew
 * Date: 2019-01-21
 * Time: 09:59
 */

namespace Adtalemtools\AdtalemBlt\Blt\Plugin\Commands;

use Acquia\Blt\Robo\Commands\Generate;

class AdtalemAliasesCommand extends Generate\AliasesCommand {

  /**
   * Generates new Acquia site aliases for blt config.
   *
   * @command adtalem:aliases:generate
   *
   */
  public function generateAliasesAcquia() {
    $this->cloudConfDir = $_SERVER['HOME'] . '/.acquia';
    $this->setAppId();
    $this->cloudConfFileName = 'cloud_api.conf';
    $this->cloudConfFilePath = $this->cloudConfDir . '/' . $this->cloudConfFileName;
    $this->siteAliasDir = $this->getConfigValue('drush.alias-dir');

    $cloudApiConfig = $this->loadCloudApiConfig();
    $this->setCloudApiClient($cloudApiConfig['key'], $cloudApiConfig['secret']);

    $this->say("<info>Gathering site info from Acquia Cloud.</info>");
    $site = $this->cloudApiClient->application($this->appId);

    $error = FALSE;
    try {
      $this->getSiteAliases($site);
    }
    catch (\Exception $e) {
      $error = TRUE;
      $this->logger->error("Did not write aliases for $site->name. Error: " . $e->getMessage());
    }
    if (!$error) {
      $this->say("<info>Aliases were written, type 'drush sa' to see them.</info>");
    }
  }

  /**
   * Gets generated drush site aliases.
   *
   * @param string $site
   *   The Acquia subscription that aliases will be generated for.
   *
   * @throws \Exception
   */
  protected function getSiteAliases($site) {
    /** @var \AcquiaCloudApi\Response\ApplicationResponse $site */
    $aliases = [];
    $sites = [];
    $this->output->writeln("<info>Gathering sites list from Acquia Cloud.</info>");

    $environments = $this->cloudApiClient->environments($site->uuid);
    $hosting = $site->hosting->type;
    $site_split = explode(':', $site->hosting->id);

    foreach ($environments as $env) {
      if ($env->label != "01dev") {
        continue;
      }
      $domains = $env->domains;
      $this->say('<info>Found ' . count($domains) . ' sites for environment ' . $env->name . ', writing aliases...</info>');

      $sshFull = $env->sshUrl;
      $ssh_split = explode('@', $env->sshUrl);
      $envName = $env->name;
      $remoteHost = $ssh_split[1];
      $remoteUser = $ssh_split[0];

      if ($hosting == 'ace') {

        $siteID = $site_split[1];
        $uri = $env->domains[0];
        $sites[$siteID][$envName] = ['uri' => $uri];
        $siteAlias = $this->getAliases($uri, $envName, $remoteHost, $remoteUser, $siteID);
        $sites[$siteID][$envName] = $siteAlias[$envName];

      }

      if ($hosting == 'acsf') {
        $this->say('<info>ACSF project detected - generating sites data....</info>');

        try {
          $acsf_sites = $this->getSitesJson($sshFull, $remoteUser);
        }
        catch (\Exception $e) {
          $this->logger->error("Could not fetch acsf data for $envName. Error: " . $e->getMessage());
        }

        // Look for list of sites and loop over it.
        if ($acsf_sites) {
          foreach ($acsf_sites['sites'] as $name => $info) {

            // Reset uri value to identify non-primary domains.
            $uri = NULL;

            // Get site prefix from main domain.
            $siteKey = $info['name'];
            // Collections listed after primary site, ignore if its already set.
            if (strpos($name, '.acsitefactory.com') && empty($sites[$siteKey])) {
              $acsf_site_name = explode('.', $name, 2);
              $siteID = $acsf_site_name[0];
              $siteID = preg_replace('/(\d*)(\w+)/', '\2', $siteID);
              $sites[$siteKey] = $info;
              $sites[$siteKey]['remote'] = $acsf_site_name[0] . '.' . $env->label;
              $sites[$siteKey]['id'] = $siteID;
            }
          }
        }
      }

    }

    ksort($sites);
    // Write the alias files to disk.
    foreach ($sites as $key => $info) {
      print $info['id'] . ":\n";
      print "    remote: " . $info['remote'] . "\n";
      print "    local: " . $info['id'] . ".local\n";
      print "    site_dir: " . $info['name'] . "\n";

    }
  }
}