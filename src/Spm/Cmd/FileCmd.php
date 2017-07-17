<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd;

class FileCmd extends Base
{
    public function execute()
    {
        list($files, $options) = self::getArgvParams(false, array(
            'sync',
            'spm-path:',
        ));
        $sugarDir = getcwd();
        chdir($this->spm->cwd);
        $realFiles = array();
        if(empty($files)) {
            throw new \Exception("You must specify a file");
        }
        $wrongFiles = array();
        foreach($files as $file) {
            $fullpath = realpath($file);
            if(!$fullpath) {
                $fullpath = $this->spm->cwd.'/'.$file;
            }
            if(file_exists($fullpath)) {
                $realFiles[] = $fullpath;
            }
            else {
                $wrongFiles[] = $fullpath;
                echo "File $file not found.\n";
            }
        }
        chdir($sugarDir);
        if(empty($realFiles)) {
            return;
        }

        if(!empty($options['sync'])) {
            $syncedFiles = array();
            if(!empty($options['spm-path'])) {
                $this->spm->spmPath = $options['spm-path'];
            }
            $realFiles = array_unique($realFiles);
            foreach($realFiles as $file) {
                $path = ltrim(substr($file, strlen(getcwd())), '/');
                $md5 = md5_file($path);
                echo "\n{$path}:\n";
                $variants = $this->spm->searchFileInAvailable($path);
                $variants = array_values($variants);
                if(!empty($variants)) {
                    $quit = false;
                    $done = false;
                    do {
                        foreach($variants as $i => $var) {
                            $varMd5 = md5_file($var['path']);
                            echo "  ".($i+1).") Rewrite {$var['info']['type']} {$var['path']}".($md5 === $varMd5 ? " (not modified)" : " (modified)")."\n";
                        }
                        echo "  s) Skip this file\n";
                        echo "  q) Quit\n";
                        $answer = trim(fgets(STDIN));
                        if($answer == 's') {
                            $done = true;
                        }
                        elseif($answer == 'q') {
                            $quit = true;
                        }
                        elseif(is_numeric($answer) && isset($variants[$answer - 1])) {
                            $file_to = $variants[$answer - 1]['path'];
                            echo "Rewrite {$file_to} with {$file} ...";
                            if(copy($file, $file_to)) {
                                $syncedFiles[] = $file;
                                echo " Ok\n";
                                $done = true;
                            }
                            else {
                                echo " FAIL\n";
                            }
                        }
                        if(!$done && !$quit) {
                            echo "Please repeat\n";
                        }
                    }
                    while(!$done && !$quit);

                    if($quit) {
                        break;
                    }
                }
                else {
                    echo "  No package found\n";
                }
            }
            if(!empty($syncedFiles)) {
                echo "\nSynchronized files:\n\n";
                foreach($syncedFiles as $file) {
                    echo "  $file\n";
                }
                echo "\nSkipped files:\n\n";
                foreach(array_diff($realFiles, $syncedFiles) as $file) {
                    echo "  $file\n";
                }
                foreach($wrongFiles as $file) {
                    echo "  $file\n";
                }
                echo "\nDone\n";
            }
            else {
                echo "\nNo changes was made.\n";
            }
            return;
        }

        foreach($realFiles as $file) {
            $path = ltrim(substr($file, strlen(getcwd())), '/');
            $fileInfo = $this->spm->getFileInfo($path);
            $this->spm->listFileInfo($fileInfo);
        }
    }
}
