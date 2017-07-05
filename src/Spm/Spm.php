<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 *
 * SugarCRM Package Manager
 */

namespace Spm;

define('SPM_VERSION', '2.0.0-alpha');

class Spm
{
    /**
     * DB for storing per SugarCRM data
     */
    private $db;

    /**
     * DB for storing per user data (system account, not sugar)
     */
    private $userDb;

    /**
     * Directory where script was executed
     */
    public $cwd;

    public $spmPath;
    public $sandboxFile = '.spmsandbox';
    public $lockFile = '.spm.lock';
    public $logFile = 'spm.log';
    public $packagesOverwrites = array();
    public $packagesInStaging = array();
    public $packagesAvailable = array();

    protected static $DEFAULT_ENVIRONMENT_NAME = 'default';

    /**
     * Simple method to find conflicts. Use it if `check` doesn't work.
     */
    public function listRestoreConflicts()
    {
        global $db;
        $q = "SELECT id_name, filename FROM upgrade_history WHERE status = 'installed'";
        $dbRes = $db->query($q);
        $overwrittenFiles = array();
        while($row = $db->fetchByAssoc($dbRes)) {
            $backup_path = clean_path( remove_file_extension($row['filename'])."-restore" );
            if(is_dir($backup_path)) {
                foreach(self::scandirRecursively($backup_path) as $f) {
                    $overwrittenFiles[$f][$row['id_name']] = $row['id_name'];
                }
            }
        }
        foreach($overwrittenFiles as $f => $idArr) {
            if(count($idArr) > 1) {
                echo "$f\n";
                foreach($idArr as $id => $id1) {
                    echo "    $id\n";
                }
                echo "\n";
            }
        }
    }

    public function listInstalled($keyword = null, $options = array())
    {
        global $db;
        $q = "SELECT id_name, version FROM upgrade_history WHERE status = 'installed'";
        if(!empty($keyword)) {
            $q .= " AND UPPER(id_name) LIKE '%".$db->quote(strtoupper($keyword))."%'";
        }
        $q .= " ORDER BY id_name ASC, date_entered DESC";
        $dbRes = $db->query($q);
        if(empty($options['each-version'])) {
            $packs = array();
            while($row = $db->fetchByAssoc($dbRes)) {
                $packs[$row['id_name']][] = $row['version'];
            }
            foreach($packs as $id_name => $versions) {
                echo "  ",$id_name,"-",implode(' -', $versions),"\n";
            }
        }
        else {
            foreach($stmt->fetchAll() as $row) {
                echo "  {$row['id_name']}-{$row['version']}\n";
            }
        }
    }

    public function listLoaded($keyword = null, $options = array())
    {
        global $db;
        $q = "SELECT id_name, version FROM stage WHERE 1";
        if(!empty($keyword)) {
            $q .= " AND id_name LIKE '%".trim($this->getDb()->quote($keyword), "'")."%'";
        }
        $q .= " ORDER BY id_name ASC, version COLLATE NATURAL_CMP DESC";
        $stmt = $this->getDb()->query($q);
        if(empty($options['each-version'])) {
            $packs = array();
            foreach($stmt->fetchAll() as $row) {
                $packs[$row['id_name']][] = $row['version'];
            }
            foreach($packs as $id_name => $versions) {
                echo "  ",$id_name,"-",implode(' -', $versions),"\n";
            }
        }
        else {
            foreach($stmt->fetchAll() as $row) {
                echo "  {$row['id_name']}-{$row['version']}\n";
            }
        }
    }

    public function listAvailable($keyword = null, $options = array())
    {
        $q = "SELECT id_name, version FROM available WHERE 1";
        if(!empty($keyword)) {
            $q .= " AND id_name LIKE '%".trim($this->getUserDb()->quote($keyword), "'")."%'";
        }
        $q .= " ORDER BY id_name ASC, version COLLATE NATURAL_CMP DESC";
        $stmt = $this->getUserDb()->query($q);
        if(empty($options['each-version'])) {
            $packs = array();
            foreach($stmt->fetchAll() as $row) {
                $packs[$row['id_name']][] = $row['version'];
            }
            foreach($packs as $id_name => $versions) {
                echo "  ",$id_name,"-",implode(' -', $versions),"\n";
            }
        }
        else {
            foreach($stmt->fetchAll() as $row) {
                echo "  {$row['id_name']}-{$row['version']}\n";
            }
        }
    }

    public function listFileInfo($fileInfo)
    {
        echo "{$fileInfo['file']}:\n";
        if(!empty($fileInfo['packages'])) {
            foreach($fileInfo['packages'] as $info) {
                echo "  {$info['type']} {$info['filename_from']} from {$info['package_id_name']}-{$info['package_version']}"
                    .($info['modified'] === null ? "" : " ".($info['modified'] ? "(modified)" : "(not modified)"))
                    .(!empty($info['overwrites']) ? ' - overwrites '.implode(' ', $info['overwrites']) : '')
                    ."\n";
            }
        }
        if(!empty($fileInfo['original'])) {
            echo "  SugarCRM ".($fileInfo['original']['modified'] ? "(modified)" : "(not modified)")."\n";
        }
        if(!empty($fileInfo['autogenerated'])) {
            echo "  autogenerated\n";
        }
    }

    public function getFileInfo($file)
    {
        $fileInfo = array(
            'file' => $file,
            'packages' => array(),
            'original' => null,
            'conflict' => false,
        );
        $stmt = $this->getDb()->prepare("SELECT package_id_name, package_version, crc, type, filename_from FROM files WHERE filename = :filename ORDER BY date_entered DESC");
        $stmt->execute(array(
            ':filename' => $file,
        ));
        $rows = $stmt->fetchAll();
        if(!empty($rows)) {
            $crc = hash_file('crc32b', $file);
            $firstRow = reset($rows);
            $overwrittenBy = $firstRow['package_id_name'];
            $overwrites = isset($this->packagesOverwrites[$firstRow['package_id_name']]) ? $this->packagesOverwrites[$firstRow['package_id_name']] : array();
        }
        $allNotModified = true;
        foreach($rows as $info) {
            $info['modified'] = $info['crc'] === null ? null : sprintf("%u",$info['crc']) != hexdec($crc);
            if($info['modified']) {
                $allNotModified = false;
            }
            $info['overwrites'] = isset($this->packagesOverwrites[$info['package_id_name']]) ? $this->packagesOverwrites[$info['package_id_name']] : array();
            $fileInfo['packages'][] = $info;
            if($info['package_id_name'] != $overwrittenBy && !in_array($info['package_id_name'], $overwrites)) {
                $fileInfo['conflict'] = true;
            }
        }
        if($allNotModified) {
            $fileInfo['conflict'] = false;
        }
        if($this->isFileAutogenerated($file)) {
            $fileInfo['autogenerated'] = true;
            $fileInfo['conflict'] = true;
        }

        static $md5 = array();
        if(empty($md5) && file_exists('files.md5'))
        {
            include('files.md5');
            $md5 = $md5_string;
        }
        if(isset($md5['./' . $file])) {
            $fileInfo['original'] = array(
                'modified' => md5_file($file) != $md5['./' . $file],
            );
        }
        return $fileInfo;
    }

