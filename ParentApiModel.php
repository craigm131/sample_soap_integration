<?php

//This is the parent class of classes that accept data from SoapApiIntegrater.php
//Programmer    Craig Millis
//
//Parameter     Type      Req/Opt     Note
//line          string    required    Either a csv or json string 
//config        object    required    This is the object for the specific api, like LoadPosting.
//log           object    required    Object needed to write to the log file

class ParentApiModel{
  
  public $requiredParams  = array('line', 'config', 'log');

  public function __construct($params){
    $this->readParams($params);
    $this->getSoapEquipMap(dirname(__FILE__) . "/../configSoapEquipTypes.json");
    $this->inspectLine();
  }

  private function readParams($params){
    $result = '';

    foreach($this->requiredParams as $value){
      if(!isset($params[$value])){
        $result .= "Missing parameter, $value";
      }
    }

    foreach($params as $key=>$value){
      $this->{$key} = $value;
    }
    
    if(isset($this->log)){
      $this->log->preLog(__CLASS__.":".__FUNCTION__.": ".$result);
    }

    return $result;
  }

  //This determines what kind of line needs to be processed
  //  command line:json     string
  //  command line:csv      string
  //  file:json             string
  //  file:csv              array
  //If it's a string, and not json, it treats it as a csv
  private function inspectLine(){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
    
    if(is_string($this->line)){
      $this->log->preLog("String: ".$this->line);
      
      //json_last_error_msg is available in php 5.5 and after, so...
      $jsonErrMap = array(
        0 => 'JSON_ERROR_NONE',
        1 => 'JSON_ERROR_DEPTH',
        2 => 'JSON_ERROR_STATE_MISMATCH',
        3 => 'JSON_ERROR_CTRL_CHAR',
        4 => 'JSON_ERROR_SYNTAX',
        5 => 'JSON_ERROR_UTF8'
      );

      //Is this a json string
      $jsonLineArr  = json_decode($this->line, TRUE);
      $jsonErr      = json_last_error();

      if(isset($jsonErrMap[$jsonErr])){
        $jsonErrMsg = $jsonErrMap[$jsonErr];
      }else{
        $jsonErrMsg = 'Unknown error, '.$jsonErr;
      }

      if($jsonErr === JSON_ERROR_NONE){
        $this->log->preLog("This is a json string because json error msg = ".$jsonErrMsg);
        $this->prepareJsonLine($jsonLineArr);
      }else{
        $this->log->preLog("Not a json string because json error msg = ".$jsonErrMsg);
        $this->log->preLog("Treating it as a csv string");
        $this->prepareCsvLine();
      }
    }elseif(is_array($this->line)){
      $this->log->preLog("Array: ".print_r($this->line, TRUE));
      if(!empty($this->line[0])){
        if(in_array($this->line[0], array('LU', 'LA', 'LD'))){
          $this->log->preLog("First element is either LU, LA, or LD, so this is a line from the csv file.");
          $this->prepareCsvLine();
        }else{
          $this->preparePassedInArrayLine();
        }
      }else{
        $this->preparePassedInArrayLine();
      }
    }else{
      $this->log->preLog("Failure: line is neither an array or string: ".print_r($this->line, TRUE));
      exit;
    }
  }
  
  private function preparePassedInArrayLine(){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
    
    if(isset($this->line['request'])){
      $this->request  = $this->line['request'];
      unset($this->line['request']);
    }else{
      $this->log->preLog("Array does not include request.");
      exit;
    }
    $this->getAllowedApiRequestsFromModel($this->request);
    $this->checkForIntegrationId($this->line);
    $this->line     = $this->formatDateFields($this->line);
  }


  private function prepareJsonLine($array){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
    
    $this->log->preLog("Converting string to an array.");
    $this->log->preLog(print_r($array, TRUE));
    $this->line = $array;
    
    if(isset($this->line['request'])){
      $this->request  = $this->line['request'];
      unset($this->line['request']);
    }else{
      $this->log->preLog("Json string does not include request.");
      exit;
    }
    $this->getAllowedApiRequestsFromModel($this->request);
    $this->checkForIntegrationId($this->line);
    $this->line     = $this->formatDateFields($this->line);
  }

