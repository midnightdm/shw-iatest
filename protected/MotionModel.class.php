<?php
use Google\Cloud\Firestore\FieldValue;
/* * * * * *
 * MotionModel class
 * protected/MotionModel.class.php
 *
 */
class MotionModel extends Firestore {
  public $motionRef;
  public $camerasRef;
  public $controlsFlagsRef;
  public $cameras;
  public $motionCollection;
  public $hasMotionArray; 

  public function __construct() {
      parent::__construct(['name' => 'Motion']);
      $this->motionRef  = $this->db->collection('Motion');
      $this->camerasRef = $this->db->collection('Cameras');  
      $this->controlsFlagsRef = $this->db->collection('Controls')->document('Flags');    
  }

  public function getMotionDocument($macAddress) {
      return $this->getDocument($macAddress);
  }

  public function getMotionCollection() {
     $this->motionCollection = [];
     $documents = $this->motionRef->documents();
     foreach($documents as $document) {
        $this->motionCollection[] = $document->data();
     }
     return $this->motionCollection;
  }

  public function getHasMotionCams() {
    $this->hasMotionArray = [];
     $documents = $this->motionRef->documents();
     foreach($documents as $document) {
        if($document['hasMotion']==true) {
            $this->hasMotionArray[] = $document->data();
        }
     }
     return $this->hasMotionArray;
  }

  public function getCamerasCollection() {
     $this->cameras = [];
     $documents = $this->camerasRef->documents();
     foreach($documents as $document) {
        $data = $document->data();
        $this->cameras[$data['srcID']] = $data;
     }
     //exit(var_dump($this->cameras));
     return $this->cameras;
  }

  public function getFillCamerasList() {
    $list = [];
    $documents = $this->camerasRef->documents();
     foreach($documents as $document) {
        $data = $document->data();
        if($data['useAsFill']==true) {
            $list[] = $data['srcID'];
        }
     }
     return $list;
  }

  public function updateMotion($mac, $dat) {
    //Convert increment bool
    if(array_key_exists('hasRemoteStopControl', $dat)) {
        $hasRemoteStopControl = $dat['hasRemoteStopControl'];
    } else {
        $hasRemoteStopControl = false;
    }
    if(!$hasRemoteStopControl && $dat['newEventCount']) {
        $dat['newEventCount'] = FieldValue::increment(1);
    } else {
        $dat['newEventCount'] = 0;
    }
    //flog("The mac is $mac, the dat is ".var_dump($dat)."\n");
    $ref = $this->db->collection("Motion")->document($mac);
    $ref->set($dat, ["merge"=>true]);  
  }

  public function saveTimestampsToCamera(array $cameraArray) {
    $ref = $this->db->collection("Cameras")->document($cameraArray['srcID']);
    $ref->set($cameraArray, ["merge"=>true]);
  }

  public function areThereCameraUpdates() {
    $snapshot = $this->controlsFlagsRef->snapshot();
    if($snapshot->exists()) {
        $data = $snapshot->data();
        return $data['areCameraUpdates']; 
    }
    return null;
  }

  public function areThereControlUpdates() {
    $snapshot = $this->controlsFlagsRef->snapshot();
    if($snapshot->exists()) {
        $data = $snapshot->data();
        return $data['areControlUpdates']; 
    }
    return [false, ''];
  }

  public function getControlsFlags() {
    $snapshot = $this->controlsFlagsRef->snapshot();
    if($snapshot->exists()) {
        return $snapshot->data();
    }
    return false;
  }

  public function getControlsFlagsLiveCams() {
    $controlsFlags = $this->getControlsFlags();
    if(!$controlsFlags) {
        error_log("Can't get liveCams from Controls/Flags");
        return;
    }
    return $controlsFlags['liveCams'];
  }

  public function testExit() {
    $controlsFlags = $this->getControlsFlags();
    if(!$controlsFlags) {
        error_log("Can't reach Controls/Flags to test for exit.\n");
        return false;
    }
    if($controlsFlags["exit"]==true) {
        return true;
    }
    return false;
  }

  public function resetExit() {
    $this->controlsFlagsRef->set(["exit"=> true],["merge"=>true]);
  }

  public function setControlsFlags($document) {
    $this->controlsFlagsRef->set($document, ["merge=>true"]);
  }

  public function resetAreCameraUpdates() {
    $this->controlsFlagsRef->set( ['areCameraUpdates' => false],['merge'=>true]);
  }

  public function resetAreControlUpdates() {
    $this->controlsFlagsRef->set( ['areControlUpdates' => [false, '']],['merge'=>true]);
  }

}