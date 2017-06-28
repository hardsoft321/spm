<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd;

class UninstallCmd extends Base
{
    public static $ALLOWED_OPTIONS = array(
        'remove-tables',
        'remove-acl',
        'remove-custom',
        'remove-prefs',
        'remove-relationships',
        'not-uninstallable',
        'no-copy',
        'lock-file:',
        'log-file:',
    );

    public function execute()
    {
        list($packages, $options) = self::getArgvParams(1, self::$ALLOWED_OPTIONS);
        list($id_name, $version) = self::parsePackageName(reset($packages));
        $this->spm->uninstall($id_name, $version, $options);
    }
}