  private function prepareCsvLine(){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    //This will be a string if passed from a command line; if passed from a csv
    //file, it will be an array.
    if(is_string($this->line)){ 
      $this->log->preLog("Converting string to an array.");
      $this->line = explode(",", $this->line);
    }
    $this->log->preLog(print_r($this->line, TRUE), 5); 
    
    //The request should be indicated in the line passed to this object.  For a csv line
    //the first element is the request. 
    $this->getAllowedApiRequestsForCsv();
    $this->request  = $this->readRequest($this->line[0]);
    $this->getAllowedApiRequestsFromModel($this->request);
    $this->line     = $this->mapFields($this->line);
    $this->checkForIntegrationId($this->line);
    $this->line     = $this->formatDateFields($this->line);
    $this->line     = $this->removeNullFields($this->line);
  }

  private function checkForIntegrationId($line){
    if(!empty($line['IntegrationId'])){
      $this->log->preLog("Success: found integrationId: ".$line['IntegrationId']);
      $this->integrationId = $line['IntegrationId'];
      unset($this->line->IntegrationId);
    }else{
      if($this->config->name !== 'OnBoarding'){
        $this->log->preLog("Failure: missing IntegrationId: ".print_r($line, TRUE));
      }
    }

    if(!empty($line['Handle'])){
      $this->log->preLog("Success: found Handle: ".$line['Handle']);
      $this->handle = $line['Handle'];
      unset($this->line->Handle);
    }
  }

  private function getAllowedApiRequestsFromModel($request){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
    
    $class    = $this->config->name.'ApiModel';
    $methods  = get_class_methods($class); 
    if($methods == NULL){
      $this->log->preLog("Failure: no methods are defined for this class: ".$class);
      exit;
    }else{
      $this->log->preLog("Success: methods defined for this class, ".$class.", are: ".implode(", ", $methods));
      if(in_array($request, $methods)){
        $this->log->preLog("Success: ".$request." is defined in the class.");
      }else{
        $this->log->preLog("Failure: ".$request." is not defined in the class.");
        exit;
      }
    }
  }

  //Since the csvs don't have keys in their array, the config file must be referenced to
  //determine the key for each field.
  private function getAllowedApiRequestsForCsv(){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $allowedApiRequests = array_keys(get_object_vars($this->config->requests));
    foreach($allowedApiRequests as $key=>$requestName){
      $fileproRequest = $this->config->requests->{$requestName}->fileproRequest;
      foreach($fileproRequest as $value){
        $requestMap[$value] = $requestName;
      }
    }
    $this->requestMap = $requestMap;

    $msg = "Requests allowed for this API are: ".print_r($this->requestMap, TRUE);
    $this->log->preLog($msg);

    return implode(",", $allowedApiRequests);
  }

  private function readRequest($request){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
    
    if(isset($this->requestMap[$request])){
      $result = $this->requestMap[$request];
    }elseif(in_array(array_values($this->requestMap))){
      $result = $request;
    }

    if(!isset($result)){
      $this->log->preLog("Failure: request, ".$request." is not recognized in the config file for this API.  The config file shows these acceptable requests (Filepro request on left, matching Soap request on right, either may be used): ".print_r($this->requestMap, TRUE));
      exit;
    }

    $this->log->preLog("Success: request, ".$request." maps to ".$result);
    $this->request = $result;

    return $result;
  }

  private function mapFields($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    //Obtain the Soap field names
    $fields = array_values(get_object_vars(($this->config->requests->{$this->request}->fields)));

    //Match Filepro fields to config fields and construct array
    $lineCombined = array_combine($fields, $line);

    if($lineCombined === FALSE){
      $this->log->preLog("Failure: number of elements in Filepro array, ".count($line)." does not match the number of fields in config field map, ".count($fields).", for this request, ".$this->request);
      $this->log->preLog("Filepro array: ".implode(",", $line));
      $this->log->preLog("Config array: ".implode(",", $fields));
      exit;
    }else{
      $this->log->preLog("Success: mapped Filepro fields to Soap fields, ".print_r($lineCombined, TRUE), 5);
    }

    return $lineCombined;
  }

