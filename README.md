Adtalem Acquia BLT integration
====

This plugin provides [Acquia BLT](https://github.com/acquia/blt) integration for Adtalem sites.


## Installation and usage

To use this plugin, you must already have a Drupal project using BLT 10.

In your project, require the plugin with Composer:

`composer require adtalemtools/adtalem-blt`

Initialize the integration by calling recipes:adtalem:init, which is provided by this plugin:

`blt recipes:adtalem:init`

Running `blt recipes:adtalem:init` will initialize a BLT configuration in the /blt directory of your project.

Make sure to commit this as well as your updated composer.json to Git.