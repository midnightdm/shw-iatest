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
    public $screens;       //Current selected screens
    public $liveCams;      //Objects of all liveCams 
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
        //flog( "\t\t >>>     Type 'q' and ENTER at any time to quit.    <<<\r\n\n\n");
        
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
        $this->fillTimeoutValue    = 360;

        $this->liveCams            = array(); //LiveCam objects - the heart of this app - get stored here
        $this->fillCams            = array(); //Objects to use when not enough current motion events
        $this->screens             = array(); //Object store of LiveCams assigned to the 4 screens
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


        $this->loopCount           = 90;
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
            if($this->loopCount==90) {
                $this->loopCount = 0;
                $this->updateMotionStatus();
                
                
            }

            // //Every 30 seconds
            // if($this->loopCount%30==0) {
            //     //Refresh fill screens even if no motion lately
            //     $this->assignAllScreens();
            // }        
            //Every 10 seconds
            if($this->loopCount%10==0) {
                //echo "doLoopActions()\n";
                $this->doLoopActions();
            }
        
            //Every 1 sec
            //$this->testForTerminationSignal();
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

    public function checkDbForDaemonReset() {
        flog("     * checkDbForDaemonReset()   ");
        if($this->MotionModel->testExit()) {
            flog( " = \033[41mTRUE -> Train Daemon stopped per database command.\033[0m\n");
            $this->run = false;
        } else {
            flog(" = NONE\n");
        }
    }

    public function doLoopActions() {
        $this->checkDbForDaemonReset();
        $this->testForCameraUpdates();
        $this->testForControlUpdates();
        $this->getControlsFlags(); 
        if($this->loopCount > 1) {
            $this->showScreenCamStats();
            $this->saveTimestampsOfCurrentCams();
        }
        $this->processMotionUpdates();
        $this->assignAllScreens();
        
        //echo "\033[44m To quit without causing data loss type \"q\" and press ENTER\033[0m\r\n";
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
        if($report['hasChange']) {
            flog("Screen {$report['screen']} switched remotely\n");
            $this->assignScreen($report['screen'], $report['isLocked']);
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
            $this->liveCams[$srcID]->isLocked = $screenMap['isLocked'];
            $this->liveCams[$srcID]->srcName = $screenMap['srcName'];
            $this->liveCams[$srcID]->isMuted = $screenMap['isMuted'];
            $this->screens[$screenName] = $this->liveCams[$srcID];
            
        }
    }

    public function getScreenContainingId($srcID) {
        foreach($this->screens as $key => $obj) {
            if($obj->srcID == $srcID) {
                return $key;
            }
        }
    }

    public function getCurrentMotionCams() {
        //Return all when cams # <= screens.
        if(count($this->inCurrent) <=4) {
            return $this->inCurrent;
        }
        //Otherwise reduce to cams less seen already
        $result = array();
        foreach($this->inCurrent as $id) {
            $obj = $this->liveCams[$id];
            $timeValue = $obj->screenCume; //This is an integer
            $result[$id] = $timeValue;
        }
        //Do some sorting of values here
        asort($result);
        //Put IDs of the smallest 4 into an array to be returne
        $idArray = array_slice(array_keys($result), 0, 4);
        return $idArray;
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
        $this->inCurrent = array_values(array_diff($this->inCurrent, $expired));

        //add new cams
        $this->inCurrent = array_merge($this->inCurrent, $this->newCams);
        flog(" New Current: ");
        flog(print_a($this->inCurrent));
        flog("----------------------------------------------\n");
        
    }


    public function assignAllScreens() {
        $keys = ['prim', 'suba', 'subb', 'subc'];
        $motionTotal = count($this->inCurrent);
        $screenKey = 0;
        $motionIncrement = 0;
        $tracer = "\nassignAllScreens()\n* Begin Motion Loop\n";
        /* Get upto 4 current cams */
        $currentCams = $this->getCurrentMotionCams();
        //OLD foreach($this->inCurrent as $id) { //Outer loop: IDs of cams with motion
        foreach($currentCams as $id) { //Outer loop: IDs of cams with motion
            $tracer .= "   screenKey->".$screenKey." ".$keys[$screenKey].", id with motion is $id\n";
            for($m=0; $screenKey<3; $m++, $screenKey++) {       //Inner loop: check existing screens status
                if($motionIncrement>=$motionTotal) {
                    $tracer .= "   Motion count reached.\n";
                    $this->MotionModel->setControlsFlags($this->flags);
                    break 2;
                }
                $obj = $this->screens[$keys[$m]];
                $tracer .= "   currentScreenView {$obj->currentScreenView} ";

                //NEW LOCK SCREEN CODED ADDED 1/27/24

                //Leave remote-switched camera assignment on if screen is locked
                if($obj->isLocked) {
                    $tracer .= "Skipped because {$keys[$m]} screen is locked. {$obj->srcID} (Kept Fill)\n";
                    $motionIncrement++;
                    continue;
                }


                //Is ID on screen already?
                if (in_array($id, array_column($this->screens, 'srcID'))) {
                    //Yes, then is the on-screen ID of this iteration?
                    if($obj->srcID == $id) {
                        //Yes, then is it the prime screen?
                        if($screenKey==0) {
                            //Yes, then does the set screen have motion status already?
                            if ($obj->viewAssignment === "motion") {
                                //Yes, then keep this ID on the tested screen. Break inner loop.
                                $tracer .=  " keeping motion assignment ".$obj->srcID." (343) \n";
                                //$motionIncrement++;
                                continue;
                            } else {
                                //No, then change obj status from fill to motion
                                $tracer .= " Keeping assignment ".$obj->srcID." changing to motion. (348)\n";
                                $this->assignMotionScreen($keys[$screenKey], $id);
                                $motionIncrement++;
                            }   
                        } else {
                            //No, then does the prime screen have a motion cam already?
                            if($this->screens[$keys[0]]->viewAssignment == "motion") {
                                //Yes, then has it reached timeout value?
                                if($obj->motionTimeoutValueExpired()) {
                                    //Yes, then replace old motion cam with this one...
                                     $tracer .= " Replacing ".$obj->srcID . " with $id (358)\n";
                                    $this->assignMotionScreen($keys[$screenKey], $id);
                                    //...and break this inner loop
                                    $motionIncrement++;
        
                                } else {
                                    //No, then leave it alone. Does the set screen have motion status already?
                                    if ($obj->viewAssignment === "motion") {
                                        //Yes, then keep this ID on the tested screen. Break inner loop.
                                        $tracer .=  " keeping assignment ".$obj->srcID." (367)\n";
                                        //$motionIncrement++;
                                        continue;
                                    } else {
                                        //No, then change obj status from fill to motion
                                        $tracer .= " Keeping assignment ".$obj->srcID." changing to motion. (372)\n";
                                        $this->assignMotionScreen($keys[$screenKey], $id);
                                        //$motionIncrement++;
                                    }   
                                }
                            } else {                                                                                                                                                                            
                                //No, then swap prime fill with this motion cam
                                $primeID = $this->screens[$keys[0]->srcID];
                                $this->assignMotionScreen($keys[$screenKey], $primeID);
                                $this->assignMotionScreen($keys[0], $id);
                                $tracer .=  "   Swapped prime fill $primeID with motion assignment $id (382)\n";
                                //...and break this inner loop
                                break;
                            }
                        }
                    } else {
                        //No, then is it the prime screen?
                        if($screenKey==0) {
                            //Yes, then does the set screen have motion status?
                            if ($obj->viewAssignment === "motion") {
                                //Yes, then has it reached timeout value?
                                if($obj->motionTimeoutValueExpired()) {
                                    //Yes, then which screen shows ID now?
                                    $idScreen = $this->getScreenContainingId($id);
                                    //Swap idScreen with prime
                                    $this->assignMotionScreen($keys[0], $id);
                                    //and make the cam formerly on prime status fill
                                    $this->assignFillScreen($keys[$idScreen], $obj->srcID);
                                    $tracer .=  "   Swapped $id on $idScreen with {$obj->srcID} on prime (400)\n";
                                    $motionIncrement++;
                                    break;
                                } else {
                                    //No, then leave it assigned and proceed with inner loop.
                                    $tracer .=  " keeping assignment ".$obj->srcID.". It has not timed out yet. (405)\n";
                                    continue;
                                }
                            } else {
                                //No, then change obj status from fill to motion
                                $tracer .= " Keeping assignment ".$obj->srcID." changing to motion. (410)\n";
                                $this->assignMotionScreen($keys[$screenKey], $id);
                                //$screenKey++;
                            }   
                        } else {
                            //No, then does the set screen have motion status?
                            if ($obj->viewAssignment === "motion") {
                                //Yes, then has it reached timeout value?
                                if($obj->motionTimeoutValueExpired()) {
                                    //Yes, then assign a fill camera
                                    $srcID = $this->getRotationCamera();
                                    // Check that the new fill camera is not assigned already
                                    while (in_array($srcID, array_column($this->screens, 'srcID'))) {
                                        $srcID = $this->getRotationCamera();
                                    }
                                    $tracer .= "   Random fill choice is $srcID (425)\n";
                                    $this->assignFillScreen($keys[$m], $srcID);
                                } else {
                                    //No, then keep this ID on the tested screen. Break inner loop.
                                        $tracer .=  " keeping motion assignment ".$obj->srcID." (429)\n";
                                        //$motionIncrement++;
                                        break;
                                }
                            } else {
                                //No, then has it reached timeout value?
                                if($obj->fillTimeoutValueExpired()[0]) {
                                    $srcID = $this->getRotationCamera();
                                    // Check that the new fill camera is not assigned already
                                    while (in_array($srcID, array_column($this->screens, 'srcID'))) {
                                        $srcID = $this->getRotationCamera();
                                    }
                                    $tracer .= "   Random fill choice is $srcID (441)\n";
                                    $this->assignFillScreen($keys[$m], $srcID);

                                } else {
                                    //No then keep this ID on screen. Break inner loop.
                                    $tracer .=  " keeping fill assignment ".$obj->srcID." (446)\n";
                                    break;
                                }
                            }
                        }
                    }
                } else {
                    //No, ID is not on screen already.  
                    //      Does the test screen have motion status already?
                    if ($obj->viewAssignment === "motion") {
                        //Yes, then has it reached timeout value?
                        if($obj->motionTimeoutValueExpired()) {
                            //Yes, then replace old motion cam with this one...
                            $tracer .= " Replacing ".$obj->srcID . " with $id (459)\n";
                            $this->assignMotionScreen($keys[$screenKey], $id);
                            //...and break this inner loop
                            $motionIncrement++;
                            break;
                        } else {
                            //No, the keep current screen assignment.
                            $tracer .=  " keeping assignment ".$obj->srcID." (466)\n";
                        }
                    } else {
                        //No, then put new motion cam in its place
                        $tracer .= " Replacing ".$obj->srcID . " with $id (472)\n";
                        $this->assignMotionScreen($keys[$screenKey], $id);
                        //...and break this inner loop
                        $motionIncrement++;
                        //$screenKey++;
                        break;
                    }
                }  
            }
        }
        
        // Assign remaining screens with fill cameras when their timeout values are expired
        $tracer .= "* Begin Fill Loop.\n";
        for ($f=$screenKey; $f <=3; $f++) {
            $formattedID = sprintf("%-15s", $this->screens[$keys[$f]]->srcID );
            $timeOut = $this->screens[$keys[$f]]->fillTimeoutValueExpired()[0] ? "true":"false"; 
            $locked = $this->screens[$keys[$f]]->isLocked ? "true" : "false";
            $tracer .= "   screenKey-> ".$f." ".$keys[$f].", $formattedID timeout? $timeOut, Duration=".$this->screens[$keys[$f]]->fillTimeoutValueExpired()[1]. ", Time To Live=".$this->screens[$keys[$f]]->fillTimeoutValueExpired()[2]." isLocked=$locked\n";

            if($this->screens[$keys[$f]]->isLocked) {
                continue;
            } else {
                if( $this->screens[$keys[$f]]->viewAssignment === "off" || $this->screens[$keys[$f]]->fillTimeoutValueExpired()[0]) {
                    $srcID = $this->getRotationCamera();
                    // Check that the new fill camera is not assigned already
                    while (in_array($srcID, array_column($this->screens, 'srcID'))) {
                        $srcID = $this->getRotationCamera();
                    }
                    $tracer .= "      Random fill choice is $srcID to replace {$this->screens[$keys[$f]]->srcID}\n";
                    $this->assignFillScreen($keys[$f], $srcID);
                }
            }


        }
        $this->eliminateDuplicateScreenAssignments();
        //Now send to database
        $tracer .= "Motion and fill loops completed.  Sending choices to db.\n";
        flog($tracer);
        $this->MotionModel->setControlsFlags($this->flags);
    }

    public function assignAllScreensOld() {
        $keys = ['prim', 'suba', 'subb', 'subc'];
        $k = 0;
        $num = count($this->inCurrent);
        $fill = 4 - $num;
        $lastAssignment = [
            $this->flags['liveCams']['prim']['srcID'],
            $this->flags['liveCams']['suba']['srcID'],
            $this->flags['liveCams']['subb']['srcID'],
            $this->flags['liveCams']['subc']['srcID']
        ];
        $newAssignment = [];
        $tracer = "TRACER: assignAllScreens() ->  num=$num, k=$k, fill=$fill\n";
        $returnString = "\n============== SCREEN ASSIGNMENTS =============\n";

        // 1st loop assigns cameras with motion
        for ($n = 0; $n < $num; $n++) {
            $tracer .= "    Motion loop n=$n, k=$k ";
            $srcID = $this->inCurrent[$n];
            $consideredCamera = $this->liveCams[$srcID];
            
            if ($k >= 4) {
                // Screen limit reached. Write results to db.
                $this->MotionModel->setControlsFlags($this->flags);
                flog($returnString."\n");
                flog($tracer."   Stopped by k >= 4\n");
                return;
            }

            //NEW LOCK SCREEN CODED ADDED 1/27/24

            //Leave remote-switched camera assignment on if screen is locked
            $activeCamera = $this->liveCams[$lastAssignment[$k]];
            if($activeCamera->isLocked) {
                $tracer .= "Skipped because {$keys[$k]} is locked.\n";
                $returnString .= "     ".$keys[$k]."-> ".$activeCamera->srcID." (Kept Fill)\n";
                $k++;
                continue;
            }

            // Don't switch away from a view if it was activated recently
            
            $timeSinceActivated = time() - $activeCamera->screenTS;
            $tracer .= "timeSinceActivated=$timeSinceActivated, motionTimeoutValue=".$activeCamera->motionTimeoutValue."\n";
            if ($timeSinceActivated < $activeCamera->motionTimeoutValue) {
                $newAssignment[] = $activeCamera->srcID;
                $tracer .= "    {$keys[$k]} switched recently. Keeping {$activeCamera->srcID}.\n";
                $returnString .= "     ".$keys[$k]."-> ".$activeCamera->srcID." (Kept Motion)\n";
                $k++;
                //If assigned camera was fill, upgrade it's status to motion
                if($srcID == $activeCamera->srcID) {
                    //$n++;
                    if($activeCamera->isAssignedAsFillCam) {
                        $activeCamera->recordScreenSwitch($keys[$k],false,false);
                        $tracer .= "Fill cam ".$activeCamera->srcID." status  is now motion cam.\n";
                    }
                    continue;
                }
                $tracer .= "    Motion loop n=$n, k=$k \n"; 
            }
            
            // Prioritize an active motion camera for the prim screen
            if ($k === 0) {
                $tracer .= "    Assign active motion from $srcID to prime screen\n";
                $returnString .= "     ".$keys[$k]."->".$srcID." (New Motion)\n";
                $this->handleCameraSelection('prim', $srcID);
                $newAssignment[] = $srcID;
                $activeCamera->recordScreenSwitch('prim', false, true);
                $consideredCamera->recordScreenSwitch('prim', false, false);
                $k++;
                
            } else {
                if ($k >= 4) {
                    // Screen limit reached. Write results to db.
                    $this->MotionModel->setControlsFlags($this->flags);
                    flog($returnString."\n");
                    flog($tracer."   Stopped by k >= 4\n");
                    return;
                }
                /* When more cameras have motion than there are screens... */
                if($num>1 && $n==0) {
                    $tracer .= "Starting numerous motion source comparison.\n";
                }
                $i=0; //Counter for keeping last choice if space 
                foreach ($this->inCurrent as $testID) {
                    $skip = 0;
                    $testCamera = $this->liveCams[$testID];
                    $tracer .= "    Testing $testID ";
                    //Skip when testCamera is the consideredCamera already
                    if(count($this->inCurrent)>1 && $testID==$srcID) {
                        $tracer .= "-> Skipped because its the considered camera already.\n";
                        $skip++;
                    }
                    /* Skip when test camera is already assigned to a view */
                    if($testID == $lastAssignment[$k]) {
                        $tracer .= "-> Skipped because its the active screen.\n";
                        //$returnString .= "     ".$keys[$k]."->".$srcID." (Kept Motion)\n";
                    }
                    if(in_array($testID, $newAssignment) || in_array($testID, $lastAssignment) ) {
                        $tracer .= "-> Skipped because its assigned to a screen already.\n";
                        $skip++;
                    }
                    if($skip>0) {
                        $i++;
                        //$tracer .= " ** Skipping now. **\n";
                        continue 2;
                    }
                     /* prioritize sources with less accumulated screen time and greater time since last seen. */
                    if (($i<$num-1) && ($testCamera->offTS < $consideredCamera->offTS) && ($testCamera->screenCume < $consideredCamera->screenCume)) {
                        $tracer .= " chosen over {$srcID}\n";
                        $srcID = $testID;
                        $consideredCamera = $this->liveCams[$srcID];
                    } else {
                        //Keep other choice
                        $tracer .= "against next choice $srcID\n";
                    }
                    $i++;
                    //Repeating loop leaves the most eligible one for handling below
                }
                $this->handleCameraSelection($keys[$k], $srcID);
                $newAssignment[] = $srcID;
                $tracer .= "    Motion choice for {$keys[$k]} was {$srcID}\n";
                $returnString .= "     ".$keys[$k]."->".$srcID." (New Motion)\n";
                $activeCamera->recordScreenSwitch($keys[$k], false, true);
                $consideredCamera->recordScreenSwitch($keys[$k],false, false);
                $k++;
                
            }
        }
        // 2nd loop assigns fill cameras
        for ($f = 0; $f < $fill; $f++) {
            $tracer .= "    Fill loop f=$f, k=$k ";
             //Stop loop when all 4 views have been assigned
            if ($k >= 4) {
                // Screen limit reached. Write results to db.
                $this->MotionModel->setControlsFlags($this->flags);
                flog($returnString."\n");
                flog($tracer."   Stopped by k >= 4\n");
                return;
            }
            $srcID = $this->getRotationCamera();
             // Check that the new fill camera is not assigned already
             while (in_array($srcID, $newAssignment) || in_array($srcID, $lastAssignment) ) {
                $tracer .= " Fill camera $srcID found in assigned array. Trying another.\n";
                $srcID = $this->getRotationCamera();
            } 
            $consideredCamera = $this->liveCams[$srcID];

            /* Replace a fill camera with another fill only if its been on past assigned timeout value */
            $activeCamera = $this->liveCams[$lastAssignment[$k]];
            $timeSinceActivated = time() - $activeCamera->screenTS;
            $tracer .= ", timeSinceActivated=".$timeSinceActivated.", fillTimeoutValue=".$activeCamera->fillTimeoutValue."\n";

            // NEW CODE 1/27/24

            //Leave remote-switched camera assignment on if screen is locked
            if($activeCamera->isLocked) {
                $tracer .= "Skipped because {$keys[$k]} is locked.\n";
                $returnString .= "     ".$keys[$k]."-> ".$activeCamera->srcID." (Kept Fill)\n";
                $k++;
                continue;
            }
            
            //Don't use motion cam for sub if already used in prime
            $isPrime = false;
            if($k>0 && $activeCamera->srcID == $lastAssignment[0]) {
                $tracer .= "-> Skipped because its assigned to prime screen already.\n";
                $isPrime = true;
            }
            if ($activeCamera->isAssignedAsFillCam && $timeSinceActivated < $activeCamera->fillTimeoutValue) {
                $tracer .= "    Keeping {$keys[$k]} fill view {$activeCamera->srcID}.\n";
                $returnString .= "     ".$keys[$k]."-> ".$activeCamera->srcID." (Kept Fill)\n";
                $k++;
                continue;
            }
            if ($activeCamera->isAssignedAsMotionCam && $timeSinceActivated < $activeCamera->motionTimeoutValue && !$isPrime) {
                $tracer .= "    Keeping {$keys[$k]} motion view {$activeCamera->srcID}.\n";
                $returnString .= "     ".$keys[$k]."-> ".$activeCamera->srcID." (Kept Motion)\n";
                $k++;
                continue;
            }
           
            //Accepted: Now switch to consideredCamera.
            $this->handleCameraSelection($keys[$k], $srcID);
            $newAssignment[] = $srcID;
            $tracer .= "      Fill choice for {$keys[$k]} was {$srcID}\n";
            $returnString .= "     ".$keys[$k]."->".$srcID." (New Fill)\n";
            $tracer .= "    Turning off active camera {$activeCamera->srcID}\n";
            $activeCamera->recordScreenSwitch($keys[$k],true,true); //Turning off current cam
            $tracer .= "    Turning on considered camera {$consideredCamera->srcID}\n";
            $consideredCamera->recordScreenSwitch($keys[$k],true,false);
            $k++;
           
        }
        flog($returnString."\n\n");
        flog($tracer."   END OF LOOP\n");
        //Now send to database
        $this->MotionModel->setControlsFlags($this->flags);
    }

    public function eliminateDuplicateScreenAssignments() {
        $tracer = "eliminateDuplicateScreenAssignments() ";
        $keys = ['prim', 'suba', 'subb', 'subc'];
        $a=[0,0,0,1,1,2];
        $b=[1,2,3,2,3,3];
        $i=0;
        while($i<5) {
            if($this->screens[$keys[$a[$i]]]->srcID == $this->screens[$keys[$b[$i]]]->srcID) {    
                $srcID = $this->getRotationCamera();
                // Check that the new fill camera is not assigned already
                while (in_array($srcID, array_column($this->screens, 'srcID'))) {
                    $srcID = $this->getRotationCamera();
                }
                $tracer .= "      $srcID to replace fill duplicate {$this->screens[$keys[$b[$i]]]->srcID} on screen {$keys[$b[$i]] }\n";
                $this->assignFillScreen($keys[$b[$i]], $srcID);
            }
            $i++;
        }
        flog($tracer);
    }


    public function assignScreen($screenName, $isLocked=false) {
        //Manual switch made as fill
        //  Buffer the srcID currently on screen
        $srcID = $this->flags['liveCams'][$screenName]['srcID'];
        //Update the control array with the new choice
        $this->getControlsFlags();
        $newSrcID = $this->flags['liveCams'][$screenName]['srcID'];
        //Switch current camera off
        $this->liveCams[$srcID]->assignScreenOff();
        //and new camera on
        $this->screens[$screenName] = $this->liveCams[$newSrcID];
        $this->liveCams[$newSrcID]->assignFillScreen($screenName, $isLocked);
    }


    public function shouldKeepFillCamera($srcID, $assigned) :bool {
        //Returns false if camera just assigned as motion
        if(in_array($srcID, $assigned)) {
            return false;
        }
        //Returns false if camera on screen more than 2 minutes
        $now = time();
        $duration = !isset($this->liveCams[$srcID]->screenTS) ? 0 : $now - $this->liveCams[$srcID]->screenTS;
        return $duration < 120;
    }

    public function showScreenCamStats() {
        $primID = $this->flags['liveCams']['prim']['srcID'];
        $subaID = $this->flags['liveCams']['suba']['srcID'];
        $subbID = $this->flags['liveCams']['subb']['srcID'];
        $subcID = $this->flags['liveCams']['subc']['srcID'];

        $primScrnTp = $this->screenTypes[$this->liveCams[$primID]->isAssignedAsFillCam]==true ? 'F' : 'M';
        $subaScrnTp = $this->screenTypes[$this->liveCams[$subaID]->isAssignedAsFillCam]==true ? 'F' : 'M';
        $subbScrnTp = $this->screenTypes[$this->liveCams[$subbID]->isAssignedAsFillCam]==true ? 'F' : 'M';
        $subcScrnTp = $this->screenTypes[$this->liveCams[$subcID]->isAssignedAsFillCam]==true ? 'F' : 'M';

        // $primScrnID = $this->liveCams[$primID]->currentScreenID;
        // $subaScrnID = $this->liveCams[$subaID]->currentScreenID;
        // $subbScrnID = $this->liveCams[$subbID]->currentScreenID;
        // $subcScrnID = $this->liveCams[$subcID]->currentScreenID;

        $primSecOnScrn = $this->liveCams[$primID]->screenAge;
        $subaSecOnScrn = $this->liveCams[$subaID]->screenAge;
        $subbSecOnScrn = $this->liveCams[$subbID]->screenAge;
        $subcSecOnScrn = $this->liveCams[$subcID]->screenAge;

        // $primSecAsPrim = $this->liveCams[$primID]->primeAge;
        // $subaSecAsPrim = $this->liveCams[$subaID]->primeAge;
        // $subbSecAsPrim = $this->liveCams[$subbID]->primeAge;
        // $subcSecAsPrim = $this->liveCams[$subcID]->primeAge;

        // $primPrimToday = $this->liveCams[$primID]->primeCume;
        // $subaPrimToday = $this->liveCams[$subaID]->primeCume;
        // $subbPrimToday = $this->liveCams[$subbID]->primeCume;
        // $subcPrimToday = $this->liveCams[$subcID]->primeCume;

        $primScrnToday = $this->liveCams[$primID]->screenCume;
        $subaScrnToday = $this->liveCams[$subaID]->screenCume;
        $subbScrnToday = $this->liveCams[$subbID]->screenCume;
        $subcScrnToday = $this->liveCams[$subcID]->screenCume;

        $str = "\n===========================================  ACTIVE SCREENS  =========================================\n";
        $str .= sprintf(
            "%-10s   %-18s  %-10s %-8s  %-10s\n",
            "ScreenName",
            "Camera",
            "Type",
            "Seconds",
            "Screen"
        );
        $str .= sprintf(
            "%-10s   %-18s  %-4s    %-5s   %-10s\n",
            "",  
            "",
            "",
            "On Screen",
            "Today"
        );
        // $str .= sprintf(
        //     "  Prime      %-18s  %-7s    %5d      %5d     %5d        %5d \n",
        //     $primID,
        //     $primScrnTp,
        //     $primSecOnScrn,
        //     $primSecAsPrim,
        //     $primPrimToday,
        //     $primScrnToday
        // );
        $str .= sprintf(
            "  Sub-A      %-18s  %-7s    %5d           %5d \n",
            $subaID,
            $subaScrnTp,
            $subaSecOnScrn,
            $subaScrnToday
        );
        $str .= sprintf(
            "  Sub-B      %-18s  %-7s    %5d              %5d \n",
            $subbID,
            $subbScrnTp,
            $subbSecOnScrn,
            $subbScrnToday
        );
        $str .= sprintf(
            "  Sub-C      %-18s  %-7s    %5d            %5d \n",
            $subcID,
            $subcScrnTp,
            $subcSecOnScrn,
            $subcScrnToday
        );
        $str .= "-------------------------------------------------------------------------------------------------------\n";

              

        flog($str);
    }

    public function handleCameraSelection($screen, $srcID) {
        if(!isset($this->flags['liveCams'][$screen])) {
            error_log($screen." is not a valid screen name");
            return;
        }
        //Screen must be 'prim', 'suba', 'subb' or 'subc'
        echo "     handleCameraSelection($screen) $srcID\n";
        if(!isset($this->cameras[$srcID])) {
            error_log($screen." is bad srcID");
            return;
        }
        $newSelection = $this->cameras[$srcID];
        $this->flags['liveCams'][$screen]['srcID']   = $newSelection['srcID'];
        $this->flags['liveCams'][$screen]['srcType'] = $newSelection['srcType'];
        $this->flags['liveCams'][$screen]['srcUrl']  = $newSelection['srcUrl'];
        $this->flags['liveCams'][$screen]['srcName'] = $newSelection['srcName'];
        //$this->MotionModel->setControlsFlags($this->flags);
    }

    public function getRotationCamera() {
        $count = count($this->rotationArray);
        //flog(        "getRotationCamera() count: $count,");
        //flog(" pointer: ".$this->rotationPointer."\n");
        $srcID = $this->rotationArray[$this->rotationPointer];
        $this->rotationPointer++;
        if($this->rotationPointer >= $count) {
            $this->rotationPointer = 0;
        }
        return $srcID;
    }


    public function assignMotionScreen($screenKey, $srcID) {
        if(!isset($this->flags['liveCams'][$screenKey])) {
            error_log($screenKey." is not a valid screen name");
            return;
        }
        //Screen must be 'prim', 'suba', 'subb' or 'subc'
        echo "     assignMotionScreen($screenKey) $srcID\n";
        if(!isset($this->cameras[$srcID])) {
            error_log($srcID." is bad srcID");
            return;
        }
        $newSelection = $this->cameras[$srcID];
        $obj          = $this->liveCams[$srcID];
        $this->flags['liveCams'][$screenKey]['srcID']   = $newSelection['srcID'];
        $this->flags['liveCams'][$screenKey]['srcType'] = $newSelection['srcType'];
        $this->flags['liveCams'][$screenKey]['srcUrl']  = $newSelection['srcUrl'];
        $this->flags['liveCams'][$screenKey]['srcName'] = $newSelection['srcName'];
        //Turn off present screen and assign new one
        $this->screens[$screenKey]->assignScreenOff();
        // 1/24/24 reversed order of next 2 lines to signMotionScreen then screens = $obj
        $obj->assignMotionScreen($screenKey);
        $this->screens[$screenKey] = $obj; 
        
    }

    public function assignFillScreen($screenKey, $srcID) {
        if(!isset($this->flags['liveCams'][$screenKey])) {
            error_log($screenKey." is not a valid screen name");
            return;
        }
        //Screen must be 'prim', 'suba', 'subb' or 'subc'
        echo "     assignFillScreen($screenKey) $srcID\n";
        if(!isset($this->cameras[$srcID])) {
            error_log($srcID." is bad srcID");
            return;
        }
        $newSelection = $this->cameras[$srcID];
        $obj          = $this->liveCams[$srcID];
        $this->flags['liveCams'][$screenKey]['srcID']   = $newSelection['srcID'];
        $this->flags['liveCams'][$screenKey]['srcType'] = $newSelection['srcType'];
        $this->flags['liveCams'][$screenKey]['srcUrl']  = $newSelection['srcUrl'];
        $this->flags['liveCams'][$screenKey]['srcName'] = $newSelection['srcName'];
        //Turn off present screen and assign new one
        $this->screens[$screenKey]->assignScreenOff();
        $this->screens[$screenKey] = $obj;
        $obj->assignFillScreen($screenKey, false);
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

    /* /Depricated
    public function screenSwitchIsRecent($screenName) :bool {
        $srcID = $this->flags['liveCams'][$screenName]['srcID'];
        $now = time();
        $age = $now-$this->liveCams[$srcID]->screenTS;
        //flog("screenSwitchIsRecent() $screenName view $srcID has $age sec age\n");
        if($age<50) {
            return true;
        }
        return false;
    }
    */

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


