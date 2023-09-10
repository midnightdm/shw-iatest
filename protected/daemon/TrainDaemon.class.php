<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *  
 *  protected/TrainDaemon.class.php
 * 
 *  This class is a daemon that runs an endless while loop and pulls
 *  updates from "Motion" documents in a Firestore database that were
 *  placed there by scripts handling incoming events from remote cameras. 
 *  
 *  The number of cameras with active motion are stored in an array then
 *  written to a seperate "Control" document that a JavaScript controller
 *  reads to switch camera views. 
 *  
 *  setup() is a substitute for __construct. Instantiate then run start().
 *
 */
class TrainDaemon {
    public $flags;         //Has liveCams conrtols for screens
    public $cameras;       //Source data for all cameras
    public $liveCams; 
    public $fillCams;
    public $buffer;
    protected $run;
    public $config;
    public $MotionModel;
    public $lastCleanUp;
    public $lastCameraSwitch;
    public $lastJsonSave;
    public $lastPassagesSave;

    public $inBuffer;
    public $inCurent;
    public $newCams;
    public $rotationArray;
    public $rotationPointer;
    protected int $loopCount;
    public array $screenTypes;
    public array $screenNames; 
    public int $todayMidnight;
    
    
    public function __construct($config_ref) {
        $this->config = $config_ref;   
    }


    public function __destruct() {
        //Run when program exits
        $dt = new DateTime('now', new DateTimeZone('America/Chicago'));    
        $this->saveTimestampsOfCurrentCams();
        $dtPrn = $dt->format("Y-m-d H:i:s");
        flog("Destructor executed $dtPrn\n");
    }

    public function start() {
        flog( " Starting shw-railcam-server\n\n");  
        flog( "\t\t >>>     Type 'q' and ENTER at any time to quit.    <<<\r\n\n\n");
        
        $this->setup();
        $this->run = true;
        $this->run();
        $dt = new DateTime('now', new DateTimeZone('America/Chicago'));
        $dtPrn = $dt->format("Y-m-d H:i:s");
        flog("Application terminated by keypress $dtPrn\n");
        exit();
    }

    protected function setup() {
        $now    = time();
        $today  = new DateTime();
        $today->setTime(0,0);
        $this->todayMidnight       = $today->getTimestamp();

        $this->liveCams            = array(); //LiveCam objects - the heart of this app - get stored here
        $this->fillCams            = array(); //Objects to use when not enough current motion events
        $this->buffer              = array();
        $this->inBuffer            = array();
        $this->inCurrent           = array();
        $this->newCams             = array();

        $this->MotionModel         = new MotionModel();
        $this->cameras = $this->MotionModel->getCamerasCollection();
        $this->rotationArray = $this->MotionModel->getFillCamerasList();
        $this->rotationPointer = 0;
        $this->screenNames = ['prim'=>4, 'suba'=> 3, 'subb'=>2, 'subc'=>1, 'off'=>0];
        $this->screenTypes = ['X', 'M', 'F', 'C']; //Off, Motion, Fill, Controled
        $this->mapLiveCams();

        $this->getControlsFlags();
        $this->mapActiveScreens();


        $this->loopCount           = 180;
        $this->lastCleanUp         = $now-50; //Used to increment cleanup routine
        $this->lastCameraSwitch    = $now-50; //Prevents rapid camera switching if 2 vessels near
        $this->lastJsonSave        = $now-10; //Used to increment liveScan.json save
        $this->lastPassagesSave    = $now-50; //Increments savePassages routine

        //Set values below in $config array in config.php
        //$this->liveScanTimeout     = intval($this->config['liveScanTimeout']); 
    
    }

