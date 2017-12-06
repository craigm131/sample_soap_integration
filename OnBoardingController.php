<?php

/*
 * This class enables downloading data from Soap's OnBoarding API, among other methods.
 * Programmer:  Craig Millis
 *
 * This class is called by:
 *   getOnBoardedCarriersFromSoap.php
 *   getInactiveCarriersFromSoap.php
 *
 * getOnBoardedCarriersFromSoap:
 * 1) creates an OnBoardingController object and passes these parameters:
 *   'method'     => 'getOnBoardedCarriersFromSoap',
 *   'targetFile' => 'trucks',
 *   'api'        => 'OnBoarding',
 *   'noLog'      => TRUE
 *
 * 2) The parent object then sets parameters:
 *   a. sets permissions and creates date object
 *   b. reads parameters:
 *      accepts log, if passed and sets to this->log
 *      this->params->method
 *      this->params->targetFile
 *      this->params->api
 *      this->params->noLog
 *      this->params->realCusts
 *      this->params->fieldMap
 *
 * 3) Creates a SoapIntegrater object and sets to this->tsIntegrater
 * 4) Iterates through each real cust and:
 *   a. gets the cust's Soap credentials from config (user and pword)
 *   b. gets a list of recently onboarded carriers from Soap
 *   c. for each carrier:
 *     i.   retrieves five reports from Soap
 *     ii.  adjusts specific fields
 *     iii. creates/updates trucks with the report data
 *   d. iterate a second time through the list of carriers, getting Cacci reports
*/

require_once('ParentSoapController.php');

class OnBoardingController extends ParentSoapController{

  var $configPath           = '/u/home/config/soap_onboarding';
  var $fieldMapPath         = '/u/home/config/soap_onboarding_map';

  function __construct($params = ''){
    parent::__construct($params);
    $this->processRequest();
  }

  protected function getCredentials($realCust, $user = ''){
    $this->log->preLog(__METHOD__, 3);
    
    $db = new DboOnBoardingCacciModel(array('log' => $this->log, 'debug' => $this->debug, 'logDetailSetting' => $this->logDetailSetting));

    $queryParams = array(
      'fileName'  => 'config',
      'realCust'  => $realCust,
      'noCust'    => 'N',
      'noauth'    => 'Y',
      'exact'     => 'Y'
    );

    $fieldMap = array(
      'UserName'  => '305',
      'Password'  => '306',
    ); 

    $grossResult = null;
    try{
      $grossResult = $db->getDataFromDb($queryParams, $fieldMap);
    }catch(Exception $e){ 
      $this->log->preLog('Failed dbData query: '.print_r($queryParams, TRUE).print_r($fieldMap, TRUE));
    }
    $this->log->preLog($grossResult, 5);

    $result = array();
    if(empty($grossResult[0]['UserName'])){
      $this->log->preLog("Failure: OnBoarding API UserName was not obtained from Vision's Company Setup, 'Loadboards' section (field 205 of config)");
    }else{
      $result['UserName'] = $grossResult[0]['UserName'];
    } 
    if(empty($grossResult[0]['Password'])){
      $this->log->preLog("Failure: OnBoarding API Password was not obtained from Vision's Company Setup, 'Loadboards' section (field 206 of config)");
    }else{
      $result['Password'] = $grossResult[0]['Password'];
    }
    $this->log->preLog(__METHOD__.' result: '.print_r($result, TRUE));
    return $result; 
  }
  
  private function processRequest(){
    $this->log->preLog(__METHOD__);

    if($this->params->method == 'getOnBoardedCarriersFromSoap'){
      require_once('CacciController.php');
      $cacciParams = array(
        'method'      => 'updateOnBoardedCarriersWithCacciReports',
        'targetFile'  => 'carrierwatch',
        'api'         => 'Cacci',
        'noLog'       => TRUE,
        'log'         => $this->log
      );
      $this->cacci = new CacciController($cacciParams);
    }

    foreach($this->params->realCusts as $realCust){
      $this->log->preLog("Cust is ".$realCust);
      $this->log->preLog("Memory being used: ".memory_get_usage());
      $credentials = $this->getCredentials($realCust);
      if(empty($credentials)){
        continue;
      }
      $additionalCreds = $this->getControlValue($realCust, array('BurstStatus' => '38'));
      $credentials = array_merge($credentials, $additionalCreds);

      $this->{$this->params->method}($credentials, $realCust);
    }
  }

