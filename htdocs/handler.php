<?php
$base = "C:\\Apache24\\htdocs\\";

//Tell PHP to save error logs
ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php-error.log");

//Load all the dependencies
include_once($base.'../protected/config.php');
include_once($base.'../protected/helper_functions.php');

//Make the config array available globally
//define('CONFIG_ARR', $config);

//Load Vendor dependencies
$path = "C:\\Apache24\\htdocs\\handler\\vendor\\autoload.php";
include_once($path);

//Load local classes
include_once($base.'../protected/Firestore.class.php');
include_once($base.'../protected/MotionModel.class.php');

set_error_handler('errorHandler', E_ALL);



$mm = new MotionModel();
 

$postdata = file_get_contents("php://input");
$xml = simplexml_load_string($postdata);
$json = json_encode($xml);
$array = json_decode($json,TRUE);

$now = new DateTime('now', new DateTimeZone('America/Chicago'));
$day = $now->format("Ymd");
$msTs = $now->getTimestamp()*1000; //millisecond Timestamp

$file = fopen("./save/motion-".$day.".txt", 'a');
$when = $now->format('Y-m-d h:i:s A');
$mac  = $array['macAddress'];


//Get db entry for camera
$cam = $mm->getMotionDocument($mac);
if($cam===false) {
    exit("<html><h1>Submitted macAddress $mac was not found</h1></html>");
} else {
//Toggle hasMotion on counter threshold
    $hasMotion = $cam['newEventCount'] >4; //Boolean
    $srcID  = $cam['srcID'];
    //Increment counter if update age below 10 sec threshold
    $age = ($msTs - $cam['eventTS']);
    $updated = [
        'hasMotion' => $hasMotion,
        'newEventCount' => ($age<10000),
        'eventTS' => $msTs,
        'when' => $when
    ];
    //flog("updated is ".var_dump($updated)."\n");
    $mm->updateMotion($mac, $updated);

    $string = "$when -> $mac -> $srcID\n";
    fwrite($file, $string);
    fclose($file);

    //Output to page
    echo "<html><h1>$mac -> $srcID<h1></html>";
}


?>