    public function isFileAutogenerated($file)
    {
        $pathPatterns = array(
            '#^custom/modules/[^/]+/logic_hooks\.php$#',
            '#^custom/modules/logic_hooks\.php$#',
            '#^custom/modules/[^/]+/Ext/.+$#',
            '#^custom/application/Ext/.+$#',
            '#^custom/Extension/application/Ext/TableDictionary/.+$#',
            '#^custom/Extension/application/Ext/Include/.*\.php$#',
            //при добавлении шаблона добавить также в sql в функции check
        );
        foreach($pathPatterns as $pattern) {
            if(preg_match($pattern, $file)) {
                return true;
            }
        }
        return false;
    }

    public function check($showAll = false)
    {
        $stmt = $this->getDb()->prepare(
        "SELECT filename FROM (
            SELECT DISTINCT filename, package_id_name FROM files
        ) t
        GROUP BY filename
        HAVING count(*) > 1
        UNION
        SELECT DISTINCT filename FROM files
        WHERE filename LIKE 'custom/%/logic_hooks.php'
            OR filename LIKE 'custom/modules/%/Ext/%'
            OR filename LIKE 'custom/application/Ext/%'
            OR filename LIKE 'custom/Extension/application/Ext/TableDictionary/%'
            OR filename LIKE 'custom/Extension/application/Ext/Include/%.php'
        ORDER BY filename");
        if(!$stmt) {
            throw new \Exception(implode(' ', $this->getDb()->errorInfo()));
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach($rows as $file) {
            $fileInfo = $this->getFileInfo($file['filename']);
            if($fileInfo['conflict'] || $showAll) {
                $this->listFileInfo($fileInfo);
            }
        }
    }

    public function checkModified()
    {
        echo "\nModified:\n";
        require_once 'include/utils/file_utils.php';
        $md5_string_calculated = $this->getMd5Array(); //TODO: unnecessary md5
        foreach($md5_string_calculated as $filename => $md5) {
            if(substr($filename, 0, 2) == './') {
                $filename = substr($filename, 2);
            }
            $fileInfo = $this->getFileInfo($filename);
            $newOrModified = empty($fileInfo['original']) || $fileInfo['original']['modified'];
            if($newOrModified && empty($fileInfo['autogenerated'])) {
                if(empty($fileInfo['packages'])) {
                    $this->listFileInfo($fileInfo);
                }
                else {
                    $lastPackage = reset($fileInfo['packages']);
                    if($lastPackage['modified']) {
                        $this->listFileInfo($fileInfo);
                    }
                }
            }
        }
    }

    public function install($id_name, $version = null, $options = array())
    {
        global $db;
        $stmt = $this->getDb()->prepare("SELECT id_name, version, filename, type FROM stage WHERE id_name = :id_name AND (version = :version OR :version IS NULL) ORDER BY version COLLATE NATURAL_CMP DESC");
        $stmt->execute(array(
            ':id_name' => $id_name,
            ':version' => $version,
        ));
        $packages = $stmt->fetchAll();
        if(empty($packages)) {
            throw new \Exception("Package $id_name not found. It must be uploaded to SugarCRM.");
        }
        if(count($packages) > 1) {
            $msg = "There are some files with the same id:";
            foreach($packages as $pack) {
                $msg .= "\n  {$pack['version']} {$pack['filename']}";
            }
            throw new \Exception($msg);
        }
        $pack = reset($packages);
        if($pack['type'] != 'module' && $pack['type'] != 'langpack') {
            throw new \Exception("Installing package of type '{$pack['type']}' is not implemented.");
        }
        $version = $pack['version'];
        $file_to_install = $pack['filename'];
        if(!is_file($file_to_install)) {
            throw new \Exception("File $file_to_install not found.");
        }

        $q = "SELECT version FROM upgrade_history WHERE UPPER(id_name) = '".$db->quote(strtoupper($id_name))
            ."' ORDER BY date_entered DESC";
        $dbRes = $db->query($q);
        while($row = $db->fetchByAssoc($dbRes)) {
            if(strnatcmp($row['version'], $version) != -1) {
                throw new \Exception("You are trying to install older version. Version {$row['version']} already installed.");
            }
        }

        if(!empty($options['lock-file'])) {
            $this->lockFile = $options['lock-file'];
        }
        if(!empty($options['log-file'])) {
            $this->logFile = $options['log-file'];
        }

        self::createLock("install $id_name-$version");
        echo "Installing package {$pack['id_name']} {$pack['version']} file $file_to_install ...\n";
        $sugarcrmLogFile = $GLOBALS['sugar_config']['logger']['file']['name'].$GLOBALS['sugar_config']['logger']['file']['ext'];
        $md5 = file_exists($sugarcrmLogFile) ? md5_file($sugarcrmLogFile) : md5('');
        $pm = new Sugar\PackageManager();
        $file_to_install = \UploadStream::path($file_to_install);
        $_REQUEST['install_file'] = $file_to_install;
        $pm->options = $options;
        $pm->performInstall($file_to_install);
        $this->log("install   {$pack['id_name']}-{$pack['version']} ".implode(',', array_keys($options)));
        if(md5_file($sugarcrmLogFile) != $md5) {
            echo "sugarcrm.log was modified; logger.level = {$GLOBALS['sugar_config']['logger']['level']}.\n";
        }
        self::releaseLock();
    }

    public function isUploaded($id_name, $version)
    {
        $stmt = $this->getDb()->prepare("SELECT 1 FROM stage WHERE id_name = :id_name AND version = :version LIMIT 1");
        $stmt->execute(array(
            ':id_name' => $id_name,
            ':version' => $version,
        ));
        $rows = $stmt->fetchAll();
        return !empty($rows);
    }

    public function isInstalled($id_name)
    {
        global $db;
        $q = "SELECT id_name FROM upgrade_history WHERE UPPER(id_name) = '".$db->quote(strtoupper($id_name))."'";
        $dbRes = $db->query($q);
        return (bool)$db->fetchByAssoc($dbRes);
    }

    public function uninstall($id_name, $version = null, $options = array())
    {
        global $db;
        $q = "SELECT id_name, version, type, filename FROM upgrade_history WHERE UPPER(id_name) = '".$db->quote(strtoupper($id_name))
            ."' ORDER BY date_entered DESC";
        $dbRes = $db->query($q);
        $packages = array();
        while($row = $db->fetchByAssoc($dbRes)) {
            $packages[] = $row;
        }
        if(empty($packages)) {
            throw new \Exception("Package with id {$id_name} is not installed");
        }
        $package = reset($packages);
        if($package['type'] != 'module' && $package['type'] != 'langpack') {
            throw new \Exception("Uninstalling package of type '{$package['type']}' is not implemented.");
        }
        $lastVersion = $package['version'];
        if(!$version) {
            $version = $lastVersion;
        }
        elseif($version != $lastVersion) {
            throw new \Exception("You should uninstall last installed version (namely $lastVersion)");
        }
        $id_name = $package['id_name'];
        $version = $package['version'];

        $target_manifest = remove_file_extension($package['filename']) . "-manifest.php";
        if(file_exists($target_manifest)) {
            $manifest = null;
            include $target_manifest;
            if(isset($manifest['is_uninstallable']) && !$manifest['is_uninstallable'] && empty($options['not-uninstallable'])) {
                throw new \Exception("Package is not uninstallable. But you can use option --not-uninstallable to uninstall it.");
            }
        }

        if(!empty($options['lock-file'])) {
            $this->lockFile = $options['lock-file'];
        }
        if(!empty($options['log-file'])) {
            $this->logFile = $options['log-file'];
        }

        //dependencies not checked here at all, Sugar checks straight dependencies, but it should checks reverse dependencies
        self::createLock("uninstall $id_name-$version");
        echo "Uninstalling package $id_name $version ...\n";
        $sugarcrmLogFile = $GLOBALS['sugar_config']['logger']['file']['name'].$GLOBALS['sugar_config']['logger']['file']['ext'];
        $md5 = file_exists($sugarcrmLogFile) ? md5_file($sugarcrmLogFile) : md5('');
        $pm = new Sugar\PackageManager();
        $GLOBALS['mi_remove_tables'] = !empty($options['remove-tables']);
        $pm->options = $options;
        $pm->performUninstall($id_name, $version);
        echo "\n";
        $this->log("uninstall {$id_name}-{$version} ".implode(',', array_keys($options)));
        if(md5_file($sugarcrmLogFile) != $md5) {
            echo "sugarcrm.log was modified; logger.level = {$GLOBALS['sugar_config']['logger']['level']}.\n";
        }
        self::releaseLock();
    }

    public function remove($id_name, $version = null)
    {
        $stmt = $this->getDb()->prepare("SELECT version, filename FROM stage WHERE id_name = :id_name AND (version = :version OR :version IS NULL) ORDER BY version COLLATE NATURAL_CMP DESC");
        $stmt->execute(array(':id_name' => $id_name, ':version' => $version));
        $packages = $stmt->fetchAll();
        if(empty($packages)) {
            throw new \Exception("Package $id_name $version not found.");
        }
        require_once('ModuleInstall/PackageManager/PackageController.php');
        $pmc = new \PackageController();
        foreach($packages as $pack) {
            echo "Removing {$pack['filename']} ...\n";
            $hash = md5($pack['filename']);
            $_SESSION['file2Hash'][$hash] = $pack['filename'];
            $_REQUEST['file'] = $hash;
            ob_start();
            $pmc->remove();
            ob_clean();
            unset($_SESSION['file2Hash'][$hash]);
        }
    }

    public function upload($id_name, $version = null, $options = array())
    {
        $stmt = $this->getUserDb()->prepare("SELECT version, filename FROM available WHERE id_name = :id_name AND (version = :version OR :version IS NULL) ORDER BY version COLLATE NATURAL_CMP DESC LIMIT 1");
        if(!$stmt) {
            throw new \Exception(implode(' ', $this->getUserDb()->errorInfo()));
        }
        $stmt->execute(array(':id_name' => $id_name, ':version' => $version));
        $row = $stmt->fetch();
        if(empty($row)) {
            throw new \Exception("Package $id_name $version not found (spm_path = {$this->spmPath}).");
        }
        $manifest_file = $row['filename'].'/manifest.php';
        if(!is_file($manifest_file)) {
            throw new \Exception("File {$manifest_file} not found.");
        }
        $manifest = $installdefs = null;
        include $manifest_file;
        $id_name1 = !empty($installdefs['id']) ? $installdefs['id'] : $manifest['name'];
        $version1 = $manifest['version'];
        if(strcasecmp($id_name1, $id_name) != 0 || ($version && strcasecmp($version1, $version) != 0)) {
            throw new \Exception("Id/version mismatch.");
        }
        require_once 'modules/UpgradeWizard/uw_utils.php';
        $err = validate_manifest($manifest);
        if($err) {
            throw new \Exception($err);
        }
        /* Проверка синтаксиса */
        if(empty($options['no-php-check'])) {
            $command = "cd {$row['filename']}; pwd; find . -name \"*.php\" -exec php -l {} \; ";
            $out = `$command`;
            if(strpos($out, 'Errors parsing')) {
                throw new \Exception($out);
            }
        }

        $upgrade_zip_type = $manifest['type'];
        // exclude the bad permutations
        if ($upgrade_zip_type != "module" && $upgrade_zip_type != "theme" && $upgrade_zip_type != "langpack") {
            throw new \Exception("'$upgrade_zip_type' is not acceptable type.");
        }

        $base_filename = "{$id_name1}-{$version1}.zip";
        $base_upgrade_dir = "upload://upgrades";
        $target_path = "$base_upgrade_dir/$upgrade_zip_type/$base_filename";
        if(file_exists($target_path)) {
            throw new FileAlreadyExistsException("File $target_path already exists.");
        }

        echo "Uploading package $id_name1 {$row['version']} from {$row['filename']} ...\n";
        if(!is_dir("$base_upgrade_dir/$upgrade_zip_type") && !\UploadStream::mkdir("$base_upgrade_dir/$upgrade_zip_type", 0770, STREAM_MKDIR_RECURSIVE)) {
            throw new \Exception("Cannot create directory $base_upgrade_dir/$upgrade_zip_type.");
        };
        /* Создание zip-пакета */
        $command = "cd {$row['filename']}; pwd; zip -r '".getcwd()."/".\UploadStream::path($target_path)."' ./* -x \"*.git*\"";
        $out = `$command`;
        $hasErrors = strpos($out, 'warning: ');
        if(!is_file($target_path)) {
            throw new \Exception("Cannot create file $target_path.\n".$out);
        }

        $target_manifest = remove_file_extension( $target_path ) . "-manifest.php";
        copy( $manifest_file, $target_manifest );

        if( isset($manifest['icon']) && $manifest['icon'] != "" ){
             $icon_location = $row['filename'].'/'.$manifest['icon'];
             copy($icon_location, remove_file_extension( $target_path )."-icon.".pathinfo($icon_location, PATHINFO_EXTENSION));
        }

        if($hasErrors) {
            echo "File uploaded, but there was warnings: ",$out;
        }
    }

    public function zip($id_name, $version = null, $options = array())
    {
        $stmt = $this->getUserDb()->prepare("SELECT version, filename FROM available WHERE id_name = :id_name AND (version = :version OR :version IS NULL) ORDER BY version COLLATE NATURAL_CMP DESC LIMIT 1");
        if(!$stmt) {
            throw new \Exception("Error on getting available packages");
        }
        $stmt->execute(array(':id_name' => $id_name, ':version' => $version));
        $row = $stmt->fetch();
        if(empty($row)) {
            throw new \Exception("Package $id_name $version not found (spm_path = {$this->spmPath}).");
        }
        $manifest_file = $row['filename'].'/manifest.php';
        if(!is_file($manifest_file)) {
            throw new \Exception("File {$manifest_file} not found.");
        }
        $manifest = $installdefs = null;
        include $manifest_file;
        $id_name1 = !empty($installdefs['id']) ? $installdefs['id'] : $manifest['name'];
        $version1 = $manifest['version'];
        if(strcasecmp($id_name1, $id_name) != 0 || ($version && strcasecmp($version1, $version) != 0)) {
            throw new \Exception("Id/version mismatch.");
        }
        /* Проверка синтаксиса */
        if(empty($options['no-php-check'])) {
            $command = "cd {$row['filename']}; pwd; find . -name \"*.php\" -exec php -l {} \; ";
            $out = `$command`;
            if(strpos($out, 'Errors parsing')) {
                throw new \Exception($out);
            }
        }

        $i = 0;
        do {
            $target_path = $this->cwd."/{$id_name1}-{$version1}".($i ? "($i)" : "").".zip";
            $i++;
        }
        while(file_exists($target_path));

        echo "Creating file {$target_path} ...\n";
        /* Создание zip-пакета */
        $command = "cd {$row['filename']}; pwd; zip -r '".$target_path."' ./* -x \"*.git*\"";
        $out = `$command`;
        $hasErrors = strpos($out, 'warning: ');
        if(!is_file($target_path)) {
            throw new \Exception("Cannot create file $target_path.\n".$out);
        }

        if($hasErrors) {
            echo "File created, but there was warnings: ",$out;
        }
    }

    public function updateStage()
    {
        $this->repairManifests();
        $pm = new Sugar\PackageManager();
        $packs = $pm->getPackagesInStaging();
        $this->packagesInStaging = array();
        $this->getDb()->exec("DROP TABLE IF EXISTS stage");
        $this->getDb()->exec("CREATE TABLE stage (id_name TEXT COLLATE NOCASE, version TEXT, filename TEXT, type TEXT)");
        $stmt = $this->getDb()->prepare("INSERT INTO stage (id_name, version, filename, type) VALUES (:id_name, :version, :filename, :type)");
        $stmt->bindParam(':id_name', $id_name);
        $stmt->bindParam(':version', $version);
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':type', $type);
        foreach($packs as $key => $pack) {
            if(!empty($pack['name'])) {
                $upgrade_content = hashToFile($pack['file']);
                $target_manifest = remove_file_extension( $upgrade_content ) . '-manifest.php';
                if(file_exists($target_manifest)) {
                    $manifest = $installdefs = null;
                    require($target_manifest);
                    $id_name = !empty($installdefs['id']) ? $installdefs['id'] : null;
                    $version = $manifest['version'];
                    $filename = hashToFile($pack['file']);
                    $type = $manifest['type'];
                    $stmt->execute();
                    $this->packagesInStaging["$id_name-$version"] = array(
                        'id_name' => $id_name,
                        'version' => $version,
                        'filename' => $filename,
                        'type' => $type,
                    );
                }
                else {
                    echo "Warning: $target_manifest not exists while update stage\n";
                }
            }
        }
    }

