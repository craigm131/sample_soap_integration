<?php

//  This file defines that class for integrating with Soap's TruckSearching API.  The methods shown here organizing the data that is acceptable to the API.
//
//  Programmer  Craig Millis
//
//  Below are example command line statements that utilized these requests.
//
//  GetTruckSearchResults
//  php runSoapApiIntegrater.php apiTruckSearching debugTrue line'{"request":"GetTruckSearchResults", "IntegrationId":"9999", "OriginCity":"Denver", "OriginState":"CO", "OriginCountry":"USA", "OriginRange":"100", "DestinationCity":"Boise", "DestinationState":"ID", "DestinationCountry":"USA", "DestinationRange":"100", "LoadType":"Nothing", "EquipmentType":"V", "HoursOld":"0"}'
//  or
//  GetTruckDetailResults
//  php runSoapApiIntegrater.php apiTruckSearching debugTrue line'{"request":"GetTruckDetailResults", "IntegrationId":"9999", "TruckId":"880178408"}'
//  or
//  GetMultipleTruckDetailResults
//  php runSoapApiIntegrater.php apiTruckSearching debugTrue line'{"request":"GetMultipleTruckDetailResults", "IntegrationId":"9999", "OriginCity":"Fort Collins", "OriginState":"CO", "OriginCountry":"USA", "OriginRange":"100", "DestinationCity":"Boulder", "DestinationState":"CO", "DestinationCountry":"USA", "DestinationRange":"100", "LoadType":"Nothing", "EquipmentType":"V", "HoursOld":"0"}'


require_once('ParentApiModel.php');

class TruckSearchingApiModel extends ParentApiModel{
  
  public function __construct($params){
    parent::__construct($params);
  }

  public function GetTruckSearchResults($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array(
      'searchRequest' => array(
        'Criteria' => $line
      )
    );

    return $body;
  }

  public function GetTruckDetailResults($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array(
      'detailRequest' => array(
        'TruckId' => $line
      )
    );

    return $body;
  }

  public function GetMultipleTruckDetailResults($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
      
    $body = array( 
      'searchRequest' => array(
        'Criteria' => $line
      )
    );
    return $body;
  }
   
}

?>
