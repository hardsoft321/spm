<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd;

class ReinstallCmd extends Base
{
    public function execute()
    {
        $allowedOptions = array_merge(
            UninstallCmd::$ALLOWED_OPTIONS,
            RemoveCmd::$ALLOWED_OPTIONS,
            UploadCmd::$ALLOWED_OPTIONS,
            InstallCmd::$ALLOWED_OPTIONS);
        list($packages, $options) = self::getArgvParams(1, $allowedOptions);
        list($id_name, $version) = self::parsePackageName(reset($packages));
        $this->spm->reinstall($id_name, $version, $options);
    }
}