    public function repairManifests()
    {
        $zips = glob(\UploadStream::path("upload://upgrades").'/{module,langpack}/*.zip', GLOB_BRACE);
        foreach($zips as $zip) {
            $target_manifest = remove_file_extension($zip) . '-manifest.php';
            if(!file_exists($target_manifest)) {
                copy("zip://$zip#manifest.php", $target_manifest);
            }
        }
    }

    public function updateAvailable($options = array())
    {
        $this->getUserDb()->exec("DROP TABLE IF EXISTS available");
        $this->getUserDb()->exec("CREATE TABLE available (id_name TEXT COLLATE NOCASE, version TEXT, filename TEXT)");
        $stmt = $this->getUserDb()->prepare("INSERT INTO available (id_name, version, filename) VALUES (:id_name, :version, :filename)");
        $stmt->bindParam(':id_name', $id_name);
        $stmt->bindParam(':version', $version);
        $stmt->bindParam(':filename', $filename);
        if(!empty($options['spm-path'])) {
            $this->spmPath = $options['spm-path'];
        }
        if(empty($this->spmPath)) {
            $this->spmPath = getenv('SPM_PATH');
        }
        if(empty($this->spmPath)) {
            echo "No SPM_PATH defined. You may define environment variable SPM_PATH or option --spm-path=<path>.\n";
        }
        $paths = explode(':', $this->spmPath);
        foreach($paths as $path) {
            if($path) {
                if(is_dir($path)) {
                    $packs = self::searchPackages(rtrim($path, '/'));
                    foreach($packs as $key => $pack) {
                        extract($pack);
                        $stmt->execute();
                        $this->packagesAvailable[] = $pack;
                    }
                }
                else {
                    echo "Warning: directory $path not exists.\n";
                }
            }
        }
    }

