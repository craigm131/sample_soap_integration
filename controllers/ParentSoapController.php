<?php

//This class is the parent to OnBoardingController.php and CacciController.php.
//Programmer:  Craig Millis
//
//This class is used in the following way:  only one of the child classes is called first, and
//that child class instantiates this parent class within its constructor.
//
//Each of the child classes have almost the same methods, e.g. getListOfCarriers.

require_once('/htdocs/common/php/logger/Logger.php');
require_once(__DIR__.'/../models/DboOnBoardingCacciModel.php');
require_once(__DIR__.'/../helpers/ObjToFlatArray.php');
require_once(__DIR__.'/../SoapApiIntegrater.php');

abstract class ParentSoapController{

  var $logDir               = '/pix/soap_api/log/';
  var $dataForDb            = array();
  var $logDetailSetting     = 2; //Set to 2 in Production. The higher the number, the more detail is written to logs
  var $debug                = FALSE; //Set to FALSE in production.  Setting to TRUE will result in the most detail written to logs
  var $areWeTesting         = FALSE; //Set to FALSE in production.  Setting to TRUE will use specific dates to ensure data is returned from Soap

  function __construct($params = ''){
    $this->setAdmin(); 
    error_reporting(-1);//Set to 0 in production
    $this->readParams($params);
    $this->tsIntegrater = new SoapApiIntegrater($params);
  }

  private function createTimestamp(){
    $t = microtime(true);
    $micro = sprintf("%06d",($t - floor($t)) * 1000000);
    $d = new DateTime( date('Y-m-d H:i:s.'.$micro, $t) );
    return $d;
  }

  private function setAdmin(){ 
    $this->oldMask            = umask(0);
    $this->defaultPerms       = 0775;
    $this->defaultPermsDec    = decoct($this->defaultPerms);
    $this->dateObj            = $this->createTimestamp();
    $this->dateTime           = date_format($this->dateObj, "mdHisu");
    $this->dateTimeLastLog    = $this->dateTime;
  }
 
  //Check for minimum parameters required
  private function readParams($params){
    //Use existing log if it was passed
    if(isset($params['log'])){
      $this->log = $params['log'];
      unset($params['log']);
    }else{
      $this->log = new Logger(array(
        'logDir'            => $this->logDir, 
        'addSubDir'         => FALSE, 
        'logDetailSetting'  => $this->logDetailSetting)
      );
    }

    $this->log->preLog(__METHOD__); 
    
    $this->log->preLog("Input parameters: ".print_r($params, TRUE));
    if(is_array($params)){
      $this->params = json_decode(json_encode($params), FALSE);//Convert to an object
      $this->log->preLog("Converted input parameters to object.", 5);
    }

    //Check that these are passed
    $requiredParams = array('api', 'method', 'targetFile');
    $msg = null;
    foreach($requiredParams as $requiredParam){
      if(!isset($this->params->$requiredParam)){
        $msg .= "\nFailure: did not provide ".$requiredParam;
      }
    }
    if($msg){ 
      $this->log->preLog($msg); 
      exit;
    }
 
    $this->params->realCusts = $this->readCusts($this->configPath); 
    $this->readFieldMapPath($this->fieldMapPath);
    
    $this->log->preLog("Input parameters after ".__FUNCTION__." method: ".print_r($this->params, TRUE), 5);
  }
  
  //Read custs in config file
  private function readCusts($path){
    $str = file_get_contents($path);

    if($str === FALSE){
      $this->log->preLog("Can't read ".$path);
      exit;
    }
    $this->log->preLog("Reading custs enabled for this process in ".$path, 2);
    $grossCusts = explode(PHP_EOL, $str);

    foreach($grossCusts as $key => $value){
      if(substr($value, 0, 1) !== '#' AND empty($value) === FALSE){
        $realCusts[$key] = $value;
      }
    }
    return $realCusts;
  }

  private function readFieldMapPath($path){
    $str = file_get_contents($path);

    if($str === FALSE){
      $this->log->preLog("Can't read ".$path);
      exit;
    }

    $obj = json_decode($str);

    if(empty($obj->{$this->params->targetFile})){
      $this->log->preLog("For target file, ".$this->params->targetFile.", there is no field map indicated in ".$path);
      exit;
    }else{
      $this->log->preLog("For target file, ".$this->params->targetFile.", field map found in ".$path, 2);
      $this->params->fieldMap = $obj->{$this->params->targetFile};
    }
  }

  protected function getControlValue($realCust, $fieldMap){
    $this->log->preLog(__METHOD__, 4);
    
    $db = new DboOnBoardingCacciModel(array('log' => $this->log, 'debug' => $this->debug, 'logDetailSetting' => $this->logDetailSetting));

    $queryParams = array(
      'fileName'  => 'control',
      'realCust'  => $realCust,
      'noCust'    => 'N',
      'noauth'    => 'Y',
      'exact'     => 'Y'
    );

    $grossResult = null;
    try{
      $grossResult = $db->getDataFromDb($queryParams, $fieldMap);
    }catch(Exception $e){ 
      $this->log->preLog('Failed dbData query: '.print_r($queryParams, TRUE).print_r($fieldMap, TRUE));
    }
    $this->log->preLog($grossResult, 5);

    $result = array('BurstStatus' => 'N');
    if(!empty($grossResult[0]['BurstStatus'])){
      $result['BurstStatus'] = $grossResult[0]['BurstStatus'];
    }
    $this->log->preLog(__METHOD__.' result: '.print_r($result, TRUE), 2);
    return $result;
  }

