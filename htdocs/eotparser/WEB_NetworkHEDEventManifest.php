<?php

$base = "C:\\Apache24\\htdocs\\";
include_once($base.'../protected/config.php');

if(isset($_GET['NetworkAcronym'])) {
    $useDirMode = true;
    $NetworkAcronym  = $_GET['NetworkAcronym'];
 	} 
//Process appropriate to action value

try {
	$conn = new PDO("sqlsrv:server=$host\SQLEXPRESS;database=$dbname", $username, $password);
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$sql = "EXEC [dbo].[WEB_NetworkHEDEventManifest] @NetworkAcronym = :NetworkAcronym";
	$stmt = $conn->prepare($sql);


	// Parameter bindings
	$stmt->bindParam(':NetworkAcronym', $NetworkAcronym, PDO::PARAM_STR);
	// Statement execution
	$stmt->execute();
	// Access output parameter
	//echo "Data Received: ".$NetworkAcronym.;
	echo "Report: WEB_HEDEventManifest <br />";
	echo "Sensor network: ".$NetworkAcronym."<br />";
	echo "Date/Time: ". date("m/d/Y H:i:s")." (UTC)<br /><br />";
	echo "SVRDateCreated,SensorLocation,EventDate,EventType,ID<br />";
	//echo  PDO::FETCH_ASSOC."<br />";
	// Handle results if the procedure returns data sets
	while( $row = $stmt->fetch( PDO::FETCH_ASSOC) ) {
		echo $row['SVRDateCreated'].", ".$row['SensorLocation'].", ".$row['EventDate'].", ".$row['EventType'].", ".$row['ID']."<br />";
		}	
	$stmt->closeCursor();
	$conn = null;
	} 
catch(PDOException $e) {
	echo "Error: " . $e->getMessage();
	//echo "\nData Received: ".$location." -> ".$ts." -> ".$src." -> ".$train_id;
	}
?>