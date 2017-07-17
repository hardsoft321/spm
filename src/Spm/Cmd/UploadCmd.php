<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd;

class UploadCmd extends Base
{
    public function execute()
    {
        list($packages, $options) = self::getArgvParams(1, array('no-php-check', 'spm-path:'));
        list($id_name, $version) = self::parsePackageName(reset($packages));
        if(!empty($options['spm-path'])) {
            $this->spm->spmPath = $options['spm-path'];
        }
        $this->spm->upload($id_name, $version, $options);
    }
}
