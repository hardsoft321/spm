<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd;

class ListCmd extends Base
{
    public function executeNonSugar()
    {
        list($keywords, $options) = self::getArgvParams(false, array('a', 'spm-path:', 'each-version'));
        if(!empty($keywords) && count($keywords) > 1) {
            echo "Only first keyword is used to filter packages\n";
        }
        if(!empty($options['a'])) {
            echo "Available:\n";
            if(!empty($options['spm-path'])) {
                $this->spm->spmPath = $options['spm-path'];
            }
            $this->spm->listAvailable(empty($keywords) ? null : reset($keywords), $options);
        }
    }

    public function execute()
    {
        list($keywords, $options) = self::getArgvParams(false, array('a', 'spm-path:', 'each-version'));
        echo "Installed:\n";
        $this->spm->listInstalled(empty($keywords) ? null : reset($keywords), $options);
        echo "Loaded:\n";
        $this->spm->listLoaded(empty($keywords) ? null : reset($keywords), $options);
    }
}
