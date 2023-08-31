<?php

require_once 'includes/central.php';

// define("DATABASE", "daten_new");

$response = array();

// $json = file_get_contents("php://input");
// $data = json_decode($json);

$jobDB = DBFactory::GetJobDB();
// $db = DBFactory::getGlobalInstance(DATABASE);

function getStepsInErrorState($db) {
    $sql = "select 'State ERROR' channel, count(workflowid) value, 'Count' unit from jrincidents where status = :status";
    $parameters = array("status" => -1);
    $types = array("channel" => JobDB::TYPE_TEXT, "value" => JobDB::TYPE_INTEGER, "unit" => JobDB::TYPE_TEXT);
    return QueryDb($db, $sql, $parameters, $types);
}

function getServiceLastSuccess($db) {
    $sql = "select module_name channel,  TIME_TO_SEC(TIMEDIFF(NOW(), last_success)) value, 'TimeSeconds' unit from jrmodulestatus ";
    $parameters = array();
    $types = array("channel" => JobDB::TYPE_TEXT, "value" => JobDB::TYPE_INTEGER, "unit" => JobDB::TYPE_TEXT);
    return QueryDb($db, $sql, $parameters, $types);
}

function getSensorData($db, $sql) {
    $parameters = array();
    $types = array("channel" => JobDB::TYPE_TEXT, "value" => JobDB::TYPE_INTEGER, "unit" => JobDB::TYPE_TEXT);
    return QueryDb($db, $sql, $parameters, $types);
}

function QueryDb($db, $sql, $parameters, $types) {
    $result = $db->preparedSelect($sql, $parameters, $types);
    if ($result === false) {
        // ResponseWithError(500, $db->getErrorMessage());
        return;
    }
    return $db->fetchAll($result);
}

header('Content-Type: application/json');



$result = array_merge(getServiceLastSuccess($jobDB), getStepsInErrorState($jobDB), getSensorData($jobDB, "select 'JRJOBIMPORT Error' channel, count(id) value, 'Count' unit from jrjobimport where error_count > 0"), getSensorData($jobDB, "select 'Email Error' channel, count(mail_id) value, 'Count' unit from jrmail where error_text <> ''"), getSensorData($jobDB, "select 'Active sessions' channel, count(*) value, 'Count' unit from jrsessions"), getSensorData($jobDB, "select 'Active users' channel, count(username) value, 'Count' unit from jrusers where blocked = 0"));
// $result = getServiceLastSuccess($jobDB);
$response["prtg"] = array("result" => $result);
echo json_encode($response);
