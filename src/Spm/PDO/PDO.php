<?php

namespace Spm\Pdo;
/**
 * Dummy used when sqlite is unavailable
 * UpdateStage supported only
 */
class PDO
{
    public $lastException;
    public $spm;

    public function query($statement) {
        throw $this->lastException;
    }

    public function quote($string) {
        throw $this->lastException;
    }

    public function exec($statement)
    {
        if(in_array($statement, array(
              "DROP TABLE IF EXISTS stage"
            , "CREATE TABLE stage (id_name TEXT COLLATE NOCASE, version TEXT, filename TEXT, type TEXT)"
            , "DROP TABLE IF EXISTS available"
            , "CREATE TABLE available (id_name TEXT COLLATE NOCASE, version TEXT, filename TEXT)"
        ))) {
            return;
        }
        throw $this->lastException;
    }

    public function prepare($statement, $driver_options = array())
    {
        if($statement == "INSERT INTO stage (id_name, version, filename, type) VALUES (:id_name, :version, :filename, :type)") {
            return new DummyStatement();
        }
        if($statement == SelectFromStageStatement::STATEMENT) {
            $stmt = new SelectFromStageStatement();
            $stmt->spm = $this->spm;
            return $stmt;
        }
        if($statement == SelectOneFromStageStatement::STATEMENT) {
            $stmt = new SelectOneFromStageStatement();
            $stmt->spm = $this->spm;
            return $stmt;
        }
        if($statement == "INSERT INTO available (id_name, version, filename) VALUES (:id_name, :version, :filename)") {
            return new DummyStatement();
        }
        if($statement == SelectFromAvailableStatement::STATEMENT) {
            $stmt = new SelectFromAvailableStatement();
            $stmt->spm = $this->spm;
            return $stmt;
        }
        throw $this->lastException;
    }
}

/**
 * Dummy used when sqlite is unavailable.
 * Do nothing.
 */
class DummyStatement
{
    public $spm;
    public $params = array();

    public function bindParam($parameter, &$variable) {
        $this->params[$parameter] = $variable;
    }

    public function execute($input_parameters = array()) {
        $this->params = array_merge($this->params, $input_parameters);
    }
}
/**
 * Dummy used to update stage when sqlite is unavailable
 */
class SelectFromStageStatement extends DummyStatement
{
    const STATEMENT = "SELECT id_name, version, filename, type FROM stage WHERE id_name = :id_name AND (version = :version OR :version IS NULL) ORDER BY version COLLATE NATURAL_CMP DESC";

    public function fetchAll()
    {
        $result = array();
        foreach($this->spm->packagesInStaging as $pack) {
            $id = $pack['id_name'];
            $version = $pack['version'];
            if($id != $this->params[':id_name'] || ($this->params[':version'] !== null && $version != $this->params[':version'])) {
                continue;
            }
            $result[$version] = $pack;
        }
        uksort($result, "strnatcmp");
        $result = array_reverse($result);
        return $result;
    }
}
class SelectOneFromStageStatement extends DummyStatement
{
    const STATEMENT = "SELECT 1 FROM stage WHERE id_name = :id_name AND version = :version LIMIT 1";

    public function fetchAll()
    {
        $result = array();
        foreach($this->spm->packagesInStaging as $pack) {
            $id = $pack['id_name'];
            $version = $pack['version'];
            if($id != $this->params[':id_name'] || ($this->params[':version'] !== null && $version != $this->params[':version'])) {
                continue;
            }
            $result[] = array('1' => 1);
        }
        return $result;
    }
}
class SelectFromAvailableStatement extends DummyStatement
{
    const STATEMENT = "SELECT version, filename FROM available WHERE id_name = :id_name AND (version = :version OR :version IS NULL) ORDER BY version COLLATE NATURAL_CMP DESC LIMIT 1";

    public function fetch()
    {
        $result = array();
        foreach($this->spm->packagesAvailable as $pack) {
            $id = $pack['id_name'];
            $version = $pack['version'];
            if($id != $this->params[':id_name'] || ($this->params[':version'] !== null && $version != $this->params[':version'])) {
                continue;
            }
            $result[$version] = $pack;
        }
        uksort($result, "strnatcmp");
        $result = array_reverse($result);
        if(!empty($result)) {
            return reset($result);
        }
        return null;
    }
}