  protected function getCarrierData($carrier, $reports, $apiParams, $resultProperty){
    $this->log->preLog(__METHOD__);
    $this->log->preLog("Memory being used: ".memory_get_usage());
    
    if(isset($carrier->CompanyName)){ $this->log->preLog($carrier->CompanyName); }

    //This increases memory used by 20kb 
    $builder = new ObjToFlatArray(array('fieldMap' => $this->params->fieldMap, 'log' => $this->log, 'debug' => $this->debug, 'logDetailSetting' => $this->logDetailSetting));
    
    //Get the list of carriers returned in the getCoreList report and flatten it into an array  
    $carrierObj = new stdClass();//Create an object so that the properties will appear in the key using ObjToFlatArray()
    $carrierObj->{$resultProperty} = $carrier;
    $builder->processData($carrierObj);
    
    foreach($reports as $report){
      $apiParams['line']['request']  = $report;
      $this->log->preLog("Memory being used: ".memory_get_usage(), 5);

      $rawResponse = $this->tsIntegrater->processDataFromCommandLine($apiParams['line']);

      //Commenting-out these log lines have little to no effect on memory used
      $this->log->preLog("Searching for records with these parameters: ".print_r($apiParams, true), 3);
      if(!empty($this->tsIntegrater->response)){
        $this->log->preLog("Result: records were returned", 3);
        $this->log->preLog("Result: ".print_r($this->tsIntegrater->response, true), 5);//set to 5 in Prod
      }else{
        $this->log->preLog("Result: no records were returned");
      }

      //Take object returned by Soap request and convert to one-dimensional array
      $builder->processData($this->tsIntegrater->response);
    }
    
    //This increases memory used by 6kb
    $result = $builder->createOutputArray($builder->sourceArray); 
     
    return $result;
  }
  
  function createRecordInDb($queryParams, $data){
    $this->log->preLog(__METHOD__);

    $params['queryParams']  = $queryParams;
    $params['fieldMap']     = array_combine(array_keys($data), array_keys($data));
    $params['data']         = $data;

    $db = new DboOnBoardingCacciModel(array('log' => $this->log, 'debug' => $this->debug, 'logDetailSetting' => $this->logDetailSetting));

    $result =  $db->createRecordInDb($params);
    unset($db);

    return $result;
  }
  
  function updateRecordInDb($queryParams, $data){
    $this->log->preLog(__METHOD__);

    $params['queryParams']  = $queryParams;
    $params['fieldMap']     = array_combine(array_keys($data), array_keys($data));
    $params['data']         = $data;

    $db = new DboOnBoardingCacciModel(array('log' => $this->log, 'debug' => $this->debug, 'logDetailSetting' => $this->logDetailSetting));

    $result = $db->updateRecordInDb($params);
    unset($db);

    return $result;
  }

  //This function will create or update records in a Database file, given the MC or DOT numbers.
  //  $data: a one dimensional array that has the number of the Database field as a key
  protected function writeToDb($carrier, $indexes, $queryParams, $data, $createIfNotFound = FALSE){//Craig 112216 changed to FALSE to avoid creating a carrier from getInactiveCarriers
    $this->log->preLog(__METHOD__, 3);
    //If have MC# from Soap, attempt to update existing record with MC#
    //  If have DOT#, attempt to update existing trucks record with DOT#
    //  else create new record 
    foreach($indexes as $key => $index){
      $this->log->preLog('carrier['.$index.'] = '.$carrier->$index.'  targetFile='.$this->params->targetFile);
      $queryParams['key']    = $carrier->$index;
      $queryParams['index']  = $key;
      
      //Craig 111716 fix bug where an alpha key submitted to the DOT number index will return all records with a null DOT number!!!
      if(preg_match("/[^0-9]/", $queryParams['key']) == 1){	  
        $this->log->preLog("Cannot accept alphas in this Database field.  Ignoring this record.");
        break;
      }

      //MCNumber will come from OnBoarding API, which may send an MC and DOT.  McNumber will come from Cacci API.
      if(($index == 'MCNumber' || $index == 'McNumber') AND !empty($carrier->$index)){
        $result = $this->updateRecordInDb($queryParams, $data);
        if($result){
          //Existing record with MC# was found and updated.  Thus, exit loop.
          break;
        }
        if($this->params->targetFile == 'carrierwatch'){
          if($createIfNotFound == TRUE){
            $this->createRecordInDb($queryParams, $data);
          }
          break;
        }
      }

      //Only the OnBoarding API will use this.
      if($index == 'USDotNumber'){
        if(!empty($carrier->$index)){
          $result = $this->updateRecordInDb($queryParams, $data);
          if($result){
            //Existing record with DOT# was found and updated.  Thus, exit loop.
            break;
          }else{
            $this->log->preLog("Existing record is not found by MC or DOT");
            //Existing record with DOT# was not found.  Thus, create the record.
            if($createIfNotFound == TRUE){
              $this->createRecordInDb($queryParams, $data);
            }
            break;
          }
        }
        //Create the record if DOT is empty and MC is not empty
        if(empty($carrier->$index) AND !empty($carrier->MCNumber)){
          //Existing record with DOT# was not found.  Thus, create the record.
          $queryParams['key']    = $carrier->MCNumber;
          $flip = array_flip($indexes);
          $queryParams['index']  = $flip['MCNumber'];

          if($createIfNotFound == TRUE){
            $this->createRecordInDb($queryParams, $data);
          }
          break;
        }
      }
    }
  }
}



?>
