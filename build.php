<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */

$dist  = __DIR__.'/dist';
if(!file_exists($dist)) {
    mkdir($dist, 0755);
}
if(file_exists($dist.'/spm.phar')) {
    unlink($dist.'/spm.phar');
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
chmod($dist.'/spm.phar', 0755);
