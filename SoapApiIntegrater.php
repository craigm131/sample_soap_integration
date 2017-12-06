<?php

///This file defines a class that will accept data and pass it to Soap.com and Home's internal load board.
//Programmer  Craig Millis
//
//  parameter   type      req/optional    note
//  api         string    required        must be one of the apis contained in the configFile
//  line        string    optional        required if pathToFile is not provided.  
//                                        This may be a comma delimted string or a json string.  If it is the former, the fields must be in the order defined in the config file, e.g. "LA","SADFFFSFDFS","DENVER","CO","BOULDER","CO","27","45000","48","0","","02/20/16","","02/22/16","","F","","F","DEMO","(456) 888-9999","1","58179","","","","","","cargoCOCO5817917"
//                                        If a json string is provided, it should include both keys and values (unlike the commad delimted string).
//  pathToFile  string    optional        required if line is not provided.  This should lead to either a CSV file or a json file.
//  debug       boolean   optional        Set to true to see the SOAP string that is sent to Soap.com 

require_once('/htdocs/common/php/db/db.php');
require_once('/htdocs/common/php/DBRequest/DBDataRequest.php');
require_once('/htdocs/common/php/DBFramework/DBModel.php');
require_once('/htdocs/common/php/logger/Logger.php');

foreach(glob(dirname(__FILE__)."/models/*.php") as $path){
  //exclude backup files that start with '_'
  if(substr(basename($path), 0, 1) !== '_'){
    require_once($path);
  }
}

error_reporting(0);//Set to 0 in Produciton; -1 reports all PHP errors

class SoapApiIntegrater extends SoapClient{

  // Output and testing flags
  protected $sendRequest      = TRUE;

  // Variables used in the file
  public $log;
  public $debug;
  public $useProductionWsdl   = TRUE; //set to TRUE in production
  public $logDetailSetting    = 2;

  public function __construct($params = array()){
    register_shutdown_function(array($this, "shutdown"));
    $this->oldMask            = umask(0);
    $this->defaultPerms       = 0777;
    $this->defaultPermsDec    = decoct($this->defaultPerms);
    $this->dateObj            = $this->createTimestamp();
    $this->dateTime           = date_format($this->dateObj, "mdHisu");
    $this->dateTimeLastLog    = $this->dateTime;
    
    $this->params               = $params;
    $this->startLog();
    $this->configObj            = $this->getConfig(dirname(__FILE__) . "/configSoapApi.json");
    $this->configObj->errorMap  = $this->getConfig(dirname(__FILE__) . "/configSoapErrorMessages.json");
    $this->checkParams();
    $this->readApiParams();
    $this->invokeSoapClient();
    $this->executeApiRequest();
  }

  public function __destruct() {
    if (isset($this->db)) {
      $this->db->close();
    }
    umask($this->oldMask);
    //echo "\nDestroying object: ".__CLASS__;//comment-out in production
  }

  private function createTimestamp(){
    $t = microtime(true);
    $micro = sprintf("%06d",($t - floor($t)) * 1000000);
    $d = new DateTime( date('Y-m-d H:i:s.'.$micro, $t) );
    return $d;
  }

  private function startLog(){
    //$logDir = "/pix/".$this->params['cust']."/edi/soap/log/";
    $logDir = "/pix/soap_api/log/";
    if(empty($this->params['noLog'])){
      $this->log = new Logger(array('logDir'=>$logDir, 'addSubDir'=>TRUE, 'logDetailSetting'=>$this->logDetailSetting));
    }else{
      $this->log = new Logger(array('noLog'=>TRUE));
    }
    $this->log->preLog("Input parameters: ".print_r($this->params, TRUE));
  }

  private function getConfig($path){
    $str = file_get_contents($path);
    $obj = json_decode($str);
    if($str === FALSE){
      $this->log->preLog("Failed to get config file, ".$path);
      exit;
    }
    if($obj == NULL){
      $this->log->preLog("Failed to decode json string: ".$str);
      exit;
    }
    $this->log->preLog("Read config file, ".$path, 2);
    $this->log->preLog("Read config file, ".$path.": ".$str, 5);
    return $obj;
  }

