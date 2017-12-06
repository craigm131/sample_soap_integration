<?php


//  This class accepts objects seqentially that all relate to a carrier and builds a flat array from them.  It's used to accept five objects returned
//  from Soap's OnBoarding API for each carrier that Soap has on file; and, then writing the data to Database.
//  Programmer:  Craig Millis

//  Sample object:  response.GetCompanySearchResultsResult.SearchResults.CompanySearchResult.CompanyId


class ObjToFlatArray{

  var $sourceArray;               //flat array that contains object property names as keys
  var $outputArray;               //flat array that maps the sourceArray to another one dimensional array (to map to Database files)
  var $keyArr;                    //Variable to build a key for each property
  var $logDetailSetting   = 1;    //The higher the number, the more detail is written to logs.  Set this to 1 in prod.
  var $debug              = FALSE;//Setting to TRUE will result in the most detail written to logs

  function __construct($params = ''){
    if(isset($params['log'])){ 
      $this->log = $params['log']; 
      unset($params['log']);
    }
    $this->log->preLog(__METHOD__, 4);
    $this->readParams($params);
  }

  private function readParams($params){
    $this->log->preLog(__METHOD__, 4);
    $this->log->preLog("Input parameters: ".print_r($params, TRUE), 4);
    if(is_array($params)){
      $this->params = json_decode(json_encode($params), FALSE);//Convert to an object
      $this->log->preLog("Converted input parameters to object.", 4);
    }

    //Check to see if an object was passed that maps source fields to target fields
    if(empty($this->params->fieldMap)){
      $this->log->preLog("Failure: must pass an object that maps source fields to target fields");
    }

    if(!empty($this->params->debug)){
      $this->debug = $this->params->debug;
    }

    if(!empty($this->params->logDetailSetting)){
      $this->logDetailSetting = $this->params->logDetailSetting;
    }
    $this->log->preLog("Input parameters after ".__FUNCTION__." method: ".print_r($this->params, TRUE), 4);
  }

  function processData($data){
    $this->log->preLog(__METHOD__, 3);

    //Is this an object?  If not, return
    if(!is_object($data) AND !is_array($data)){
      $this->log->preLog("Not an object or an array: ".print_r($data, TRUE), 3);
      return;  
    }

    //Create a flat array that represents the object and store it in $this->sourceArray
    $this->traverseData($data);

    //echo sourceArray
    $this->log->preLog("Result: ".print_r($this->sourceArray, TRUE), 5);  
  }

  //Flatten object or multi-dim array and store resulting one-dim array in $this->sourceArray
  function traverseData($data){
    $this->log->preLog(__METHOD__, 5);
    $this->log->preLog("Key: ".print_r($this->keyArr, TRUE), 5);
    $this->log->preLog("Data: ".print_r($data, TRUE), 5);

    //Is value an array or object?  If so, loop.  If not, write key and value to array. 
    if(!is_object($data) AND !is_array($data)){
      $this->log->preLog("Not an object or array", 5);
      $this->sourceArray[implode('.', $this->keyArr)] = $data;
      array_pop($this->keyArr);
      return;
    }

    //Detect an empty array
    if(empty($data)){
      $this->log->preLog("Data is empty", 5);
      $this->sourceArray[implode('.', $this->keyArr)] = null;
      array_pop($this->keyArr);
      return;
    } 

    //Detect object that has no properties 
    if(!(array)$data){
      $this->log->preLog("Object is empty", 5);
      $this->sourceArray[implode('.', $this->keyArr)] = null;
      array_pop($this->keyArr);
      return;
    } 

    foreach($data as $key => $value){  
      $this->keyArr[] = $key;
      $this->traverseData($value);
    }
    array_pop($this->keyArr);
  }

