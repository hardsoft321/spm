<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd\Md5;

use Spm\Cmd\Base;

class CompareCmd extends Base
{
    protected $files;

    public function executeNonSugar()
    {
        list($files, $options) = self::getArgvParams(false, array());
        if(count($files) > 2) {
            array_shift($files);
            array_shift($files);
            throw new \Exception("Unknown option ".implode(' ', $files));
        }
        if(empty($files)) {
            throw new \Exception("At least one file must be specified");
        }
        foreach($files as $key => $file) {
            if(!($files[$key] = realpath($file))) {
                throw new \Exception("File {$file} not exists.");
            }
        }
        if(isset($files[1])) {
            $this->spm->md5Compare($files[0], $files[1]);
        }
        $this->files = $files;
    }

    public function execute()
    {
        if(isset($this->files[1])) {
            return;
        }
        $this->spm->md5Compare($this->files[0], null);
    }
}
