# spm - SugarCRM Package Manager

Command-line interface for managing SugarCRM packages.

## Features

* list currently installed packages (with search by package id)
* list uploaded packages
* install packages
* uninstall packages
* upload packages from its sources
* remove uploaded packages
* zip sources into a package (with PHP syntax check)
* run Quick Repair and Rebuild
* run SQL-queries on SugarCRM database
* search file among installed packages
* search conflicts between packages (overlapping files)
* create simple package with interactive dialogue

## Requirements

* Linux
* SugarCRM (tested with CE v6.5.16)
* php5-sqlite
* zip utility
* zip php extension

## Usage

Put file `spm` in some directory. Then you can run it with `php /path/to/spm`.
For many commands current directory must be inside of the SugarCRM directory.
