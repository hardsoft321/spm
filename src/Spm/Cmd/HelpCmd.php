<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd;

class HelpCmd extends Base
{
    public function executeNonSugar()
    {
        list($commands, $options) = self::getArgvParams(false, array());
        $readme = file_get_contents(__DIR__.'/README.txt');
        if(!empty($commands)) {
            $mans = preg_split('/\nspm\s+/', $readme);
            foreach($commands as $cmd) {
                $cmdMans = array();
                foreach($mans as $i => $man) {
                    if(strcasecmp(substr($man, 0, strlen($cmd)), $cmd) === 0) {
                        $cmdMans[] = ($i == 0 ? "" : "spm ").$man."\n";
                    }
                }
                echo $cmdMans ? implode('', $cmdMans) : "Manual does not have info about command $cmd\n";
            }
        }
        else {
            echo $readme;
        }
    }
}
