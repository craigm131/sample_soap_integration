<?php

//  This file defines that class for integrating with Soap's LoadPosting API.  The methods shown here organizing the data that is acceptable to the API.
//
//  Programmer  Craig Millis 
//
//  Below are example command line statements that utilize these requests.
//
//  Process csv file
//  php runSoapApiIntegrater.php apiLoadPosting debugTrue pathToFile/htdocs/integrations/soap_api/44_29418.csv
//  or
//  Process csv line
//  php runSoapApiIntegrater.php apiLoadPosting debugTrue line"LA","9999","DENVER","CO","BOULDER","CO","27","45000","48","0","","02/20/16","","02/22/16","","F","","F","DEMO","(456) 888-9999","1","58179","","","","","","cargoCOCO5817917"
//  or
//  PostLoads
//  php runSoapApiIntegrater.php apiLoadPosting debugTrue line'{"request":"PostLoads","IntegrationId":"9999", "Handle":"CraigM", "OriginCountry":"USA","OriginCity":"Fort Collins","OriginState":"CO","DestinationCountry":"USA","DestinationCity":"BOULDER","DestinationState":"CO","Width":"27","Weight":"45000","Length":"48","Stops":"0","PickUpDate":"02/20/16","DeliveryDate":"02/22/16","TypeOfEquipment":"F","Quantity":"1","LoadNumber":"cargoCOCO5817917", "IsFavorite":"", "LoadId":"", "Width":"", "IsLoadFull":"", "EquipmentOptions":{"TrailerOptionType":"Tarps"}}' 
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

require_once('ParentApiModel.php');

class LoadPostingApiModel extends ParentApiModel{
  
  public function __construct($params){
    parent::__construct($params);
  }

  public function PostLoads($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    //Fields required in Soap that aren't passed by Database.
    $reqFields = array(
      'IsFavorite'  => '',
      'LoadId'      => '',
      'Width'       => '',
      'IsLoadFull'  => ''
    );
    foreach($reqFields as $key => $value){
      if(!isset($line[$key])){  
        $line[$key] = $value;
        $this->log->preLog("Added required field, ".$key."=>".$value);
      }
    }

    //When load is partial, the IsLoadFull value must be set to empty to result in 'FALSE'.  Setting to 'false' will be interpreted by the WSDL as 'TRUE'
    if($line['IsLoadFull'] == 'P'){
      $line['IsLoadFull'] = '';
      $this->log->preLog("Converting IsLoadFull from ".$line['IsLoadFull']." to ''");
    }

    //Add Pro # to SpecInfo field so that the Soap user sees it in the load's details
    $line['SpecInfo'] = "Pro #".$line['SpecInfo']."; ".$line['Unique Shipment ID For Spots'];
    if(isset($line['Unique Shipment ID For Spots'])){
      unset($line['Unique Shipment ID For Spots']);
    }

    //Per Soap, DeliveryDate cannot be blank.  It must contain '0001-01-01' at a minimum.
    if(empty($line['DeliveryDate'])){
      $line['DeliveryDate'] = '0001-01-01';
      $this->log->preLog("DeliveryDate is empty.  Entering '0001-01-01'.  This is required by Soap.");
    }

    //Adjust fields based on Home equipment type
    $trailerOptionType = $this->adjEquipOption(strtoupper($line['TypeOfEquipment']), $line['EquipmentOptions']['TrailerOptionType']);
    if(!empty($trailerOptionType)){  
      $line['EquipmentOptions']['TrailerOptionType'] = $trailerOptionType;
    }

    //Map Home equipment type to Soap equipment type
    $line['TypeOfEquipment'] = $this->getSoapEquipType($line['TypeOfEquipment']);

    //Add country if absent
    $line['OriginCountry'] = $this->getCountry($line['OriginState'], $line['OriginZip'], $line['OriginCountry']);
    $line['DestinationCountry'] = $this->getCountry($line['DestinationState'], $line['DestinationZip'], $line['DestinationCountry']);

    $body = array( 
      'loads' => array(
        'Loads' => array(
          'Load' => array(
            $line
          )
        )
      )
    );
    return $body;
  } 

    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array( 
      'loads' => array(
        'Loads' => array(
          $line['LoadId']
        )
      )
    );
    return $body;
  } 

  //This will delete loads using the unique number that Home assigned to the load
  public function DeleteLoadsByLoadNumber($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array(
      'deleteRequest' => array(
        'LoadNumbers' => array(
          $line['LoadNumber']
        )
      )
    );
    return $body;
  } 

  public function GetLoads($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array( 
      'listRequest' => array(
      )
    );
    return $body;
  } 

  public function GetLoadViews($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array( 
      'viewsRequest' => array(
        $line
      )
    );
    return $body;
  } 

  public function GetLoadViewsByLoadNumber($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
    
    $body = array( 
      'loadNumber' => $line
    );
    return $body;
  } 
}

?>
