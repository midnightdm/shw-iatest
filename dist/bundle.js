/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/DataModel.js":
/*!**************************!*\
  !*** ./src/DataModel.js ***!
  \**************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   DataModel: () => (/* binding */ DataModel)
/* harmony export */ });
const DataModel = {
  cameraStatus: {
    showVideo: null,
    showVideoOn: null,
    webcamNum: null,
    webcamZoom: 0,
    videoIsFull: false,
    videoIsPassingCloseup: false,
    zoomArray: ["off", "left", "center", "right"],
    zoomMode: 0
  },
  trackerStatus: {
    popupOn: false,
    enabled: null,
    followingId: null,
    obj: null
  },
  promoSources: [],
  promoIsOn: false,
  labelIndex: 0,
  lab: "_ABCDEFGHIJKLMNOPQRSTUVWXYZ*#@&~1234567890abcdefghijklmnopqrstuvwxyz",
  red: "#ff0000",
  region: null,
  title: null,
  focusPosition: null,
  map1ZoomLevel: 10,
  passagesCollection: null,
  alertpublishCollection: null,
  voicepublishCollection: null,
  announcementsCollection: null,
  apubFieldName: null,
  vpubFieldName: null,
  lsLenField: null,
  showVideoField: null,
  showVideoOnField: null,
  webcamNumField: null,
  webcamZoomField: null,
  webcamSource: {
    "A": null,
    "B": null,
    "C": null,
    "D": null
  },
  webcamName: {
    "A": null,
    "B": null,
    "C": null,
    "D": null
  },
  webcamType: {
    "A": null,
    "B": null,
    "C": null,
    "D": null
  },
  videoSource: null,
  videoType: "application/x-mpegURL",
  videoIsOn: false,
  videoProgram: null,
  videoProgramIsOn: false,
  passengerTrackerIsOn: false,
  manualTrackerIsOn: false,
  vesselsAreInCameraRange: false,
  vesselsInCamera: [],
  vesselsArePass: [],
  map1: {},
  map2: {},
  map3: {},
  polylines: {},
  mileMarkersList1: [],
  mileMarkerLabels1: [],
  mileMarkersList2: [],
  mileMarkerLabels2: [],
  mileMarkersList3: [],
  mileMarkerLabels3: [],
  rotatingKey: 0,
  passRotKey: 0,
  numVessels: 0,
  fakeDataIterator: 0,
  passagesList: [{
    type: "default"
  }],
  alertsPassenger: [{
    apubVesselName: "loading",
    apubID: "loading",
    date: new Date()
  }, {
    apubVesselName: "loading",
    apubID: "loading",
    date: new Date()
  }, {
    apubVesselName: "loading",
    apubID: "loading",
    date: new Date()
  }, {
    apubVesselName: "loading",
    apubID: "loading",
    date: new Date()
  }, {
    apubVesselName: "loading",
    apubID: "loading",
    date: new Date()
  }],
  alertsAll: [{
    apubVesselName: "loading",
    apubID: "loading",
    date: new Date()
  }, {
    apubVesselName: "loading",
    apubID: "loading",
    date: new Date()
  }, {
    apubVesselName: "loading",
    apubID: "loading",
    date: new Date()
  }, {
    apubVesselName: "loading",
    apubID: "loading",
    date: new Date()
  }, {
    apubVesselName: "loading",
    apubID: "loading",
    date: new Date()
  }],
  announcement: {},
  waypoint: {},
  prevWebcamNum: 0,
  prevWaypoint: {},
  prevVpubID: 0,
  prevApubID: 0,
  isReload: true,
  news: [{
    key: "f00",
    text: "Clinton's Riverview Park is a great place to view Mississippi River boat traffic."
  }, {
    key: "f01",
    text: "Welcome to the <em>dashboard</em> page. It's optimized for HD wide screens."
  }],
  newsKey: 0,
  transponder: {
    step: 0,
    stepMax: 7,
    viewList: []
  }
};

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _DataModel_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./DataModel.js */ "./src/DataModel.js");


/**
 *                            Data Model
 */

