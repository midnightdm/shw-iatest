// Import The Cloud Functions for Firebase SDK to create Cloud Functions and set up triggers.
const functions = require('firebase-functions/v1');

// The Firebase Admin SDK to access Firestore.
const admin = require("firebase-admin");


admin.initializeApp();
const db = admin.firestore();
const xml2js = require('xml2js');
const database = {wasUpdated: false, error: "none", macAddress: "null"};
//const cors = require('cors')({origin: true});

// Imports the Google Cloud client library
const {Storage} = require('@google-cloud/storage');
// Creates a client
const storage = new Storage()

//Export the cloud function
exports.handleMotionEvent = functions.https.onRequest(async (req, res) => {
    // Grab the text parameter.
    const event = req.body;
    const parser = new xml2js.Parser();
    parser.parseStringPromise(event)
        .then( (obj) => processPost(obj))
        .then(()=>{
            if(database.wasUpdated) { 
                // Send back a message that we've successfully written the message
                res.status(201).send("macAddress was "+database.macAddress);
            } else {
                //Send back failure report
                res.status(500).send("error reported "+database.error);
            }
        })
  });

exports.updateMotionStatus = functions.https.onRequest(async (req, res)=> {
    updateStatus().then((json)=>{
        res.status(200).send(JSON.stringify(json));
    })
})


exports.handleMotionTest = functions.https.onRequest(async (req, res)=>{
    //Grab the post
    const event = req.body;
    const parser = new xml2js.Parser();
    parser.parseStringPromise(event)
        .then( (obj) => processTestPost(obj))
        .then(()=> { res.status(201).end() });
});

exports.getMotionCams = functions.https.onRequest(async (req, res)=>{
    getMotion().then((json)=>{
        res.set('Access-Control-Allow-Origin', '*');
        if (req.method === 'OPTIONS') {
            // Send response to OPTIONS requests
            res.set('Access-Control-Allow-Methods', 'GET');
            res.set('Access-Control-Allow-Headers', 'Content-Type');
            res.set('Access-Control-Max-Age', '3600');
            res.status(204).send(JSON.stringify(json));
        } else {
            res.status(200).send(JSON.stringify(json));
        } 
    })
});


  //Helper functions used by the code above
async function processPost(inputObj) {
    const obj = inputObj['EventNotificationAlert'];
    const macAddress = String(obj["macAddress"]);
    //functions.logger.log("processPost received macAddress", macAddress);
    // return macAddress
    if(await updateMotionDocumentTimestamp(macAddress)) {
        database.macAddress = macAddress
        database.wasUpdated = true;
    } else {
        database.macAddress = macAddress
        database.wasUpdated = false;
    }

}

async function processTestPost(inputObj) {
    functions.logger.log("Running processTestPost");
    //const obj = inputObj['EventNotificationAlert'];
    saveEventToBucketAsText(JSON.stringify(inputObj));  
}

 

async function updateMotionDocumentTimestamp(macAddress) {
    functions.logger.log("Trying to write to db "+macAddress);
    const docRef = db.collection('Motion').doc(macAddress);
    const doc = await docRef.get();
    functions.logger.log(macAddress+" doc exists? "+doc.exists);
    if(!doc.exists) {
        functions.logger.log("Doc doesn't exist.");
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
    functions.logger.log("update age", age);
    if(age < 10000) {
        docRef.update({
            hasMotion: hasMotion,
            newEventCount: admin.firestore.FieldValue.increment(1),
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
    let id, obj, now=Date.now(), loopCount=0, motionLessCount=0, viewEnabledCount=0, useAsFillCount=0, age;
    const motionCollection = [];
    const camerasCollection = [];
    const cameras = {};
    const ages = [];
    const enableds = [];
    const fills = [];

    //Push camera collection in an array & object
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
        fills.push({[id]:cameras[id].useAsFill})
        motionCollection[loopCount].isViewEnabled = cameras[id].isViewEnabled;
        motionCollection[loopCount].useAsFill = cameras[id].useAsFill;
        motionCollection[loopCount].srcUrl        = cameras[id].srcUrl;
        motionCollection[loopCount].srcType       = cameras[id].srcType;
        //Increment count of enabled cameras
        if(motionCollection[loopCount].isViewEnabled) {
            viewEnabledCount++;
        }
        if(motionCollection[loopCount].useAsFill) {
            useAsFillCount++;
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
    return {viewEnabledCount, useAsFillCount, motionLessCount,'length': motionCollection.length,  'ages': ages }
}

async function getMotion() {
    const motionCollection = []
    //Put current motion events in an array
    const motionSnapshot = await db.collection('Motion').get();
    motionSnapshot.forEach( async (doc) => {
        const data = doc.data();
        //Gather only view enabled cameras
        if(data.isViewEnabled == true) {
            motionCollection.push(data);
        }
    });
    return motionCollection
}

function saveEventToBucketAsText(eventObject) {
    // Define the name of the bucket where you want to store the text file
      const bucketName = 'sh-railcam-tour';
      let now = new Date;
      when = now.toISOString();
      //let when = now.toLocaleString('en-US', { timeZone: 'America/Chicago'});
      const fileName = `event_${when}.html`;
      storage.bucket(bucketName).file(fileName).save(eventObject);
}