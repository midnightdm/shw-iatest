<?php

use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\FieldValue;


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * 
 *                                                     *
 *   NOTE: The .json file below is project specific    *
 *   and private.  Admin will need to secure one       *
 *   and put their own reference here. Be sure to      *
 *   include it in your .gitignore file to prevent     *
 *   public exposure.                                  *
 *                                                     *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * */


/**
 * This is a custom library to interact with the firebase firestore cloud db
 */
class Firestore {
  protected $db;
  protected $name;
  protected $credentials;
  public $projectID;
  
  

  public function __construct($collection) {
    global $config;
    $this->projectID = $config['cloud_projectID'];
    $this->name = $collection['name'];
    
    //Load credentials
    $path = 'C:\Apache24\protected\serviceAccountKey.json';
    $strJsonFileContents = file_get_contents($path);
    $this->credentials = json_decode($strJsonFileContents, true);
    
    //flog("Firestore::__construct() -> google key_id:".$this->credentials['private_key_id']." collection: ". $this->name ."\n\n"); 
    $this->db = new FirestoreClient([
       'keyFile' => $this->credentials,
       'projectId'=> $this->projectID 
    ]);    
  }

  public function getDocument($name) {
    $snapshot = $this->db->collection($this->name)->document($name)->snapshot();
    if(!$snapshot->exists()) {
        return false;
    }
    return $snapshot->data();
  }

  public function serverTimestamp() {
    return FieldValue::serverTimestamp();
  }
   
  
}