  protected function getOnBoardedCarriersFromSoap($credentials, $realCust){
    $this->log->preLog(__METHOD__);
    //if($this->areWeTesting == TRUE){
    if(TRUE){
      $selectDate = '2016-12-21';
      $this->log->preLog('selectDate: '.$selectDate);
    }else{
      //Craig: removing temporary workaround now that ITS fixed their bug.
      $selectDate = date_format($this->dateObj, "Y-m-d");
      $this->log->preLog('selectDate: '.$selectDate);
    }
    $carriers = $this->getListOfCarriers($credentials, $selectDate);
    
    if(empty($carriers)){
      $this->log->preLog("No carriers returned");
      return;
    }
    $reports = array(
      'GetCarrierConfirmInfoAndW9',
      'GetCarrierContractsAndAgreements',
      'GetCarrierCustomInformationList',
      'GetCarrierAddendumsandContracts',
      'GetCarrierPreferredLane'
    );
    $apiParams = array(
      'noLog'   => TRUE,
      'api'     => 'OnBoarding',
      'line'    => $credentials
    );
    $resultProperty = 'GetCoreListSearchResultsResult.Body.MyCoreCarriers';

    $queryParams = array(
      'fileName'      => $this->params->targetFile,
      'realCust'      => $realCust,
      'noCust'        => 'N',
      'noauth'        => 'Y',
      'exact'         => 'Y'
    );
    
    //These are Database's indexes for trucks_pd with the corresponding field from Soap's GetCoreListSearchResults
    if($this->params->targetFile == 'trucks'){
      $indexes = array( 
        'P' => 'MCNumber',
        'H' => 'USDotNumber'
      );
    }elseif($this->params->targetFile == 'trucks_pnd'){
      $indexes = array( 
        'P' => 'MCNumber',
        'E' => 'USDotNumber'
      );
    }else{
      $this->log->preLog('Database file, '.$this->params->targetFile.', is not recognized.');
      return;
    }

    foreach($carriers as $carrier){
      $apiParams['line']['Token'] = $carrier->Token;
      $data = $this->getCarrierData($carrier, $reports, $apiParams, $resultProperty);

      if($this->params->targetFile == 'trucks' || $this->params->targetFile == 'trucks_pd'){

        //Default to email
        $data['32']  = 'Y';
        $data['32']  = 'Y';
        $data['31']  = 'E';
        $data['91']  = 'E';
        $data['28']  = 'E';
        $data['32']  = 'E';
        $data['5']   = 'E';

        if($credentials['BurstStatus'] != 'Y'){
          $data['91'] = 'N';
          $data['28'] = 'N';
        }

        //Set field 22 'Agreement on file' to 'Y' if agreement and signer exists; 'N' otherwise.
        if(!empty($data['22']) AND !empty($data['23'])){
          $data['22'] = 'Y';
        }else{
          //Accommodate push of two files:  if this file is pushed before the map, the following code avoids
          //entering 'N' for all carriers
          if(isset($data['22'])){
            $data['22'] = 'N';
          }
        }
        unset($data['23']); 
        
      }
      $this->writeToDb($carrier, $indexes, $queryParams, $data, TRUE);
    }

    //Now write the cacci reports for these carriers to db
    $this->cacci->updateOnBoardedCarriersWithCacciReports($realCust, $carriers);
  }

  protected function getInactiveCarriersFromSoap($credentials, $realCust){
    $this->log->preLog(__METHOD__);
    $carriers = $this->getListOfCarriers($credentials);
    if(empty($carriers)){
      $this->log->preLog("No carriers returned");
      return;
    }
    
    $queryParams = array(
      'fileName'      => $this->params->targetFile,
      'realCust'      => $realCust,
      'noCust'        => 'N',
      'noauth'        => 'Y',
      'exact'         => 'Y'
    );
    
    $indexes = array( 
      'P' => 'MCNumber',
      'H' => 'USDotNumber'
      //,'A' => 'CompanyName'
    );

    if($this->areWeTesting == TRUE){
      $this->log->preLog("Using test data");
      $str = '{"0":
        {
          "CompanyName" : "JIMENEZ TRUCKING",
          "Status"      : "Suspended",
          "MCNumber"    : "500941",
          "USDotNumber" : "1295080"
        }
      }';
    $str = '
      {"0":
        {
          "CompanyName" : "JIM CALKINS TRUCKING",
          "Status"      : "Suspended",
          "MCNumber"    : "907282",
          "USDotNumber" : "2575389"
        }
      ,
      "1":
        {
          "CompanyName" : "JIMMY BARNETT",
          "Status"      : "Pending",
          "MCNumber"    : "259672",
          "USDotNumber" : ""
        }
      }';
      $carriers = json_decode($str);
    }
    
    foreach($carriers as $carrier){
      $this->log->preLog($carrier->CompanyName);
      $data = array('230' => $carrier->Status);
      
      //Move on to next carrier if status is active
      if($data['230'] == 'Active'){
        $this->log->preLog('Status is active; not updating '.$this->params->targetFile."\n");
        continue;
      }else{
        $data['50']  = 'Status per Soap '.date_format($this->dateObj,'m/d/y H:i').': '.$data['230'];  
        $data['230'] = 'N';
      }
      $this->writeToDb($carrier, $indexes, $queryParams, $data, FALSE);
    }
  }

  private function getListOfCarriers($credentials, $selectDate = NULL){
    $this->log->preLog(__METHOD__);

    //getCoreListSearchResults
    $params = array(
      'noLog'   => TRUE,
      'api'     => 'OnBoarding',
      'line'    => array(
        'request'                     => 'GetCoreListSearchResults',
        'CarrierListSearchCriteria'   => array(
          'ChangeDate'    => $selectDate,
          'MemberSince'   => $selectDate
        )
      )
    );
    $params['line'] = array_merge($params['line'], $credentials);

    $this->log->preLog("Getting carriers that were updated on ".$selectDate);
    $this->log->preLog("Searching for records with these parameters: ".print_r($params, true), 3);
    
    $rawResponse = $this->tsIntegrater->processDataFromCommandLine($params['line']);

    if(!isset($this->tsIntegrater->response->GetCoreListSearchResultsResult->Body->MyCoreCarriers)){
      return;
    }
    $carriers = $this->tsIntegrater->response->GetCoreListSearchResultsResult->Body->MyCoreCarriers;

    //Craig: resolve bug where only one record is returned
    if(is_object($carriers)){
      $carriers = array($carriers);
    }	     

    $this->log->preLog("Result: ".count($carriers)." carriers returned");
    if(count($carriers) == 0){
      return;
    }
    $n = 1;
    foreach($carriers as $carrier){
      $this->log->preLog($n.": ".$carrier->CompanyName.'  '.$carrier->Status, 2);
      $n++;
    }
    $this->log->preLog("Result: ".print_r($this->tsIntegrater->response, true), 3);
  
    return $carriers;
  }
}

?>
