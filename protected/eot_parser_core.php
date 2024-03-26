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
//include_once($base.'../protected/Firestore.class.php');
//include_once($base.'../protected/MotionModel.class.php');

//Load objects and get time
//$mm   = new MotionModel();
$now  = new DateTime('now', new DateTimeZone('America/Chicago'));
$day  = $now->format("Ymd");
$msTs = $now->getTimestamp()*1000; //millisecond Timestamp
$when = $now->format(DateTime::ATOM);

$keys = [
    "TS", "SIG", "SRC", "ID", "RR", "SYMB", "BP", "MOT", "MRK", "BATST", "BATCU", "TRB", "CMD", "TYP", "VLV", "LOC"
];


//Create location log file 
$path1  = "C:\\railcam\\public\\HED\\".$location."-".$day.".txt";


//Create train ID log file
$path2 = "C:\\railcam\\public\\HED\\id".$_GET['ID']."-".$day.".txt"; 

//Prepare header line if location file is new.
$fileDoesExist = file_exists($path1);
$header = $fileDoesExist ? "" : "TS, SIG, SRC, ID, RR, SYMB, BP, MOT, MRK, BATST, BATCU, TRB, CMD, TYP, VLV, LOC";
$file1 = fopen($path1, 'a');
fwrite($file1, $header);

$file2 = fopen($path2, 'a');
fwrite($file2, $header);


//Write to log with duration as 0
//$srcID  = $cam['srcID'];
$string = "\n";

foreach($keys as $k) {
    // Check if key exists in $_GET using isset() before accessing it
    if (isset($_GET[$k])) {
      $string .= $_GET[$k] . ",";
    }
  }
  
  // Remove the trailing comma if the string is not empty
  $string = rtrim($string, ",");

fwrite($file1, $string);
fwrite($file2, $string);
fclose($file1);
fclose($file2);

//Output to page
$output = "<html><h1>$when -> $location-> {$_GET['ID']}";

$output .= "</h1></html>";
echo $output;
    
?>