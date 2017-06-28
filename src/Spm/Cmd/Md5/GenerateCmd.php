<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd\Md5;

use Spm\Cmd\Base;

class GenerateCmd extends Base
{
    public function execute()
    {
        list($files, $options) = self::getArgvParams(false, array());
        if(count($files) > 1) {
            array_shift($files);
            throw new \Exception("Unknown option ".implode(' ', $files));
        }

        $sugarDir = getcwd();
        chdir($this->spm->cwd);
        if(!empty($files)) {
            $file = reset($files);
            $pathinfo = pathinfo($file);
            $dirpath = realpath($pathinfo['dirname']);
            if(!$dirpath) {
                throw new \Exception("Directory {$pathinfo['dirname']} not exists.");
            }
            $file = $dirpath.'/'.$pathinfo['basename'];
        }
        else {
            $i = 0;
            do {
                $file = $this->spm->cwd."/md5_array_calculated-".date('Y-m-d').($i ? "($i)" : "").".php";
                $i++;
            }
            while(file_exists($file));
        }
        chdir($sugarDir);
        $this->spm->md5Generate($file);
    }
}
