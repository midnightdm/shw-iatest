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
  public bool $useAsFill;
  public string $when;
  
  //Properties from 'timestamps' subarray
  public int $offTS      = 0; 
  public int $primeTS    = 0;
  public int $screenTS   = 0;
  public int $eventAge   = 0;
  public int $primeAge   = 0;
  public int $screenAge  = 0;
  public int $primeCume  = 0;
  public int $screenCume = 0;
  public int $currentDay = 0;
  public int $currentScreenID = 0;
  public int $screenType; 

  //Callback
  public TrainDaemon $trainDaemon; 

  private static $timing_properties = array('offTS', 'primeTS', 'screenTS', 'eventAge', 'primeAge', 'screenAge', 'primeCume', 'screenCume','currentDay', 'currentScreenID', 'screenType');
  
  public function __construct(array $array, TrainDaemon $trainDaemon) {
    $this->map($array);
    $this->trainDaemon = $trainDaemon;
    //Set starting time values to midnight
    $this->offTS     = $this->trainDaemon->todayMidnight;
    $this->primeTS   = $this->trainDaemon->todayMidnight;
    $this->screenTS  = $this->trainDaemon->todayMidnight;    
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



  /**
   * Record switch to new screen. screenID values are
   *      0=off           4=prime
   *      3=suba, 2=subb, 1=subc
   */
  public function recordScreenSwitch(int $screenID, int $screenType) {
    $this->currentScreenID = $screenID;
    $this->screenType = $screenType;
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
    if($screenID==0) {
        $this->offTS = $timestamp;
        switch($this->currentScreenID) {
            case 4:
                $this->primeAge = $timestamp-$this->primeTS;
                $this->primeCume = $this->primeAge+$this->primeCume;
            case 3: case 2: case 1:
                $this->screenAge = $timestamp-$this->screenTS;
                $this->screenCume = $this->screenAge+$this->screenCume;
                $this->saveTimestamps();
                break;
        }
    }
    //Record timestamp of screen on 
    switch($screenID) {
        case 4:
            $this->primeTS = $timestamp;
        case 3:  case 2:  case 1:
            $this->screenTS = $timestamp;
            $this->currentScreenID = $screenID;
            break;
    } 
  }

  public function calculateScreenCounters() {
    //echo "calculateScreenCounters() ".$this->srcID." , screenID ".$this->currentScreenID."\n";
    $timestamp = time();
    $today = getdate(); 
    $dayOfWeek = $today['wday'];
    //Reset cume values on new day
    if($dayOfWeek != $this->currentDay) {
        $this->primeCume = 0;
        $this->screenCume = 0;
        $this->currentDay = $dayOfWeek;
    }    
    switch($this->currentScreenID) {
        case 4:
            $this->primeAge = $timestamp-$this->primeTS;
            $this->primeCume = $this->primeAge+$this->primeCume;
        case 3: case 2: case 1:
            $this->screenAge = $timestamp-$this->screenTS;
            $this->screenCume = $this->screenAge+$this->screenCume;
            $this->saveTimestamps();
            break;
    }
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
}