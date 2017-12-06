<?php

//This class enables downloading data from Soap's CACCI API, among other methods.
//Programmer:  Craig Millis
//
//The class:
//  1) accepts an array that includes the method to execute and related parameters, like the file where data will be written;
//  2) reads a config file to determine which customers have this feature enabled, /u/home/config/soap_cacci
//  3) reads a config file to determine how to map Soap fields to Database fields, /u/home/config/soap_cacci_map
//
require_once('ParentSoapController.php');

class CacciController extends ParentSoapController{

  var $configPath     = '/u/home/config/soap_cacci';
  var $fieldMapPath   = '/u/home/config/soap_cacci_map';

  function __construct($params = ''){
    parent::__construct($params);
    $this->processRequest();
    //e.g. $params = array(
    //  [method] => runCacciNightlyCarrierSync
    //  [targetFile] => carrierwatch
    //  [api] => Cacci
    //  [noLog] => 1
    //  )
  }

  //Return the Soap username and password for the unencoded customer
  protected function getCredentials($realCustomer, $user = ''){
    $this->log->preLog(__METHOD__);
    
    $db = new DboOnBoardingCacciModel(array('log' => $this->log, 'debug' => $this->debug, 'logDetailSetting' => $this->logDetailSetting));
    $grossResult = $db->getIntegrationIdFromConfig($realCustomer);
    if(empty($grossResult)){
      $this->log->preLog("Failure: no Soap Integration Id found in field 201 of config for customer, ".$realCustomer);
      $this->log->preLog("Integration Id: ".print_r($grossResult, TRUE), 4);
      return;
    } 
    $result = array('IntegrationId' => $grossResult);
    $this->log->preLog('credentials: '.print_r($result, TRUE));
    return $result;
  }

  private function processRequest(){
    $this->log->preLog(__METHOD__);

    if($this->params->method == 'runCacciNightlyCarrierSync'){
      foreach($this->params->realCustomers as $realCustomer){
        $this->log->preLog("Customer is ".$realCustomer);
        $this->log->preLog("Memory being used: ".memory_get_usage());
        $credentials = $this->getCredentials($realCustomer);
        if(empty($credentials)){
          continue;
        }

        //Comment next line in prod!!
        //$credentials['UserName'] = 'SVanOtten';

        $this->{$this->params->method}($credentials, $realCustomer);
        
        //Write to database
        $dbCommand = $realCustomer.' dreport carrierwatch -m '.$realCustomer.' -db post -u -y noauto -s soap';
        $this->log->insertLogNow("Executing '".$dbCommand."'");
        exec($dbCommand);
        $this->log->preLog("Finished executing");
      }
    }
  }
  
  function updateOnBoardedCarriersWithCacciReports($realCustomer, $carriers){
    $this->log->preLog(__METHOD__);
    
    //check if customer is in config file and proceed if it is
    if(in_array($realCustomer, $this->params->realCustomers)){
      $this->log->preLog('Cacci is enabled for '.$realCustomer.' per '.$this->configPath);
    }else{
      $this->log->preLog('Cacci is NOT enabled for '.$realCustomer.' per '.$this->configPath);
      return;
    } 

    $credentials = $this->getCredentials($realCustomer);
    if(empty($credentials)){
      return;
    }
    $grossDbCarriers = $this->getMonitoredCarriersFromDb($realCustomer); 
    $this->updateDbWithCacciReports($realCustomer, $credentials, $carriers, $grossDbCarriers, TRUE);
 
    //Write to DB
    $dbCommand = $realCustomer.' dreport carrierwatch -m '.$realCustomer.' -db post -u -y noauto -s soap';
    $this->log->insertLogNow("Executing '".$dbCommand."'");
    exec($dbCommand, $output, $returnStatus);
    $this->log->preLog("Finished executing");
    $this->log->preLog("output: ".print_r($output, TRUE));
    $this->log->preLog("return status: ".print_r($returnStatus, TRUE));
    $this->log->preLog("process: ".print_r(posix_getpwuid(posix_geteuid()), TRUE));
  }    

