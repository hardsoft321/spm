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
            $cmp1 = strcasecmp($pack1['id_name'], $pack2['id_name']);
            return $cmp1 ? $cmp1 : ($versionOrder == 'asc' ? 1 : -1) * strnatcmp($pack1['version'], $pack2['version']);
        });
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
