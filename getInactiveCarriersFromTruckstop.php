<?php

require_once(dirname(__FILE__).'/controllers/OnBoardingController.php');

$params = array(
  'method'      => 'getInactiveCarriersFromSoap',//Used to denote the inactive carriers in Home
  'targetFile'  => 'trucks',
  'api'         => 'OnBoarding',
  'noLog'       => TRUE//set to true so that SoapApiIntegrater doesn't log
);

$obj = new OnBoardingController($params);


?>