  protected function runCacciNightlyCarrierSync($credentials, $realCustomer){
    $this->log->preLog(__METHOD__);

    //Get monitored carriers from Database
    $grossDbCarriers = $this->getMonitoredCarriersFromDb($realCustomer); 
    $this->log->preLog('grossDbCarriers: '.print_r($grossDbCarriers, TRUE), 5);//set to 5 in prod
    if($this->areWeTesting == TRUE){
      $msg = "Trucks returned ".count($grossDbCarriers)." carriers being monitored";
      $this->log->preLog($msg);
      echo "\n".$msg;
      $dbCarriers = array_slice($grossDbCarriers, 0, 1);
      $msg = "Limiting to ".count($dbCarriers)." carriers for testing (to save time)";
      $this->log->preLog($msg);
      echo "\n".$msg;
    }
    $dbCarriers = $grossDbCarriers;
    //$dbCarriers = array_slice($grossDbCarriers, 0, 3);//Comment in Prod

    $n = 0;
    foreach($dbCarriers as $dbCarrier){
      $n++;
      $mc = $dbCarrier['McNumber'];
      $dot = $dbCarrier['DotNumber'];
      $msg = $n.": ".$dbCarrier['Vendor Name'].": MC=".$mc." DOT=".$dot;
      $this->log->preLog($msg); 
      if($this->areWeTesting == TRUE){ echo "\n".$msg; }
    }

    //Get monitored carriers from Soap
    $tsCarriers = $this->getMonitoredCarriersFromSoap($credentials);
    if($this->areWeTesting == TRUE){
      $msg = "Soap returned ".count($tsCarriers)." carriers being monitored";
      $this->log->preLog($msg);
      echo "\n".$msg;
    }
    $n = 0;
    if(!empty($tsCarriers)){
      foreach($tsCarriers as $tsCarrier){
        $n++;
        $mc = $tsCarrier->McNumber;
        $dot = $tsCarrier->DotNumber;
        $msg = $n.": ".$tsCarrier->CompanyName.": MC=".$mc." DOT=".$dot." Id=".$tsCarrier->CompanyId;
        $this->log->preLog($msg); 
        if($this->areWeTesting == TRUE){ echo "\n".$msg; }
      }
    }

    //Foreach carrier that is in the Database list and not in the Soap list
    //add the carrier to the Soap list using AddMonitorEntry and the Company Id from GetMonitorList
    if(!empty($dbCarriers)){
      $this->log->preLog("Checking ".count($dbCarriers)." carriers that are monitored in Database, to ensure they're monitored in Soap");
      $n = 0;
      foreach($dbCarriers as $dbCarrier){
        $n++;
        $mc = $dbCarrier['McNumber'];
        $dot = $dbCarrier['DotNumber'];
        $msg = $n.": ".$dbCarrier['Vendor Name'].": MC=".$mc." DOT=".$dot;
        $this->log->preLog($msg);
        if($this->areWeTesting == TRUE){ echo "\n".$msg; }
          
        if(empty($mc) && empty($dot)){
          $this->log->preLog("No MC or DOT for carrier, ".$dbCarrier['Vendor Name']);
          continue;
        }
        $match = FALSE;
        if(empty($tsCarriers)){
          $match = FALSE;
        }else{
          foreach($tsCarriers as $tsCarrier){
            if($tsCarrier->McNumber == $mc && !empty($mc)){
              $this->log->preLog("This monitored carrier in Database matches one in Soap, ".$tsCarrier->CompanyName.", by MC: ".$mc, 3);
              $match = TRUE;
              break;
            }elseif($tsCarrier->DotNumber == $dot && !empty($dot)){
              $this->log->preLog("This monitored carrier in Database matches one in Soap, ".$tsCarrier->CompanyName.", by DOT: ".$dot, 3);
              $match = TRUE;
              break;
            }
          }
        }
        if($match == TRUE){
          $this->log->preLog("Success: Database and Soap are in sync", 5);
        }else{
          $this->log->preLog("This monitored carrier in Database does not match one in Soap, attempting to add to monitoring in Soap", 3);
          $id = $this->getCompanyIdFromSoap($credentials, $mc, $dot);
          if(!empty($id)){
            $this->log->preLog("Soap company id: ".print_r($id, TRUE), 3);
            $this->addCarrierToMonitoring($credentials, $id);
          }
        }
      }
    }

    //Foreach carrier that is in the Soap list and not in the Database list
    //Remove the carrier from the Soap list
    if(!empty($tsCarriers)){
      $this->removeTsCarriersIfNotInDb($tsCarriers, $dbCarriers, $credentials);
    }

    //Create list of monitored carriers, whose data has changed, using GetMonitoredChanges
    //Foreach carrier, update Database 
    $changedCarriers = $this->getMonitoredCarriersWithChangesFromSoap($credentials);
    if($changedCarriers == new stdClass() || count($changedCarriers) == 0){
      $this->log->preLog("No carriers had changed data");
      return;
    }
    $msg = "Number of carriers that have changed data: ".count($changedCarriers);
    $this->log->preLog($msg);
    if($this->areWeTesting == TRUE){echo "\n$msg";}
 
    if(!is_array($changedCarriers)){
      $changedCarriers = array(0 => $changedCarriers);
    }
    $this->updateDbWithCacciReports($realCustomer, $credentials, $changedCarriers, $grossDbCarriers);
  }

