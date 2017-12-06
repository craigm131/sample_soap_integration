<?php

//  This file defines that class for integrating with Soap's OnBoarding API.  The methods shown here organizing the data that is acceptable to the API.
//
//  Programmer  Craig Millis

require_once('ParentApiModel.php');

class OnBoardingApiModel extends ParentApiModel{
  
  public function __construct($params){
    parent::__construct($params);
  }

  public function GetCoreListSearchResults($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = $line; 
    return $body;
  }

  public function GetCarrierMonitoringAlertsListGroup($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = $line; 
    return $body;
  }

  public function GetCarrierMonitoringListGroupAlertDetails($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);
      
    $body = $line; 
    return $body;
  }

  public function GetCarrierContractsAndAgreements($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = $line; 
    return $body;
  }

  public function GetCarrierConfirmInfoAndW9($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = $line; 
    return $body;
  }

  public function GetCarrierCustomInformationList($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = $line; 
    return $body;
  }

  public function GetCarrierPreferredLane($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = $line; 
    return $body;
  }
   
  public function GetCarrierAddendumsandContracts($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = $line; 
    return $body;
  }
  
  public function GetInsurance($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = $line; 
    return $body;
  }
}

?>
