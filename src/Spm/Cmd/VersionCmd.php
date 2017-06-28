<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd;

use Spm\Spm;

class VersionCmd extends Base
{
    public function executeNonSugar()
    {
        global $sugar_config;
        echo "Spm: ".SPM_VERSION."\n";
        if(is_file('manifest.php')) {
            $manifest = null;
            $installdefs = null;
            include 'manifest.php';
            if(!empty($manifest['version']) && !empty($installdefs['id'])) {
                echo "{$installdefs['id']}: {$manifest['version']}\n";
            }
        }
        if(Spm::enterSugar()) {
            if(!empty($sugar_config['sugar_version'])) {
                echo "SugarCRM: {$sugar_config['sugar_version']}\n";
            }
            if(!empty($sugar_config['suitecrm_version'])) {
                echo "SuiteCRM: {$sugar_config['suitecrm_version']}\n";
            }
            Spm::cleanupSugar();
        }
    }
}
