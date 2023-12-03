<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *  
 *  protected/LiveCam.class.php
 * 
 *  This class is for single Live Cam objects.  Properties come from
 *  the Motion document of the database as an array. Once converted into
 *  an object new properties are added to track cumulative screen time and 
 *  active camera status.
 *  
 */
final class LiveCam {
  //Main properties
  public int $eventTS;         //Has liveCams conrtols for screens
  public bool $hasMotion;       //Source data for all cameras
  public bool $hasRemoteStopControl; 
  public bool $isViewEnabled;
  public string $macAddress;
  public int $newEventCount;
  public string $srcID;
  public string $srcType;
  public string $srcUrl;
  public bool $isAssignedAsFillCam = false;
  public bool $isAssignedAsMotionCam = false;
  public string $viewAssignment = "off";
  public bool $useAsFill;
  public string $when;
  public int $fillTimeoutValue;
  public int $motionTimeoutValue;
  
  //Properties from 'timestamps' subarray
  public int $offTS      = 0; 
  //public int $primeTS    = 0;
  public int $screenTS   = 0;
  public int $eventAge   = 0;
  //public int $primeAge   = 0;
  public int $screenAge  = 0;
  //public int $primeCume  = 0;
  public int $screenCume = 0;
  public int $currentDay = 0;
  public string $currentScreenView = 'off';
  public int $screenType;

  //Callback
  public TrainDaemon $trainDaemon; 

  private static $timing_properties = array('offTS', 'screenTS', 'eventAge', 'screenAge', 'screenCume','currentDay', 'currentScreenView', 'viewAssignment');
  
  public function __construct(array $array, TrainDaemon $trainDaemon) {
    $this->map($array);
    $this->trainDaemon = $trainDaemon;
    //Set starting time values to midnight
    $this->offTS     = $this->trainDaemon->todayMidnight;
    //$this->primeTS   = $this->trainDaemon->todayMidnight;
    $this->screenTS  = $this->trainDaemon->todayMidnight;  
    $this->generateMotionTimeoutValue();
    $this->generateFillTimeoutValue();  
  }

/**
 * Maps data array to object properties.
 */
public function map(array $array): void {
    foreach($array as $key => $value) {
        if(is_array($value) && $key=="timestamps") {
            foreach($value as $k=>$v) {
                $this->$k = $v;
            }
        }
        $this->$key = $value;
    }
    if(!isset($array['timestamps']['screenType'])) {
        $this->screenType = 0;
    }
  }




//Depricated function
  public function recordScreenSwitch(string $screenName, bool $isFill=false, bool $isOff=false) {
    $timestamp = time();
    $today = getdate(); 
    $dayOfWeek = $today['wday'];
    //Reset cume values on new day
    if($dayOfWeek != $this->currentDay) {
        $this->primeCume = 0;
        $this->screenCume = 0;
        $this->currentDay = $dayOfWeek;
    }
    //Tally counters on screen off
    if($isOff) {
        $this->isAssignedAsFillCam = false;
        $this->isAssignedAsMotionCam = false;
        $this->offTS = $timestamp;
        $this->currentScreenView = 'off';
        switch($screenName) {
            case 'prim':
                $this->primeAge = $timestamp-$this->primeTS;
                $this->primeCume = $this->primeAge+$this->primeCume;
            case 'suba': case 'subb': case 'subc':
                $this->screenAge = $timestamp-$this->screenTS;
                $this->screenCume = $this->screenAge+$this->screenCume;
                $this->saveTimestamps();
                break;
            default: log_error($screenName." is invalid screen name");
        } 
        return;
    }
    //Record timestamp of screen on 
    switch($screenName) {
        case 'prim':
            $this->primeTS = $timestamp;
        case 'suba':  case 'subb':  case 'subc':
            $this->screenTS = $timestamp;
            $this->currentScreenView = $screenName;
            break;
    }
    //Assign screen timeouts and Fill/Motion status
    if($isFill) {
        $this->generateFillTimeoutValue();
        $this->isAssignedAsFillCam = true;
        $this->isAssignedAsMotionCam = false;
    } else {
        $this->generateMotionTimeoutValue();
        $this->isAssignedAsMotionCam = true;
        $this->isAssignedAsFillCam = false;
    }
  }

  public function motionTimeoutValueExpired() :bool {
    $timeSinceActivated = time() - $this->screenTS;
    return $timeSinceActivated > $this->motionTimeoutValue;
  }

  public function fillTimeoutValueExpired() :array {
    $timeSinceActivated = time() - $this->screenTS;
    $ret = [
        ($timeSinceActivated > $this->fillTimeoutValue),
        $timeSinceActivated,
        $this->fillTimeoutValue
    ];
    return $ret;
  }

  public function assignMotionScreen($screenKey) {
    $this->viewAssignment = "motion";
    $this->currentScreenView = $screenKey;
    $this->screenTS = time();
    $this->generateMotionTimeoutValue();
  }

  public function assignFillScreen($screenKey) {
    $this->viewAssignment = "fill";
    $this->currentScreenView = $screenKey;
    $this->screenTS = time();
    $this->generateFillTimeoutValue();
  }

  public function assignScreenOff() {
    $timestamp = time();
    $this->currentScreenView = "off";
    $this->viewAssignment = "off";
    $this->offTS = $timestamp;
    $this->screenAge = $timestamp-$this->screenTS;
    $this->screenCume = $this->screenAge+$this->screenCume;
    $this->saveTimestamps();
  }

  public function calculateScreenCounters() {
    $timestamp = time();
    $today = getdate(); 
    $dayOfWeek = $today['wday'];
    //Reset cume values on new day
    if($dayOfWeek != $this->currentDay) {
        $this->screenCume = 0;
        $this->currentDay = $dayOfWeek;
    }    
    $this->screenAge = $timestamp-$this->screenTS;
    $this->screenCume = $this->screenAge+$this->screenCume;
    $this->saveTimestamps();
            
    
  }

  public function saveTimestamps() {
    //echo "     saveTimestamps() for ".$this->srcID." \n";
    $cameraTimestamps = [];
    foreach(self::$timing_properties as $key) {
        $cameraTimestamps[] = [$key=>$this->$key];
    }
    $data = ['srcID'=>$this->srcID, 'timestamps'=>$cameraTimestamps];
    $this->trainDaemon->MotionModel->saveTimestampsToCamera($data);
  }

  public function generateFillTimeoutValue() {
    $this->fillTimeoutValue = rand(150, 360);
  }

  public function generateMotionTimeoutValue() {
    $this->motionTimeoutValue = rand(150, 360);
  }
}