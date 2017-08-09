# spm - SugarCRM Package Manager

Command-line interface for managing SugarCRM packages.

It is intended to deal with package sources, not with any repository like packagist.

## Features

* list currently installed packages (with search by package id)
* list uploaded packages
* install uploaded packages
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

* php >= 5.3.0
* Linux
* SugarCRM/SuiteCRM
* zip php extension

## Build
```sh
composer install
php -d phar.readonly=0 build.php
```
File `dist/spm.phar` will appear.

## Usage

Under your path create a link `spm` to `dist/spm.phar`.
Run `spm help` to see [manual](src/Spm/Cmd/README.txt).

For many commands current directory must be inside of the SugarCRM directory.

---

[Блог](http://blog321.ru/sugarcrm-packages-and-git/)

Powered by [hardsoft321](http://hardsoft321.org/)