  protected function getMonitoredCarriersFromDb($realCustomer){
    $this->log->preLog(__METHOD__);
    
    $queryParams = array(
      'fileName'      => 'trucks',
      'realCustomer'      => $realCustomer,
      'noCustomer'        => 'N',
      'noauth'        => 'Y',
      'exact'         => 'N',
      'limit'         => ''
    );

    $fieldMap = array(
      'Vendor Name'   => 1,
      'Legal Name'    => 13,
      'Federal ID'    => 25,
      'home ID'      => 29,
      'DotNumber'     => 214,
      'McNumber'      => 215,
      'Monitor'       => 630
    );
    
    $db = new DboOnBoardingCacciModel(array('log' => $this->log, 'debug' => $this->debug, 'logDetailSetting' => $this->logDetailSetting));
    
    $grossCarriers = $db->getDataFromDb($queryParams, $fieldMap);

    if(empty($grossCarriers)){
      $this->log->preLog("No carriers returned from ".$queryParams['fileName']." for ".$realCustomer);
      return;
    }

    $carriers = array();
    //Get the carriers that should be monitored
    foreach($grossCarriers as $grossCarrier){
      if($grossCarrier['Monitor'] == 'Y'){
        //Accommodate MC numbers with leading zeroes
        if(!empty($grossCarrier['McNumber'])){
          if(strlen($grossCarrier['McNumber']) < 6){
            $adjMc = str_pad($grossCarrier['McNumber'], 6, '0', STR_PAD_LEFT);
            $this->log->preLog("MC is less than 6 digits: '".$grossCarrier['McNumber']."'.  Changing to: '".$adjMc."'");
            $grossCarrier['McNumber'] = $adjMc;
            $this->log->preLog(print_r($grossCarrier, TRUE));
          }
        }
        $carriers[] = $grossCarrier;
      }  
    }
    $this->log->preLog("Carriers returned from ".$queryParams['fileName'].": ".count($grossCarriers));

    if(empty($carriers)){
      $this->log->preLog("No carriers returned from ".$queryParams['fileName']." for ".$realCustomer);
      return;
    }
    $this->log->preLog("Carriers being monitored from ".$queryParams['fileName'].": ".count($carriers));

    return $carriers;
  }

