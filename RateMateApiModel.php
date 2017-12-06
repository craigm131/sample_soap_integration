<?php

//  This file defines that class for integrating with Soap's RateMate API.  The methods shown here organizing the data that is acceptable to the API.
//
//  Programmer  Craig Millis
//
//  Below are example command line statements that utilize these requests
//
//  GetHistoricalRates
/*  php runSoapApiIntegrater.php apiRateMate debugTrue line'{"request":"GetHistoricalRates", "IntegrationId":"9999", 
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
*/

require_once('ParentApiModel.php');

class RateMateApiModel extends ParentApiModel{
  
  public function __construct($params){
    parent::__construct($params);
  }

  public function GetHistoricalRates($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array( 
      'request' => $line
    );
    return $body;
  }

  public function GetNegotiationStrength($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array( 
      'request' => $line
    );
    return $body;
  }

  public function GetFuelSurcharge($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
      
    $body = array( 
      'request' => $line
    );
    return $body;
  }

  public function GetRateIndex($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
      
    $body = array( 
      'request' => $line
    );
    return $body;
  }
   
}

?>
