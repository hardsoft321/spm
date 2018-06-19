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

        $installdefs = array();
        $INSTALL_QUESTIONS = array(
            '1' => array('id' => 'pre_install', 'name' => 'pre-install script'),
            '2' => array('id' => 'post_install', 'name' => 'post-install script'),
            '3' => array('id' => 'copy', 'name' => 'copy dir'),
        );
        do {
            echo "Installation content (comma-separated) []:\n";
            foreach($INSTALL_QUESTIONS as $i => $q) {
                echo " $i) {$q['name']}\n";
            }
            $answer = trim(fgets(STDIN));
            $done = true;
            if(!empty($answer)) {
                $items = explode(',', $answer);
                foreach($items as &$item) {
                    $item = trim($item);
                    if(!isset($INSTALL_QUESTIONS[$item])) {
                        echo "Illegal answer: $item\n";
                        $done = false;
                    }
                }
                unset($item);
                if($done) {
                    foreach($items as $item) {
                        $q = $INSTALL_QUESTIONS[$item];
                        $installdefs[$q['id']] = true;
                    }
                }
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
            $copy = '';
            if(!empty($installdefs['copy'])) {
                $copy = "
    'copy' => array(
        array(
            'from' => '<basepath>/source/copy',
            'to' => '.'
        ),
    ),";
            }
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
    'id' => '$id_name',$copy
);

MANIFEST
);
            if(is_dir($spm_home_dir)) {
                file_put_contents($spm_home_dir.'/last_author', $author);
            }
        }

        if(!empty($installdefs['pre_install']) || !empty($installdefs['post_install'])) {
            echo "Creating scripts ...\n";
            if(file_exists('scripts')) {
                echo "Warning: scripts already exists.\n";
            }
            else {
                mkdir('scripts');
            }
        }

        if(!empty($installdefs['pre_install'])) {
            echo "Creating scripts/pre_install.php ...\n";
            if(file_exists('scripts/pre_install.php')) {
                echo "Warning: scripts/pre_install.php already exists.\n";
            }
            else {
                file_put_contents('scripts/pre_install.php', <<<PRE_INSTALL
<?php
function pre_install()
{
}

PRE_INSTALL
);
            }
        }

        if(!empty($installdefs['post_install'])) {
            echo "Creating scripts/post_install.php ...\n";
            if(file_exists('scripts/post_install.php')) {
                echo "Warning: scripts/post_install.php already exists.\n";
            }
            else {
                file_put_contents('scripts/post_install.php', <<<POST_INSTALL
<?php
function post_install()
{
}

POST_INSTALL
);
            }
        }

        if(!empty($installdefs['copy'])) {
            echo "Creating source ...\n";
            if(file_exists('source')) {
                echo "Warning: source already exists.\n";
            }
            else {
                mkdir('source');
            }
        }

        if(!empty($installdefs['copy'])) {
            echo "Creating source/copy ...\n";
            if(file_exists('source/copy')) {
                echo "Warning: source/copy already exists.\n";
            }
            else {
                mkdir('source/copy');
            }
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