  private function checkParams(){
    $result = '';
    
    if(!isset($this->params['api'])){
      $result .= "\nMissing api.  Must pass one of the following:";
      foreach($this->configObj->apis as $key=>$value){
        $result .= "\n\t".$key;
      }
    }else{
      //On encoding and decoding...granted, this is odd.  This works, though...short on time.
      $allowedApis = array_keys(json_decode(json_encode($this->configObj->apis), TRUE));
      if(in_array($this->params['api'], $allowedApis) === FALSE){
        $result .= "\nThe API, ". $this->params['api'].", is not recognized.  Must be one of these:";
        foreach($this->configObj->apis as $key=>$value){
          $result .= "\n\t".$key;
        }
      }
    }

    if(isset($this->params['line'])){
      if((isset($this->params['pathToFile']))){
        $result .= "\nBoth parameters 'line' and 'pathToFile' were provided.  Only one should be submitted.";
      }
    }

    if(isset($this->params['pathToFile'])){
      if(!file_exists($this->params['pathToFile'])){
        $result .= "\nPath does not lead to a file: ".$this->params['pathToFile'];
      }
    }

    if(isset($this->params['debug'])){
      if(strtoupper($this->params['debug']) == 'TRUE'){
        $this->debug = TRUE;
      }else{
        $this->debug = FALSE;
      }
    }

    if($result !== ''){
      $this->echoDebug(__FUNCTION__, $result);
      $this->log->preLog("Failure: insufficient parameters: ".$result);
      exit;
    }
  }

  private function readApiParams(){
    $this->log->preLog(__FUNCTION__);
    
    $this->api        = $this->configObj->apis->{$this->params['api']};
    $this->api->name  = $this->params['api'];
    
    $testIntegrationId = FALSE;
    if(!empty($this->params['line']['IntegrationId'])){
      if($this->params['line']['IntegrationId'] == '4512'){
        $testIntegrationId = TRUE;
        $this->log->preLog("Input parameters contain integration id 4512");
      }
    }

    if($this->useProductionWsdl == TRUE AND $testIntegrationId === FALSE){
      $this->api->wsdl      = $this->configObj->apis->{$this->params['api']}->wsdlProduction;  
    }else{
      $this->api->wsdl      = $this->configObj->apis->{$this->params['api']}->wsdlTest;  
    }
    if(isset($this->params['line']['IntegrationId'])){
      $this->log->preLog("IntegrationId: ".$this->params['line']['IntegrationId']);
    }
    $this->log->preLog("Using this wsdl: ".$this->api->wsdl);
    $this->log->preLog("Reading parameters for api, ".$this->api->name, 2);
    $this->log->preLog("Reading parameters for api, ".$this->api->name.": ".print_r($this->api, TRUE), 5);
  }

  //Invoke SoapClient to read in the WSDL.  
  //Passing the second parameter, array('trace'=>1), allows one to use __getLastRequest for debugging.
  private function invokeSoapClient(){
    $this->log->preLog(__FUNCTION__);

    $daysToCacheWsdl = 30;
    $seconds = $daysToCacheWsdl * 60 * 60 * 24;

    $wsdlSettings = array(
      'soap.wsdl_cache_enabled'   => 1,//Set to 1 to enable soap caching
      'soap.wsdl_cache_dir'       => '/htdocs/integrations/soap_api/wsdl',//location of cached wsdls
      'soap.wsdl_cache_limit'     => 10,//maximum number of wsdl files to cache
      'soap.wsdl_cache'           => 1,//type of caching, 1=disk caching, 2=memory cache
      'soap.wsdl_cache_ttl'       => $seconds//number of seconds that cached files will be used instead of the originals
    );

    foreach($wsdlSettings as $key => $value){
      ini_set($key, $value);
      $this->log->preLog($key.": ".$value);
    }  

    try{
      parent::__construct(
        $this->api->wsdl, 
        array(
          'trace'     => 1      //Set to 1 to be able to trace faults, however this may consume more memory
          //,'location'  	=> ?, //location is the url of the SOAP server to which the request is sent
          //,'uri'       	=> ?  //uri is the target namespace of the SOAP service
        )
      );
    }catch(Exception $e){
      $this->echoDebug(__FUNCTION__, debug_backtrace());
      $this->log->preLog(print_r(debug_backtrace(), TRUE));
    }     
  }

