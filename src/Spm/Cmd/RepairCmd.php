<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd;

class RepairCmd extends Base
{
    public function execute()
    {
        list($subjects, $options) = self::getArgvParams(false, array('v'));
        $this->spm->repair($options);
    }
}
