<?php
// INCLUDE FILE for all trigger alarm START locations

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

//set direction mode

if(isset($useDirMode) && $useDirMode===true) {
    $useDirMode=true;
} else {
    $useDirMode = false;
}


//Create log file
$path  = "C:\\railcam\\public\\logs\\".$location."-".$day.".txt";

//Prepare header line if file is new.
$fileDoesExist = file_exists($path);
$header = $fileDoesExist ? "" : "DateTime,Location,SecDuration"; 
$file = fopen($path, 'a');
fwrite($file, $header);

//Get curent camera data
$cam = $mm->getMotionDocument($location);
if($cam===false || !array_key_exists('srcID', $cam) || !array_key_exists('eventTS', $cam)) {
    exit("<html><h1>Location $location was not found in the database. Use the Edit page to create or change site IDs.</h1></html>");
} else if($useDirMode) {
    if(!isset($direction)) {
        exit("<html><h1>Direction missing from call script</h1></html>");
    }
    //Toggle hasMotion on, set timestamps and direction
    $updated = [
        'hasMotion' => true,
        'hasRemoteStopControl' => false,
        'heading' => $direction,
        'eventTS' => $msTs,
        'when' => $when
    ];
} else {
    //Toggle hasMotion on and set timestamps
    $updated = [
        'hasMotion' => true,
        'hasRemoteStopControl' => false,
        'eventTS' => $msTs,
        'newEventCount' => 0,
        'when' => $when
    ];
}
//Write updated to db    
$mm->updateMotion($location, $updated);

//Write to log with duration as 0
$srcID  = $cam['srcID'];
$string = "\n".$when.",".$srcID.",0,";
if($useDirMode) {
    $string .= $direction;
} else {
    $string .= "--";
}
fwrite($file, $string);
fclose($file);

//Output to page
$output = "<html><h1>$when -> $srcID START-> 0";
if($useDirMode) {
    $output .= " DIRECTION -> $direction ";
}
$output .= "</h1></html>";
echo $output;
    
?>