  private function executeApiRequest(){
    $this->log->preLog(__FUNCTION__);

    //Use file if the path was passed
    if(isset($this->params['pathToFile'])){
      $path = $this->params['pathToFile'];
      
      //Copy file to log dir
      $sourcePath = $this->log->logDir.basename($path);   
      if(copy($path, $sourcePath) === FALSE){
        $this->log->preLog("Failure: did not copy file from ".$path." to ".$sourcePath);
        exit;
      }else{
        $this->log->preLog("Success: copied file from ".$path." to ".$sourcePath);
      }

      $fileStr = file_get_contents($sourcePath);
      if($fileStr === FALSE){
        $this->log->preLog("Failed to read file into string.");
        exit;
      }
      
      //If it's a json string, process
      $array = json_decode($fileStr);
      $jsonError = json_last_error();//shows 'No Error' if no error has occurred
      
      if(json_last_error() === JSON_ERROR_NONE){
        $this->processDataFromJsonFile($sourcePath, $array);
      }else{
        $this->processDataFromCsvFile($sourcePath);  
      }  
    }else{
      return $this->processDataFromCommandLine();
    } 
  }

  function processDataFromCommandLine($line = ''){
    $this->log->preLog(__FUNCTION__);
    
    $responsePath = $this->log->logDir."commandline_response.txt";

    //$line can either be passed via the constructor params or this method's params
    if(!empty($this->params['line'])){
      $line = $this->params['line'];
    }elseif($line == ''){
      return;
    }
    
    if(is_string($line)){
      $msg = $line; 
    }else{
      $msg = print_r($line, TRUE);
    }
    
    //Send envelope and get response
    $body = $this->constructBody($line);
    $response = $this->executeRequest($this->apiModel->request, $body);
    $formattedResponse = $this->formatResponse($response, $line);
    if(is_string($formattedResponse)){
      $msg .= ",response:".$formattedResponse;
    }else{
      $msg .= ",response:".print_r($formattedResponse, TRUE);
    }

    if(empty($this->params['noLog'])){
      file_put_contents($responsePath, $msg);
    }

    return $response;
  } 

  private function processDataFromJsonFile($path, $array){
    $this->log->preLog(__FUNCTION__);

    $responsePath = dirname($path)."/".basename($path, ".csv")."_response.csv";
    $this->openFile($path, 'sourceHandle', 'r');
    $this->openFile($responsePath, 'responseHandle', 'a');
    
    foreach($array as $lineArray){
      $line = json_encode($lineArray);//converts to string
      $body = $this->constructBody($line);
      $response = $this->executeRequest($this->apiModel->request, $body);
      $lineArray['response'] = $this->formatResponse($response, $line);
      dbutcsv($this->responseHandle, $lineArray);
    }
    
    fclose($this->responseHandle);
    fclose($this->sourceHandle);
  }

