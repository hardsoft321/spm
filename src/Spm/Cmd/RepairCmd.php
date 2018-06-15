<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd;

class RepairCmd extends Base
{
    public static $ALLOWED_OPTIONS = array(
        'v',
        'actions:',
        'modules:',
    );

    public function execute()
    {
        list($subjects, $options) = self::getArgvParams(false, self::$ALLOWED_OPTIONS);
        $this->spm->repair($options);
    }
}