  protected function getMonitoredCarriersFromSoap($credentials){
    $this->log->preLog(__METHOD__);

    //Get Cacci's list of carriers being monitored
    $apiParams = array(
      'line'    => array(
        'request' => 'GetMonitorList'
      )
    );
    $apiParams['line'] = array_merge($apiParams['line'], $credentials);

    $rawResponse = $this->tsIntegrater->processDataFromCommandLine($apiParams['line']);
    
    if(empty($this->tsIntegrater->response->GetMonitorListResult->MonitorList->MonitoredCompany)){
      $this->log->preLog("No Carriers returned from ".$apiParams['line']['request'].": ".print_r($rawResponse, TRUE));
      return;
    }else{
      $carriers = $this->tsIntegrater->response->GetMonitorListResult->MonitorList->MonitoredCompany;
    }
    
    if(is_object($carriers)){
      $carriers = array(0 => $carriers);
    }
    
    $this->log->preLog("Carriers being monitored in Soap Cacci: ". count($carriers)."\n");
    return $carriers;
  }

  protected function getCompanyIdFromSoap($credentials, $mc = '', $dot = ''){
    $this->log->preLog(__METHOD__, 3);
    
    $apiParams = array(
      'line'    => array(
        'request'     => 'GetCompanySearchResults'
      )
    );
    $apiParams['line'] = array_merge($apiParams['line'], $credentials);

    if(!empty($mc)){
      $apiParams['line'] = array_merge($apiParams['line'], array('Criteria' => array('McNumber' => $mc)));
    }elseif(!empty($dot)){
      $apiParams['line'] = array_merge($apiParams['line'], array('Criteria' => array('DotNumber' => $dot)));
    }else{
      $this->log->preLog("No MC# or DOT# in input parameters");
      return;
    }

    $this->log->preLog("apiParams: ".print_r($apiParams, TRUE), 5);//Set to 5 in production

    $rawResponse = $this->tsIntegrater->processDataFromCommandLine($apiParams['line']);
    
    if(isset($this->tsIntegrater->response->GetCompanySearchResultsResult->SearchResults->CompanySearchResult->CompanyId)){
      $response = $this->tsIntegrater->response->GetCompanySearchResultsResult->SearchResults->CompanySearchResult->CompanyId;
      return $response;
    }
    if(isset($this->tsIntegrater->response->GetCompanySearchResultsResult->SearchResults->CompanySearchResult[0]->CompanyId)){
      $this->log->preLog("Multiple records returned from Soap.  Using first record.");
      $response = $this->tsIntegrater->response->GetCompanySearchResultsResult->SearchResults->CompanySearchResult[0]->CompanyId;
      return $response;
    }
    $this->log->preLog("CompanyId not returned...company has not been onboarded in Soap");
    if(isset($this->tsIntegrater->response)){
      $this->log->preLog("Response: ".print_r($this->tsIntegrater->response, TRUE), 3);
    }
    //Try DOT#
    if($dot !== '' && $mc !== 'try dot'){
      return $this->getCompanyIdFromSoap($credentials, 'try dot', $dot); 
    }
  }

  protected function addCarrierToMonitoring($credentials, $companyId){
    $this->log->preLog(__METHOD__, 3);

    $apiParams = array(
      'line'    => array(
        'request'     => 'AddMonitorEntry',
        'CompanyId'   => $companyId
      )
    );
    $apiParams['line'] = array_merge($apiParams['line'], $credentials);

    $rawResponse = $this->tsIntegrater->processDataFromCommandLine($apiParams['line']); 
    $response = $this->tsIntegrater->response->AddMonitorEntryResult;
  
    if($response->Errors = new stdClass()){
      $this->log->preLog("Success: added to monitoring in Trucktop\n");
      $this->log->preLog("Response: ".print_r($response, TRUE), 5);
    }else{
      $this->log->preLog("Response: ".print_r($response, TRUE));
    }
    return $response;
  }

