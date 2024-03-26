<?php

$base = "C:\\Apache24\\htdocs\\";
include_once($base.'../protected/config.php');

if(isset($_GET['NetworkAcronym'])) {
    $useDirMode = true;
    $NetworkAcronym  = $_GET['NetworkAcronym'];
 	} 
if(isset($_GET['SensorLocation'])) {
    $useDirMode = true;
    $SensorLocation  = $_GET['SensorLocation'];
 	} 
if(isset($_GET['Heartbeat'])) {
    $useDirMode = true;
    $Heartbeat  = $_GET['Heartbeat'];
 	} 
//Process appropriate to action value

try {
	$conn = new PDO("sqlsrv:server=$host\SQLEXPRESS;database=$dbname", $username, $password);
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$sql = "EXEC [dbo].[SEN_LocationHeartbeatUpdate] @NetworkAcronym = :NetworkAcronym,@SensorLocation = :SensorLocation,@Heartbeat = :Heartbeat";
	$stmt = $conn->prepare($sql);
	// Parameter bindings
	$stmt->bindParam(':NetworkAcronym', $NetworkAcronym, PDO::PARAM_STR);
	$stmt->bindParam(':SensorLocation', $SensorLocation, PDO::PARAM_STR);
	$stmt->bindParam(':Heartbeat', $Heartbeat, PDO::PARAM_STR);
	// Statement execution
	$stmt->execute();
	$stmt->closeCursor();
	$conn = null;
	} 
catch(PDOException $e) {
	echo "Error: " . $e->getMessage();
	}
?>