  private function formatDateFields($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
    foreach($line as $key=>$value){
      if(stripos($key, 'date') !== FALSE){
        if(is_array($value)){
          foreach($value as $arrKey=>$arrValue){
            if(!empty($arrValue)){
              $line[$key][$arrKey] = date('Y-m-d', strtotime($line[$key][$arrKey]));

              //Remove dates that are older than today as this will cause an error
              $today = date('Y-m-d');
              $this->log->preLog("Comparing today's date, ".$today.", to the PickupDate, ".$line[$key][$arrKey]);
              /*
              if($today > $line[$key][$arrKey]){
                $this->log->preLog("Date is in the past.  Removing: ".$line[$key][$arrKey]);
                unset($line[$key][$arrKey]);
              }
               */
            }else{
              unset($line[$key][$arrKey]);
            }
          } 
        }else{
          if(!empty($line[$key])){
            $line[$key] = date('Y-m-d', strtotime($line[$key]));
          }
        }
      }
    }
    $this->log->preLog("Formatted date and time fields: ".print_r($line, TRUE), 3); 
    return $line;
  }

  private function getSoapEquipMap($file){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $this->log->preLog("Reading equipment type map file: ".$file); 
    $equipMap = json_decode(file_get_contents($file));

    $this->log->preLog("Equipment map: ".print_r($equipMap, TRUE), 5);

    $this->equipMap = $equipMap;

    return $equipMap;
  }

  protected function getSoapEquipType($homeType){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
    if(isset($this->equipMap->$homeType)){
      $result = $this->equipMap->$homeType;
    }else{
     $result = 'SPEC';
    }
    $this->log->preLog("Converting equipment type from ".$homeType." to ".$result);
       
    return $result;
  }

  protected function adjEquipOption($equipType, $currEquipOptions){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
    $equipTypes = array(
      'EVLG'  => 'Expedited',
      'EXPD'  => 'Expedited',
      'FM'    => 'Team',
      'FT'    => 'Tarps',
      'RM'    => 'Team',
      'RZ'    => 'Hazardous',
      'VM'    => 'Team',
      'VZ'    => 'Hazardous'
    );
    if(in_array($equipType, array_keys($equipTypes))){
      $result = $this->adjEquipOptionHelper($equipType, $equipTypes[$equipType], $currEquipOptions);
    }
    return $result;
  }

  private function adjEquipOptionHelper($equipType, $equipOption, $currEquipOptions){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
    if(!in_array($equipOption, $currEquipOptions)){
      $currEquipOptions[] = $equipOption; 
      $this->log->preLog("Adding '".$equipOption."' EquipmentOption because Home equipment type is '".$equipType."'");
    }
    return $currEquipOptions;
  }

  protected function getCountry($state, $zip, $country){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $msg = "\nChecking for country";
    $canProvinces = array('AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'ON', 'PE', 'QC', 'SK', 'YT', 'NT', 'NU');
    if(empty($country)){
      $msg .= "\nCountry not present";
      if(isset($zip)){  //This will never be true until Filepro includes the OriginZip and DestinationZip in the soap_loadpost process
        $zipLen = strlen(trim($zip));
        $msg .= "\nZipcode present, ".$zip.", and contains ".$zipLen." characters";
        if($zipLen == 6){
          $msg .= "\nZip has six characters; country is set to 'CAN'";
          $result = 'CAN';
        }else{
          $msg .= "\nZip does not have six characters; country is set to 'USA'";
          $result = 'USA';
        }
      }elseif(isset($state)){
        $msg .= "\nZipcode is not present; using state: ".$state;
        if(in_array($state, $canProvinces)){ 
          $msg .= "\nState is Canadian; country is set to 'CAN'";
          $result = 'CAN';
        }else{
          $msg .= "\nState is not Canadian; country is set to 'USA'";
          $result = 'USA';
        }
      }
    }else{
      $result = $country;
    }
    $this->log->preLog($msg);
    return $result;
  }

  private function removeNullFields($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
    foreach($line as $key=>$value){
      if($value == NULL ){
        unset($line[$key]);
      }
    }
    $this->log->preLog("Removed null fields: ".print_r($line, TRUE), 5); 
    return $line;
  }
  
  public function createRequestBody(){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    return $this->{$this->request}($this->line); 
  }
}

?>