  private function processDataFromCsvFile($path){
    $this->log->preLog(__FUNCTION__);

    $responsePath = dirname($path)."/".basename($path, ".csv")."_response.csv"; 
    $this->openFile($path, 'sourceHandle', 'r');
    $this->openFile($responsePath, 'responseHandle', 'a');

    while(($line = fgetcsv($this->sourceHandle)) !== FALSE){
      //Send envelope and get response
      $body = $this->constructBody($line);
      $response = $this->executeRequest($this->apiModel->request, $body);
      $line['response'] = $this->formatResponse($response, $line);

      //Since a csv file is being processed, it means that batching requests is occurring where Database creates the csv.  Thus, the response from
      //Soap should be written to Database's soap file.  Note that $line['LoadNumber'] is Home's unique string to identify the request, e.g.
      //'cargoGAVA11231306'.

      //Convert the message to a shortened version that will be stored in Database 
      $this->writeToDbSoap($this->mapErrorMessage($line['response']), 47, $line[27]);
      
      dbutcsv($this->responseHandle, $line);
    };
    
    fclose($this->responseHandle);
    fclose($this->sourceHandle);
  }

  private function mapErrorMessage($msg){
    $this->log->preLog(__FUNCTION__, 5);
    $msg = strtoupper($msg);
    foreach($this->configObj->errorMap as $key=>$value){
      //$this->log->preLog("key: ".$key."  value: ".$value);
      if(substr($msg, 0, strlen($key)) === $key){
        $result = $value;
        break;
      }else{
        $result = $this->configObj->errorMap->DEFAULT;
      }
    }
    $this->log->preLog(__METHOD__." Soap message, ".$msg.", maps to Home message, ".$result);
    return $result;
  }

  private function getCust($id){
    $this->log->preLog(__FUNCTION__, 5); 
    $dashPosition = strpos($id, '-');
    $cust = substr($id, 0, $dashPosition);
    $this->log->preLog(__METHOD__.": Position of dash in ".$id." is ".$dashPosition." so cust is ".$cust);
    return $cust;
  }

  private function writeToDbSoap($msg, $field, $uniqueLoadId){
    $this->log->preLog(__FUNCTION__, 5); 

    $this->log->preLog("Writing this string to Database's soap file: ".$msgi, 5);
    $this->log->preLog("To field ".$field." of soap file, where field 28 equals ".$uniqueLoadId, 5);

    $params = array(
      'realCust'  => $this->getCust($uniqueLoadId),
      'noCust'    => 'N',
      'noauth'    => 'Y',
      'exact'     => 'Y',
      'fileName'  => 'soap',
      'index'     => 'C',
      'key'       => $uniqueLoadId,
      'limit'     => 1
    );
    
    $fieldMap = array(
      'Actions'           => 1,
      'Truck Stop Account'=> 2,
      'Starting City'     => 3,
      'Contact'           => 19,
      'Contact Phone'     => 20,
      'Pro #'             => 22,
      'Unique Posting ID' => 28,
      'ITS Integration ID'=> 43,
      'Comment'           => 47
    );
    $this->log->preLog("dbrequest: ".print_r($params, true));

    try{
      $db = new DBDataRequest();
      $this->log->preLog("db result: ".print_r($db->getRequestResult($params, $fieldMap), TRUE));
      //$db->setRequest($params, $fieldMap);
      $db->doUpdate(array('Comment'=>$msg));
    }catch(Exception $e){
      $this->log->preLog("Failed to write to Database's soap file");
    }
  }

  private function constructBody($line){
    $this->log->preLog(__FUNCTION__); 

    //Construct envelope body
    $requestParams = array(
      'line'        => $line,
      'config'      => $this->api,
      'log'         => $this->log
    );
    $modelName        = $this->api->name.'ApiModel';
    $this->apiModel   = new $modelName($requestParams);
    $body             = $this->apiModel->createRequestBody();

    //Add username and password one level down in array
    if($this->api->name !== 'OnBoarding'){
      $body[key($body)]['UserName']       = $this->configObj->username;
      $body[key($body)]['Password']       = $this->configObj->password; 
      $body[key($body)]['IntegrationId']  = $this->apiModel->integrationId;
      if(isset($this->apiModel->handle)){
        $body[key($body)]['Handle']       = $this->apiModel->handle;
      }
    }

    $this->log->preLog("Line: ".print_r($line, TRUE), 5);
    $this->log->preLog("Constructed body: ".print_r($body, TRUE));

    //Ensure that integrationIds and handles are not assigned to wrong records
    unset($this->apiModel->integrationId);
    unset($this->apiModel->handle);
    $this->log->preLog("Unset integrationId and handle within apiModel object.");
    
    return $body;
  }