    protected function run() {
        //Runtime initialization
        stream_set_blocking(STDIN, 0); // Set STDIN to non-blocking mode
        sleep(1); //To make sure above command in effect before first STDIN check.

        flog( "\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  * \033[0m\r\n"); 
        flog( "\033[41m *                       A T T E N T I O N,                     * \033[0m\r\n");
        flog( "\033[41m *                          T H O M A S                         * \033[0m\r\n");
        flog( "\033[41m *                                                              * \033[0m\r\n");
        flog( "\033[41m *  Please keep this running to ensure camera switching.        * \033[0m\r\n");
        flog( "\033[41m *                                                              * \033[0m\r\n");
        flog( "\033[41m *  If it stops, close it and relaunch Railcam Server Daemon    * \033[0m\r\n");
        flog( "\033[41m *    using the 'php' desktop shortcut.                         * \033[0m\r\n");
        flog( "\033[41m *                                                              * \033[0m\r\n");
        flog( "\033[41m *                                                              * \033[0m\r\n");
        flog( "\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  * \033[0m\r\n\r\n");
        
        
        while($this->run==true) {
        
            //*********************************************************************** 
            //*                                                                     *
            //*                  This is MAIN LOOP of this server                   * 
            //*                                                                     *
            echo $this->loopCount."-";
            
            //Every 3 minutes
            if($this->loopCount==180) {
                $this->loopCount = 0;
                $this->updateMotionStatus();
            }
        
            //Every 20 seconds
            if($this->loopCount%20==0) {
                //echo "doLoopActions()\n";
                $this->doLoopActions();
            }
        
            //Every 1 sec
            $this->testForTerminationSignal();
            $this->loopCount++;
            sleep(1);

        //*                                                                      *
        //*                          End of main loop                            *
        //*                                                                      *
        //************************************************************************ 
        }
        
    }

    public function testForTerminationSignal() {
        // Check for key press
        
        $read   = array(STDIN);     $write  = null;      $except = null;
        // Check the streams (in this case, just STDIN) and see if any have data to be read
        if (stream_select($read, $write, $except, 0) > 0) {
            // Read a single character
            $input = fgetc(STDIN);
            // If the character is 'q', exit the loop
            if ($input === 'q') {
                $this->run = false;
            }
        }
    }

    public function doLoopActions() {
        $this->testForCameraUpdates();
        $this->testForControlUpdates();
        $this->getControlsFlags(); 
        if($this->loopCount > 1) {
            $this->showScreenCamStats();
            $this->saveTimestampsOfCurrentCams();
        }
        if($this->processMotionUpdates()) {
            $this->assignAllScreens();
        }
        
        echo "\033[44m To quit without causing data loss type \"q\" and press ENTER\033[0m\r\n";
    }

    public function testForCameraUpdates() {
        if($this->MotionModel->areThereCameraUpdates()) {
            $this->cameras = $this->MotionModel->getCamerasCollection();
            $this->rotationArray = $this->MotionModel->getFillCamerasList();
            flog("Camera source was data revised\n");
            $this->mapLiveCams();
            $this->MotionModel->resetAreCameraUpdates();
        }
    }

    public function testForControlUpdates() {
        $report = $this->MotionModel->areThereControlUpdates();
        if($report[0]) {
            flog("Screen {$report[1]} switched remotely\n");
            $this->assignScreen($report[1]);
            $this->MotionModel->resetAreControlUpdates();
        }
    }

    public function getControlsFlags() {
        $this->flags = $this->MotionModel->getControlsFlags();
    }

    public function mapActiveScreens() {
        foreach($this->flags['liveCams'] as $screenName => $screenMap) {
            //echo "mapActiveScreens screenName: $screenName, screenMap ".is_array($screenMap);
            $srcID = $screenMap['srcID'];
            $this->liveCams[$srcID]->currentScreenID = $this->screenNames[$screenName];
            //$this->liveCams[$srcID]->screenType = $this->cameras[$srcID]['timestamps']['screenType'];
        }
    }

