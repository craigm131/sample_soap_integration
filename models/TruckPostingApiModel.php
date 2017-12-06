<?php

//  This file defines that class for integrating with Soap's TruckPosting API.  The methods shown here organizing the data that is acceptable to the API.
//
//  Programmer  Craig Millis
//
//  Below are example command line statements that utilized these requests.
//
//  PostTrucks
//  php runSoapApiIntegrater.php apiTruckPosting line'{"request":"PostTrucks", "IntegrationId":"9999", "DateAvailable":"2/20/16", "TruckNumber":"truck1234", "DestinationCity":"Boise", "DestinationState":"ID", "DestinationCountry":"USA", "OriginCity":"Denver", "OriginState":"CO", "OriginCountry":"USA", "EquipmentType":"V", "IsLoadFull":"", "TruckID":"", "Quantity":""}' debugTrue
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


require_once('ParentApiModel.php');

class TruckPostingApiModel extends ParentApiModel{
  
  public function __construct($params){
    parent::__construct($params);
  }

  public function PostTrucks($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

     
    //Fields required in Soap that aren't passed by Database.
    $reqFields = array(
      'Quantity'      => '',
      'TruckID'       => '',
      'IsLoadFull'    => ''
    );
    foreach($reqFields as $key => $value){
      if(!isset($line[$key])){  $line[$key] = $value; }
      $this->log->preLog("Added required field, ".$key."=>".$value);
    }

    //Adjust fields based on Home equipment type
    if(isset($line['EquipmentOptions']['TrailerOptionType'])){
      $trailerOptionType = $this->adjEquipOption(strtoupper($line['TypeOfEquipment']), $line['EquipmentOptions']['TrailerOptionType']);
    }
    if(!empty($trailerOptionType)){  
      $line['EquipmentOptions']['TrailerOptionType'] = $trailerOptionType;
    }

    //Map Home equipment type to Soap equipment type
    //NOTE: TruckPosting API's field is 'EquipmentType' while LoadPosting API's is 'TypeOfEquipment'
    $line['EquipmentType'] = $this->getSoapEquipType($line['EquipmentType']);

    //Add country if absent
    $initFields = array('OriginCountry','OriginState','OriginCity','OriginZip','DestinationCountry','DestinationState','DestinationCity','DestinationZip');
    foreach($initFields as $initField){
      if(!isset($line[$initField])){
        $line[$initField] = NULL;
      }
    }
    $line['OriginCountry'] = $this->getCountry($line['OriginState'], $line['OriginZip'], $line['OriginCountry']);
    if(!empty($line['DestinationState'])){
      $line['DestinationCountry'] = $this->getCountry($line['DestinationState'], $line['DestinationZip'], $line['DestinationCountry']);
    }

    $body = array( 
      'trucks' => array(
        'Trucks' => array(
          'Truck' => array(
            $line
          )
        )
      )
    );
    return $body;
  }

  public function GetTruckDetailResults($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array(
      'detailRequest' => $line
    );
    return $body;
  }

  public function DeleteTrucks($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
      
    $body = array( 
      'trucks' => array(
        'Trucks' => array(
          $line['Trucks']
        )
      ) 
    );
    return $body;
  }

  public function DeleteTrucksByTruckNumber($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
      
    $body = array(
      'trucks' => array(
        'TruckNumbers' => array(
          $line['TruckNumbers']
        )
      ) 
    );
    return $body;
  }

  public function GetTrucks($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
      
    $body = array( 
      'listRequest' => array(
      )
    );
    return $body;
  }

}

?>
