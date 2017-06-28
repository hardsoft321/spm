<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd;

class ZipCmd extends Base
{
    public function executeNonSugar()
    {
        list($packages, $options) = self::getArgvParams(1, array('no-php-check', 'spm-path:'));
        list($id_name, $version) = self::parsePackageName(reset($packages));
        $this->spm->updateAvailable($options);
        $this->spm->zip($id_name, $version, $options);
    }
}
