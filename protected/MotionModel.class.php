<?php
use Google\Cloud\Firestore\FieldValue;
/* * * * * *
 * MotionModel class
 * src/MotionModel.class.php
 *
 */
class MotionModel extends Firestore {
  public $motionRef;
  public $camerasRef;
  public $cameras;
  public $motionCollection; 

  public function __construct() {
      parent::__construct(['name' => 'Motion']);
      $this->motionRef  = $this->db->collection('Motion');
      $this->camerasRef = $this->db->collection('Cameras');     
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


}