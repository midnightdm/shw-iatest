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

  public $screenNames; 
  
  
  public function __construct($config_ref) {
    $this->config = $config_ref;   
  }





  public function setup() {
    $now    = time();
    
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
    $this->mapLiveCams();
    
    $this->lastCleanUp         = $now-50; //Used to increment cleanup routine
    $this->lastCameraSwitch    = $now-50; //Prevents rapid camera switching if 2 vessels near
    $this->lastJsonSave        = $now-10; //Used to increment liveScan.json save
    $this->lastPassagesSave    = $now-50; //Increments savePassages routine
    
    //Set values below in $config array in config.php
    //$this->liveScanTimeout     = intval($this->config['liveScanTimeout']); 
    
  }

  public function start() {
    flog( " Starting shw-railcam-server\n\n");  
    flog( "\t\t >>>     Type CTRL+C at any time to quit.    <<<\r\n\n\n");
    
    $this->setup();
    $this->run = true;
    //$this->reloadSavedScans();
    //sleep(3);
    $this->run();
  }

  protected function run() {
    //Runtime initialization
    
    //A run once message for Brian at start up to enable companion app
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
        $this->testForCameraUpdates();
        $this->getControlsFlags(); 
        if($this->processMotionUpdates()) {
            $this->assignCameras();
        };
        sleep(20);

      //*                                                                      *
      //*                          End of main loop                            *
      //*                                                                      *
      //************************************************************************ 
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

  public function assignCameras() {
    $keys = ['prim', 'suba', 'subb', 'subc']; $k=0;
    $num = count($this->inCurrent);
    $fill = 4 - $num;
    $returnString = "\n============== SCREEN ASSIGNMENTS =============\n";
    //1st loop assigns cameras with motion
    for($n=0; $n<$num; $n++) { 
        if($k==4) { return; } 
        if(count($this->inCurrent)>$n+1) {
            $result = $this->descriminateCamera($this->inCurrent[$n], $this->inCurrent[$n+1], $keys[$k]);
            $this->handleCameraSelection($keys[$k], $result[0]);
            //Provide screenID of both winner and loser to the switch function.
            $this->liveCams[$result[0]]->recordScreenSwitch($this->screenNames[$keys[$k]]);
            $this->liveCams[$result[1]]->recordScreenSwitch(0);
            $returnString .= "     ".$keys[$k]."->".$result[0]." (Motion)\n";
        } else {
            $this->handleCameraSelection($keys[$k], $this->inCurrent[$n]);
            $this->liveCams[$this->inCurrent[$n]]->recordScreenSwitch($this->screenNames[$keys[$k]]);
            $this->liveCams[$this->inCurrent[$n]]->saveTimestamps();
            $returnString .= "     ".$keys[$k]."->".$this->inCurrent[$n]." (Motion)\n";
        }
        $k++;
    }
    //2nd loop assigns fill cameras
    for($f=0; $f<$fill; $f++) {
        if($k==4) { return; }
        //test to keep or kill current fill camera
        $currentFillSrcID = $this->flags['liveCams'][$keys[$k]]['srcID'];
        $shouldKeep = $this->shouldKeepFillCamera($currentFillSrcID);
        if($shouldKeep) {
            $returnString .= "     ".$keys[$k]."->".$currentFillSrcID." (Kept Fill)\n";
            $k++;
            continue;
        }
        //Get new fill camera choice from rotation
        $srcID = $this->getRotationCamera();
        //skip choice if current
        if(in_array($srcID, $this->inCurrent)) {
            continue;
        }
        $this->handleCameraSelection($keys[$k], $srcID);
        $this->liveCams[$srcID]->recordScreenSwitch($this->screenNames[$keys[$k]]);
        $this->liveCams[$srcID]->saveTimestamps();
        $returnString .= "     ".$keys[$k]."->".$srcID." (New Fill)\n";
        $k++;
    }
    flog($returnString."\n");
    //Now send to database
    $this->MotionModel->setControlsFlags($this->flags);
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

  public function getControlsFlags() {
      $this->flags = $this->MotionModel->getControlsFlags();
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
      
}