    public function processMotionUpdates() {//:boolean
        $dt = new DateTime('now', new DateTimeZone('America/Chicago'));
        $dtStr = $dt->format('D H:i:s');
        $dtPrn = $dt->format("Y-m-d H:i:s");
        //Get all hasMotion cameras
        $this->buffer = $this->MotionModel->getHasMotionCams();
        // echo "buffer: ";
        // print_r($this->buffer);
        flog("\n=========== MOTION UPDATE $dtPrn ============\n");
        flog(" Old Current: ");
        $last = $this->inCurrent;
        flog(print_a($last));
        flog("----------------------------------------------\n");
    
        //collect ids of buffered cameras and strip any duplicates
        $inBuffer = array_map(fn($cam) => $cam['srcID'], $this->buffer);
        $this->inBuffer = array_unique($inBuffer);

        //Create or update liveCam objects from buffer array
        foreach($this->buffer as $camera) {
            if(isset($this->liveCams[$camera['srcID']])) {
                $this->liveCams[$camera['srcID']]->map($camera);
            } else {
                $this->liveCams[$camera['srcID']] = new LiveCam($camera, $this);
            }
        }

        flog("   In Buffer: ") ;
        flog(print_a($this->inBuffer));
        flog("------------------------------------------\n");

        //identify new cameras (those in buffer which aren't in current)
        $this->newCams = array_diff($this->inBuffer, $this->inCurrent);
        flog("    New Cams: ");
        flog(print_a($this->newCams));
        flog("-------------------------------------------\n");

        //remove old cameras (those in current which aren't in buffer)
        $expired = array_diff($this->inCurrent, $this->inBuffer);
        flog("Expired Cams: ");
        flog(print_a($expired));
        flog("--------------------------------------------\n");
        // foreach($expired as $srcID) {
        //     unset($this->liveCams[$srcID]);
        // }
        $this->inCurrent = array_values(array_diff($this->inCurrent, $expired));

        //add new cams
        $this->inCurrent = array_merge($this->inCurrent, $this->newCams);
        flog(" New Current: ");
        flog(print_a($this->inCurrent));
        flog("----------------------------------------------\n");
        //Return true if inCurrent is different from last loop
        if(count($expired) || count($this->newCams)) {
            $isDifferent = true;
        } else {
            $isDifferent = false;
        }
        
        $isDifferentString = $isDifferent ? "YES, change screens" : "NO, keep screens unchanged";
        flog("Are the sensed motion cameras different?  $isDifferentString\n\n");

        return $isDifferent;
    }

    public function assignAllScreens() {
        $keys = ['prim', 'suba', 'subb', 'subc']; $k=0;
        $lastAssignment = [
            $this->flags['liveCams']['prim']['srcID'],
            $this->flags['liveCams']['suba']['srcID'],
            $this->flags['liveCams']['subb']['srcID'],
            $this->flags['liveCams']['subc']['srcID']
        ];
        $num = count($this->inCurrent);
        $fill = 4 - $num;
        $returnString = "\n============== SCREEN ASSIGNMENTS =============\n";
        
        
        //1st loop assigns cameras with motion
        for($n=0; $n<$num; $n++) { 
            //Test screen age, don't change if last switch < 50 sec old
            if($k < 4 && $this->screenSwitchIsRecent($keys[$k])) {
                $k++;
            }
            if($k==4) { 
                flog($returnString."\n");
                return; 
            } 
            if(count($this->inCurrent)>$n+1) {
                $result = $this->descriminateCamera($this->inCurrent[$n], $this->inCurrent[$n+1], $keys[$k]);
                //In case $n+1 was choice, skip 1 place to avoid duplicate
                if($result[0] == $this->inCurrent[$n+1]) {
                    $n++;
                }
                //Switch current camera off
                $this->liveCams[$lastAssignment[$k]]->recordScreenSwitch(0,0);
                //and new camera on
                $this->handleCameraSelection($keys[$k], $result[0]);
                $this->liveCams[$result[0]]->recordScreenSwitch($this->screenNames[$keys[$k]],1);
                $returnString .= "     ".$keys[$k]."->".$result[0]." (Motion)\n";
            } else {
                //Switch current camera off
                $this->liveCams[$lastAssignment[$k]]->recordScreenSwitch(0,0);
                //and new camera on
                $this->handleCameraSelection($keys[$k], $this->inCurrent[$n]);
                $this->liveCams[$this->inCurrent[$n]]->recordScreenSwitch($this->screenNames[$keys[$k]],1);
                $returnString .= "     ".$keys[$k]."->".$this->inCurrent[$n]." (Motion)\n";
            }
            $k++;
        }
        //2nd loop assigns fill cameras
        for($f=0; $f<$fill; $f++) {
            //Test screen age, don't change if last switch < 50 sec old
            if($k==4) { 
                flog($returnString."\n");
                return; 
            }
            if($k < 4 && $this->screenSwitchIsRecent($keys[$k])) {
                $k++;
            }
            
            //test to keep or kill current fill camera
            $currentFillSrcID = $this->flags['liveCams'][$keys[$k]]['srcID'];
            $shouldKeep = $this->shouldKeepFillCamera($currentFillSrcID);
            if($shouldKeep) {
                $this->liveCams[$currentFillSrcID]->screenType = 2;
                $returnString .= "     ".$keys[$k]."->".$currentFillSrcID." (Kept Fill)\n";
                $k++;
                continue;
            }
            //Get new fill camera choice from rotation
            $srcID = $this->getRotationCamera();
            //skip choice if current
            if(in_array($srcID, $lastAssignment) || in_array($srcID, $this->inCurrent)) {
                continue;
            }
            //Switch current camera off
            $this->liveCams[$lastAssignment[$k]]->recordScreenSwitch(0,0);
            //and new camera on
            $this->handleCameraSelection($keys[$k], $srcID);
            $this->liveCams[$srcID]->recordScreenSwitch($this->screenNames[$keys[$k]],2);
            $returnString .= "     ".$keys[$k]."->".$srcID." (New Fill)\n";
            $k++;
        }
        flog($returnString."\n");
        //Now send to database
        $this->MotionModel->setControlsFlags($this->flags);
    }

