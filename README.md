Adtalem Acquia BLT integration
====

This plugin provides [Acquia BLT](https://github.com/acquia/blt) integration for Adtalem sites.


## Installation and usage

To use this plugin, you must already have a Drupal project using BLT 10.

In your project, require the plugin with Composer:

`composer require adtalemtools/adtalem-blt`

Initialize the integration by calling recipes:adtalem:init, which is provided by this plugin:

`blt recipes:adtalem:init`

Running `blt recipes:adtalem:init` will install the Adtalem BLT commands in the /blt/src/Plugins directory of your project.

Then add the Adtalem BLT namespace in the autoload PSR-4 section of your project's `composer.json`:
`
    "autoload": {
        "psr-4": {
            "Adtalem\\": "blt/src/"
            }
        },
`
Make sure to commit this as well as your updated composer.json to Git.

##Available commands:
`
  adtalem:aliases:generate              Generates new Acquia site aliases for blt config.
  adtalem:db:download                   Download a backup for the site.
  adtalem:db:list                       List available backups.
  adtalem:drupal:update                 Update current database to reflect the state of the Drupal file system.
  adtalem:git-hook:execute:commit-msgs  Validates a git commit message.
  adtalem:local:data:cleanup            Cleanup old backups stored locally.
  adtalem:local:data:list               List backups stored locally.
  adtalem:local:data:restore            Restore a local site from a backup.
  adtalem:local:data:sync               Sync data from an upstream to local.
  adtalem:refresh:db                    Download and restore a backup for the site.
  adtalem:site:data:backup              Backup the database and files for the given sites.
  adtalem:site:data:download            Download a backup for the site.
  adtalem:site:data:list                List available backups.
  adtalem:site:data:restore             Restore the given sites from a backup.
  adtalem:site:data:sync                Sync the data from the PROD env to the target env for given sites.
  adtalem:sync:all-files                Synchronize files for all sites.
  adtalem:sync:all-sites                [adtalem:sync:all] Synchronize each multisite.
  adtalem:sync:files                    Synchronize files for an individual multisite.
  adtalem:sync:site                     [adtalem:sync] Synchronize an individual multisite.
  adtalem:sync:site:env                 [adtalem:env] Synchronize an individual multisite by environment.
  adtalem:sync:truncate                 [adtalem:truncate] Truncate database for multisite.
  adtalem:tests:behat:run               [adtalem:tests:behat] Executes all behat tests.
`