  private function openFile($path, $handle, $mode){
    $this->{$handle} = fopen($path, $mode);
    if($this->{$handle} === FALSE){
      $this->log->preLog("Failure: could not open or create file, ".$path);
      exit;
    }else{
      $this->log->preLog("Success: opened file, ".$path);
    }
  }

  private function formatResponse($response, $line){
    $this->log->preLog(__FUNCTION__);

    $errors = NULL;
    if(isset($response)){
      if(is_array($response) || is_object($response)){
        foreach($response as $obj){
          if(isset($obj->Errors->Error)){
            $errors = $obj->Errors->Error;
            break;
          }
        }
      }
    }
    
    $this->log->preLog("errors: ".print_r($errors, true));
    $this->log->preLog("response: ".print_r($response, true));
    
    if(is_array($errors)){
      if(isset($errors[0]->ErrorMessage)){
        $errors = $errors[0]->ErrorMessage;
      }
    }else{
      if(isset($errors->ErrorMessage)){
        $errors = $errors->ErrorMessage;
      }
    }

    $msg = $errors;
    if(empty($msg)){
      if(strpos(print_r($response, true), 'e->faultstring') !== FALSE){
        $this->log->preLog("response contains 'e->faultstring', thus Soap error");
        $msg = $this->configObj->errorMap->DEFAULT;
      }else{
        $this->log->preLog("response does not contain 'e->faultstring'", 5);

        if(isset($line[0])){
          if($line[0] == 'LD'){
            $msg = 'deleted';
          }else{
            $msg = $this->configObj->errorMap->DEFAULTOK;
          }
        }else{
          $msg = $this->configObj->errorMap->DEFAULTOK;
        }
      }    
    }

    $this->log->preLog("msg: ".print_r($msg, true), 5);

    $this->message  = $msg; 
    $this->response = $response;
    return $msg;
  }

  private function getFieldIndex($fieldName){
    $this->log->preLog(__FUNCTION__);
    $array  = array_values(get_object_vars(($this->api->requests->{$this->params['request']}->fields)));
    $result = array_search($fieldName, $array);
    $this->log->preLog("Field index for ".$fieldName." is ".$result);
    return $result;
	}

  function echoDebug($function, $result){ 
    if($this->debug === TRUE){

      echo "\n$function result: ";
      if(is_string($result)){
        echo $result;
      }else{
        var_dump($result);
      }
    }
  }

  //This function executes a function that is defined in the WSDL and handles the results returned by the API. 
  public function executeRequest($request, $params){
    $this->log->preLog(__FUNCTION__);
    
    $paramsString = json_encode($params);
    
    try{
      $result = $this->{$request}($params);
    }catch(Exception $e){
      $this->log->preLog("Failure: ".__FUNCTION__." ".$paramsString);
      $result = '';
      if(isset($e->detail->message)){
        $result = "e->detail->message: ".$e->detail->message;
        $this->log->preLog($result);
      }
      if(isset($e->faultstring)){
        $result = "e->faultstring: ".$e->faultstring;
        $this->log->preLog($result); 
      }
      if($result == ''){
      	$this->log->preLog("No results found.");
      }
      return $result;
    }

    $this->echoDebug(__FUNCTION__, $result);
    $this->log->preLog("Request result: ".print_r($result, TRUE), 5);
    return $result;
  }

