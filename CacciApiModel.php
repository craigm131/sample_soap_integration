<?php

//  This file defines that class for integrating with Truckstop's CACCI API.  The methods shown here organizing the data that is acceptable to the API.
//
//  Programmer  Craig Millis

require_once('ParentApiModel.php');

class CacciApiModel extends ParentApiModel{
  
  public function __construct($params){
    parent::__construct($params);
  }

  public function GetCompanySearchResults($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array( 
      'searchRequest' => $line
    );
    return $body;
  }
   
  public function GetCprReport($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array(
      'request' => $line
    ); 
    return $body;
  }
   
  public function GetCsaReport($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array(
      'request' => $line
    ); 
    return $body;
  }
   
  public function GetCPRProfile($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array(
      'request' => $line
    ); 
    return $body;
  }
   
  public function GetInsurance($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array(
      'request' => $line
    ); 
    return $body;
  }
   
  public function GetCPRAuthorityStatus($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array(
      'request' => $line
    ); 
    return $body;
  }
   
  public function GetCPRAuthorityHistory($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array(
      'request' => $line
    ); 
    return $body;
  }
   
  public function GetMonitorList($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array(
      'request' => $line
    ); 
    return $body;
  }
   
  public function AddMonitorEntry($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array(
      'request' => $line
    ); 
    return $body;
  }
   
  public function RemoveMonitorEntry($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array(
      'request' => $line
    ); 
    return $body;
  }
   
  public function GetMonitoredChanges($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array(
      'request' => $line
    ); 
    return $body;
  }
   
  public function GetChangeHistoryForCompany($line){
    $this->log->preLog(__CLASS__.":".__FUNCTION__);

    $body = array(
      'request' => $line
    ); 
    return $body;
  }
   
}

?>
