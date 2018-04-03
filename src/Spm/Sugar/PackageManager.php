<?php
/*********************************************************************************
 * SugarCRM Community Edition is a customer relationship management program developed by
 * SugarCRM, Inc. Copyright (C) 2004-2013 SugarCRM Inc.
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
 * details.
 * 
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 * 
 * You can contact SugarCRM, Inc. headquarters at 10050 North Wolfe Road,
 * SW2-130, Cupertino, CA 95014, USA. or at email address contact@sugarcrm.com.
 * 
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 * 
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * SugarCRM" logo. If the display of the logo is not reasonably feasible for
 * technical reasons, the Appropriate Legal Notices must display the words
 * "Powered by SugarCRM".
 ********************************************************************************/

namespace Spm\Sugar;

use Spm\DependenciesException;

require_once('ModuleInstall/PackageManager/PackageManager.php');

class PackageManager extends \PackageManager
{
    public $options = array();
    private $cleanUpDirs = array();

    private function addToCleanup($dir)
    {
        if(empty($this->cleanUpDirs)) {
            register_shutdown_function(array($this, "cleanUpTempDir"));
        }
        $this->cleanUpDirs[] = $dir;
    }

    public function cleanUpTempDir()
    {
        foreach($this->cleanUpDirs as $dir) {
            rmdir_recursive($dir);
        }
    }

    /**
     * Добавлен запуск pre/post_install, dependencies
     */
    function performInstall($file, $silent=true){
        global $sugar_config;
        global $mod_strings;
        global $current_language;
        $base_upgrade_dir       = $this->upload_dir.'/upgrades';
        $base_tmp_upgrade_dir   = "$base_upgrade_dir/temp";
        if(!file_exists($base_tmp_upgrade_dir)){
            mkdir_recursive($base_tmp_upgrade_dir, true);
        }

        $GLOBALS['log']->debug("INSTALLING: ".$file);
        $mi = new ModuleInstaller();
        $mi->silent = $silent;
             $GLOBALS['log']->debug("ABOUT TO INSTALL: ".$file);
        if(preg_match("#.*\.zip\$#", $file)) {
             $GLOBALS['log']->debug("1: ".$file);
            // handle manifest.php
            $target_manifest = remove_file_extension( $file ) . '-manifest.php';
            $dependencies = array();
            $installdefs = array();
            include($target_manifest);
            if(!empty($manifest['dependencies'])) {
                $dependencies = $manifest['dependencies'];
            }
            $GLOBALS['log']->debug("2: ".$file);
            $unzip_dir = mk_temp_dir( $base_tmp_upgrade_dir );
            $this->addToCleanup($unzip_dir);
            unzip($file, $unzip_dir );
            $GLOBALS['log']->debug("3: ".$unzip_dir);
            $id_name = $installdefs['id'];
            $version = $manifest['version'];
            $type = $manifest['type'];
            $uh = new UpgradeHistory();
            //check dependencies first
            if(!empty($dependencies)) {
                $not_found = $uh->checkDependencies($dependencies);
                if(!empty($not_found) && count($not_found) > 0){
                    throw new DependenciesException( $mod_strings['ERR_UW_NO_DEPENDENCY']."[".implode(',', $not_found)."]");
                }
            }
            $previous_install = array();
            if(!empty($id_name) & !empty($version))
                $previous_install = $uh->determineIfUpgrade($id_name, $version);
            $previous_version = (empty($previous_install['version'])) ? '' : $previous_install['version'];
            $previous_id = (empty($previous_install['id'])) ? '' : $previous_install['id'];

            $preInstallFile = "$unzip_dir/" . constant('SUGARCRM_PRE_INSTALL_FILE');
            if(is_file($preInstallFile)) {
                echo "{$mod_strings['LBL_UW_INCLUDING']}: $preInstallFile\n";
                include($preInstallFile);
                pre_install();
            }

            if(empty($this->options['no-copy'])) {
                if(!empty($previous_version)){
                    $mi->install($unzip_dir, true, $previous_version);
                }else{
                    $mi->install($unzip_dir);
                }
            }
            else {
                if(isset($installdefs['beans'])){
                    foreach($installdefs['beans'] as $bean){
                        if(!empty($bean['module']) && !empty($bean['class']) && !empty($bean['path'])){
                            $module = $bean['module'];
                            if(!empty($bean['tab'])){
                                $tab_modules[] = $module;
                                UpdateSystemTabs('Add', $tab_modules);
                            }
                        }
                    }
                }
                //TODO: run pre_execute, post_execute
            }

            if($type == 'langpack') {
                $langInfo = $this->getLangInfo($manifest, $unzip_dir);
                $sugar_config['languages'] = $sugar_config['languages'] + $langInfo;
                ksort( $sugar_config );
                if( !write_array_to_file( "sugar_config", $sugar_config, "config.php" ) ){
                    throw new \Exception($mod_strings['ERR_UW_CONFIG_FAILED']);
                }
            }

            $postInstallFile = "$unzip_dir/" . constant('SUGARCRM_POST_INSTALL_FILE');
            if(is_file($postInstallFile))
            {
                echo "{$mod_strings['LBL_UW_INCLUDING']}: $postInstallFile\n";
                include($postInstallFile);
                post_install();
            }

            $GLOBALS['log']->debug("INSTALLED: ".$file);
            $new_upgrade = new UpgradeHistory();
            $new_upgrade->filename      = $file;
            $new_upgrade->md5sum        = md5_file($file);
            $new_upgrade->type          = $manifest['type'];
            $new_upgrade->version       = $manifest['version'];
            $new_upgrade->status        = "installed";
            //$new_upgrade->author        = $manifest['author'];
            $new_upgrade->name          = $manifest['name'];
            $new_upgrade->description   = $manifest['description'];
            $new_upgrade->id_name       = $id_name;
            $serial_manifest = array();
            $serial_manifest['manifest'] = (isset($manifest) ? $manifest : '');
            $serial_manifest['installdefs'] = (isset($installdefs) ? $installdefs : '');
            $serial_manifest['upgrade_manifest'] = (isset($upgrade_manifest) ? $upgrade_manifest : '');
            $new_upgrade->manifest      = base64_encode(serialize($serial_manifest));
            //$new_upgrade->unique_key    = (isset($manifest['unique_key'])) ? $manifest['unique_key'] : '';
            $new_upgrade->save();
                    //unlink($file);
        }//fi
    }

