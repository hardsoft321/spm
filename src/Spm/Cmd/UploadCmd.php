<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd;

class UploadCmd extends Base
{
    public static $ALLOWED_OPTIONS = array(
        'no-php-check',
        'spm-path:',
    );

    public function execute()
    {
        list($packages, $options) = self::getArgvParams(1, self::$ALLOWED_OPTIONS);
        list($id_name, $version) = self::parsePackageName(reset($packages));
        $this->spm->upload($id_name, $version, $options);
    }
}