  //Map sourceArray to target fields, creating the output array with Database fields as keys.
  function createOutputArray($array){
    $this->log->preLog(__METHOD__, 4);

    foreach($this->params->fieldMap as $key => $value){
      $str = '';
      if(is_object($value)){
        foreach($value as $subvalue){
          //Check if the desired value is in a form like aaa.bbb.ccc(ddd).eee
          if(strpos($subvalue, '(') === FALSE){
            if(isset($this->sourceArray[$subvalue])){
              $str .= $this->sourceArray[$subvalue];
              $this->log->preLog("key: ".$key."  value: ".$str, 4);
            }else{
              $this->log->preLog("key: ".$key."  this data is not returned from Soap", 4);
            }
          }else{
            $str .= $this->readSpecificElement($subvalue);
            if($str !== ''){
              $this->log->preLog("key: ".$key."  value: ".$str, 4);
            }else{
              $this->log->preLog("key: ".$key."  this data is not returned from Soap", 4);
            }
          }
        }
      }else{
        //Check if the desired value is in a form like aaa.bbb.ccc(ddd).eee
        if(strpos($value, '(') === FALSE){
          if(isset($this->sourceArray[$value])){
            $str = $this->sourceArray[$value];
            $this->log->preLog("key: ".$key."  value: ".$str, 4);
          }else{ 
            $this->log->preLog("key: ".$key."  this data is not returned from Soap", 4);
          }
        }else{
          $str = $this->readSpecificElement($value);
          if($str !== ''){
            $this->log->preLog("key: ".$key."  value: ".$str, 4);
          }else{
            $this->log->preLog("key: ".$key."  this data is not returned from Soap", 4);
          }
        } 
      }
      $this->outputArray[$key] = $str;
    } 
     
    $this->log->preLog("fieldMap: ".print_r($this->params->fieldMap, TRUE), 3);
    $this->log->preLog("Source Array: ".print_r($array, TRUE), 2); //set to 3 in prod
    $this->log->preLog("Output Array created when merging the Source Array with the fieldmap: ".print_r($this->outputArray, TRUE), 3);//set to 3 in prod
  
    return $this->outputArray;
  }

  function readSpecificElement($value){
    $this->log->preLog(__METHOD__, 5);

    $str = $this->traverseString(array($value));

    return $str;
  }

