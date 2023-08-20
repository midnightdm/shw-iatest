<?php
// INCLUDE FILE for all trigger alarm STOP locations

//Load configuration and initialize error handling
include_once($base.'../protected/config.php');
include_once($base.'../protected/helper_functions.php');
ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php-error.log");
set_error_handler('errorHandler', E_ALL);

//Load Vendor dependencies
$autoload = "C:\\Apache24\\htdocs\\handler\\vendor\\autoload.php";
include_once($autoload);

//Load local classes
include_once($base.'../protected/Firestore.class.php');
include_once($base.'../protected/MotionModel.class.php');

//Load objects and get time
$mm   = new MotionModel();
$now  = new DateTime('now', new DateTimeZone('America/Chicago'));
$day  = $now->format("Ymd");
$msTs = $now->getTimestamp()*1000; //millisecond Timestamp
$when = $now->format(DateTime::ATOM);

//Create log file
$path  = "C:\\railcam\\public\\logs\\".$location."-".$day.".txt";

//Prepare header line if file is new.
$fileDoesExist = file_exists($path);
$header = $fileDoesExist ? "" : "DateTime,Location,SecDuration"; 
$file = fopen($path, 'a');
fwrite($file, $header);

//Get curent camera data
$cam = $mm->getMotionDocument($mac);
if($cam===false || !array_key_exists('srcID', $cam) || !array_key_exists('eventTS', $cam)) {
    exit("<html><h1>macAddress $mac was not found</h1></html>");
} else {
    //Determine seconds since event started
    $srcID  = $cam['srcID'];
    $lastTs = $cam['eventTS'];
    $age = $msTs - $lastTs;
    $duration = floor($age/1000);

    //

    //Toggle hasMoton off and write current time to db
    $updated = [
        'hasMotion' => false,
        'hasRemoteStopControl' => true,
        'eventTS' => $msTs,
        'when' => $when
    ];
    $mm->updateMotion($mac, $updated);

    //Write to log 
    $string = "\n".$when.",".$srcID.",".$duration;
    fwrite($file, $string);
    fclose($file);

    //Output to page
    echo "<html><h1>$when -> $srcID -> $duration<h1></html>";   
}

?>