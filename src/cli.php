<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 *
 * Entry Point for CLI
 */

if(!Phar::running()) {
    require __DIR__.'/../vendor/autoload.php';
    define("SPM_ENTRY_POINT", __FILE__);
}

if(!defined('SUGARCRM_PRE_INSTALL_FILE'))
{
    define('SUGARCRM_PRE_INSTALL_FILE', 'scripts/pre_install.php');
    define('SUGARCRM_POST_INSTALL_FILE', 'scripts/post_install.php');
    define('SUGARCRM_PRE_UNINSTALL_FILE', 'scripts/pre_uninstall.php');
    define('SUGARCRM_POST_UNINSTALL_FILE', 'scripts/post_uninstall.php');
}

$cmd = \Spm\Cmd\Base::getCmdFromCli();
if(!$cmd) {
    echo "Unknown command. Run `spm help`.\n";
    exit(2);
}
if(method_exists($cmd, 'executeNonSugar')) {
    try {
        $cmd->executeNonSugar();
    }
    catch (Exception $e) {
        echo $e->getMessage(),"\n";
        exit(3);
    }
}
if(!method_exists($cmd, 'execute')) {
    exit;
}

try {
    $login = $cmd->getUserLogin();
}
catch (Exception $e) {
    echo $e->getMessage(),"\n";
    exit(5);
}

if(!\Spm\Spm::enterSugar($login)) {
    echo "SugarCRM root dir not found.\n";
    exit(4);
}

try {
    $cmd->execute();
    $error = null;
}
catch (Exception $e) {
    echo $e->getMessage(),"\n";
    $error = true;
}
\Spm\Spm::cleanupSugar();
if($error) {
    exit(1);
}
