<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */

namespace Spm;

class PackList
{
    protected $packages = array();

    public function getPackages()
    {
        return $this->packages;
    }

    public function setPackages($packages)
    {
        $this->packages = $packages;
    }

    public function sort($versionOrder = 'asc')
    {
        self::sortPackages($this->packages, $versionOrder);
    }

    public static function sortPackages(&$packages, $versionOrder)
    {
        usort($packages, function ($pack1, $pack2) use ($versionOrder) {
            $cmp1 = strnatcasecmp($pack1['id_name'], $pack2['id_name']);
            return $cmp1 ? $cmp1 : ($versionOrder == 'asc' ? 1 : -1) * strnatcasecmp($pack1['version'], $pack2['version']);
        });
    }

    public static function sortPackagesTopologically(&$packages, $prevPackages = array(), $versionOrder = 'asc')
    {
        $currLevelPackages = array();
        $nextLevelPackages = array();
        foreach($packages as $pack) {
            $allResolved = true;
            if(!empty($pack['dependencies'])) {
                foreach($pack['dependencies'] as $dependency) {
                    $depResolved = false;
                    foreach($prevPackages as $prev) {
                        $prev_id = !empty($prev['id_name']) ? $prev['id_name'] : $prev['id'];
                        if(strcasecmp($prev_id, $dependency['id_name']) == 0
                            && (empty($dependency['version']) || strcasecmp($prev['version'], $dependency['version']) >= 0)) //TODO: should be MAJOR scope
                        {
                            $depResolved = true;
                            break;
                        }
                    }
                    if(!$depResolved) {
                        $allResolved = false;
                        break;
                    }
                }
            }
            if($allResolved) {
                $currLevelPackages[] = $pack;
            }
            else {
                $nextLevelPackages[] = $pack;
            }
        }
        if(!empty($currLevelPackages)) {
            self::sortPackages($currLevelPackages, $versionOrder);
            if(!empty($nextLevelPackages)) {
                $nextSorted = self::sortPackagesTopologically($nextLevelPackages
                    , array_merge($prevPackages, $currLevelPackages), $versionOrder);
                $packages = array_merge($currLevelPackages, $nextLevelPackages);
                return $nextSorted;
            }
            else {
                $packages = $currLevelPackages;
                return true;
            }
        }
        else {
            $packages = $nextLevelPackages;
            return empty($nextLevelPackages);
        }
    }

    public function lookup($id_name, $version = null, $versionOrder = 'asc')
    {
        $packs = array();
        foreach($this->packages as $pack) {
            if(strcasecmp($pack['id_name'], $id_name) == 0 && ($version === null || strcasecmp($pack['version'], $version) == 0)) {
                $packs[] = $pack;
            }
        }
        self::sortPackages($packs, $versionOrder);
        return $packs;
    }
}