    public function assignScreen($screenName) {
        //Buffer the srcID currently on screen
        $srcID = $this->flags['liveCams'][$screenName]['srcID'];
        //Update the control array with the new choice
        $this->getControlsFlags();
        $newSrcID = $this->flags['liveCams'][$screenName]['srcID'];
        //Switch current camera off
        $this->liveCams[$srcID]->recordScreenSwitch(0,0);
        //and new camera on
        $this->handleCameraSelection($screenName, $newSrcID);
        $this->liveCams[$newSrcID]->recordScreenSwitch($this->screenNames[$screenName], 3);
    }

    public function shouldKeepFillCamera($srcID) :bool {
        //Returns false if camera on screen more than 2 minutes
        $now = time();
        $duration = !isset($this->liveCams[$srcID]->screenTS) ? 0 : $now - $this->liveCams[$srcID]->screenTS;
        return $duration > 120;
    }

    public function descriminateCamera($srcID1, $srcID2, $screenName) {
        //Compares stats of 2 screens to pick priority
        $screenID = $this->screenNames[$screenName];
        $cams = [ $this->liveCams[$srcID1], $this->liveCams[$srcID2]];
        $scores = [5,5];
        $now    = time();
        for($i=0; $i<count($cams); $i++) {
            $cam = $cams[$i];
            //Is the submitted screen the present screen for this camera? 
            if($cam->currentScreenID != $screenID) {
                // Yes, bias neutral. Has it been on > 2 min?
                $duration = !isset($cam->screenTS) ? 0 : $now-$cam->screenTS;
                if($duration > 120) {
                    // Yes bias negative
                    $scores[$i]--;
                } else {
                    //  No bias positive
                    $scores[$i]++;
                }
                
            } else {
                //  No, bias positive
                $scores[$i]++;
            }
        }    
        //Is this for the prime screen?
        if($screenID==4) {
            // Yes, calculate primeAge & primeCume
            $cams[0]->primeAge = $cams[0]->primeTS===0 ? 0 : $now-$cams[0]->primeTS;
            $cams[0]->primeCume = $cams[0]->primeAge + $cams[0]->primeCume;
            //Error check for null on 1st run
            $cams[1]->primeAge = $cams[1]->primeTS===0 ? 0 : $now-$cams[0]->primeTS;
            
            $cams[1]->primeCume = $cams[1]->primeAge + $cams[1]->primeCume;
            //Does todays primeCume exceed competitor?
            if($cams[0]->primeCume > $cams[1]->primeCume) {
                // Yes bias negative to the winner, positive to loser
                $scores[0]--;  
                $scores[1]++;
            } else {
                $scores[0]++;
                $scores[1]--;
            } 
        }  
        //Calculate screenAge & screenCume
        $cams[0]->screenAge =  $cams[0]->screenTS===0 ? 0 : $now-$cams[0]->screenTS;
        $cams[0]->screenCume = $cams[0]->screenAge + $cams[0]->screenCume;
        $cams[1]->screenAge =  $cams[1]->screenTS===0 ? 0 : $now-$cams[1]->screenTS;
        $cams[1]->screenCume = $cams[1]->screenAge + $cams[1]->screenCume;
        //Does todays screenCume exceed competitor?
        if($cams[0]->screenCume > $cams[1]->screenCume) {
            // Yes bias negative to the winner, positive to loser
            $scores[0]--;  
            $scores[1]++;
        } else {
            $scores[0]++;
            $scores[1]--;
        } 
        //Return array with winning srcID in 0, loser in 1
        $result = $scores[0] > $scores[1] ? [$srcID1, $srcID2, $scores[0], $scores[1]] : [$srcID2, $srcID1, $scores[1], $scores[0]];
        flog("Screen choice $screenName: ".$result[0]." beat ".$result[1]." ".$result[2]."-".$result[3]."\n");
        return $result;
    }

