<?php

require_once 'includes/central.php';

interface PrtgDatabaseQuery {
    public function getStepsInErrorStatusQuery() : string;
    public function getServiceLastSuccessQuery() : string;
    public function getSensorDataQueries() : array;

}

class PrtgMssqlQueries implements PrtgDatabaseQuery {
    public function getStepsInErrorStatusQuery() : string {
        return "select 'State ERROR' channel, count(workflowid) value, 'Count' unit from jrincidents where status = :status";
    }

    public function getServiceLastSuccessQuery() : string {
        return "select 'State ERROR' channel, count(workflowid) value, 'Count' unit from jrincidents where status = :status";
    }

    public function getSensorDataQueries() : array {
        $queries = [];
        $queries[] = "select 'JRJOBIMPORT Error' channel, count(id) value, 'Count' unit from jrjobimport where error_count > 0";
        $queries[] = "select 'Active sessions' channel, count(*) value, 'Count' unit from jrsessions";
        $queries[] = "select 'Active users' channel, count(username) value, 'Count' unit from jrusers where blocked = 0";
        $queries[] = "select 'Email Error' channel, count(mail_id) value, 'Count' unit from jrmail where error_text <> ''";
        return $queries;
    }
}



class PrtgBase {
    protected $jobDB;
    protected $prtgDatabaseQuery;

    public function __construct() {
        $this->jobDB = DBFactory::GetJobDB();
        $this->prtgDatabaseQuery = new PrtgMssqlQueries();
    }

    function getStepsInErrorState() {
        $sql = $this->prtgDatabaseQuery->getStepsInErrorStatusQuery();
        $parameters = array("status" => -1);
        $types = array("channel" => JobDB::TYPE_TEXT, "value" => JobDB::TYPE_INTEGER, "unit" => JobDB::TYPE_TEXT);
        return $this->queryDb($sql, $parameters, $types);
    }
    
    function getServiceLastSuccess() {
        $sql = $this->prtgDatabaseQuery->getServiceLastSuccessQuery();
        $parameters = array();
        $types = array("channel" => JobDB::TYPE_TEXT, "value" => JobDB::TYPE_INTEGER, "unit" => JobDB::TYPE_TEXT);
        return $this->queryDb($sql, $parameters, $types);
    }
    
    function getSensorData($sql) {
        $parameters = array();
        $types = array("channel" => JobDB::TYPE_TEXT, "value" => JobDB::TYPE_INTEGER, "unit" => JobDB::TYPE_TEXT);
        return $this->queryDb($sql, $parameters, $types);
    }
    
    function queryDb($sql, $parameters, $types) {
        $result = $this->jobDB->preparedSelect($sql, $parameters, $types);
        if ($result === false) {
            // ResponseWithError(500, $this->jobDB->getErrorMessage());
            error_log($this->jobDB->getErrorMessage());
            return;
        }
        return $this->jobDB->fetchAll($result);
    }

    public function execute() {
        $result = [];
        // $result[] = $this->getServiceLastSuccess();
        $result[] = $this->getStepsInErrorState();
        foreach ($this->prtgDatabaseQuery->getSensorDataQueries() as $query) {
            $result[] = $this->getSensorData($query);
        }
        $mergedResult = array_merge(...$result);

        $response = array();
        $response["prtg"] = array("result" => $mergedResult);
        return $response;
    }
}


header('Content-Type: application/json');

$prtg = new PrtgBase();
echo json_encode($prtg->execute());
?>

