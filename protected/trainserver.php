<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

//Load dependencies
include_once('config.php');

$autoload  = "C:\\Apache24\\htdocs\\handler\\vendor\\autoload.php";
//echo "Vendor Path = ".$autoload."\n";
require_once($autoload);

include_once('daemon/TrainDaemon.class.php');
include_once('daemon/LiveCam.class.php');
include_once('helper_functions.php');
include_once('Firestore.class.php');
include_once('MotionModel.class.php');


//Initialize error handling
ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php-error.log");
set_error_handler('errorHandler', E_ALL);





//This is the active part of the app. It creates the daemon object then starts the loop.
$trainDaemon = new TrainDaemon($config);
$trainDaemon->start();


?>