<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd;

use Spm\Spm;

class RepairCmd extends Base
{
    public static $ALLOWED_OPTIONS = array(
        'v',
        'actions:',
        'modules:',
    );

    public function executeNonSugar()
    {
        if(Spm::chdirToSugarRoot()) {
            $moduleExtFile = getcwd() . '/custom/application/Ext/Include/modules.ext.php';
            if (file_exists($moduleExtFile)) {
                unlink($moduleExtFile);
            }
        }
    }

    public function execute()
    {
        list($subjects, $options) = self::getArgvParams(false, self::$ALLOWED_OPTIONS);
        $this->spm->repair($options);
    }
}
