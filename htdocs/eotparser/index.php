<?php

$base = "C:\\Apache24\\htdocs\\";
include_once($base.'../protected/config.php');

if(isset($_GET['LOC'])) {
    $useDirMode = true;
    $location  = $_GET['LOC'];
    $ts        = $_GET['TS'];
    $src       = $_GET['SRC'];
    $train_id  = $_GET['ID'];
    include_once($base.'../protected/eot_parser_core.php');
    
} 

//Process appropriate to action value
try {
    $conn = new PDO("sqlsrv:server=$host\SQLEXPRESS;database=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Stored procedure call string
    $sql = "
        EXEC [dbo].[HED_HEDEventAdd] 
	    @SensorLocation = :SensorLocation,
	    @EventDate = :EventDate,
	    @EventType = :EventType,
	    @ID = :ID";
     // Prepare the statement
    $stmt = $conn->prepare($sql);


  // Bind parameters
  $stmt->bindParam(":SensorLocation", $location, PDO::PARAM_STR);
  $stmt->bindParam(":EventDate", $ts, PDO::PARAM_STR);
  $stmt->bindParam(":EventType", $src, PDO::PARAM_STR);
  $stmt->bindParam(":ID", $train_id, PDO::PARAM_STR);
 
  // Execute the procedure
  $stmt->execute();

  // Access output parameter
  echo "<html><pre>Data Received: ".$location." -> ".$ts." -> ".$src." -> ".$train_id."</pre></html>\n";

  // Handle results if the procedure returns data sets

  $stmt->closeCursor();
  $conn = null;

} catch(PDOException $e) {
  echo "<html><pre>Error: " . $e->getMessage();
  echo "\n\tData Received: ".$location." -> ".$ts." -> ".$src." -> ".$train_id."</pre></html>";
}


/* * * * * * * * * * * * * * * * * * *  * * * * * 
 *                                              *
 *    OLD PARSER FOR SAVING TO FTP              *
 *    REPLACED BY CODE THAT SAVES TO DB         *
 *                                              *
 *  * * * * * * * * * * * * * * * * * * * * * * *
$base = "C:\\Apache24\\htdocs\\";


if(isset($_GET['LOC'])) {
    $useDirMode = true;
    $location  = $_GET['LOC'];
    include_once($base.'../protected/eot_parser_core.php');
} 
//Process appropriate to action value

    

$theArray = var_dump($_GET);
exit("<html><h1>The submitted URL included the following paramaters</h1>
<pre>$theArray</pre>
</html>");

*/