# spm - SugarCRM Package Manager

Command-line interface for managing SugarCRM packages.

## Features

* list currently installed packages (with search by package id)
* list uploaded packages
* install packages
* uninstall packages (without clearing user settings or modifying viewdefs by default)
* upload packages from its sources
* remove uploaded packages
* zip sources into a package (with PHP syntax check)
* run Quick Repair and Rebuild
* run SQL-queries on SugarCRM database (queries can be checked against whitelist)
* search file among installed packages
* search conflicts between packages (overlapping files)
* create simple package with interactive dialogue
* support sandbox (file with a list of required packages): compare with current, automatic installation
* compare md5 checksums

## Requirements

* Linux
* SugarCRM (tested with CE v6.5.16)
* php5-sqlite
* zip utility
* zip php extension

## Usage

Put file `spm` in some directory. Then you can run it with `php /path/to/spm`.
Run `php /path/to/spm help` to see manual.

For many commands current directory must be inside of the SugarCRM directory.