  //This function is used for debugging the XML request string.
  public function __doRequest($request, $location, $action, $version, $one_way = 0){
    $this->log->preLog("Executing ".__FUNCTION__.", request=".$request, 5);
      
    $doc = new DOMDocument;
    $doc->preserveWhiteSpace = FALSE;
    $doc->loadxml($request);
    $doc->formatOutput = TRUE;
    $out = $doc->savexml();
    $this->log->preLog($out);

    //Echo the request
    if($this->debug === TRUE){
      echo "\ndebug: ".$this->debug;
      echo "\nrequest: ".$request;
      echo "\nlocation: ".$location;
      echo "\naction: ".$action;
      echo "\nTest-> ".$out." <-Test";
      file_put_contents("xmlString.txt", $out);
    }

    //Send request if desired
    if($this->sendRequest){
      return parent::__doRequest($request, $location, $action, $version, $one_way);
    }else{
      return '';
    }
  }

  function sendToHomeLoadboard($body, $request){
    $this->log->preLog(__FUNCTION__);

    $bdexMap = array(
      'PostLoads' => array(
        'data'         => $body['loads']['Loads']['Load'][0],
        'url'          => 'http://home.com:80/api/1/loads/create_or_update',
        'curlRequest'  => 'POST'
      ),
      'GetLoadSearchResults' => array(
        'data'         => $body['searchRequest']['Criteria'],
        'url'          => 'http://home.com:80/api/1/loads/search',
        'curlRequest'  => 'GET' 
      ),
      'PostTrucks' => array(
        'data'         => $body['trucks']['Trucks']['Truck'][0],
        'url'          => 'http://home.com:80/api/1/trucks/create_or_update',
        'curlRequest'  => 'POST'
      ),
      'GetTruckSearchResults' => array(
        'data'         => $body['searchRequest']['Criteria'],
        'url'          => 'http://home.com:80/api/1/trucks/search',
        'curlRequest'  => 'GET' 
      )
    );

    $bdexMap[$request]['data']['DestinationCountry'] = 'USA';

    if(isset($bdexMap[$request])){
      $data         = $bdexMap[$request]['data'];
      $url          = $bdexMap[$request]['url'];
      $curlRequest  = $bdexMap[$request]['curlRequest'];
    }else{
      $this->log->insert("Request for BDEX is not recognized: ".$request);
      return;
    }

    $this->log->preLog("Data before formatting: ".print_r($data, TRUE));

    if(is_string($data)){
      $data = json_decode($data, TRUE);
    }

    unset($data['IntegrationId']);
    unset($data['Handle']);

    $this->log->preLog("Unset IntegrationId and Handle");

    $this->log->preLog("Data after formatting: ".print_r($data, TRUE));
    
    $curlLogFile = 'curlLog.txt';
    $curlLog = fopen($curlLogFile, 'w+');
    if($curlLog === FALSE){
      $this->log->preLog("Failed to open file: ".$curlLogFile);
    }else{
      $this->log->preLog("Opened file: ".$curlLogFile);
    }

    $curl = curl_init();
    curl_setopt_array($curl, array(
      //CURLOPT_URL               => "http://home.com:8989/loads/soap.json", 
      //CURLOPT_URL               => "http://home.com:80/api/1/loads/create_or_update", 
      CURLOPT_URL               => $url, 
      CURLOPT_CUSTOMREQUEST     => $curlRequest, 
      CURLOPT_POSTFIELDS        => $data,
      CURLOPT_RETURNTRANSFER    => TRUE,
      CURLOPT_FOLLOWLOCATION    => TRUE,
      CURLOPT_VERBOSE           => TRUE,
      CURLOPT_STDERR            => $curlLog
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    
    fclose($curlLog);

    $this->log->preLog("Sending this to CURL: ".print_r($data, TRUE));
    $this->log->preLog("Sending this CURL request to Home Loadboard: ".print_r($curlLog, TRUE));
    $this->log->preLog("Sending this CURL request to Home Loadboard: ".file_get_contents('curlLog.txt'));
    $this->log->preLog("Response from Home Loadboard: ".print_r($response, TRUE)); 
  }

  function shutdown(){
    //Write to log - must submit full path to file
    if(isset($this->logDir)){
      $logFile = $this->logDir.$this->logName;
    }
  }
}

?>
