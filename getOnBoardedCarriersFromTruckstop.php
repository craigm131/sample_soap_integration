<?php

require_once(__DIR__.'/controllers/OnBoardingController.php');

$params = array(
  'method'      => 'getOnBoardedCarriersFromSoap',//Used to download onboarded carriers from Soap
  'targetFile'  => 'trucks',
  'api'         => 'OnBoarding',
  'noLog'       => TRUE//set to true so that SoapApiIntegrater doesn't log
);

$obj = new OnBoardingController($params);


?>
