<?php

//This class enables CRUD from Database files related to Soap's OnBoarding and CACCI APIs.
//Programmer:  Craig Millis


require_once('/htdocs/common/php/logger/Logger.php');
require_once('/htdocs/common/php/DBRequest/DataRequest.php');

class DboOnBoardingCacciModel{

  var $logDetailSetting   = 1;//The higher the number, the more detail is written to logs
  var $debug              = FALSE;//Setting to TRUE will result in the most detail written to logs

  function __construct($params = ''){
    if(isset($params['log'])){ 
      $this->log = $params['log'];
      unset($params['log']); 
    }
    $this->log->preLog(__METHOD__, 4);
    $this->readParams($params);
    $this->db = new DataRequest();
  }

  //Check for minimum parameters required
  private function readParams($params){
    $this->log->preLog(__METHOD__, 4);
    $this->log->preLog("Input parameters: ".print_r($params, TRUE), 4);
    if(is_array($params)){
      $this->params = json_decode(json_encode($params), FALSE);//Convert to an object
      $this->log->preLog("Converted input parameters to object.", 4);
    }  
    if(!empty($this->params->debug)){
      $this->debug = $this->params->debug;
    }
    if(!empty($this->params->logDetailSetting)){
      $this->logDetailSetting = $this->params->logDetailSetting;
    }
    $this->log->preLog("Input parameters after ".__FUNCTION__." method: ".print_r($this->params, TRUE), 4);
  }
 
  private function getRealCustomer($encodedCustomer){
    $this->log->preLog(__METHOD__);
    
    $params = array(
      'fileName'  => 'qualify',
      'index'     => 'C',
      'key'       => $encodedCustomer,
      'noauth'    => 'Y',
      'noqual'    => 'Y',
      'limit'     => 1 
    );

    $fieldMap = array('realCustomer' => 1);
    
    $result = $this->db->getRequestResult($params, $fieldMap);
   
    return $result[0]['realCustomer'];
  }

  function getIntegrationIdFromConfig($realCustomer){
    $this->log->preLog(__METHOD__);
    $queryParams = array(
      'noAuth'    => 'Y',
      'realCustomer'  => $realCustomer,
      'fileName'  => 'config',
    );

    $fieldMap = array('IntegrationId' => 301);
    
    try{ 
      $result = $this->db->getRequestResult($queryParams, $fieldMap);
    }catch(Exception $e){
      $this->log->preLog('Failure: no record found in '.$queryParams['fileName']);
      $this->log->preLog($e->getMessage());
    }
   
    return $result[0]['IntegrationId'];
  }

  function updateRecordInDb($params){
    $this->log->preLog(__METHOD__);

    foreach($params as $key => $value){
      $$key = $value;
    }

    $this->log->preLog('Params: '.print_r($params, TRUE), 3);//3 in prod

    try{
      $result = $this->db->getRequestResult($queryParams, $fieldMap);
    }catch(Exception $e) {
      $this->log->preLog('Failure during record search');
      return FALSE;
    }

    $this->log->preLog('getRequestResult returned: '.print_r($result, TRUE), 2);//Set to 5 in prod?
    if(empty($result)){
      $this->log->preLog('No existing record to update...returning');
      return FALSE;
    } 

    try{
      $result = $this->db->doUpdate($data);
      $this->log->preLog('Success: record written to '.$queryParams['fileName']);
    
      $this->log->preLog('doUpdate passed: '.print_r($data, TRUE)."\n");
      $this->log->preLog('doUpdate returned: '.print_r($result, TRUE)."\n");
      return TRUE;
    }catch(Exception $e) {
      $this->log->preLog('Failure: record NOT written to '.$queryParams['fileName']);
      $this->log->preLog($e->getMessage());
      return FALSE;
    }
  }

  function createRecordInDb($params){
    $this->log->preLog(__METHOD__);

    foreach($params as $key => $value){
      $$key = $value;
    }

    $this->log->preLog('Params: '.print_r($params, TRUE), 3);

    if($queryParams['fileName'] == 'trucks'){
      //Only insert create date and source for created records (not updates)
      $data['30'] = 'Y';
      $data['38'] = date_format(date_create(date('Y-m-d')), "m/d/y");
      $data['39'] = 'SOAP';
      $fieldMap['30'] = 30;
      $fieldMap['38'] = 38;
      $fieldMap['39'] = 39; 

      //Accommodate Home Id field 9
      //and account number for field 4
      $fieldMap['4'] = 4;
      $fieldMap['9'] = 9;
    }

    try{
      $result = $this->db->getRequestResult($queryParams, $fieldMap);
    }catch(Exception $e) {
      $this->log->preLog("Failure during record search");
      return FALSE;
    }

    $this->log->preLog('Record returned: '.print_r($result, TRUE), 3);
    if(!empty($result)){
      $this->log->preLog('A record already exists...returning');
      $this->log->preLog('Result: '.print_r($result, TRUE));
      return FALSE;
    }
    
    try{
      $result = $this->db->doUpdate($data);

      if($queryParams['fileName'] == 'trucks'){
        //Now that we have the record number, write it to field 9
        $data['9'] = 3000 + $result[0]['rn'];
        //and write the account number to field 4
        $data['4'] = 3 + $result[0]['rn'];
        $result = $this->db->doUpdate($data);
      }
      $this->log->preLog('Success: record written to '.$queryParams['fileName']);

      $this->log->preLog('doUpdate passed: '.print_r($data, TRUE)."\n");
      $this->log->preLog('doUpdate returned: '.print_r($result, TRUE)."\n");
      return TRUE;
    }catch(Exception $e) {
      $this->log->preLog('Failure: record NOT written to '.$queryParams['fileName']);
      $this->log->preLog($e->getMessage());
      return FALSE;
    }
  }

  function getDataFromDb($queryParams, $fieldMap){
    $this->log->preLog(__METHOD__, 3);
    $this->log->preLog(print_r($queryParams, TRUE), 3);
    $this->log->preLog(print_r($fieldMap, TRUE), 3);
    
    try{
      $result = $this->db->getRequestResult($queryParams, $fieldMap);
      $this->log->preLog(__METHOD__.' Success: obtained records from '.$queryParams['fileName']);
    }catch(Exception $e) {
      $result = __METHOD__.' Failure: could not obtain records from '.$queryParams['fileName'].': '.$e->getMessage();
      $this->log->preLog($result);
    }
    return $result;
  }
}

?>
