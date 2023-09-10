<?php
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
 

$now = new DateTime('now', new DateTimeZone('America/Chicago'));
$day = $now->format("Ymd");
$msTs = $now->getTimestamp()*1000; //millisecond Timestamp
$allcams  = []; //Array of all cameras in Motion collection
$enableds = []; //Array of enabled camera ids
$fills    = []; //Array of fill camera ids
$inmotion = []; //Array of inmotion camera ids
$nomotion = []; //Array of idle camera ids

//Get cameras in srcID named array
$cameras = $mm->getCamerasCollection();


//Get motion events in numbered array
$motionCollection = $mm->getMotionCollection();

//Loop through motion array data

for($loopCount=0; $loopCount<count($motionCollection); $loopCount++) {
   $id = $motionCollection[$loopCount]['srcID'];
   /* Sync shared data from enabled cameras */
   $motionCollection[$loopCount]['isViewEnabled'] = $cameras[$id]['isViewEnabled'];
   $motionCollection[$loopCount]['useAsFill']     = $cameras[$id]['useAsFill'];
   $motionCollection[$loopCount]['srcUrl']        = $cameras[$id]['srcUrl'];
   $motionCollection[$loopCount]['srcType']       = $cameras[$id]['srcType'];
   //Increment count of fill cameras
   if($motionCollection[$loopCount]['useAsFill']) {
        $fills[] = $id;
   }
   //Increment count of enabled cameras
   if($motionCollection[$loopCount]['isViewEnabled']) {
      $enableds[] = $id;
      //Skip age out for alarm stop controlled sites
      if(!$motionCollection[$loopCount]['hasRemoteStopControl']) {
        //Test for > 120 sec age of last motion detect
        $age = $msTs-$motionCollection[$loopCount]['eventTS'];
        if($age > 120000) {
            $motionCollection[$loopCount]['hasMotion'] = false;
            $nomotion[$id] = floor($age/10000);
        } elseif($motionCollection[$loopCount]['hasMotion']) {
            $inmotion[] = $id;
        }     
      }  
   }
   //Count of cams enabled or not
   $allcams[] = $id;
   //Write changes back to db
   $mm->updateMotion($motionCollection[$loopCount]['macAddress'], $motionCollection[$loopCount]);
}

//Tally counts
$totalCams     = Count($allcams);
$totalEnabled  = Count($enableds);
$totalFills    = Count($fills);
$totalInMotion = Count($inmotion); 
$totalIdle     = Count($nomotion); //with ages

//Output to page
echo "<html><h1>updateMotionStatus()</h1>";
echo "<h3>Cameras in Collection</h3>";
echo "<p>$totalCams</p>";
echo "<h3>Enabled Cameras</h3>";
echo "<p>$totalEnabled</p>";
echo "<h3>Fill Cameras</h3>";
echo "<p>$totalFills</p>";
echo "<h3>Cameras With Motion</h3>";
echo "<p>$totalInMotion</p><ul>";
foreach($inmotion as $key => $cam) {
   echo "<li>$cam</li>";
}
echo "</ul><h3>Idle Cameras (Minutes Idle)</h3>";
echo "<p>$totalIdle</p><ul>";
foreach($nomotion as $cam => $age) {
   echo "<li>$cam ($age)</li>";
}
echo "</ul></html>";

?>