    public function searchFileInAvailable($file)
    {
        $stmt = $this->getUserDb()->prepare("SELECT filename FROM available WHERE id_name = :id_name ORDER BY version COLLATE NATURAL_CMP DESC");
        if(!$stmt) {
            throw new \Exception(implode(' ', $this->getUserDb()->errorInfo()));
        }
        $files = array();
        $fileInfo = $this->getFileInfo($file);
        if(!empty($fileInfo['packages'])) {
            foreach($fileInfo['packages'] as $info) {
                $stmt->execute(array(':id_name' => $info['package_id_name']));
                foreach($stmt->fetchAll() as $packRow) {
                    $f = realpath($packRow['filename'].'/'.$info['filename_from']);
                    if(file_exists($f)) {
                        $files[$f] = array(
                            'path' => $f,
                            'info' => $info,
                        );
                    }
                }
            }
        }
        return $files;
    }

    protected static function searchPackages($path, $prefix = '')
    {
        $packages = array();
        if(is_file($path.'/manifest.php')) {
            $manifest = $installdefs = null;
            include $path.'/manifest.php'; //user must add only trusted sources
            if((!empty($manifest['name']) || !empty($installdefs['id'])) && !empty($manifest['version'])) {
                $packages[] = array(
                    'filename' => $path,
                    'id_name' => !empty($installdefs['id']) ? $installdefs['id'] : $manifest['name'],
                    'version' => $manifest['version'],
                );
                return $packages;
            }
        }
        foreach(scandir($path) as $f) {
            if($f != '.' && $f != '..') {
                if(is_dir($path.'/'.$f)) {
                    $packages = array_merge($packages, self::searchPackages($path.'/'.$f, $prefix.$f.'/'));
                }
            }
        }
        return $packages;
    }

    public function updateCopiedFiles()
    {
        global $db;
        if(!class_exists('\ZipArchive')) {
            throw new \Exception("ZipArchive class required but not exists.");
        }
        $this->getDb()->exec("DROP TABLE IF EXISTS files");
        $this->getDb()->exec("CREATE TABLE files (filename TEXT, filename_from TEXT, package_id_name TEXT COLLATE NOCASE, package_version TEXT, crc INT, type TEXT, date_entered TEXT)");
        $stmt = $this->getDb()->prepare("INSERT INTO files (filename, filename_from, package_id_name, package_version, crc, type, date_entered)
            VALUES (:filename, :filename_from, :id_name, :version, :crc, :type, :date_entered)");
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':filename_from', $filename_from);
        $stmt->bindParam(':id_name', $id_name);
        $stmt->bindParam(':version', $version);
        $stmt->bindParam(':crc', $crc);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':date_entered', $date_entered);