  function traverseString($arr){
    $this->log->preLog(__METHOD__, 5);

    $this->log->preLog("array: ".print_r($arr, TRUE), 5);//set to 5 in prod
    //Inputs...
    //Value
    //"GetInsuranceResult.Insurance.InsuranceCoverage.CoverageDescription(CARGO).Limits.InsuranceCoverageLimit.LimitDescription(DEDUCTIBLE REEFER BREAKDOWN).LimitAmount"
    
    //SourceArray
    //[GetInsuranceResult.Insurance.InsuranceCoverage.0.CoverageDescription] => LIABILITY
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.CoverageDescription] => CARGO
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.CoverageDetail.Description] =>
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.CoverageDetails.InsuranceCoverageDetail.Description] => MOTOR TRUCK CARGO
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.CoversAuto] =>
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.CoversCargo] => 1
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.EffectiveDate] => 2016-01-06T00:00:00
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.ExpirationDate] => 2017-01-06T00:00:00
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.HasUnlistedAutoLimit] =>
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.HasUnlistedCargoLimit] =>
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.LastCertUpdate] => 1/8/2016 11:34:36 AM
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.Limits.InsuranceCoverageLimit.0.IsAutoSynonym] =>
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.Limits.InsuranceCoverageLimit.0.IsCargoSynonym] => 1
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.Limits.InsuranceCoverageLimit.0.LimitAmount] => 100000.0000
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.Limits.InsuranceCoverageLimit.0.LimitDescription] => LIMIT
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.Limits.InsuranceCoverageLimit.0.RMISLimitID] =>
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.Limits.InsuranceCoverageLimit.1.IsAutoSynonym] =>
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.Limits.InsuranceCoverageLimit.1.IsCargoSynonym] =>
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.Limits.InsuranceCoverageLimit.1.LimitAmount] => 2500.0000
    //[GetInsuranceResult.Insurance.InsuranceCoverage.1.Limits.InsuranceCoverageLimit.1.LimitDescription] => DEDUCTIBLE REEFER BREAKDOWN

    //Actions
    //Read the map array, iterating through each value
    $str = '';
    foreach($arr as $value){
      if(strpos($value, '(') === FALSE){
        //If the map array value does not contain parens, e.g. 
        // 0 => "GetInsuranceResult.Insurance.InsuranceCoverage.1.Limits.InsuranceCoverageLimit.1.LimitAmount
        //Then obtain the matching value and return it as a string, e.g. 2500.0000
        if(isset($this->sourceArray[$value])){
          $str = $this->sourceArray[$value];
          $this->log->preLog('a str: '.$str, 5);//set to 5 in prod
        }
      }else{
        //If it contains parens:
        //GetInsuranceResult.Insurance.InsuranceCoverage.CoverageDescription(CARGO).Limits.InsuranceCoverageLimit.LimitDescription(DEDUCTIBLE REEFER BREAKDOWN).LimitAmount
        //Obtain the portion of the string that precedes the element with parens, and after
        //  prefix = GetInsuranceResult.Insurance.InsuranceCoverage
        //  suffix = .Limits.InsuranceCoverageLimit.LimitDescription(DEDUCTIBLE REEFER BREAKDOWN).LimitAmount
        $openParenPosition  = strpos($value, '(');
        $closeParenPosition = strpos($value, ')');
        $strPrecedingParen  = substr($value, 0, $openParenPosition);
        $arrPrecedingParen  = explode('.', $strPrecedingParen);
        array_pop($arrPrecedingParen);
        $prefix             = implode('.', $arrPrecedingParen);
        $suffix             = substr($value, $closeParenPosition + 1);
        $targetValue        = substr($value, $openParenPosition + 1, $closeParenPosition - $openParenPosition - 1);
        if($targetValue === '1'){
          $targetValue = (bool)1;
        }

        //Return array of scrubbed keys where the related value equals the value inside the parens, e.g. CARGO
        // 0 => "GetInsuranceResult.Insurance.InsuranceCoverage.1.Limits.InsuranceCoverageLimit.LimitDescription(DEDUCTIBLE REEFER BREAKDOWN).LimitAmount
        // 1 => "GetInsuranceResult.Insurance.InsuranceCoverage.2.Limits.InsuranceCoverageLimit.LimitDescription(DEDUCTIBLE REEFER BREAKDOWN).LimitAmount
        $scrubbedKeys = array();
        foreach($this->sourceArray as $sourceKey=>$sourceValue){
          //if(substr($sourceKey, 0, strlen($prefix)) === $prefix && substr($sourceKey, -1*strlen($suffix)) === $suffix && $sourceValue == $targetValue){
          //Only match the first characters of sourceValue to accommodate cases where parens are included, e.g. COMBINED SINGE LIMIT (EA OCCURRENCE)
          if(substr($sourceKey, 0, strlen($prefix)) === $prefix && $sourceValue === $targetValue){
            $vars = array('sourceKey', 'prefix', 'sourceValue', 'targetValue');
            foreach($vars as $var){
              $this->log->preLog("$var: ".$$var, 5);//Set to 5 in prod
            }
            $this->log->preLog('is sourceValue boolean? '.is_bool($sourceValue), 5);//set to 5 in prod
            $this->log->preLog('is sourceValue numeric? '.is_numeric($sourceValue), 5);//set to 5 in prod
            $this->log->preLog('is targetValue boolean? '.is_bool($targetValue), 5);//set to 5 in prod
            $this->log->preLog('is targetValue numeric? '.is_numeric($targetValue), 5);//set to 5 in prod

            //GetInsuranceResult.Insurance.InsuranceCoverage.1.CoverageDescription
            $tempArr = explode('.', $sourceKey);
            array_pop($tempArr);

            //GetInsuranceResult.Insurance.InsuranceCoverage.1.Limits.InsuranceCoverageLimit.LimitDescription(DEDUCTIBLE REEFER BREAKDOWN).LimitAmount
            $scrubbedKeys[] = implode('.', $tempArr).$suffix;
          }
        }
        
        $this->log->preLog('scrubbedKeys: '.print_r($scrubbedKeys, TRUE), 5);//set to 5 in prod 

        if(!empty($scrubbedKeys)){
          //Submit back to 'Read the map array...', above
          $str = $this->traverseString($scrubbedKeys);
        }
        $this->log->preLog('b str: '.$str, 5);//set to 5 in prod

        $variables = array('prefix', 'suffix', 'targetValue', 'str');
        foreach($variables as $variable){
          $this->log->preLog("\n".$variable.': '.print_r($$variable, TRUE), 5);
        }
      }//end of if then
    }
    $this->log->preLog('c str: '.$str, 5);//set to 5 in prod
    return $str; 
  }
}

?>
