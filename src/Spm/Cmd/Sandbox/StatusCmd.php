<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd\Sandbox;

use Spm\Cmd\Base;

class StatusCmd extends Base
{
    public function execute()
    {
        list($environments, $options) = self::getArgvParams(false, array('file:', 'input'));
        if(!empty($options['file']) && !empty($options['input'])) {
            throw new \Exception("Options conflict: file and input");
        }
        $this->spm->sandboxStatus($environments, $options);
    }
}
