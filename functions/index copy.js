/**
 * Import function triggers from their respective submodules:
 *
 * const {onCall} = require("firebase-functions/v2/https");
 * const {onDocumentWritten} = require("firebase-functions/v2/firestore");
 *
 * See a full list of supported triggers at https://firebase.google.com/docs/functions
 */

const {log}    = require("firebase-functions/logger");
const {onRequest} = require("firebase-functions/v2/https");
const { setGlobalOptions } = require('firebase-functions/v2');

// The Firebase Admin SDK to access Firestore.
//const admin = require('firebase-admin');
//const serviceAccount = require('./serviceAcountKey.json');
const {initializeApp} = require("firebase-admin/app");
const {getFirestore, FieldValue} = require("firebase-admin/firestore");
// initializeApp({
//     credential: admin.credential.cert(serviceAccount)
// })
initializeApp();
setGlobalOptions({maxInstances: 10});




const db = getFirestore();
//Load express framework for API calls
const express = require('express');
const app = express();
//const port = 3030;

//app.set("port", port);
//app.listen(8080, '0.0.0.0');


 // Imports the Google Cloud client library
 const {Storage} = require('@google-cloud/storage');
 //const { Buffer } = require("buffer");
 const {parseString} = require('xml2js');
//const { aggregateQuerySnapshotEqual } = require("firebase/firestore");

 // Creates a client
 const storage = new Storage();

//Returns json to backend
app.get('/json', async (req, res) => {
    updateStatus().then((json)=>{
        res.status(200).send(JSON.stringify(json))
    })
  });


//Receive post and save body as text file in storage bucket
  app.post("/", (req, res) => {
    const event = req.body;
    if(parseString(event, processPost)) {
        res.status(201).send(); 
    } else {
        res.status(500).end()
    }
  });



// Create and deploy your first functions
exports.events = onRequest(app);
//exports.events=functions.https.onRequest(app);


//External functions
function processPost(err, result) {
    if (err) {
        log(err);
        return false;
    }
    if(updateMotionDocumentTimestamp(result['EventNotificationAlert'])) {
        return true
    }
    //saveEventToFirestoreAsDocument(result['EventNotificationAlert']); 
    //saveEventToBucketAsText(result['EventNotificationAlert']);       
    return false
}


function saveEventToFirestoreAsDocument(eventObject) {
    db.collection("Events").add(eventObject);
}

function saveEventToBucketAsText(eventObject) {
    // Define the name of the bucket where you want to store the text file
      const bucketName = 'sh-railcam-tour';
      let now = Date.now();
      const fileName = `event_${now}}.xml`;
      storage.bucket(bucketName).file(fileName).save(eventObject);
}

async function updateMotionDocumentTimestamp(eventObject) {
    const macAddress = String(eventObject["macAddress"]);
    //log("Trying macAddress path "+macAddress);
    const docRef = db.collection('Motion').doc(macAddress);
    const doc = await docRef.get();
    if(!doc.exists) {
        return false 
    }
    const data = doc.data();
    const now = new Date();
    const when = now.toLocaleString('en-US', { timeZone: 'America/Chicago'});
    const ts = now.getTime();
    
    //toggle hasMotion on counter threshold
    let hasMotion = (data.newEventCount > 4)
    //Increment counter if update age below 10 sec threshold
    let age = (now-data.eventTS);
    log("update age", age);
    //db.FieldValue.increment(1)
    if(age < 10000) {
        docRef.update({
            hasMotion: hasMotion,
            newEventCount: FieldValue.increment(1),
            eventTS: ts,
            when: when
        });
    } else {
        docRef.update({
            hasMotion: hasMotion,
            newEventCount: 0,
            eventTS: ts,
            when: when
        });
    }
    return true
}


async function updateStatus() {
    //Declare variables
    let id, obj, now=Date.now(), loopCount=0, motionLessCount=0, viewEnabledCount=0, age;
    const motionCollection = [];
    const camerasCollection = [];
    const cameras = {};
    const ages = [];
    const enableds = [];

    //Push camera colllection in an array & object
    const camerasSnapshot = await db.collection('Cameras').get();
    camerasSnapshot.forEach( async (doc)=>{
        const data = doc.data();
        camerasCollection.push(data);
        cameras[data.srcID] = data
    });

    //Put current motion events in an array
    const motionSnapshot = await db.collection('Motion').get();
    motionSnapshot.forEach( async (doc) => {
        const data = doc.data();
        motionCollection.push(data);
    });

    //Loop through motion array data
    for(loopCount=0; loopCount< motionCollection.length; loopCount++) {
        id  = motionCollection[loopCount].srcID
        //Sync shared data from Cameras
        enableds.push({[id]:cameras[id].isViewEnabled})
        motionCollection[loopCount].isViewEnabled = cameras[id].isViewEnabled;
        motionCollection[loopCount].srcUrl        = cameras[id].srcUrl;
        motionCollection[loopCount].srcType       = cameras[id].srcType;
        //Increment count of enabled cameras
        if(motionCollection[loopCount].isViewEnabled) {
            viewEnabledCount++;
        }
        //Test for > 30 sec age of last motion detect
        age = now-motionCollection[loopCount].eventTS
        if(age > 30000) {
            motionCollection[loopCount].hasMotion = false;
            motionLessCount++;
        }
        ages.push(age);
        //Write changes back to document
        await db.collection('Motion').doc(motionCollection[loopCount].macAddress).update(motionCollection[loopCount])
    }
    //Return counts
    return {viewEnabledCount, motionLessCount,'length': motionCollection.length,  'ages': ages }
}