    public function showScreenCamStats() {
        $primID = $this->flags['liveCams']['prim']['srcID'];
        $subaID = $this->flags['liveCams']['suba']['srcID'];
        $subbID = $this->flags['liveCams']['subb']['srcID'];
        $subcID = $this->flags['liveCams']['subc']['srcID'];

        $primScrnTp = $this->screenTypes[ $this->liveCams[$primID]->screenType ];
        $subaScrnTp = $this->screenTypes[$this->liveCams[$subaID]->screenType];
        $subbScrnTp = $this->screenTypes[$this->liveCams[$subbID]->screenType];
        $subcScrnTp = $this->screenTypes[$this->liveCams[$subcID]->screenType];

        $primScrnID = $this->liveCams[$primID]->currentScreenID;
        $subaScrnID = $this->liveCams[$subaID]->currentScreenID;
        $subbScrnID = $this->liveCams[$subbID]->currentScreenID;
        $subcScrnID = $this->liveCams[$subcID]->currentScreenID;

        $primSecOnScrn = $this->liveCams[$primID]->screenAge;
        $subaSecOnScrn = $this->liveCams[$subaID]->screenAge;
        $subbSecOnScrn = $this->liveCams[$subbID]->screenAge;
        $subcSecOnScrn = $this->liveCams[$subcID]->screenAge;

        $primSecAsPrim = $this->liveCams[$primID]->primeAge;
        $subaSecAsPrim = $this->liveCams[$subaID]->primeAge;
        $subbSecAsPrim = $this->liveCams[$subbID]->primeAge;
        $subcSecAsPrim = $this->liveCams[$subcID]->primeAge;

        $primPrimToday = $this->liveCams[$primID]->primeCume;
        $subaPrimToday = $this->liveCams[$subaID]->primeCume;
        $subbPrimToday = $this->liveCams[$subbID]->primeCume;
        $subcPrimToday = $this->liveCams[$subcID]->primeCume;

        $primScrnToday = $this->liveCams[$primID]->screenCume;
        $subaScrnToday = $this->liveCams[$subaID]->screenCume;
        $subbScrnToday = $this->liveCams[$subbID]->screenCume;
        $subcScrnToday = $this->liveCams[$subcID]->screenCume;

        $str = "\n===========================================  ACTIVE SCREENS  =========================================\n";
        $str .= sprintf(
            "%-10s  %-10s  %-18s  %-10s %-8s  %-10s   %-10s %-10s\n",
            "ScreenName",
            "ScreenID",
            "Camera",
            "Type",
            "Seconds",
            "Seconds",
            "Prime",
            "Screen"
        );
        $str .= sprintf(
            "%-10s  %-10s  %-20s  %-4s    %-5s  %-10s   %-10s  %-10s\n",
            "",
            "",
            "",
            "",
            "On Screen",
            "As Prime",
            "Today",
            "Today"
        );
        $str .= sprintf(
            " Prime         %-6s  %-20s  %-7s    %5d      %5d     %5d        %5d \n",
            $primScrnID,
            $primID,
            $primScrnTp,
            $primSecOnScrn,
            $primSecAsPrim,
            $primPrimToday,
            $primScrnToday
        );
        $str .= sprintf(
            " Sub-A         %-6s  %-20s  %-7s    %5d      %5d     %5d        %5d \n",
            $subaScrnID,
            $subaID,
            $subaScrnTp,
            $subaSecOnScrn,
            $subaSecAsPrim,
            $subaPrimToday,
            $subaScrnToday
        );
        $str .= sprintf(
            " Sub-B         %-6s  %-20s  %-7s    %5d      %5d     %5d       %5d \n",
            $subbScrnID,
            $subbID,
            $subbScrnTp,
            $subbSecOnScrn,
            $subbSecAsPrim,
            $subbPrimToday,
            $subbScrnToday
        );
        $str .= sprintf(
            " Sub-C         %-6s  %-20s  %-7s    %5d      %5d     %5d       %5d \n",
            $subcScrnID,
            $subcID,
            $subcScrnTp,
            $subcSecOnScrn,
            $subcSecAsPrim,
            $subcPrimToday,
            $subcScrnToday
        );
        $str .= "-------------------------------------------------------------------------------------------------------\n";

              

        flog($str);
    }

