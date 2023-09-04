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

  //Callback
  public TrainDaemon $trainDaemon; 

  private static $timing_properties = array('offTS', 'primeTS', 'screenTS', 'eventAge', 'primeAge', 'screenAge', 'primeCume', 'screenCume','currentDay', 'currentScreenID');
  
  public function __construct(array $array, TrainDaemon $trainDaemon) {
    $this->map($array);
    $this->trainDaemon = $trainDaemon;    
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
  }



  /**
   * Record switch to new screen. screenID values are
   *      0=off           4=prime
   *      3=suba, 2=subb, 1=subc
   */
  public function recordScreenSwitch(int $screenID) {
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
    $cameraTimestamps = [];
    foreach(self::$timing_properties as $key) {
        $cameraTimestamps[] = [$key=>$this->$key];
    }
    $data = ['srcID'=>$this->srcID, 'timestamps'=>$cameraTimestamps];
    $this->trainDaemon->MotionModel->saveTimestampsToCamera($data);
  }
}