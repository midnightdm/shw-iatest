<?php

$base = "C:\\Apache24\\htdocs\\";

include_once($base.'../protected/config.php');
$conn = new PDO("sqlsrv:server=$host\SQLEXPRESS;database=$dbname", $username, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//$sql = "SELECT SensorLocation FROM sen_sensorlocations";
$Network="SH";
$sql=	"select
		sn.NetworkName,
		sl.SensorLocation,
		st.Attr as [SensorType],
		(select 
			count(*) 
		from 
			HED_HEDEvents e 
		where 
			e.SensorLocationTypeLinkGUID=slt.SensorLocationTypeLinkGUID
			and DATEDIFF(hh,e.SVRDateCreated,getdate()) <24) as [HEDEventCount],
		(select 
			case 
				when min(e.SVRDateCreated) is null then 'No data...'
				else cast(min(e.SVRDateCreated) as varchar(max))
			end 
		from 
			HED_HEDEvents e 
		where 
			e.SensorLocationTypeLinkGUID=slt.SensorLocationTypeLinkGUID
			and DATEDIFF(hh,e.SVRDateCreated,getdate()) <24) as [FirstHEDEvent],
		(select 
			case 
				when max(e.SVRDateCreated) is null then 'No data...'
				else cast(max(e.SVRDateCreated) as varchar(max))
			end 
		from 
			HED_HEDEvents e 
		where 
			e.SensorLocationTypeLinkGUID=slt.SensorLocationTypeLinkGUID
			and DATEDIFF(hh,e.SVRDateCreated,getdate()) <24) as [LastHEDEvent]

	from
		SEN_SensorLocations sl
		inner join SEN_SensorLocationTypeLinks slt on (sl.SensorLocationGUID=slt.SensorLocationGUID)
		inner join TBL_SensorNetworks sn on (sl.SensorNetworkGUID=sn.SensorNetworkGUID)
		inner join TBL_SensorTypes st on (slt.SensorTypeGUID=st.SensorTypeGUID)
	where
		sn.NetworkAcronym='".$Network."'
		and st.Attr='HED'
	order by
		sl.SensorLocation";
//echo @sql;
echo "Report: RPT_HEDEvents (Last 24 hour summary of logged HED events)". "<br />";
echo "Network: ".$Network."<br />";
echo "Date/Time: ". date("m/d/Y H:i:s")." (UTC)<br /><br />";
echo "NetworkName,SensorLocation,SensorType,HEDEventCount,FirstHEDEvent,LastHEDEvent<br />";

foreach ($conn->query($sql) as $row) {
    echo $row['NetworkName'].", ".$row['SensorLocation'].", ".$row['SensorType'].", ".$row['HEDEventCount'].", ".$row['FirstHEDEvent'].", ".$row['LastHEDEvent']. "<br />";
}

//sqlsrv_free_stmt( $stmt);

?>