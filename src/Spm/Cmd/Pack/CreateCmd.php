<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd\Pack;

use Spm\Cmd\Base;

class CreateCmd extends Base
{
    public function executeNonSugar()
    {
        $spm_home_dir = $_SERVER['HOME'].'/.spm';
        echo "Package structure will be created in this directory.\n";

        $defaultName = basename(getcwd());
        echo "Input name of new package [$defaultName]: ";
        $id_name = ($s = trim(fgets(STDIN))) ? $s : $defaultName;

        $defaultAuthor = file_exists($spm_home_dir.'/last_author')
            ? file_get_contents($spm_home_dir.'/last_author') : $this->spm->getRunningUser();
        echo "Input author name [$defaultAuthor]: ";
        $author = ($s = trim(fgets(STDIN))) ? $s : $defaultAuthor;

        echo "Input description: ";
        $description = trim(fgets(STDIN));

        $licensesDir = $spm_home_dir.'/licenses';
        do {
            echo "Choose license among txt files in $licensesDir [n]:\n";
            $licensesFiles = glob($licensesDir."/*.txt");
            echo " n) none\n";
            echo " r) rescan directory\n";
            foreach($licensesFiles as $i => $file) {
                echo " ".($i+1).") ".basename($file)."\n";
            }
            $answer = trim(fgets(STDIN));
            $done = false;
            if($answer == 'n' || $answer === '') {
                $license = false;
                $done = true;
            }
            elseif(is_numeric($answer) && isset($licensesFiles[$answer - 1])) {
                $license = $licensesFiles[$answer - 1];
                $done = true;
            }
        }
        while(!$done);

        $publishedDate = date('Y-m-d');
        $id_name_quoted = var_export($id_name, true);
        $description_quoted = var_export($description, true);
        $author_quoted = var_export($author, true);

        echo "Creating manifest.php ...\n";
        if(file_exists('manifest.php')) {
            echo "Error: manifest.php already exists.\n";
        }
        else {
            file_put_contents('manifest.php', <<<MANIFEST
<?php
\$manifest = array(
    'name' => $id_name_quoted,
    'acceptable_sugar_versions' => array(),
    'acceptable_sugar_flavors' => array('CE'),
    'author' => $author_quoted,
    'description' => $description_quoted,
    'is_uninstallable' => true,
    'published_date' => '$publishedDate',
    'type' => 'module',
    'version' => '1.0.0',
);
\$installdefs = array(
    'id' => '$id_name',
    'copy' => array(
        array(
            'from' => '<basepath>/source/copy',
            'to' => '.'
        ),
    ),
);

MANIFEST
);
            if(is_dir($spm_home_dir)) {
                file_put_contents($spm_home_dir.'/last_author', $author);
            }
        }

        echo "Creating source directory ...\n";
        if(file_exists('source')) {
            echo "Error: source already exists.\n";
        }
        else {
            mkdir('source');
            mkdir('source/copy');
        }

        echo "Creating README.txt ...\n";
        if(file_exists('README.txt')) {
            echo "Error: README.txt already exists.\n";
        }
        else {
            touch('README.txt');
            file_put_contents('README.txt', $description."\n");
        }

        echo "Creating LICENSE.txt ...\n";
        if(file_exists('LICENSE.txt')) {
            echo "Error: LICENSE.txt already exists.\n";
        }
        else {
            if($license) {
                copy($license, 'LICENSE.txt');
            }
            else {
                touch('LICENSE.txt');
            }
        }
    }
}
