<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
namespace Spm\Cmd;

class DbqueryCmd extends Base
{
    public function execute()
    {
        list($subjects, $options) = self::getArgvParams(false, array('s', 'f'));
        if(count($subjects) > 1) {
            array_shift($subjects);
            throw new \Exception("Unknown option ".implode(' ', $subjects));
        }
        if(empty($subjects)) {
            echo "Reading sql statements from stdin, press Ctrl+D to exit\n";
            $sql = '';
            while($line = fgets(STDIN)) {
              $sql .= $line."\n";
            }
        }
        else {
            $sql = reset($subjects);
        }
        $allowedQueries = null;
        if(file_exists('.spmqueries.php')) {
            $allowedQueries = require '.spmqueries.php';
            if(!is_array($allowedQueries)) {
                throw new \Exception('.spmqueries.php must return array');
            }
        }
        if($allowedQueries === null) {
            if(!empty($options['s'])) {
                throw new \Exception(".spmqueries.php not exists but its option 's' defined");
            }
            if(!empty($options['f'])) {
                throw new \Exception(".spmqueries.php not exists but its option 'f' defined");
            }
        }
        if(!empty($options['s']) && !empty($options['f'])) {
            throw new \Exception("Options 's' and 'f' can not be used concurrently");
        }
        echo "Checking .spmqueries.php file: ".($allowedQueries === null ? "not exists - queries will not be checked with whitelist" : "exists")."\n";
        $this->spm->dbquery($sql, $allowedQueries, $options);
    }
}
