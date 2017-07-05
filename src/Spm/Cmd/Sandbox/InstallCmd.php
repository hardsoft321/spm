<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd\Sandbox;

use Spm\Cmd\Base;

class InstallCmd extends Base
{
    public function execute()
    {
        $allowedOptions = array('file:', 'no-uninstall', 'input', 'env:');
        $allowedOptions = array_merge($allowedOptions, \Spm\Cmd\InstallCmd::$ALLOWED_OPTIONS);
        $allowedOptions = array_merge($allowedOptions, \Spm\Cmd\UninstallCmd::$ALLOWED_OPTIONS);
        list($x, $options) = self::getArgvParams(0, $allowedOptions);
        if(!empty($options['file']) && !empty($options['input'])) {
            throw new \Exception("Options conflict: file and input");
        }
        $this->spm->sandboxInstall($options);
    }
}
