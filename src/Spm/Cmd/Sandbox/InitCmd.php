<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd\Sandbox;

use Spm\Cmd\Base;

class InitCmd extends Base
{
    public function execute()
    {
        list($x, $options) = self::getArgvParams(0, array('no-merge'));
        $this->spm->sandboxInit($options);
    }
}
