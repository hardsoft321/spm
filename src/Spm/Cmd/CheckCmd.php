<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd;

class CheckCmd extends Base
{
    public function execute()
    {
        list($subjects, $options) = self::getArgvParams(false, array('by-restore', 'a', 'modified'));
        if(!empty($options['by-restore'])) {
            $this->spm->listRestoreConflicts();
        }
        $this->spm->check(!empty($options['a']));
        if(!empty($options['modified'])) {
            $this->spm->checkModified();
        }
    }
}