        $sql = "SELECT id_name, version, filename, date_entered FROM upgrade_history ORDER BY id_name ASC, date_entered DESC";
        $dbRes = $db->query($sql);
        $sugarPath = getcwd();
        $mi = new Sugar\ModuleInstaller();
        while($row = $db->fetchByAssoc($dbRes)) {
            $manifestFile = remove_file_extension($row['filename']).'-manifest.php';
            if(file_exists($manifestFile) && file_exists($row['filename'])) {
                $id_name = $row['id_name'];
                $version = $row['version'];
                $package_filename = $row['filename'];
                $date_entered = $row['date_entered'];
                $manifest = $installdefs = null;
                require $manifestFile;
                if(empty($installdefs)) {
                    continue;
                }
                $copyDefs = array();

                if(!empty($installdefs['copy'])) {
                    foreach($installdefs['copy'] as $cp) {
                        $from = clean_path(ltrim(str_replace('<basepath>', '', $cp['from']), '/'));
                        $to = ltrim(substr(realpath($sugarPath.'/'.str_replace('<basepath>', '.', $cp['to'])), strlen($sugarPath)), '/');
                        $copyDefs[] = array(
                            'from' => $from,
                            'to' => $to,
                            'type' => 'copy',
                        );
                    }
                }

                if(!empty($installdefs['image_dir'])) {
                    $from = clean_path(ltrim(str_replace('<basepath>', '', $installdefs['image_dir']), '/'));
                    $to = ltrim(substr(realpath($sugarPath.'/custom/themes'), strlen($sugarPath)), '/');
                    $copyDefs[] = array(
                        'from' => $from,
                        'to' => $to,
                        'type' => 'image_dir',
                    );
                }

                foreach($mi->extensions as $extname => $ext) {
                    $section = $ext["section"];
                    if(!method_exists($mi, "install_$extname") && !empty($ext["section"])) {
                        $extname = $ext["extdir"];
                        $module = isset($ext['module']) ? $ext['module'] : '';
                        if(isset($installdefs[$section])) {
                            foreach($installdefs[$section] as $item) {
                                if(!empty($module)) {
                                    $item['to_module'] = $module;
                                }
                                if(empty($item['to_module'])) {
                                    continue;
                                }
                                if(isset($item['from'])) {
                                    $from = ltrim(str_replace('<basepath>', '', $item['from']), '/');
                                } else {
                                    $from = '';
                                }
                                if($item['to_module'] == 'application') {
                                    $path = "custom/Extension/application/Ext/$extname";
                                } else {
                                    $path = "custom/Extension/modules/{$item['to_module']}/Ext/$extname";
                                }
                                if(isset($item["name"])) {
                                    $target = $item["name"];
                                } else if (!empty($from)){
                                    $target = basename($from, ".php");
                                } else {
                                    $target = $id_name;
                                }
                                $copyDefs[] = array(
                                    'from' => $from ? $from : 'manifest.php',
                                    'to' => "$path/$target.php",
                                    'type' => $section,
                                );
                            }
                        }
                    }
                }

                if(!empty($installdefs['language'])) {
                    foreach($installdefs['language'] as $packs) {
                        if(empty($packs['from']) || empty($packs['to_module'])) {
                            continue;
                        }
                        $from = ltrim(str_replace('<basepath>', '', $packs['from']), '/');

                        $path = 'custom/Extension/modules/' . $packs['to_module']. '/Ext/Language';
                        if($packs['to_module'] == 'application'){
                            $path ='custom/Extension/' . $packs['to_module']. '/Ext/Language';
                        }
                        $path .= '/'.$packs['language'].'.'. $id_name . '.php';

                        $copyDefs[] = array(
                            'from' => $from,
                            'to' => $path,
                            'type' => 'language',
                        );
                    }
                }

                if(!empty($installdefs['relationships'])) {
                    foreach($installdefs['relationships'] as $relationship) {
                        if(empty($relationship['meta_data'])) {
                            continue;
                        }
                        $from = ltrim(str_replace('<basepath>', '', $relationship['meta_data']), '/');
                        $filename = basename ( $relationship [ 'meta_data' ] ) ;
                        $path = 'custom/metadata/' . $filename;
                        $copyDefs[] = array(
                            'from' => $from,
                            'to' => $path,
                            'type' => 'relationships',
                        );
                        //TODO: module_vardefs, module_layoutdefs
                    }
                }

                //TODO: custom_fields, dashlets, dcaction, connectors
                if(empty($copyDefs)) {
                    continue;
                }

                $zip = new \ZipArchive;
                $res = $zip->open($package_filename);
                if ($res === TRUE) {
                    for($i = 0; $i < $zip->numFiles; $i++) {
                        $stat = $zip->statIndex($i);
                        if(!empty($stat['crc'])) {
                            $filename = false;
                            $filename_from = false;
                            $cp1 = false;
                            foreach($copyDefs as $cp) {
                                if(strpos($stat['name'], $cp['from']) === 0) {
                                    $filename = rtrim(($cp['to'] ? $cp['to'].'/' : '').ltrim(substr($stat['name'], strlen($cp['from'])), '/'), '/');
                                    $filename_from = $stat['name'];
                                    $cp1 = $cp;
                                    break;
                                }
                            }
                            if($filename) {
                                $crc = $cp1['from'] == 'manifest.php' ? null : $stat['crc'];
                                $type = $cp1['type'];
                                $stmt->execute();
                            }
                        }
                    }
                    $zip->close();
                } else {
                    echo "Error: zip {$row['filename']} not opened, code: ", $res, "\n";
                }
            }
        }
    }

    public function repair($options = array())
    {
        $randc = new Sugar\RepairAndClear();
        $show_output = !empty($options['v']);
        if($show_output) {
            ob_start();
            $randc->oneLine = false;
        }
        $randc->repairAndClearAll(array('clearAll'),array(translate('LBL_ALL_MODULES')), false, $show_output);
        if($show_output) {
            echo "\n";
            ob_flush();
        }
        if($randc->sql) {
            echo "/* Differences found between database and vardefs. */
/* You may run `spm dbquery \"<sql>\"`. */\n\n";
            echo $randc->sql,"\n\n";
        }

        require_once 'modules/Configurator/Configurator.php';
        $configuratorObj = new \Configurator();
        $configuratorObj->loadConfig();
        $js_custom_version = empty($configuratorObj->config['js_custom_version']) || !is_numeric($configuratorObj->config['js_custom_version'])
            ? 100 : $configuratorObj->config['js_custom_version'];
        $configuratorObj->config['js_custom_version'] = $js_custom_version + 1;
        $js_lang_version = empty($configuratorObj->config['js_lang_version']) || !is_numeric($configuratorObj->config['js_lang_version'])
            ? 100 : $configuratorObj->config['js_lang_version'];
        $configuratorObj->config['js_lang_version'] = $js_lang_version + 1;
        $configuratorObj->saveConfig();
    }

    public function dbquery($sql, $allowedQueries = null, $options = array())
    {
        global $db;
        $sql = str_replace(
            array(
                "\n",
                '&#039;',
            ),
            array(
                '',
                "'",
            ),
            preg_replace('#(/\*.+?\*/\n*)#', '', $sql)
        );
        $toRun = array();
        $toSkip = array();
        foreach (explode(";", $sql) as $stmt) {
            $stmt = trim($stmt);

            if (!empty ($stmt)) {
                if($allowedQueries !== null && empty($options['f']) && !in_array($stmt, $allowedQueries)) {
                    $toSkip[] = $stmt;
                }
                else {
                    $toRun[] = $stmt;
                }
            }
        }

        if(!empty($toSkip)) {
            if(empty($options['s'])) {
                throw new \Exception("Statement is not allowed. Write it into .spmqueries.php file or use command options to force query: "
                    .implode(";", $toSkip));
            }
            foreach($toSkip as $stmt) {
                echo "Skip query: $stmt\n";
            }
        }

        foreach($toRun as $stmt) {
                echo "Query: $stmt\n";
                $res = $db->query($stmt);
                $error = $db->checkError();
                if(!$res || $error) {
                    throw new \Exception("Error executing sql query");
                }
                $head = $db->getFieldsArray($res);
                if(!empty($head)) {
                    while($row = $db->fetchByAssoc($res, false)) {
                        print_r($row);
                    }
                }
                echo "Done\n";
        }
    }

    protected function getDb()
    {
        if(!$this->db) {
            try {
                $this->db = new \PDO('sqlite::memory:');
                $this->db->sqliteCreateCollation('NATURAL_CMP', 'strnatcmp');
            }
            catch(\PDOException $e) {
                echo "Connection to sqlite failed\n";
                $this->db = new Pdo\PDO();
                $this->db->spm = $this;
                $this->db->lastException = $e;
            }
        }
        return $this->db;
    }

    protected function getUserDb()
    {
        if(!$this->userDb) {
            try {
                $this->userDb = new \PDO('sqlite::memory:');
                $this->userDb->sqliteCreateCollation('NATURAL_CMP', 'strnatcmp');
            }
            catch(\PDOException $e) {
                echo "Connection to sqlite failed\n";
                $this->userDb = new Pdo\PDO();
                $this->userDb->spm = $this;
                $this->userDb->lastException = $e;
            }
        }
        return $this->userDb;
    }

    protected function log($msg)
    {
        file_put_contents($this->logFile, date('Y-m-d H:i:s')." [".get_current_user()."]: ".$msg."\n", FILE_APPEND);
    }

    protected function hasLock() {
        return file_exists($this->lockFile);
    }

    protected function createLock($action = '')
    {
        if(file_exists($this->lockFile)) {
            throw new \Exception("Probably other installation in progress or exited with error. See {$this->lockFile} file.");
        }
        $r = file_put_contents($this->lockFile,
"Lock file generated by spm utility before installing/uninstalling package and removed after success.

action: {$action}
user: ".get_current_user()."
timestamp: ".date("Y-m-d H:i:s")."
"
);
        if($r === false) {
            throw new \Exception("Fail to write {$this->lockFile} file");
        }
    }

    protected function releaseLock()
    {
        if(file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }

    /**
     * https://github.com/fayebsg/sugarcrm-cli.git
     *  + pea
     */
    public static function enterSugar()
    {
        global $sugar_config;
        if(!self::chdirToSugarRoot()) {
            return false;
        }

        if (!defined('sugarEntry')) {
            define('sugarEntry', true);
        }

        require('config.php');
        $GLOBALS['sugar_config'] = $sugar_config;
        require_once('include/entryPoint.php');

        // Scope is messed up due to requiring files within a function
        // We need to explicitly assign these variables to $GLOBALS
        foreach (get_defined_vars() as $key => $val) {
            $GLOBALS[$key] = $val;
        }

        if (empty($current_language)) {
            $current_language = $sugar_config['default_language'];
        }

        $GLOBALS['app_list_strings'] = return_app_list_strings_language($current_language);
        $GLOBALS['app_strings'] = return_application_language($current_language);
        $GLOBALS['mod_strings'] = array_merge(
            return_module_language($current_language, "Administration"),
            return_module_language($current_language, "UpgradeWizard")
        );

        global $current_user;
        $current_user = new \User();
        $current_user->getSystemUser();

        if (\UploadStream::getSuhosinStatus() == false) {
            echo "Warning: ",htmlspecialchars_decode($GLOBALS['app_strings']['ERR_SUHOSIN']),"\n";
        }
        return true;
    }

    protected static function chdirToSugarRoot($path = null)
    {
        if($path === null) {
            $path = getcwd();
        }
        if(self::isSugarDir($path)) {
            if(getcwd() != $path) {
                chdir($path);
            }
            return true;
        }
        $parentPath = dirname($path);
        if($parentPath == $path) {
            return false;
        }
        return self::chdirToSugarRoot($parentPath);
    }

    public static function isSugarDir($path = null)
    {
        if($path === null) {
            $path = getcwd();
        }
        return file_exists($path.'/sugar_version.php') && file_exists($path.'/include/entryPoint.php');
    }

    public static function cleanupSugar()
    {
        sugar_cleanup();
    }

    public static function scandirRecursively($path, $prefix = '')
    {
        $files = array();
        foreach(scandir($path) as $f) {
            if($f != '.' && $f != '..') {
                if(is_dir($path.'/'.$f)) {
                    $files = array_merge($files, self::scandirRecursively($path.'/'.$f, $prefix.$f.'/'));
                }
                else {
                    $files[] = $prefix.$f;
                }
            }
        }
        return $files;
    }

    public function sandboxInit($options = array())
    {
        global $db;
        if($this->hasSandbox()) {
            throw new \Exception("Sandbox file {$this->sandboxFile} already exists");
        }
        echo "Creating file {$this->sandboxFile}\n";
        file_put_contents($this->sandboxFile,
"; For each package use section with unique name.
; `path` defines relative path to folder with package sources.
; `id` defines package id_name.
; `version` must be specified.
; `environment` (optional) means that this package must be installed in some custom
;     environment other than production. Multiple environments separated by spaces.
; `overwrite` (optional) means that this package overwrites some packages and
;     must be reinstalled after them. Their id_names separated by spaces should be
;     defined here.
;
; Example:
; [example]
;     path = packages/example_dir
;     id = example_id
;     version = 1.0.0


");

        $packagesInDb = array();
        $q = "SELECT id_name, version FROM upgrade_history WHERE status = 'installed' ORDER BY date_entered";
        $dbRes = $db->query($q);
        while($row = $db->fetchByAssoc($dbRes)) {
            $key = $row['id_name'].(empty($options['no-merge']) ? '' : '-'.$row['version']);
            $packagesInDb[$key] = $row;
        }
        foreach($packagesInDb as $key => $row) {
            file_put_contents($this->sandboxFile, "
; [$key]
;     path =
;     id = {$row['id_name']}
;     version = {$row['version']}
", FILE_APPEND);
        }
    }

    public function hasSandbox()
    {
        return file_exists($this->sandboxFile);
    }

    public function sandboxStatus($options = array())
    {
        $statusData = $this->getSandboxStatusData($options);
        if(!empty($statusData['incorrectEnvironments'])) {
            echo "Warning: unknown environments - ".implode(' ', $statusData['incorrectEnvironments'])."\n";
        }
        echo "Current environments: ".implode(' ', $statusData['currentEnvironments'])
                .". Available environments: ".implode(' ', $statusData['environments']).".\n";
        if(!empty($statusData['sandboxUnknown'])) {
            echo "New installed packages found (write to {$this->sandboxFile}):\n";
            foreach($statusData['sandboxUnknown'] as $row) {
                echo "  {$row['id_name']}-{$row['version']}\n";
            }
        }
        if(!empty($statusData['unknownReinstall'])) {
            echo "Packages to reinstall after installing these packages:\n";
            foreach($statusData['unknownReinstall'] as $pack) {
                echo "  {$pack['id']}-{$pack['version']}".(!empty($pack['environment']) ? " ({$pack['environment']})" : "")."\n";
            }
        }
        echo "\n";
        if(!empty($statusData['sandboxNotInstalled'])) {
            echo "Packages not yet installed (run `spm sandbox-install`):\n";
            foreach($statusData['sandboxNotInstalled'] as $pack) {
                echo "  {$pack['id']}-{$pack['version']}".(!empty($pack['environment']) ? " ({$pack['environment']})" : "")."\n";
            }
        }
        if(!empty($statusData['needReinstall'])) {
            echo "Packages to reinstall after installing new packages:\n";
            foreach($statusData['needReinstall'] as $pack) {
                echo "  {$pack['id']}-{$pack['version']}".(!empty($pack['environment']) ? " ({$pack['environment']})" : "")."\n";
            }
        }
    }

    public function getSandboxStatusData($options = array())
    {
        global $db;

        $currentEnvironments = null;
        if(!empty($options['env'])) {
            $currentEnvironments = array_filter(explode(' ', $options['env']));
        }
        if(empty($currentEnvironments)) {
            $currentEnvironments = array(self::$DEFAULT_ENVIRONMENT_NAME);
        }

        if(!empty($options['input'])) {
            $ini_string = '';
            while($line = fgets(STDIN)) {
                $ini_string .= $line."\n";
            }
        }
        else {
            $file = !empty($options['file']) ? $options['file'] : $this->sandboxFile;
            if(!is_file($file)) {
                throw new \Exception("Sandbox file $file not found. You should run `spm sandbox-init` or use --file=<path> option with correct path.");
            }
            $ini_string = file_get_contents($file);
        }
        $statusData = array(
            'sandboxUnknown' => array(),
            'sandboxNotInstalled' => array(),
            'environments' => array(),
            'currentEnvironments' => $currentEnvironments,
            'incorrectEnvironments' => array(),
            'needReinstall' => array(),
            'overwrites' => array(),
            'unknownReinstall' => array(),
        );
        $packagesInFile = parse_ini_string($ini_string, true);
        if($packagesInFile === false) {
            throw new \Exception("Cannot parse sandbox file $file.");
        }

        $packagesInDb = array();
        $q = "SELECT id_name, version FROM upgrade_history WHERE status = 'installed' ORDER BY date_entered";
        $dbRes = $db->query($q);
        while($row = $db->fetchByAssoc($dbRes)) {
            $packagesInDb[] = $row;
        }

        foreach($packagesInDb as $row) {
            $inFile = false;
            foreach($packagesInFile as $pack) {
                if($pack['id'] == $row['id_name'] && strnatcmp($pack['version'], $row['version']) >= 0) {
                    $inFile = true;
                    break;
                }
            }
            if(!$inFile) {
                $row['id'] = $row['id_name'];
                $statusData['sandboxUnknown'][$row['id_name']] = $row;
            }
        }

        foreach($packagesInFile as $section => $pack) {
            foreach($pack as $key => $v) {
                if(!in_array($key, array('path','id','version','environment','overwrite'))) {
                    echo "Warning: unknown key $key in $section section\n";
                }
            }
            $packEnvironments = !empty($pack['environment']) ? explode(' ', $pack['environment']) : array(self::$DEFAULT_ENVIRONMENT_NAME);
            $inCurrentEnvironments = false;
            foreach($packEnvironments as $env) {
                $statusData['environments'][$env] = $env;
                if(in_array($env, $currentEnvironments)) {
                    $inCurrentEnvironments = true;
                }
            }
            $packagesInFile[$section]['inCurrentEnvironments'] = $inCurrentEnvironments;
            if(!$inCurrentEnvironments) {
                continue;
            }
            $inDb = false;
            foreach($packagesInDb as $row) {
                if($pack['id'] == $row['id_name'] && strnatcmp($row['version'], $pack['version']) >= 0) {
                    $inDb = true;
                    break;
                }
            }
            if(!$inDb) {
                $statusData['sandboxNotInstalled'][] = $pack;
            }
        }

        foreach($packagesInFile as $section => $pack) {
            if(empty($pack['overwrite']) || !$pack['inCurrentEnvironments']) {
                continue;
            }
            $overwrites = array_filter(explode(' ', $pack['overwrite']));
            foreach($overwrites as $o) {
                $isKnown = false;
                foreach($packagesInFile as $pack2) {
                    if($pack2['id'] == $o) {
                        $isKnown = true;
                        break;
                    }
                }
                if(!$isKnown) {
                    throw new \Exception("Error: Unknown package $o in $section section");
                }
            }
            $statusData['overwrites'][$pack['id']] = $overwrites;
        }

        $statusData['needReinstall'] = $this->getReinstalls($statusData['sandboxNotInstalled'], $packagesInFile);
        $statusData['unknownReinstall'] = $this->getReinstalls($statusData['sandboxUnknown'], $packagesInFile);

        $statusData['incorrectEnvironments'] = array_diff($statusData['currentEnvironments'], $statusData['environments']);
        return $statusData;
    }

    protected function getReinstalls($newPackages, $packagesInFile, $depth = 0)
    {
        if($depth >= 10) {
            throw new \Exception("Max recursion level reached on reinstalls search");
        }
        foreach($newPackages as $id => $newPack) { //переустанавливать уже новую версию
            if(isset($packagesInFile[$id])) {
                $packagesInFile[$id]['version'] = $newPack['version'];
            }
        }
        $reinstallPackages = array();
        foreach($packagesInFile as $pack) {
            if(empty($pack['overwrite']) || !$pack['inCurrentEnvironments']) {
                continue;
            }
            $overwrites = array_filter(explode(' ', $pack['overwrite']));
            $needReinstall = false;
            foreach($newPackages as $pack2) {
                if($pack2['id'] == $pack['id']) {
                    $needReinstall = false;
                }
                elseif(in_array($pack2['id'], $overwrites)) {
                    $needReinstall = true;
                }
            }
            if($needReinstall) {
                $reinstallPackages[$pack['id']] = $pack;
            }
        }
        if(!empty($reinstallPackages)) {
            $reinstallPackages2 = $this->getReinstalls($reinstallPackages, $packagesInFile, $depth + 1);
            $reinstallPackages = array_merge(array_diff_key($reinstallPackages, $reinstallPackages2), $reinstallPackages2);
        }
        return $reinstallPackages;
    }

    public function updatePackagesOverwrites()
    {
        if($this->hasSandbox()) {
            $statusData = $this->getSandboxStatusData(); //TODO: environments
            $this->packagesOverwrites = $statusData['overwrites'];
        }
    }

    public function sandboxInstall($options = array())
    {
        $statusData = $this->getSandboxStatusData($options);
        if(!empty($statusData['incorrectEnvironments'])) {
            echo "Warning: unknown environments - ".implode(' ', $statusData['incorrectEnvironments'])."\n";
        }
        if(empty($statusData['sandboxNotInstalled'])) {
            return;
        }

        $this->updateStage();
        foreach($statusData['sandboxNotInstalled'] as $pack) {
            if(empty($pack['id'])) {
                throw new \Exception('id must be specified in '.print_r($pack, true));
            }
            if(empty($pack['version'])) {
                throw new \Exception('version must be specified in '.print_r($pack, true));
            }
            if(empty($pack['path'])) {
                throw new \Exception('path must be specified in '.print_r($pack, true));
            }
            if($this->isUploaded($pack['id'], $pack['version'])) {
                continue;
            }
            $this->updateAvailable(array('spm-path' => $pack['path']));
            try {
                $this->upload($pack['id'], $pack['version']);
            }
            catch(FileAlreadyExistsException $ex) {
                echo $ex->getMessage()."\n";
            }
        }

        $installOptionsString = Cmd\Base::optionsToString(Cmd\InstallCmd::$ALLOWED_OPTIONS, $options);
        $uninstallOptionsString = Cmd\Base::optionsToString(Cmd\UninstallCmd::$ALLOWED_OPTIONS, $options);
        foreach($statusData['sandboxNotInstalled'] as $pack) {
            if($this->hasLock()) {
                throw new \Exception("Probably other installation in progress or exited with error. See {$this->lockFile} file.");
            }
            if(empty($options['no-uninstall'])) {
                while($this->isInstalled($pack['id'])) {
                    echo shell_exec('/usr/bin/env php '.SPM_ENTRY_POINT.' uninstall '.$pack['id'].' '.$uninstallOptionsString);
                }
            }
            echo shell_exec('/usr/bin/env php '.SPM_ENTRY_POINT.' install '.$pack['id'].'-'.$pack['version'].' '.$installOptionsString); //exec new process to avoid redeclare errors (post_install, etc.)
        }

        foreach($statusData['needReinstall'] as $pack) {
            if($this->hasLock()) {
                throw new \Exception("Probably other installation in progress or exited with error. See {$this->lockFile} file.");
            }
            echo shell_exec('/usr/bin/env php '.SPM_ENTRY_POINT.' uninstall '.$pack['id'].'-'.$pack['version'].' '.$uninstallOptionsString);
            echo shell_exec('/usr/bin/env php '.SPM_ENTRY_POINT.' install '.$pack['id'].'-'.$pack['version'].' '.$installOptionsString);
        }
    }

    public function md5Generate($file)
    {
        require_once 'include/utils/file_utils.php';
        if(file_exists($file)) {
            throw new \Exception("File $file already exists.");
        }
        $md5_string_calculated = $this->getMd5Array();
        echo "Creating file $file\n";
        $res = write_array_to_file('md5_string_calculated', $md5_string_calculated, $file);
        if(!$res) {
            throw new \Exception("Write failure.");
        }
    }

    public function getMd5Array()
    {
        require_once 'include/utils/file_utils.php';
        $ignoreDirs = array('cache', 'upload', '.git');
        $md5arr = generateMD5array('./', $ignoreDirs);

        $ignorePaths = array();
        if(file_exists('.spmignore')) {
            $ignorePaths = array_merge($ignorePaths, file('.spmignore', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        }
        if(file_exists('.gitmodules')) {
            $submodules = parse_ini_file('.gitmodules', true);
            foreach($submodules as $mod) {
                $ignorePaths[] = $mod['path'].'/*';
            }
        }

        $md5arrFiltered = array();
        foreach($md5arr as $filename => $md5) {
            if(substr($filename, 0, 2) == './') {
                $filename1 = substr($filename, 2);
            }

            $ignore = false;
            foreach($ignorePaths as $mask) {
                if(fnmatch($mask, $filename1)) {
                    $ignore = true;
                    break;
                }
            }
            if($ignore) {
                continue;
            }

            $md5arrFiltered[$filename1] = $md5;
        }

        $gitIgnoreFiles = array();
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
        );
        $gitIgnoreProc = proc_open('git check-ignore --stdin', $descriptorspec, $pipes);
        if (is_resource($gitIgnoreProc)) {
            foreach($md5arrFiltered as $filename => $md5) {
                fwrite($pipes[0], $filename);
                fwrite($pipes[0], PHP_EOL);
            }
            fclose($pipes[0]);
            while($line = fgets($pipes[1])) {
                $gitIgnoreFiles[trim($line)] = 1;
            }
            fclose($pipes[1]);
            proc_close($gitIgnoreProc);
        }

        return array_diff_key($md5arrFiltered, $gitIgnoreFiles);
    }

    public function md5Compare($file1, $file2 = null)
    {
        if(!is_file($file1)) {
            throw new \Exception("File $file1 not exists");
        }
        if($file2 && !is_file($file2)) {
            throw new \Exception("File $file2 not exists");
        }

        $md5_string_calculated = null;
        $md5_string = null;
        require $file1;
        if(is_array($md5_string_calculated)) {
            $md5_array1 = $md5_string_calculated;
        }
        elseif(is_array($md5_string)) {
            $md5_array1 = $md5_string;
        }
        else {
            throw new \Exception("Error on getting first array.");
        }

        if(is_file($file2)) {
            $md5_string_calculated = null;
            $md5_string = null;
            require $file2;
            if(is_array($md5_string_calculated)) {
                $md5_array2 = $md5_string_calculated;
            }
            elseif(is_array($md5_string)) {
                $md5_array2 = $md5_string;
            }
            else {
                throw new \Exception("Error on getting second array.");
            }
        }
        else {
            $md5_array2 = $this->getMd5Array();
            $file2 = 'current';
        }

        $diff = array_diff(array_keys($md5_array2), array_keys($md5_array1));
        if($diff) {
            echo "New files (in $file2, but not in $file1):\n";
            echo "  ",implode("\n  ", $diff),"\n";
        }

        $diff = array_diff(array_keys($md5_array1), array_keys($md5_array2));
        if($diff) {
            echo "Deleted files (in $file1, but not in $file2):\n";
            echo "  ",implode("\n  ", $diff),"\n";
        }

        $diff = array_diff_assoc(array_intersect_key($md5_array1, $md5_array2), array_intersect_key($md5_array2, $md5_array1));
        if($diff) {
            echo "Modified files:\n";
            echo "  ",implode("\n  ", array_keys($diff)),"\n";
        }
    }
}