  private function removeTsCarriersIfNotInDb($tsCarriers, $dbCarriers, $credentials){
    $this->log->preLog(__METHOD__);
    
    $n = 0;
    $this->log->preLog("Checking ".count($tsCarriers)." carriers that are monitored in Soap, to see if they're monitored in Database");
    foreach($tsCarriers as $tsCarrier){
      $n++;
      $mc = $tsCarrier->McNumber;
      $dot = $tsCarrier->DotNumber;
      $msg = $n.": ".$tsCarrier->CompanyName.": MC=".$mc." DOT=".$dot." Id=".$tsCarrier->CompanyId;
      $this->log->preLog($msg);
      if($this->areWeTesting == TRUE){ echo "\n".$msg; }

      if(empty($mc) && empty($dot)){
        $this->log->preLog("No MC or DOT!");
        continue;
      }
      $match = FALSE;
      if(empty($dbCarriers)){
        $match = FALSE;
      }else{
        foreach($dbCarriers as $dbCarrier){
          if($dbCarrier['McNumber'] == $mc && !empty($mc)){
            $this->log->preLog("This monitored carrier in Soap matches one in Database, ".$dbCarrier['Vendor Name'].", by MC: ".$mc, 3);
            $match = TRUE;
            break;
          }elseif($dbCarrier['DotNumber'] == $dot && !empty($dot)){
            $this->log->preLog("This monitored carrier in Soap matches one in Database, ".$dbCarrier['Vendor Name'].", by DOT: ".$dot, 3);
            $match = TRUE;
            break;
          }
        }
      }
      if($match == TRUE){
        $this->log->preLog("Success: Database and Soap are in sync", 5);
      }else{
        $this->log->preLog("This monitored carrier in Soap does not match one in Database, removing from monitoring in Soap");
        $this->removeCarrierFromMonitoring($credentials, $tsCarrier->CompanyId);
      }
    }
  }

  protected function removeCarrierFromMonitoring($credentials, $companyId){
    $this->log->preLog(__METHOD__, 3);

    $apiParams = array(
      'noLog'   => TRUE,
      'api'     => 'Cacci',
      'line'    => array(
        'request'     => 'RemoveMonitorEntry',
        'CompanyId'   => $companyId
      )
    );
    $apiParams['line'] = array_merge($apiParams['line'], $credentials);

    $rawResponse = $this->tsIntegrater->processDataFromCommandLine($apiParams['line']); 
    $response = $this->tsIntegrater->response->RemoveMonitorEntryResult;
    
    if($response->Errors = new stdClass()){
      $this->log->preLog("Success: removed from monitoring in Trucktop\n");
      $this->log->preLog("Response: ".print_r($response, TRUE), 4);
    }else{
      $this->log->preLog("Response: ".print_r($response, TRUE));
    }
    return $response;
  }

  protected function getMonitoredCarriersWithChangesFromSoap($credentials){
    $this->log->preLog(__METHOD__, 3);

    $apiParams = array(
      'line'    => array(
        'request'       => 'GetMonitoredChanges',
        'StartingDate'  => date('Y-m-d', strtotime(date_format($this->dateObj, "Y-m-d")." -1 days")),//keep at -1 days in prod
        'PageNumber'    => 1,
        'PageSize'      => 200
      )
    );
    $apiParams['line'] = array_merge($apiParams['line'], $credentials);
    
    if($this->areWeTesting == TRUE){
      $startingDate = '2015-12-01';
      $apiParams['line']['StartingDate'] = $startingDate;
      echo "\nFor testing, using starting date of ".$startingDate;
    }
 
    $rawResponse = $this->tsIntegrater->processDataFromCommandLine($apiParams['line']); 
    
    if(empty($this->tsIntegrater->response->GetMonitoredChangesResult->Changes->MonitoredCompanyChangeResult)){
      $this->log->preLog('No changes returned: '.print_r($rawResponse, TRUE));
      return;
    }else{
      $response = $this->tsIntegrater->response->GetMonitoredChangesResult->Changes->MonitoredCompanyChangeResult;
    }

    if(isset($response->Errors)){
      if($response->Errors = new stdClass()){
        $this->log->preLog("Success: obtained monitored carriers with changes\n");
      }
    }else{
      $this->log->preLog("Success: obtained monitored carriers with changes\n");
    }
    $this->log->preLog("Response: ".print_r($response, TRUE), 5);
    
    return $response;
  }