    public function handleCameraSelection($screen, $srcID) {
        //Screen must be 'prim', 'suba', 'subb' or 'subc'
        echo "     handleCameraSelection() $srcID\n";
        $newSelection = $this->cameras[$srcID];
        $this->flags['liveCams'][$screen]['srcID']   = $newSelection['srcID'];
        $this->flags['liveCams'][$screen]['srcType'] = $newSelection['srcType'];
        $this->flags['liveCams'][$screen]['srcUrl']  = $newSelection['srcUrl'];
    }

    public function getRotationCamera() {
        $count = count($this->rotationArray);
        echo "getRotationCamera() count: $count,";
        if($this->rotationPointer > count($this->rotationArray)-1) {
            $this->rotationPointer = 0;
        }
        echo " pointer: ".$this->rotationPointer."\n";
        $srcID = $this->rotationArray[$this->rotationPointer];
        $this->rotationPointer++;
        return $srcID;
    }

    public function mapLiveCams() {
        foreach($this->cameras as $camera) {
            if(isset($this->liveCams[$camera['srcID']])) {
                $this->liveCams[$camera['srcID']]->map($camera);
            } else {
                $this->liveCams[$camera['srcID']] = new LiveCam($camera, $this);
            }
        }
    }

    public function saveTimestampsOfCurrentCams() {
        $lastAssignment = [
            $this->flags['liveCams']['prim']['srcID'],
            $this->flags['liveCams']['suba']['srcID'],
            $this->flags['liveCams']['subb']['srcID'],
            $this->flags['liveCams']['subc']['srcID']
        ];
        foreach($lastAssignment as $srcID) {
            $this->liveCams[$srcID]->calculateScreenCounters();
        }
    }

    public function screenSwitchIsRecent($screenName) :bool {
        $scrID = $this->flags['liveCams'][$screenName]['srcID'];
        $now = time();
        $age = $now-$this->liveCams[$scrID]->screenTS;
        if($age<50) {
            return true;
        }
        return false;
    }

    public function updateMotionStatus() {
        //echo $this->flags['updateUrl'];
        $html = grab_page($this->flags['updateUrl']);
        flog("\n\nLocal run of {$this->flags['updateUrl']} every 3 min\n\n");  
        $obj = simplexml_load_string($html);
        $str = "";
        for($i=0; $i<3; $i++) {
            $str.= "{$obj->h3[$i]} {$obj->p[$i]}\n";  
        }
        $str.= "{$obj->h3[3]} {$obj->p[3]}\n";
        $ulElement = $obj->ul[0];
        if ($ulElement) {
            foreach ($ulElement->li as $liElement) {
                $str .= "   $liElement\n";
            }
        }
        $str.= "{$obj->h3[4]} {$obj->p[4]}\n";
        $ulElement = $obj->ul[1];
        if ($ulElement) {
            foreach ($ulElement->li as $liElement) {
                $str .= "   $liElement\n";
            }
        }
        flog($str."\n");
    }
}


