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

require_once('modules/Administration/QuickRepairAndRebuild.php');
require_once('include/utils/layout_utils.php');

class RepairAndClear extends \RepairAndClear
{
    public $sql;
    public $oneLine = true;
    public function repairDatabase()
    {
        global $dictionary, $mod_strings;

        if(isset($mod_strings['LBL_ALL_MODULES']) && !in_array($mod_strings['LBL_ALL_MODULES'], $this->module_list)) {
            $this->repairDatabaseSelectModules();
        }
        $_REQUEST['repair_silent']=1;
        $_REQUEST['execute']=$this->execute;
        $GLOBALS['reload_vardefs'] = true;
        $hideModuleMenu = true;
        include_once('modules/Administration/repairDatabase.php');
        if(!empty($sql)) {
            $this->sql = $this->removeComments($sql);
        }
    }

    public function removeComments($sql) {
        $qry_str = "";
        foreach (explode("\n", $sql) as $line) {
            if ($this->oneLine) {
                if (!empty($line) && substr($line, -2) != "*/") {
                    $qry_str .= $line . ";";
                }
            } else {
                $qry_str .= $line;
                if (!empty($line) && substr($line, -2) != "*/") {
                    $qry_str .= ";";
                }
                $qry_str .= "\n";
            }
        }
        return $qry_str;
    }

    public function repairDatabaseSelectModules()
    {
        global $mod_strings, $dictionary;
        include 'include/modules.php'; //bug 15661
        $db = \DBManagerFactory::getInstance();

        $sql = '';
        //repair DB
        $dm = inDeveloperMode();
        $GLOBALS['sugar_config']['developerMode'] = true;
        foreach ($this->module_list as $bean_name) {

            $bean_name = $beanList[$bean_name]; //PEA

            if (isset($beanFiles[$bean_name]) && file_exists($beanFiles[$bean_name])) {
                require_once $beanFiles[$bean_name];
                $GLOBALS['reload_vardefs'] = true;
                $focus = new $bean_name();
                #30273
                if ($focus->disable_vardefs == false) {
                    include 'modules/' . $focus->module_dir . '/vardefs.php';

                    if ($this->show_output) {
                        print_r("<p>" . $mod_strings['LBL_REPAIR_DB_FOR'] . ' ' . $bean_name . "</p>");
                    }

                    $sql .= $db->repairTable($focus, $this->execute);
                }
            }
        }
        // TODO: repairCustomFields
        // TODO: repairTableParams for other than modules

        $GLOBALS['sugar_config']['developerMode'] = $dm;

        $this->sql = $this->removeComments($sql);
    }

    public function clearThemeCache()
    {
        if(file_exists('include/SugarTheme/SugarThemeRegistry.php')) {
            require_once 'include/SugarTheme/SugarThemeRegistry.php';
            \SugarThemeRegistry::buildRegistry();
        }
        parent::clearThemeCache();
    }
}
