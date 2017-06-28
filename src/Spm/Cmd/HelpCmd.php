<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 *
 * SugarCRM Package Manager
 */
namespace Spm\Cmd;

class HelpCmd extends Base
{
    public function executeNonSugar()
    {
        echo file_get_contents(__DIR__.'/README.txt');
    }
}