  private function updateDbWithCacciReports($realCustomer, $credentials, $tsCarriers, $grossDbCarriers, $onboarding = FALSE){
    $this->log->preLog(__METHOD__);

    $this->log->preLog('tsCarriers: '.print_r($tsCarriers, TRUE), 4);
    
    $n = 0;
    foreach($tsCarriers as $tsCarrier){
      if($onboarding === TRUE){
        //Accommodate data from OnBoarding's GetCoreListSearchResults
        $name = $tsCarrier->CompanyName;
        $mc   = $tsCarrier->MCNumber;
        $dot  = $tsCarrier->USDotNumber;
      }else{
        //Accommodate data from Cacci's GetMonitoredChanges
        $name = $tsCarrier->Name;
        $mc   = $tsCarrier->McNumber;
        $dot  = $tsCarrier->DotNumber;
      }
      $n++;
      $carrier = $this->getCompanySearchResultsFromSoap($credentials, $mc, $dot);
      if(isset($carrier->CompanyId)){
        $id = $carrier->CompanyId;
      }else{
        $id = 'unknown';
      }
      $msg = $name." MC=".$mc." DOT=".$dot." CompanyId=".$id;
      if($this->areWeTesting == TRUE){echo "\n$n: ".$msg;}
      $this->log->preLog($n.": ".$msg);

      $msg = "carrier: ".print_r($carrier, TRUE);
      if($this->areWeTesting == TRUE){echo "\n$msg";}
      
      $report = $this->getCacciReportsFromSoap($credentials, $carrier);
     
      $msg = "report: ".print_r($report, TRUE);
      if($this->areWeTesting == TRUE){echo "\n$msg";}
        
      $this->log->preLog('carrier report: '.print_r($report, TRUE), 4);//set to 5 in prod

      if(empty($mc)){
        $this->log->preLog('No mc number...moving to next carrier');
        continue;
      }

      //Adjust specific carrierwatch fields
      //Get the home id for this mc number from trucks so that it can be written to carrierwatch
      $homeId = '';
      foreach($grossDbCarriers as $grossDbCarrier){
        if($grossDbCarrier['McNumber'] == $mc){
          $homeId = $grossDbCarrier['home ID'];
        }
      }
      if(empty($homeId)){
        $this->log->preLog("This carrier, MC=,".$mc.", is not being monitored('3rd Party Ins Monitoring' flag is not set to 'Y' in the carrier profile)...moving to next carrier");
        continue;
      }
      $report[1] = $homeId;
      $this->log->preLog('Entering home ID '.$homeId.' in field 1', 4);

      //Enter today's date so that the carrierwatch script will write this record to trucks; and clear field 3
      $today = date('m/d/y');
      $report[2] = $today;
      $this->log->preLog('Entering '.$today.' in field 2', 4);
      $report[3] = '';

      //Adjust date fields to mm/dd/yyyy
      $dateFields = array('40', '41', '49', '52', '60', '61', '69', '72', '80', '81', '89', '92', '116');
      foreach($dateFields as $dateField){
        if(!empty($report[$dateField])){
          $report[$dateField] = date('m/d/Y', strtotime($report[$dateField]));
        }
      }

      //Adjust authority fields
      $adjustFields = array(
        '23' => array(
          'ACTIVE'    => 'Y',
          'INACTIVE'  => 'N',
          'NONE'      => 'N'
        ),
        '24' => array(
          'ACTIVE'    => 'Y',
          'INACTIVE'  => 'N',
          'NONE'      => 'N'
        ),
        '25' => array(
          'ACTIVE'    => 'Y',
          'INACTIVE'  => 'N',
          'NONE'      => 'N'
        ),
        '26' => array(
          'ACTIVE'    => 'Y',
          'INACTIVE'  => 'N',
          'NONE'      => 'N'
        ),
        '27' => array(
          'ACTIVE'    => 'Y',
          'INACTIVE'  => 'N',
          'NONE'      => 'N'
        ),
        '28' => array(
          'ACTIVE'    => 'Y',
          'INACTIVE'  => 'N',
          'NONE'      => 'N'
        )
      );
      foreach($adjustFields as $field=>$adjustments){
        if(isset($report[$field])){
          foreach($adjustments as $adjKey=>$adjVal){
            if($report[$field] == $adjKey){
              $report[$field] = $adjVal;
            }
          }
        }
      }
      $this->writeCarrierReportToDatabase($realCustomer, $carrier, $report);
    }
  }

