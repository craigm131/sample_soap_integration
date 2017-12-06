<?php

//  This file defines that class for integrating with Soap's LoadSearching API.  The methods shown here organizing the data that is acceptable to the API.
//
//  Programmer  Craig Millis
//
//  Below are example command line statements that utilized these requests.
//
//  GetLoadSearchResults
//  php runSoapApiIntegrater.php apiLoadSearching debugTrue line'{"request":"GetLoadSearchResults", "IntegrationId":"9999", "OriginCity":"Fort Collins", "OriginState":"CO", "OriginCountry":"USA", "OriginRange":"100", "DestinationCity":"Boulder", "DestinationState":"CO", "DestinationCountry":"USA", "DestinationRange":"100", "LoadType":"Nothing", "EquipmentType":"V"}'
//  or
//  GetLoadSearchDetailResult
//  php runSoapApiIntegrater.php apiLoadSearching debugTrue line'{"request":"GetLoadSearchDetailResult", "IntegrationId":"9999", "LoadId":"880178408"}'
//  or
//  GetMultipleLoadDetilResults
//  php runSoapApiIntegrater.php apiLoadSearching debugTrue line'{"request":"GetMultipleLoadDetailResults", "IntegrationId":"9999", "OriginCity":"Fort Collins", "OriginState":"CO", "OriginCountry":"USA", "OriginRange":"100", "DestinationCity":"Boulder", "DestinationState":"CO", "DestinationCountry":"USA", "DestinationRange":"100", "LoadType":"Nothing", "EquipmentType":"V"}'
//

require_once('ParentApiModel.php');

class LoadSearchingApiModel extends ParentApiModel{
  
  public function __construct($params){
    parent::__construct($params);
  }

  public function GetLoadSearchResults($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    //Miscellaneous fields required by Soap
    $reqFields = array(
      'LoadType'          => 'Nothing',
      'EquipmentType'     => 'V',
      'OriginRange'       => '1',
      'DestinationRange'  => '1'
    );
    foreach($reqFields as $key => $value){
      if(!isset($line[$key])){  $line[$key] = $value; }
      $this->log->preLog("Added required field, ".$key."=>".$value);
    }
    
    //Adjust fields based on Home equipment type
    if(isset($line['EquipmentOptions']['TrailerOptionType'])){
      $trailerOptionType = $this->adjEquipOption(strtoupper($line['EquipmentType']), $line['EquipmentOptions']['TrailerOptionType']);
    }
    if(!empty($trailerOptionType)){  
      $line['EquipmentOptions']['TrailerOptionType'] = $trailerOptionType;
    }

    //Map Home equipment type to Soap equipment type
    $line['EquipmentType'] = $this->getSoapEquipType($line['EquipmentType']);

    //Add country if absent
    if(!isset($line['OriginCountry'])){
      $line['OriginCountry'] = NULL;
    }
    $line['OriginCountry'] = $this->getCountry($line['OriginState'], $line['OriginZip'], $line['OriginCountry']);
    if(!empty($line['DestinationState'])){
      if(empty($line['DestinationZip'])){$line['DestinationZip']='';}
      if(empty($line['DestinationCountry'])){$line['DestinationCountry']='';}
      $line['DestinationCountry'] = $this->getCountry($line['DestinationState'], $line['DestinationZip'], $line['DestinationCountry']);
    }

    $body = array(
      'searchRequest' => array(
        'Criteria' => $line
      )
    );

    return $body;
  }

  public function GetLoadSearchDetailResult($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array(
      'detailRequest' => array(
        'LoadId' => $line
      )
    );

    return $body;
  }

  public function GetMultipleLoadDetailResults($line){
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