let player = null;
const dataModel = _DataModel_js__WEBPACK_IMPORTED_MODULE_0__.DataModel;

/**
 * 
 *                            Functions
 *  
 */

async function fetchWaypoint() {
  const adminSnapshot = onSnapshot(doc(db, "Passages", "Admin"), querySnapshot => {
    let dataSet = querySnapshot.data();
    let apubID, vpubID, lsLen, apublishCollection, vpublishCollection, waypoint;
    let wasOutput = false; //Resets when screen updates

    console.log("TRACER: Admin obj & dataModel.sitename ", dataSet, dataModel.sitename);
    console.log("TracerB: showVideoField, showVideoOnField, webcamNumField, webcamZoomfield", dataModel.showVideoField, dataModel.showVideoOnField, dataModel.webcamNumField, dataModel.webcamZoomField);
    apubID = dataSet[dataModel.apubFieldName].toString();
    vpubID = dataSet[dataModel.vpubFieldName].toString();
    lsLen = dataSet[dataModel.lsLenField];
    if (!sitename.includes("dash")) {
      dataModel.webcamSource.A = dataSet.webcamSources[dataModel.sitename + "A"].src || null;
      dataModel.webcamSource.B = dataSet.webcamSources[dataModel.sitename + "B"].src || null;
      dataModel.webcamSource.C = dataSet.webcamSources[dataModel.sitename + "C"].src || null;
      dataModel.webcamSource.D = dataSet.webcamSources[dataModel.sitename + "D"].src || null;
      dataModel.webcamName.A = dataSet.webcamSources[dataModel.sitename + "A"].name || null;
      dataModel.webcamName.B = dataSet.webcamSources[dataModel.sitename + "B"].name || null;
      dataModel.webcamName.C = dataSet.webcamSources[dataModel.sitename + "C"].name || null;
      dataModel.webcamName.D = dataSet.webcamSources[dataModel.sitename + "D"].name || null;
      dataModel.webcamType.A = dataSet.webcamSources[dataModel.sitename + "A"].type || null;
      dataModel.webcamType.B = dataSet.webcamSources[dataModel.sitename + "B"].type || null;
      dataModel.webcamType.C = dataSet.webcamSources[dataModel.sitename + "C"].type || null;
      dataModel.webcamType.D = dataSet.webcamSources[dataModel.sitename + "D"].type || null;
      dataModel.cameraStatus.showVideo = dataSet[dataModel.showVideoField];
      dataModel.cameraStatus.showVideoOn = dataSet[dataModel.showVideoOnField];
      dataModel.cameraStatus.webcamNum = dataSet[dataModel.webcamNumField];
      dataModel.cameraStatus.webcamZoom = dataSet[dataModel.webcamZoomField];
      dataModel.cameraStatus.videoIsPassingCloseup = dataSet.videoIsPassingCloseup;
      dataModel.cameraStatus.videoIsFull = dataSet.videoIsFull;
    }
    apublishCollection = dataModel.alertpublishCollection;
    vpublishCollection = dataModel.voicepublishCollection;
    dataModel.resetTime = dataSet.resetTime;
    dataModel.idTime = dataSet.idTime;
    /* DATA FORMAT
      idTime [
        {min:14 sec:50 videoIsFull:false },
        {min:44 sec:50 videoIsFull:false },
      ]
     */
    dataModel.promoSources = dataSet.promoSources;
    dataModel.videoProgram = dataSet.videoProgram;
    /* DATA FORMAT 
      videoProgram {
        date: 5,
        hour: 3,
        min : 0,
        month: 11,
        sec: 0
        source: "waypoint-notifications.m3u8",
        title: "Waypoint Notifications",
        videoIsFull: true
       }
     *
                                        */

    console.log("snapshotUpdate reset time:", dataModel.resetTime);
    console.log("snapshotUpdate id time:", dataModel.idTime);
    //console.log("snapshotYodate idPlus15:", dataModel.idPlus15);

    //Compare lsLen to liveScan array size
    if (lsLen < liveScans.length) {
      //Reset array and maps if update array size is less
      liveScans.forEach(o => {
        o.map1marker.setMap(null);
        o.map2marker.setMap(null);
        o.map3marker.setMap(null);
      });
      liveScans.splice(0, liveScans.length);
      dataModel.labelIndex = 0;
    }
    //On 1st load initiate prevVpubID
    if (dataModel.prevVpubID == 0) {
      dataModel.prevVpubID = vpubID;
    }

    //Check for new waypoint on each snapshot update
    getDoc(doc(db, apublishCollection, apubID)).then(document => {
      if (document.exists()) {
        waypoint = document.data();
        let dt = new Date();
        let ts = Math.round(dt.getTime() / 1000);
        let diff = ts - waypoint.apubTS;
        //Is apubID (waypoint) new?
        if (apubID > dataModel.prevApubID) {
          //Is model 0 default?
          if (dataModel.prevApubID == 0) {
            //Yes. Update stored obj and save new apubID
            dataModel.waypoint = waypoint;
            dataModel.prevApubID = apubID;
          }
          //Yes. Output true to report it.
          return true;
        }
      } else {
        outputWaypoint(dataModel.cameraStatus.showVideoOn, dataModel.cameraStatus.showVideo, dataModel.cameraStatus.webcamNum, dataModel.cameraStatus.videoIsFull, dataModel.promoIsOn, dataModel.videoProgramIsOn, dataModel.cameraStatus.videoIsPassingCloseup);
        wasOutput = true;
        return false;
      }
    }).then(isNew => {
      if (!isNew) return;
      //Waypoint is new, so continue
      //   Calculate waypoint by event and direction data
      let dir = dataModel.waypoint.apubDir.includes('wn') ? "down" : "up";
      //Strip waypoint basename as event name
      let event = dataModel.waypoint.apubEvent.substr(0, dataModel.waypoint.apubEvent.length - 2);
      let str = event + "-" + dir + "-map-v2.jpg";
      //let str = event + "-" + dir + "-map.png"
      dataModel.waypoint.bgMap = "https://storage.googleapis.com/www.clintonrivertraffic.com/images/" + str;
      //Prevent audio play on reload
      if (dataModel.isReload) {
        dataModel.isReload = false;
        outputWaypoint(dataModel.cameraStatus.showVideoOn, dataModel.cameraStatus.showVideo, dataModel.cameraStatus.webcamNum, dataModel.cameraStatus.videoIsFull, dataModel.promoIsOn, dataModel.videoProgramIsOn, dataModel.cameraStatus.videoIsPassingCloseup);
        wasOutput = true;
        console.log("waypoint output skipping audio play on browser reload.");
        return;
      }
      //Change class of event with matching apubID
      if (dataModel.waypoint.apubID === dataModel.alertsPassenger[19].apubID) {
        const li = document.getElementById("pass19");
        li.classList.add('isNew');
        console.log("waypoint match found to passenger event " + diff + " seconds ago -> playSound()");
        playSound();
      } else if (dataModel.waypoint.apubID === dataModel.alertsAll[19].apubID) {
        const li = document.getElementById("all19");
        li.classList.add('isNew');
        console.log("waypoint match found to 'any' event " + diff + " seconds ago -> playSound()");
        playSound();
      } else {
        console.log("no waypoint match to an event was found");
      }
      outputWaypoint(dataModel.cameraStatus.showVideoOn, dataModel.cameraStatus.showVideo, dataModel.cameraStatus.webcamNum, dataModel.cameraStatus.videoIsFull, dataModel.promoIsOn, dataModel.videoProgramIsOn, dataModel.cameraStatus.videoIsPassingCloseup);
    });

    //Also check for new voice annoucement on each snapshot update
    getDoc(doc(db, vpublishCollection, vpubID)).then(document => {
      if (document.exists()) {
        //let announcement = document.data()
        dataModel.announcement = document.data();
        let dt = new Date();
        let ts = Math.round(dt.getTime() / 1000);
        let diff = ts - dataModel.announcement.vpubTS;
        if (vpubID > dataModel.prevVpubID && diff < 300) {
          return true;
        }
        return false;
      } else {
        console.log("No announcements.", vpubID);
        dataModel.announcement = {
          vpubText: "No new announcements."
        };
        return false;
      }
    }).then(isNew => {
      if (isNew) {
        dataModel.prevVpubID = vpubID;
        playAnnouncement();
      }
    });
    if (!wasOutput) {
      //Ensure view update for showVideo boolean changes
      outputWaypoint(dataModel.cameraStatus.showVideoOn, dataModel.cameraStatus.showVideo, dataModel.cameraStatus.webcamNum, dataModel.cameraStatus.videoIsFull, dataModel.promoIsOn, dataModel.videoProgramIsOn, dataModel.cameraStatus.videoIsPassingCloseup);
    }
  });
  return new Promise((resolve, reject) => {
    resolve();
    reject();
  });
}
function outputWaypoint(showVideoOn, showVideo, webcamNum, videoIsFull, playPromo, playProgram, videoIsPassingCloseup) {
  if (privateMode == true) {
    showVideoOn = false;
  }
  console.log("outputWaypoint(showVideoOn, showVideo, webcamNum, videoIsFull), videoSource", showVideoOn, showVideo, webcamNum, videoIsFull, dataModel.videoSource);
  if (showVideoOn == true && showVideo == true) {
    dataModel.videoIsOn = true;
    waypointDiv.style = `display: none`;
    videoTag.style = `display: block; z-index: 0`;
    const options = {
      autoplay: true,
      preload: "auto",
      fluid: true,
      loadingSpinner: false,
      techOrder: ["html5", "youtube"]
    };
    console.log("webcamNum is", webcamNum);
    if (videoIsPassingCloseup && !dataModel.cameraStatus.videoIsPassingCloseup) {
      //Turn on closeup if passing and not on already
      togglePassingCloseup(videoIsPassingCloseup, videoIsFull);
    } else if (dataModel.cameraStatus.videoIsPassingCloseup && !videoIsPassingCloseup) {
      //Turn off closeup if on and not passing
      togglePassingCloseup(videoIsPassingCloseup, videoIsFull);
    }
    // Play promo from rotation at random
    if (playPromo && tvMode) {
      let sk = Math.floor(Math.random() * dataModel.promoSources.length);
      let promoSource = location.protocol + '//' + location.host + '/' + dataModel.promoSources[sk];
      console.log("promo source", promoSource);
      //clearZoom();
      waypointLabel.innerHTML = "Thank You For Watching";
      dataModel.promoIsOn = true;
      zoomControl(dataModel.cameraStatus.webcamZoom);
      //Turn off vessel name overlay when promo running
      if (overlay2.classList.contains("active")) {
        overlay2.classList.remove("active");
      }
      player = videojs("video", options, function onPlayerReady() {
        this.on('ended', function () {
          this.src({
            type: dataModel.videoType,
            src: dataModel.videoSource
          });
          this.play();
          dataModel.promoIsOn = false;
          //togglePassingCloseup(videoIsPassingCloseup, videoIsFull)
          if (videoIsPassingCloseup && !dataModel.cameraStatus.videoIsPassingCloseup) {
            //Turn on closeup if passing and not on already
            togglePassingCloseup(videoIsPassingCloseup, videoIsFull);
          } else if (dataModel.cameraStatus.videoIsPassingCloseup && !videoIsPassingCloseup) {
            //Turn off closeup if on and not passing
            togglePassingCloseup(videoIsPassingCloseup, videoIsFull);
          }
          zoomControl(dataModel.cameraStatus.webcamZoom);
          waypointLabel.innerHTML = dataModel.webcamName[webcamNum]; //"3 Miles South of Drawbridge";
        });

        this.src({
          type: "application/x-mpegURL",
          src: promoSource
        });
        this.play();
      });
    }
    //Play scheduled video program
    if (playProgram && tvMode) {
      waypointLabel.innerHTML = dataModel.videoProgram.dataTitle;
      dataModel.videoProgramIsOn = true;
      zoomControl(dataModel.cameraStatus.webcamZoom);
      //Turn off vessel overlay when program playing
      if (overlay2.classList.contains("active")) {
        overlay2.classList.remove("active");
      }
      player = videojs("video", options, function onPlayerReady() {
        this.on('ended', function () {
          this.src({
            type: dataModel.videoType,
            src: dataModel.videoSource
          });
          this.play();
          dataModel.videoProgramIsOn = false;
          //togglePassingCloseup(videoIsPassingCloseup, videoIsFull)
          if (videoIsPassingCloseup && !dataModel.cameraStatus.videoIsPassingCloseup) {
            //Turn on closeup if passing and not on already
            togglePassingCloseup(videoIsPassingCloseup, videoIsFull);
          } else if (dataModel.cameraStatus.videoIsPassingCloseup && !videoIsPassingCloseup) {
            //Turn off closeup if on and not passing
            togglePassingCloseup(videoIsPassingCloseup, videoIsFull);
          }
          zoomControl(dataModel.cameraStatus.webcamZoom);
          waypointLabel.innerHTML = dataModel.webcamName[webcamNum]; //"3 Miles South of Drawbridge";
        });

        this.src({
          type: dataModel.videoProgram.type,
          src: dataModel.videoProgram.source
        });
        this.play();
      });
    } else {
      zoomControl(dataModel.cameraStatus.webcamZoom);
      //Switch camera source if changed
      if (webcamNum != dataModel.prevWebcamNum) {
        waypointLabel.innerHTML = dataModel.webcamName[webcamNum]; //"3 Miles South of Drawbridge"
        dataModel.videoSource = dataModel.webcamSource[webcamNum];
        dataModel.videoType = dataModel.webcamType[webcamNum];
        console.log("video source", dataModel.videoSource);
        if (player == null) {
          player = videojs("video", options);
        }
        player.ready(function () {
          player.src({
            type: dataModel.videoType,
            src: dataModel.videoSource
          });
          player.play();
        });
        dataModel.prevWebcamNum = webcamNum;
        console.log("outputWaypoint(showVideoOn, showVideo, webcamNum, videoIsFull), videoSource", showVideoOn, showVideo, webcamNum, videoIsFull, dataModel.videoSource);
      }
    }
    //waypointLabel.style = `z-index: 1`;
  } else {
    dataModel.videoIsOn = false;
    videoTag.style = `display: none`;
    waypoint.style = `background-image: url(${dataModel.waypoint.bgMap})`;
    waypointLabel.innerHTML = "WAYPOINT";
    waypointDiv.innerHTML = `<h3>${dataModel.waypoint.apubText}</h3>`;
    waypointDiv.style.display = "block";
    console.log("outputWaypoint(showVideoOn, showVideo, webcamNum, videoIsFull)", showVideoOn, showVideo, webcamNum, videoIsFull);
  }
  return new Promise((resolve, reject) => {
    resolve();
    reject();
  });
}
function initVideo() {
  const options = {
    autoplay: true,
    preload: "auto",
    fluid: true,
    loadingSpinner: false,
    techOrder: ["html5", "youtube"]
  };
  player = videojs("video", options, function onPlayerReady() {
    this.on('ended', function () {
      this.src({
        type: dataModel.videoType,
        src: dataModel.videoSource
      });
      this.play();
      dataModel.promoIsOn = false;
      //togglePassingCloseup(videoIsPassingCloseup, videoIsFull)
      if (videoIsPassingCloseup && !dataModel.cameraStatus.videoIsPassingCloseup) {
        //Turn on closeup if passing and not on already
        togglePassingCloseup(videoIsPassingCloseup, videoIsFull);
      } else if (dataModel.cameraStatus.videoIsPassingCloseup && !videoIsPassingCloseup) {
        //Turn off closeup if on and not passing
        togglePassingCloseup(videoIsPassingCloseup, videoIsFull);
      }
      zoomControl(dataModel.cameraStatus.webcamZoom);
      waypointLabel.innerHTML = dataModel.webcamName[webcamNum]; //"3 Miles South of Drawbridge";
    });

    this.src({
      type: "application/x-mpegURL",
      src: promoSource
    });
    this.play();
  });
}
})();

/******/ })()
;
//# sourceMappingURL=bundle.js.map