    function performUninstall($name, $version = ''){
        $uh = new UpgradeHistory();
        $uh->name = $name;
        $uh->id_name = $name;
        $uh->version = $version;
        $found = $uh->checkForExisting($uh);
        if($found != null){
            global $sugar_config;
            global $mod_strings;
            global $current_language;
            $base_upgrade_dir       = $this->upload_dir.'/upgrades';
            $base_tmp_upgrade_dir   = "$base_upgrade_dir/temp";
            if(is_file($found->filename)){
                $hash = md5($found->filename);
                $_SESSION['file2Hash'][$hash] = $found->filename;
                $_REQUEST['install_file'] = $hash; // used in ModuleInstaller
                if(!isset($GLOBALS['mi_remove_tables']))$GLOBALS['mi_remove_tables'] = true;
                $unzip_dir = mk_temp_dir( $base_tmp_upgrade_dir );
                unzip($found->filename, $unzip_dir );
                register_shutdown_function("rmdir_recursive", $unzip_dir);
                $mi = new ModuleInstaller();
                $mi->options = $this->options;
                $mi->silent = true;

                $preUninstallFile = "$unzip_dir/" . constant('SUGARCRM_PRE_UNINSTALL_FILE');
                if(is_file($preUninstallFile))
                {
                    echo "{$mod_strings['LBL_UW_INCLUDING']}: $preUninstallFile\n";
                    include($preUninstallFile);
                    pre_uninstall();
                }

                if(empty($this->options['no-copy'])) {
                    $mi->uninstall( "$unzip_dir");
                }
                //TODO: restore sytem tabs

                if($found->type == 'langpack') {
                    $langInfo = $this->getLangInfo(array(), $unzip_dir); //TODO: manifest
                    reset($langInfo);
                    $new_lang_name = key($langInfo);
                    $new_langs = array();
                    $old_langs = $sugar_config['languages'];
                    foreach( $old_langs as $key => $value ){
                        if( $key != $new_lang_name ){
                            $new_langs += array( $key => $value );
                        }
                    }
                    $sugar_config['languages'] = $new_langs;
                    $default_sugar_instance_lang = 'en_us';
                    if($sugar_config['default_language'] == $new_lang_name){
                        $cfg = new \Configurator();
                        $cfg->config['languages'] = $new_langs;
                        $cfg->config['default_language'] = $default_sugar_instance_lang;
                        $cfg->handleOverride();
                    }
                    ksort( $sugar_config );
                    if( !write_array_to_file( "sugar_config", $sugar_config, "config.php" ) ){
                        throw new \Exception($mod_strings['ERR_UW_CONFIG_FAILED']);
                    }
                }

                $found->delete();
                //unlink(remove_file_extension( $found->filename ) . '-manifest.php');
                //unlink($found->filename);
                unset($_SESSION['file2Hash'][$hash]);
            }else{
                echo "Warning: file {$found->filename} not found. Just deleting from upgrade_history.\n";
                //file(s_ have been deleted or are not found in the directory, allow database delete to happen but no need to change filesystem
                $found->delete();
            }
        }
        else {
            throw new \Exception("Рackage not found");
        }
    }

    /**
     * modules/Administration/UpgradeWizard_prepare.php
     */
    protected function getLangInfo($manifest, $unzip_dir) {
        $zip_from_dir = ".";
        if( isset( $manifest['copy_files']['from_dir'] ) && $manifest['copy_files']['from_dir'] != "" ){
            $zip_from_dir   = $manifest['copy_files']['from_dir'];
        }
        // find name of language pack: find single file in include/language/xx_xx.lang.php
        $d = dir( "$unzip_dir/$zip_from_dir/include/language" );
        while( $f = $d->read() ){
            if( $f == "." || $f == ".." ){
                continue;
            }
            else if( preg_match("/(.*)\.lang\.php\$/", $f, $match) ){
                $new_lang_name = $match[1];
            }
        }
        if( $new_lang_name == "" ){
            throw new \Exception("Can't find name of language pack ".$install_file);
        }

        $new_lang_desc = $this->getLanguagePackName( "$unzip_dir/$zip_from_dir/include/language/$new_lang_name.lang.php" );
        if( $new_lang_desc == "" ){
            throw new \Exception("Can't find description of language pack include/language/$new_lang_name.lang.php");
        }

        return array($new_lang_name => $new_lang_desc);
    }

    /**
     * modules/Administration/UpgradeWizardCommon.php
     */
    protected function getLanguagePackName( $the_file ){
        global $app_list_strings;
        require_once( "$the_file" );
        if( isset( $app_list_strings["language_pack_name"] ) ){
            return( $app_list_strings["language_pack_name"] );
        }
        return( "" );
    }
}
