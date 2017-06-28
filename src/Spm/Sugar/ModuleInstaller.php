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

require_once('ModuleInstall/ModuleInstaller.php');

class ModuleInstaller extends \ModuleInstaller
{
    public $options;

    public function remove_acl_actions()
    {
        if(!empty($this->options['remove-acl'])) {
            parent::remove_acl_actions();
        }
    }

    public function uninstall_customizations($beans)
    {
        if(!empty($this->options['remove-custom'])) {
            parent::uninstall_customizations($beans);
        }
    }

    public function uninstall_user_prefs($module)
    {
        if(!empty($this->options['remove-prefs'])) {
            parent::uninstall_user_prefs($module);
        }
    }

    public function uninstall_relationships($include_studio_relationships = false)
    {
        if(!empty($this->options['remove-relationships'])) {
            parent::uninstall_relationships($include_studio_relationships); //здесь из форм связанных модулей удаляются ссылки на модуль
        }
    }

    /**
     * Исправлена ошибка с удалением файлов, если они копировались в одну папку.
     */
    public function uninstall_copy(){
        if(!empty($this->installdefs['copy'])){
                    foreach($this->installdefs['copy'] as $cp){
                        $cp['to'] = clean_path(str_replace('<basepath>', $this->base_dir, $cp['to']));
                        $cp['from'] = clean_path(str_replace('<basepath>', $this->base_dir, $cp['from']));
                        $GLOBALS['log']->debug('Unlink ' . $cp['to']);
                /* BEGIN - RESTORE POINT - by MR. MILK August 31, 2005 02:22:11 PM */
                        //rmdir_recursive($cp['to']);

                        $backup_path = clean_path( remove_file_extension(urldecode(hashToFile($_REQUEST['install_file'])))."-restore/".$cp['to'] );
                        $this->uninstall_new_files($cp, $backup_path); //PEA: here uninstall all files
                        //$this->copy_path($backup_path, $cp['to'], $backup_path, true);
                /* END - RESTORE POINT - by MR. MILK August 31, 2005 02:22:18 PM */
                    }
                    foreach($this->installdefs['copy'] as $cp){
                        $cp['to'] = clean_path(str_replace('<basepath>', $this->base_dir, $cp['to']));
                        $cp['from'] = clean_path(str_replace('<basepath>', $this->base_dir, $cp['from']));
                        $GLOBALS['log']->debug('Unlink ' . $cp['to']);
                /* BEGIN - RESTORE POINT - by MR. MILK August 31, 2005 02:22:11 PM */
                        //rmdir_recursive($cp['to']);

                        $backup_path = clean_path( remove_file_extension(urldecode(hashToFile($_REQUEST['install_file'])))."-restore/".$cp['to'] );
                        //$this->uninstall_new_files($cp, $backup_path);
                        $this->copy_path($backup_path, $cp['to'], $backup_path, true); //PEA: now copy backup
                /* END - RESTORE POINT - by MR. MILK August 31, 2005 02:22:18 PM */
                    }
                    $backup_path = clean_path( remove_file_extension(urldecode(hashToFile($_REQUEST['install_file'])))."-restore");
                    if(file_exists($backup_path))
                        rmdir_recursive($backup_path);
                }
    }
}
