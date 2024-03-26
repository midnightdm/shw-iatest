<?php

$base = "C:\\Apache24\\htdocs\\";

if(isset($_GET['action']) && isset($_GET['source'])) {
    $location = $_GET['source'];
    //Enable direction mode if heading paramater exists
    if(isset($_GET['heading'])) {
        $useDirMode = true;
        $direction  = $_GET['heading'];
    } else {
        $useDirMode = false;
    }
    //Process appropriate to action value
    if($_GET['action']=="start") {
        include_once($base.'../protected/start_handler_core.php');
    } elseif ($_GET['action']=="stop") {
        include_once($base.'../protected/stop_handler_core.php');
    }
} else {
    $theArray = var_dump($_GET);
    exit("<html><h1>The submitted URL was missing the 'action' and/or 'source' paramaters neccessary to use this trigger.</h1>
    <pre>$theArray</pre>
    </html>");
}