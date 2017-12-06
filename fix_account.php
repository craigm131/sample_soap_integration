<?php

//This class enables CRUD from Database files related to Soap's OnBoarding and CACCI APIs.
//Programmer:  Craig Millis


require_once('/htdocs/common/php/logger/Logger.php');
require_once('/htdocs/common/php/DBRequest/DBDataRequest.php');

class FixAccount{

  public $logDetailSetting   = 1;//The higher the number, the more detail is written to logs
  public $debug              = FALSE;//Setting to TRUE will result in the most detail written to logs
  public $logDir = '/pix/soap_api/log';

  function __construct(){
    $this->startLog($this->logDir);
    $this->db = new DBDataRequest();
  }

  private function startLog($logDir = NULL, $createSub=FALSE){
    $this->oldMask            = umask(0);
    $this->defaultPerms       = 0775;
    $this->defaultPermsDec    = decoct($this->defaultPerms);
    $this->dateObj            = $this->createTimestamp();
    $this->dateTime           = date_format($this->dateObj, "mdHisu");
    $this->dateTimeLastLog    = $this->dateTime;
    $this->log = new Logger(array('logDir'=>$logDir, 'addSubDir'=>false, 'logDetailSetting'=>$this->logDetailSetting));
    $this->log->preLog(__CLASS__." ".__METHOD__, 3);
  }
 
   private function createTimestamp(){
    $t = microtime(true);
    $micro = sprintf("%06d",($t - floor($t)) * 1000000);
    $d = new DateTime( date('Y-m-d H:i:s.'.$micro, $t) );
    return $d;
  }

  function updateRecordInDb(){
    $queryParams = array(
      'fileName'  => 'trucks',
      'realCust'  => 'cargo',
      'noCust'    => 'N',
      'noauth'    => 'Y',
      'exact'     => 'N'
    );

    $fieldMap = array(
      '1'   => 1,
      '24'  => 24,
      '29'  => 29,
      '214' => 214,
      '215' => 215
    );

    try{
      $results = $this->db->getRequestResult($queryParams, $fieldMap);
    }catch(Exception $e) {
      $this->log->preLog("Failure during record search");
      return FALSE;
    }
    $this->log->preLog("count: ".count($results));
    $this->log->preLog("all results: ".print_r($results, TRUE));


    $select_results = [];
    foreach($results as $result){
      if(empty($result[1]) AND !empty($result[2])){
        $select_results[] = $result;
      }
    };
    
    $this->log->preLog("count: ".count($select_results));
    $this->log->preLog("select results: ".print_r($select_results, TRUE));
    $this->log->preLog("count: ".count($select_results));

    $select_results = array_slice($select_results, 0, 1);
    $this->log->preLog("sliced: ".print_r($select_results, TRUE));

    foreach($select_results as $select_result){
      $queryParams['exact'] = 'Y';
      $queryParams['rn']    = $select_result['rn'];
      $data['24'] = 300000 + substr($select_result[2], -5);
      $results = $this->db->getRequestResult($queryParams, $fieldMap);
      $this->log->preLog("Found record: ".print_r($results, TRUE));

      try{
        $result = $this->db->doUpdate($data);
        $this->log->preLog("Success: record written to ".$queryParams['fileName']);
        $this->log->preLog("doUpdate passed: ".print_r($data, TRUE));
        $this->log->preLog("doUpdate returned: ".print_r($result, TRUE));
        return TRUE;
      }catch(Exception $e) {
        $this->log->preLog("Failure: record NOT written to ".$queryParams['fileName']);
        return FALSE;
      }
    }
  }
}

?>
