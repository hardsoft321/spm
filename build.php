<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */

$dist  = __DIR__.'/dist';
if(!file_exists(__DIR__.'/vendor/autoload.php')) {
    echo "You must run 'composer install'\n";
    exit(1);
}
if(!file_exists($dist)) {
    mkdir($dist, 0755);
}
if(file_exists($dist.'/spm.phar')) {
    echo "Clearing..\n";
    if(!unlink($dist.'/spm.phar')) {
        echo "Cannot clear {$dist}/spm.phar\n";
        exit(1);
    }
}
$phar = new Phar($dist.'/spm.phar', FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, 'spm.phar');
$phar->buildFromDirectory(__DIR__.'/src');
$phar->buildFromDirectory(__DIR__.'/vendor');
$phar->setStub(
'#!/usr/bin/env php
<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 * @link https://github.com/eapunk/spm
 *
 * SugarCRM Package Manager
 */
define("SPM_ENTRY_POINT", __FILE__);
$loader = require "phar://".__FILE__."/autoload.php";
$loader->addPsr4("Spm\\\\", "phar://spm.phar/Spm");
include "phar://".__FILE__."/cli.php";
__HALT_COMPILER();
?>
');
if(!file_exists($dist.'/spm.phar')) {
    echo "File {$dist}/spm.phar does not created\n";
    exit(1);
}
chmod($dist.'/spm.phar', 0755);
echo "Now you can use {$dist}/spm.phar\n";
