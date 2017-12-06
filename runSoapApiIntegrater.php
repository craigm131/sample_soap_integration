<?php

//  This file is used to instantiate the SoapApiIntegrater class that will send data to Soap's LoadPosting API.
//
//  Programmer:  Craig Millis
//
//  The file is called by /u/home/bin/exp_truckstop and should be passed parameters:
//    - api               required    string      e.g. 'LoadPosting
//    - request           required    string      e.g. 'PostLoads' the request to executes, that's related to the API
//    - pathToFile        optional*   string      optional if line is not provided; the prc.truckstop creates this csv file
//    - line              optional*   string      optional if pathToFile is not provided
//
//    - pathToDestFile    optional    string      path to where a results file should be saved, e.g. /pix/cargo/edi/truckstop_api/results.txt
//    - debug flag        optional    boolean     setting this to TRUE will echo results
//
//  Below are example command line statements that utilized these requests.
//   
//  For LoadPosting:
//  Process csv file
//  php runSoapApiIntegrater.php apiLoadPosting debugTrue pathToFile/htdocs/integrations/truckstop_api/ITSv3_0ohleb44_29418.csv
//  or
//  Process csv line
//  php runSoapApiIntegrater.php apiLoadPosting debugTrue line"LA","9999","DENVER","CO","BOULDER","CO","27","45000","48","0","","02/20/16","","02/22/16","","F","","F","DEMO","(456) 888-9999","1","58179","","","","","","cargoCOCO5817917"
//  or
//  PostLoads
//  php runSoapApiIntegrater.php apiLoadPosting debugTrue line'{"request":"PostLoads","IntegrationId":"9999", "Handle":"CraigM", "OriginCountry":"USA","OriginCity":"Fort Collins","OriginState":"CO","DestinationCountry":"USA","DestinationCity":"BOULDER","DestinationState":"CO","Width":"27","Weight":"45000","Length":"48","Stops":"0","PickUpDate":"05/22/16","PickUpTime":"14:00", "DeliveryDate":"05/23/16","TypeOfEquipment":"EXPD","Quantity":"1","LoadNumber":"cargoCOCO5817917", "IsFavorite":"", "LoadId":"", "Width":"", "IsLoadFull":"", "EquipmentOptions":{"TrailerOptionType":["Tarps", "Hazardous"]}}' 
//  or
//  DeleteLoads
//  php runSoapApiIntegrater.php apiLoadPosting debugTrue line'{"request":"DeleteLoads","IntegrationId":"9999","LoadId":"885835677"}'
//  or
//  DeleteLoadsByLoadNumber
//  php runSoapApiIntegrater.php apiLoadPosting debugTrue line'{"request":"DeleteLoadsByLoadNumber", "IntegrationId":"9999", "LoadNumber":"cargoCOCO5817917"}'
//  or
//  GetLoads
//  php runSoapApiIntegrater.php apiLoadPosting line'{"request":"GetLoads", "IntegrationId":"9999"}' debugTrue
//  or
//  GetLoadViews
//  php runSoapApiIntegrater.php apiLoadPosting line'{"request":"GetLoadViews", "IntegrationId":"9999"}' debugTrue
//  or
//  GetLoadViewsByLoadNumber
//  php runSoapApiIntegrater.php apiLoadPosting line'{"request":"GetLoadViewsByLoadNumber", "IntegrationId":"9999", "LoadNumber":"cargoCOCO5817917"}' debugTrue
//
//  LoadSearching:
//  GetLoadSearchResults
//  php runSoapApiIntegrater.php apiLoadSearching debugTrue line'{"request":"GetLoadSearchResults", "IntegrationId":"9999", "OriginCity":"Fort Collins", "OriginState":"CO", "OriginCountry":"USA", "OriginRange":"100", "DestinationCity":"Boulder", "DestinationState":"CO", "DestinationCountry":"USA", "DestinationRange":"100", "LoadType":"Nothing", "EquipmentType":"V", "PickupDates":["2016-04-02", "2016-04-03"]}'
//  or
//  GetLoadSearchDetailResult
//  php runSoapApiIntegrater.php apiLoadSearching debugTrue line'{"request":"GetLoadSearchDetailResult", "IntegrationId":"9999", "LoadId":"880178408"}'
//  or
//  GetMultipleLoadDetilResults
//  php runSoapApiIntegrater.php apiLoadSearching debugTrue line'{"request":"GetMultipleLoadDetailResults", "IntegrationId":"9999", "OriginCity":"Fort Collins", "OriginState":"CO", "OriginCountry":"USA", "OriginRange":"100", "DestinationCity":"Boulder", "DestinationState":"CO", "DestinationCountry":"USA", "DestinationRange":"100", "LoadType":"Nothing", "EquipmentType":"V"}'
//
//  TruckPosting:
//  PostTruck
//  php runSoapApiIntegrater.php apiTruckPosting line'{"request":"PostTrucks", "IntegrationId":"9999", "DateAvailable":"7/28/16", "Tber":"truck1231", "DestinationCity":"Boise",   "DestinationState":"ID", "DestinationCountry":"USA", "OriginCity":"Denver", "OriginState":"CO", "OriginCountry":"USA", "EquipmentType":"V", "IsLoadFull":"", "TruckID":"", "Quantity":"", "EquipmentOptions":["Tarp"]}'   debugTrues
//  or
//  GetTruckDetailResults
//  php runSoapApiIntegrater.php apiTruckPosting line'{"request":"GetTruckDetailResults", "IntegrationId":"9999", "TruckId":"54633210"}' debugTrue
//  or
//  DeleteTrucks
//  php runSoapApiIntegrater.php apiTruckPosting line'{"request":"DeleteTrucks", "IntegrationId":"9999", "Trucks":"54633210"}' debugTrue
//  or
//  DeleteTrucksByTruckNumber
//  php runSoapApiIntegrater.php apiTruckPosting line'{"request":"DeleteTrucksByTruckNumber", "IntegrationId":"9999", "TruckNumbers":"truck1231"}' debugTrue
//  or
//  GetTrucks
//  php runSoapApiIntegrater.php apiTruckPosting line'{"request":"GetTrucks", "IntegrationId":"9999"}' debugTrue
//
//  TruckSearching:
//  GetTruckSearchResults
//  php runSoapApiIntegrater.php apiTruckSearching debugTrue line'{"request":"GetTruckSearchResults", "IntegrationId":"9999", "OriginCity":"Denver", "OriginState":"CO", "OriginCountry":"USA", "OriginRange":"100", "DestinationCity":"Boise", "DestinationState":"ID", "DestinationCountry":"USA", "DestinationRange":"100", "LoadType":"Nothing", "EquipmentType":"V", "HoursOld":"0"}'
//  or
//  GetTruckDetailResults
//  php runSoapApiIntegrater.php apiTruckSearching debugTrue line'{"request":"GetTruckDetailResults", "IntegrationId":"9999", "TruckId":"880178408"}'
//  or
//  GetMultipleTruckDetailResults
//  php runSoapApiIntegrater.php apiTruckSearching debugTrue line'{"request":"GetMultipleTruckDetailResults", "IntegrationId":"9999", "OriginCity":"Fort Collins", "OriginState":"CO", "OriginCountry":"USA", "OriginRange":"100", "DestinationCity":"Boulder", "DestinationState":"CO", "DestinationCountry":"USA", "DestinationRange":"100", "LoadType":"Nothing", "EquipmentType":"V", "HoursOld":"0"}'
//  
//  RateMate:
//  GetHistoricalRates
/*  php runSoapApiIntegrater.php apiRateMate debugTrue line'{"request":"GetHistoricalRates", "IntegrationId":"9999", 
      "Criteria":{
        "Origin":{
          "City":"Bloomington",
          "State":"MN",
          "Country":"USA"
        },
        "Destination":{
          "City":"Chicago",
          "State":"IL",
          "Country":"USA"
        },
        "EquipmentCategory":"Van"
      },
      "RateCriteria":{
        "DesiredMargin":"20",
        "Radius":"Within100Miles"
      }
    }'
*/
//  or
//  GetNegotiationStrength
/*  php runSoapApiIntegrater.php apiRateMate debugTrue line'{"request":"GetNegotiationStrength", "IntegrationId":"9999", 
      "Criteria":{
        "Origin":{
          "City":"Los Angeles",
          "State":"CA",
          "Country":"USA"
        },
        "Destination":{
          "City":"Houston",
          "State":"TX",
          "Country":"USA"
        },
        "EquipmentCategory":"Flat"
      }
    }'
*/
//  or
//  GetFuelSurcharge
/*  php runSoapApiIntegrater.php apiRateMate debugTrue line'{"request":"GetFuelSurcharge", "IntegrationId":"9999", 
      "Criteria":{
        "Origin":{
          "City":"Los Angeles",
          "State":"CA",
          "Country":"USA"
        },
        "Destination":{
          "City":"Houston",
          "State":"TX",
          "Country":"USA"
        },
        "EquipmentCategory":"Flat"
      },
      "MilesPerGallon":"5.5"
    }'
*/
//  or
//  GetRateIndex
/*  php runSoapApiIntegrater.php apiRateMate debugTrue line'{"request":"GetRateIndex", "IntegrationId":"9999", 
      "Criteria":{
        "Origin":{
          "City":"Los Angeles",
          "State":"CA",
          "Country":"USA"
        },
        "Destination":{
          "City":"Houston",
          "State":"TX",
          "Country":"USA"
        },
        "EquipmentCategory":"Flat"
      },
      "RateCriteria":{
        "DesiredMargin":"20",
        "Radius":"Within100Miles"
      }
}'

    OnBoarding:
    GetCoreListSearchResults
    php runSoapApiIntegrater.php apiOnBoarding line'{"request":"GetCoreListSearchResults", "UserName":"Home", "Password":"password", "CarrierListSearchCriteria":""}'

    GetCarrierMonitoringAlertsListGroup
    php runSoapApiIntegrater.php apiOnBoarding line'{"request":"GetCarrierMonitoringAlertsListGroup", "UserName":"Home", "Password":"password"}'

    GetCarrierMonitoringListGroupAlertDetails
    php runSoapApiIntegrater.php apiOnBoarding line'{"request":"GetCarrierMonitoringListGroupAlertDetails", "UserName":"Home", "Password":"password", "Token":"1PGTnVtq+xSFrP3Wc/PFtg=="}'

    GetCarrierContractsAndAgreements
    php runSoapApiIntegrater.php apiOnBoarding line'{"request":"GetCarrierContractsAndAgreements", "UserName":"Home", "Password":"password", "Token":"AR+A5XSS9x0mKCQHY02rPs6AwDvsc/JWzdE2V2innn5Ck6Yst4z3hg=="}'

    GetCarrierConfirmInfoAndW9
    php runSoapApiIntegrater.php apiOnBoarding line'{"request":"GetCarrierConfirmInfoAndW9", "UserName":"Home", "Password":"password", "Token":"AR+A5XSS9x0mKCQHY02rPs6AwDvsc/JWzdE2V2innn5Ck6Yst4z3hg=="}'

    GetCarrierCustomInformationList
    php runSoapApiIntegrater.php apiOnBoarding line'{"request":"GetCarrierCustomInformationList", "UserName":"Home", "Password":"password", "Token":"AR+A5XSS9x0mKCQHY02rPs6AwDvsc/JWzdE2V2innn5Ck6Yst4z3hg=="}'

    GetCarrierPreferredLane
    php runSoapApiIntegrater.php apiOnBoarding line'{"request":"GetCarrierPreferredLane", "UserName":"Home", "Password":"password", "Token":"AR+A5XSS9x0mKCQHY02rPs6AwDvsc/JWzdE2V2innn5Ck6Yst4z3hg=="}'

    GetCarrierAddendumsandContracts
    php runSoapApiIntegrater.php apiOnBoarding line'{"request":"GetCarrierAddendumsandContracts", "UserName":"Home", "Password":"password", "Token":"AR+A5XSS9x0mKCQHY02rPs6AwDvsc/JWzdE2V2innn5Ck6Yst4z3hg=="}'

    CACCI:
    GetCompanySearchResults
    php runSoapApiIntegrater.php apiCacci debugTrue line'{"request":"GetCompanySearchResults", "IntegrationId":"9999", "Criteria":{"McNumber":"326288"}}'
    php runSoapApiIntegrater.php apiCacci debugTrue line'{"request":"GetCompanySearchResults", "IntegrationId":"9999", "Criteria":{"CompanyName":"American"}}'

    GetCprReport
    php runSoapApiIntegrater.php apiCacci debugTrue line'{"request":"GetCprReport", "IntegrationId":"9999", "ID":"c7d4e770-d1f4-e320-21f3-b13802a49eeb"}'

    GetCsaReport
    php runSoapApiIntegrater.php apiCacci debugTrue line'{"request":"GetCsaReport", "IntegrationId":"9999", "ID":""}'

    GetCPRProfile
    php runSoapApiIntegrater.php apiCacci debugTrue line'{"request":"GetCPRProfile", "IntegrationId":"9999", "ID":"c7d4e770-d1f4-e320-21f3-b13802a49eeb"}'

    GetInsurance
    php runSoapApiIntegrater.php apiCacci debugTrue line'{"request":"GetInsurance", "IntegrationId":"9999", "ID":"c7d4e770-d1f4-e320-21f3-b13802a49eeb"}'
    php runSoapApiIntegrater.php apiCacci debugTrue line'{"request":"GetInsurance", "IntegrationId":"9999", "ID":"90285617-d963-94f6-09dd-14da8dad6683"}'

    GetCPRAuthorityStatus
    php runSoapApiIntegrater.php apiCacci debugTrue line'{"request":"GetCPRAuthorityStatus", "IntegrationId":"9999", "ID":""}'

    GetCPRAuthorityHistory
    php runSoapApiIntegrater.php apiCacci debugTrue line'{"request":"GetCPRAuthorityHistory", "IntegrationId":"9999", "ID":""}'

    GetMonitorList
    php runSoapApiIntegrater.php apiCacci debugTrue line'{"request":"GetMonitorList", "IntegrationId":"9999", "ID":""}'

    AddMonitorEntry
    php runSoapApiIntegrater.php apiCacci debugTrue line'{"request":"AddMonitorEntry", "IntegrationId":"9999", "CompanyId":"c818c161-83f1-5585-9899-28f7ee74ef75"}'
    
    RemoveMonitorEntry
    php runSoapApiIntegrater.php apiCacci debugTrue line'{"request":"RemoveMonitorEntry", "IntegrationId":"9999", "CompanyId":"12345678-0000-0000-0000-1A2B3C4D5E6F"}'

    GetMonitoredChanges
    php runSoapApiIntegrater.php apiCacci debugTrue line'{"request":"GetMonitoredChanges", "IntegrationId":"9999", "StartingDate":"2015-12-01", "PageNumber":1, "PageSize":50}'

    GetChangeHistoryForCompany
    php runSoapApiIntegrater.php apiCacci debugTrue line'{"request":"RemoveMonitorEntry", "IntegrationId":"9999", "CompanyId":"12345678-0000-0000-0000-1A2B3C4D5E6F", "PageNumber":1, "PageSize":50}'
 */


error_reporting(-1);//remove error reporting in production by setting to -1
ini_set('display_errors', 1);//remove error reporting in production

require_once('SoapApiIntegrater.php');

$knownParams = array('api', 'line', 'pathToFile', 'debug', 'echo', 'respond', 'pathToDestFile', 'cust', 'client');
$params = [];

if($_GET){
  $params = $_GET;  
}elseif($_POST){
  $params = $_POST;
}else{
  //Read parameters
  foreach($argv as $key=>$passedItem){
    if($key > 0){
      foreach($knownParams as $knownParam){
        if(strpos($passedItem, $knownParam) === 0){
          $passedParam = substr($passedItem, strlen($knownParam), strlen($passedItem)-strlen($knownParam));
          $params[$knownParam] = $passedParam;
          break;
        }
      }
    }
  }

  if((count($argv) - 1) !== count($params)){
    echo "\nUnidentified parameter";
  }
}

$integrater = new SoapApiIntegrater($params);

//var_dump($integrater->response);
if(isset($params['echo'])){
  if($params['echo']){
    echo $integrater->message;
  }
}

if(isset($params['respond'])){
  if($params['respond']){
    echo json_encode($integrater->response);
  }
}

return $integrater->response;

?>
