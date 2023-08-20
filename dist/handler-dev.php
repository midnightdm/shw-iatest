<?php
//Handler for IL-Galesburg
$mac = "84:9a:40:ab:9c:4f";
$base = "C:\\Apache24\\htdocs\\";



//Tell PHP to save error logs
ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php-error.log");

//Load all the dependencies
include_once($base.'../protected/config.php');
include_once($base.'../protected/helper_functions.php');


//Load Vendor dependencies
$autoload = "C:\\Apache24\\htdocs\\handler\\vendor\\autoload.php";
include_once($autoload);

//Load local classes
include_once($base.'../protected/Firestore.class.php');
include_once($base.'../protected/MotionModel.class.php');

set_error_handler('errorHandler', E_ALL);


$mm = new MotionModel();
 

//$postdata = file_get_contents("php://input");
//$xml = simplexml_load_string($postdata);
//$json = json_encode($xml);
//$array = json_decode($json,TRUE);

$now = new DateTime('now', new DateTimeZone('America/Chicago'));
$day = $now->format("Ymd");
$msTs = $now->getTimestamp()*1000; //millisecond Timestamp



//Prepare header line if file is new.
$path  = "C:\\railcam\\public\\logs\\dev-test-".$day.".txt";
$fileDoesExist = file_exists($path);
$header = $fileDoesExist ? "" : "DateTime,Location"; 
$file = fopen($path, 'a');
fwrite($file, $header);
//$when = $now->format('Y-m-d h:i:s A');
$when = $now->format(DateTime::ATOM); 



//Get db entry for camera
$cam = $mm->getMotionDocument($mac);
if($cam===false) {
    exit("<html><h1>macAddress $mac was not found</h1></html>");
} else {
//Toggle hasMotion on counter threshold
    $newEventCount = $cam['newEventCount'];
    $hasMotion = $newEventCount > 2; //Boolean
    $srcID  = $cam['srcID'];
    //Increment counter if update age below 12 sec threshold
    $age = ($msTs - $cam['eventTS']);
    $updated = [
        'hasMotion' => $hasMotion,
        'newEventCount' => ($age<12000),
        'eventTS' => $msTs,
        'when' => $when
    ];
    //flog("updated is ".var_dump($updated)."\n");
    $mm->updateMotion($mac, $updated);

    //Write to log once at moment event threshold was reached.
    if($cam['newEventCount']==2) {
        $string = "\n".$when.",".$srcID;
        fwrite($file, $string);
    }
    fclose($file);

    //Output to page
    echo "<html><h1>$when -> $mac -> $srcID -> $newEventCount<h1></html>";
    
}


?>
