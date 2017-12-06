<?php

declare(ticks = 1);

function sig_handler($signo){
  switch ($signo) {
    case SIGTERM:
      // handle shutdown tasks
      echo "\nSIGTERM";
      exit;
      break;
    case SIGINT:
      echo "\nSIGINT";
      exit;
      break;
    case SIGHUP:
      // handle restart tasks
      echo "\nSIGHUP";
      break;
    case SIGUSR1:
      echo "Caught SIGUSR1...\n";
      break;
    default:
      // handle all other signals
  }
}

echo "Installing signal handler...\n";

// setup signal handlers
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGINT, "sig_handler");
pcntl_signal(SIGHUP,  "sig_handler");
pcntl_signal(SIGUSR1, "sig_handler");



require_once(__DIR__.'/controllers/CacciController.php');

$params = array(
  'method'      => 'runCacciNightlyCarrierSync',
  'targetFile'  => 'carriers',
  'api'         => 'Cacci',
  'noLog'       => TRUE
);

$obj = new CacciController($params);


?>