  //This function defines the reports that will be called from the Cacci API.
  private function getCacciReportsFromSoap($credentials, $carrier){
    $this->log->preLog(__METHOD__);

    $reports = array(
      'GetCprReport',
      'GetCsaReport',
      'GetCPRProfile',
      'GetInsurance', //This consumes 100kb of memory each time it's run!
      'GetCPRAuthorityStatus',
      'GetCPRAuthorityHistory'
    );
    $apiParams = array(
      'line'    => $credentials
    );
    if(is_array($carrier)){
      $this->log->preLog('An array is being returned, rather than an object: '.print_r($carrier, TRUE));
      if(isset($carrier[0])){
	      $this->log->preLog('Using first element of array');
	      $carrier = $carrier[0];
      }
    }
    if(empty($carrier->CompanyId)){
      $this->log->preLog('No company id...returning', 2);
      return;
    }
    $apiParams['line']['ID'] = $carrier->CompanyId;
    $resultProperty = 'GetCompanySearchResultsResult.SearchResults.CompanySearchResult';

    $data = $this->getCarrierData($carrier, $reports, $apiParams, $resultProperty);

    return $data;
  }

  protected function writeCarrierReportToDatabase($realCustomer, $carrier, $data){
    $this->log->preLog(__METHOD__);

    $queryParams = array(
      'fileName'      => $this->params->targetFile,
      'index'         => 'B',//MC number
      'realCustomer'      => $realCustomer,
      'noCustomer'        => 'N',
      'noauth'        => 'Y',
      'exact'         => 'Y',
    );
    if(empty($carrier->McNumber)){
      $this->log->preLog("McNumber does not exist in Soap; returning...");
      return;
    }
    $queryParams['key'] = $carrier->McNumber;
    
    $indexes = array( 
      'B' => 'McNumber'
    );
    
    $this->writeToDb($carrier, $indexes, $queryParams, $data, TRUE);
  }

  protected function getCompanySearchResultsFromSoap($credentials, $mc, $dot=''){
    $this->log->preLog(__METHOD__, 4);
    
    $apiParams = array(
      'line'    => array(
        'request'   => 'GetCompanySearchResults',
        'Criteria'  => array(
          'McNumber'        => $mc
          //,'DotNumber'   => $dot//Craig 120916 removed dot because Soap has two records with same dot TRANS-MOTION LLC
        )
      )
    );
    $apiParams['line'] = array_merge($apiParams['line'], $credentials);

    $rawResponse = $this->tsIntegrater->processDataFromCommandLine($apiParams['line']); 
    
    if(empty($this->tsIntegrater->response->GetCompanySearchResultsResult->SearchResults->CompanySearchResult)){
      $this->log->preLog(__METHOD__." No response for $mc!");
      return;
    }
    $response = $this->tsIntegrater->response->GetCompanySearchResultsResult->SearchResults->CompanySearchResult;
    if(isset($response->Errors)){
      if($response->Errors !== new stdClass()){
        $this->log->preLog('Errors: '.print_r($response), 1);
        return $response;
      }
    }
    if(is_array($response)){
      $this->log->preLog("Soap returned an array for MC".$mc."...".print_r($response, TRUE));
      $carriers = $response;
      //Soap has returned more than one carrier.
      foreach($carriers as $key=>$carrier){
        if(!empty($carrier->McNumberPrefix)){
          if($carrier->McNumberPrefix == 'MC'){
            $response = $carrier;
            $this->log->preLog("Using element ".$key." because McNumberPrefix=MC");
            break;
          }
        }
      }
    }

    $this->log->preLog("Success: obtained carrier\n", 3);
    $this->log->preLog("Response: ".print_r($response, TRUE), 3);
    return $response;
  }
}

?>
