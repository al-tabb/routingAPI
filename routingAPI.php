<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: X-Requested-With");
header("Content-type: application/json");
?>
<?php
$httpHost = "";
#if ($_SERVER['HTTP_HOST']==$httpHost){
	if (true){
  if (isset($_GET)){
    if (isset($_GET['startLat'])==false){
      echo "{\"error\": \"Invalid parameters\"}";
    }
    elseif (isset($_GET['startLong'])==false){
      echo "{\"error\": \"Invalid parameters\"}";
    }
    elseif (isset($_GET['endLat'])==false){
      echo "{\"error\": \"Invalid parameters\"}";
    }
    elseif (isset($_GET['endLong'])==false){
      echo "{\"error\": \"Invalid parameters\"}";
    }
    elseif (isset($_GET['restrictionMode'])==false){
      echo "{\"error\": \"Invalid parameters\"}";
    }
    else{
      if (isset($_GET['truckWeight'])){
	$weightBool = true;
      }
      else{
	$weightBool = false;
      }
      if (isset($_GET['truckHeight'])){
	$heightBool = true;
      }
      else{
	$heightBool = false;
      }
      $host = "xxx";
      $port = "5432";
      $dbname = $_ENV["database_name"];
      $user = "xxx";
      $password = "xxx";
      $connectString = "host=".$host." port=".$port." dbname=".$dbname." user=".$user." password=".$password;
      $db = pg_connect($connectString);
      $startLat = $_GET['startLat'];
      $startLong = $_GET['startLong'];
      $endLat = $_GET['endLat'];
      $endLong = $_GET['endLong'];
      $truckHeight = $_GET['truckHeight'];
      $truckWeight = $_GET['truckWeight'];
      $truckDefinitionWeight = "truck_definition_weight";
      $source = "source";
      $target = "target";
      $routeCost = "costalen";
      $reverseRouteCost = "costarevlen";
      $routeTable = "trfinal";
      $restrictionsTable = "restrictions";
      $routeGeom = $routeTable.".st_force2d";
      $routeId = "truckroute_id";
      $verticesTable = "trfinal_vertices_pgr";
      $verticesGeom = $verticesTable.".the_geom";
      $verticesId = "id";
      $calcEdgeId = "";
      $calcVertexId = "";
      $finalProjection = "4326";
      $maxHeightField = "max_height";
      $maxWeightField = "max_weight";
      $restrictionField = "rest";
      $cityField = "city";
      $streetNameField = "structured_name_1";
      $truckRouteTypeField = "type_tr";
      $truckRouteTypes = array("Provincial Highway", "Federal Road", "Designated Municipal Truck Route", "Designated Municipal Truck Route with Restrictions", "Municipal Road with No Truck Travel Restriction");
      $nonTruckRouteTypes = array("Non-Truck Route", "Port Restricted Access", "Truck Travel Restriction or Prohibition");
      if (withinArea($startLat,$startLong,$routeTable,$routeGeom,$db,$finalProjection,$routeTable)==false){
	$errorParams = array($startLat, $startLong, $endLat, $endLong, $truckWeight, $truckHeight, 'FALSE', "Start point out of bounds");
	$errorSQL = "INSERT INTO tracking (startlat, startlong, endlat, endlong, truckweight, truckheight, success, errordescription) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
	$errorResult = pg_query_params($db, $errorSQL, $errorParams);
	echo "{\"Error\": \"Start Point out of bounds.\"}";
      }
      elseif (withinArea($endLat,$endLong,$routeTable,$routeGeom,$db,$finalProjection,$routeTable)==false){
	$errorParams = array($startLat, $startLong, $endLat, $endLong, $truckWeight, $truckHeight, 'FALSE', "End point out of bounds");
	$errorSQL = "INSERT INTO tracking (startlat, startlong, endlat, endlong, truckweight, truckheight, success, errordescription) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
	$errorResult = pg_query_params($db, $errorSQL, $errorParams);
	echo "{\"Error\": \"End Point out of bounds.\"}";
      }
      else{
	if ($truckWeight>=0 && $truckWeight<4600){
	  $routeCost = "costalen";
	  $reverseRouteCost = "costarevlen";
	}
	else if ($truckWeight >=4600 && $truckWeight < 5500){
	  $routeCost = "costblen";
	  $reverseRouteCost = "costbrevlen";
	}
	else if ($truckWeight >=5500 && $truckWeight <10000){
	  $routeCost = "costclen";
	  $reverseRouteCost = "costcrevlen";
	}
	else if ($truckWeight >= 10000 && $truckWeight < 13600){
	  $routeCost = "costdlen";
	  $reverseRouteCost = "costdrevlen";
	}
	else if ($truckWeight >=13600 && $truckWeight < 26100){
	  $routeCost = "costelen";
	  $reverseRouteCost = "costerevlen";
	}
	else if ($truckWeight >= 26100){
	  $routeCost = "costflen";
	  $reverseRouteCost = "costfrevlen";
	}
	else{
	  $errorParams = array($startLat, $startLong, $endLat, $endLong, $truckWeight, $truckHeight, 'FALSE', "Invalid truck weight");
	  $errorSQL = "INSERT INTO tracking (startlat, startlong, endlat, endlong, truckweight, truckheight, success, errordescription) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
	  $errorResult = pg_query_params($db, $errorSQL, $errorParams);
	  echo "{\"error\":\"Invalid parameters.\"}";
	  exit;
	}
	if ($truckHeight < 0){
	  $errorParams = array($startLat, $startLong, $endLat, $endLong, $truckWeight, $truckHeight, 'FALSE', "Invalid truck height");
	  $errorSQL = "INSERT INTO tracking (startlat, startlong, endlat, endlong, truckweight, truckheight, success, errordescription) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
	  $errorResult = pg_query_params($db, $errorSQL, $errorParams);
	  echo "{\"error\":\"Invalid parameters.\"}";
	  exit;
	}
	$routeCost = "costflen";
	$reverseRouteCost = "costfrevlen";
	$startParams = array($startLong, $startLat);
	$startSQL = "SELECT ST_Distance(ST_Transform(".$routeGeom.",".$finalProjection."), ST_SetSRID(ST_MakePoint($1,$2),".$finalProjection.")) AS dist, ".$routeId." FROM ".$routeTable." WHERE ".$routeCost." > 0 ORDER BY dist ASC LIMIT 1";
	$endParams = array($endLong, $endLat);
	$endSQL = "SELECT ST_Distance(ST_Transform(".$routeGeom.",".$finalProjection."), ST_SetSRID(ST_MakePoint($1,$2),".$finalProjection.")) AS dist, ".$routeId." FROM ".$routeTable." WHERE ".$routeCost." > 0 ORDER BY dist ASC LIMIT 1";
	$startPointResult = pg_query_params($db, $startSQL,$startParams);
	if ($startPointResult == false){
#insert into tracking success = false
	  $errorParams = array($startLat, $startLong, $endLat, $endLong, $truckWeight, $truckHeight, 'FALSE', "Start point error");
	  $errorSQL = "INSERT INTO tracking (startlat, startlong, endlat, endlong, truckweight, truckheight, success, errordescription) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
	  $errorResult = pg_query_params($db, $errorSQL, $errorParams);
	  echo "{\"error\":\"Routing error.\"}";
	  exit;
	}
	$endPointResult = pg_query_params($db, $endSQL,$endParams);
	if ($endPointResult == false){
	#insert into tracking success = false;
	  $errorParams = array($startLat, $startLong, $endLat, $endLong, $truckWeight, $truckHeight, 'FALSE', "End point error");
	  $errorSQL = "INSERT INTO tracking (startlat, startlong, endlat, endlong, truckweight, truckheight, success, errordescription) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
	  $errorResult = pg_query_params($db, $errorSQL, $errorParams);
	  echo "{\"error\":\"Routing error.\"}";
	  exit;
	}
	while ($row = pg_fetch_row($startPointResult)){
	  $startEdge = $row[1];
	}
	while ($row = pg_fetch_row($endPointResult)){
	  $endEdge = $row[1];
	}
	$startMeasureSQL = "SELECT ST_LineLocatePoint(ST_LineMerge(ST_Transform(".$routeGeom.",".$finalProjection.")), ST_SetSRID(ST_MakePoint($1,$2),".$finalProjection.")) as measure, ".$routeId." FROM ".$routeTable." WHERE ".$routeId."=".$startEdge;
	$endMeasureSQL = "SELECT ST_LineLocatePoint(ST_LineMerge(ST_Transform(".$routeGeom.",".$finalProjection.")), ST_SetSRID(ST_MakePoint($1,$2),".$finalProjection.")) as measure, ".$routeId." FROM ".$routeTable." WHERE ".$routeId."=".$endEdge;
	$startMeasureResult = pg_query_params($db, $startMeasureSQL,$startParams);
	if ($startMeasureResult == false){
	#insert into tracking success = false;
	  $errorParams = array($startLat, $startLong, $endLat, $endLong, $truckWeight, $truckHeight, 'FALSE', "Start measure error");
	  $errorSQL = "INSERT INTO tracking (startlat, startlong, endlat, endlong, truckweight, truckheight, success, errordescription) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
	  $errorResult = pg_query_params($db, $errorSQL, $errorParams);
	  echo "{\"error\":\"Routing error.\"}";
	  exit;
	}
	$endMeasureResult = pg_query_params($db, $endMeasureSQL,$endParams);
	if ($endMeasureResult == false){
	  $errorParams = array($startLat, $startLong, $endLat, $endLong, $truckWeight, $truckHeight, 'FALSE', "End measure error");
	  $errorSQL = "INSERT INTO tracking (startlat, startlong, endlat, endlong, truckweight, truckheight, success, errordescription) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
	  $errorResult = pg_query_params($db, $errorSQL, $errorParams);
	  echo "{\"error\":\"Routing error.\"}";
	  exit;
	}
	while ($row = pg_fetch_row($startMeasureResult)){
	  $startMeasure = $row[0];
	}
	while ($row = pg_fetch_row($endMeasureResult)){
	  $endMeasure = $row[0];
	}
	$routeSQL = "SELECT ST_AsGeoJSON(ST_Transform(".$routeGeom.", ".$finalProjection.")) as geojson, * FROM ".$routeTable." INNER JOIN (SELECT * FROM pgr_trsp('SELECT ".$routeId." as id, ".$source."::integer,".$target."::integer, ".$routeCost."::double precision as cost, ".$reverseRouteCost."::double precision as reverse_cost FROM ".$routeTable;
	if ($_GET['restrictionMode']=='avoid' || $_GET['restrictionMode']=='warn'){
	  if ($weightBool == False && $heightBool == True){
#enable routing around restrictions
	    $routeSQL.=" WHERE (".$maxHeightField." >= ".$truckHeight." OR ".$maxHeightField." = -1) AND ".$truckDefinitionWeight."!= -1 AND ".$routeCost." > 0";
	    #$routeSQL.=" WHERE ".$routeCost." > 0";
	  }
	  elseif ($weightBool == True && $heightBool == False){
	    #enable routing around restrictions
	    $routeSQL.=" WHERE (".$maxWeightField." >= ".$truckWeight." OR ".$maxWeightField." = -1) AND ".$truckDefinitionWeight."!= -1  AND ".$routeCost." > 0";
	    #$routeSQL.=" WHERE ".$routeCost." > 0";
	  }
	  elseif ($weightBool == True && $heightBool == True){
	    #enable routing around restrictions
	    $routeSQL.=" WHERE (".$maxHeightField." >= ".$truckHeight." OR ".$maxHeightField." = -1) AND (".$maxWeightField." >= ".$truckWeight." OR ".$maxWeightField." = -1) AND ".$routeCost." > 0";
	    #$routeSQL.=" WHERE ".$routeCost." > 0";
	  }
	}
	$routeSQL.="',".$startEdge.",".$startMeasure.",".$endEdge.",".$endMeasure.",true,true, 'SELECT to_cost, target_id, via_path FROM ".$restrictionsTable."')) AS route ON ".$routeTable.".".$routeId." = route.id2 ORDER BY seq ASC";
	$result1 = pg_query($db, $routeSQL);
	if ($result1 == false){
	  echo "{\"error\":\"Routing error.\"}";
	  exit;
	}

	$count = 0;
	$printString = "[";
	$truckRouteCount = 0;
	$truckRouteBool = false;
	$nonTruckRouteBool = false;
	while ($row = pg_fetch_assoc($result1)){
	  if ($count == 0){
	    $firstRow = $row;
	  }
	  else{
	    if ($count==1){
	      $secondVertex = $row['id1'];
	      $startEndMeasureSQL = "SELECT ST_LineLocatePoint(ST_LineMerge(ST_Transform(".$routeGeom.",".$finalProjection.")), (SELECT ST_Transform(".$verticesGeom.",".$finalProjection.") FROM ".$verticesTable." WHERE ".$verticesId."=".$secondVertex.")) as measure, ".$routeId." FROM ".$routeTable." WHERE ".$routeId."=".$startEdge;
	      $startEndMeasureResult = pg_query($db, $startEndMeasureSQL);
	      if ($startEndMeasureResult == false){
	      #insert into tracking success = fals
		$errorParams = array($startLat, $startLong, $endLat, $endLong, $truckWeight, $truckHeight, 'FALSE', "Start End Measure error");
		$errorSQL = "INSERT INTO tracking (startlat, startlong, endlat, endlong, truckweight, truckheight, success, errordescription) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
		$errorResult = pg_query_params($db, $errorSQL, $errorParams);
		echo "{\"error\":\"Routing error.\"}";
		exit;
	      }
	      while($row2=pg_fetch_assoc($startEndMeasureResult)){
		$startEndMeasure = $row2['measure'];
	      }
	      if ($startEndMeasure<$startMeasure){
		$temp = $startMeasure;
		$startMeasure = $startEndMeasure;
		$startEndMeasure = $temp;
	      }
	      $startSubStringSQL = "SELECT ST_AsGeoJSON(ST_Transform(ST_LineSubstring(st_force2d,".$startMeasure.",".$startEndMeasure."),".$finalProjection.")) as geojson FROM ".$routeTable." WHERE ".$routeId."=".$firstRow[$routeId];
	      $startSubStringResult = pg_query($db, $startSubStringSQL);
	      	if ($startSubStringResult == false){
	      #insert into tracking success = false;
	      $errorParams = array($startLat, $startLong, $endLat, $endLong, $truckWeight, $truckHeight, 'FALSE', "Start sub string error");
	      $errorSQL = "INSERT INTO tracking (startlat, startlong, endlat, endlong, truckweight, truckheight, success, errordescription) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
	      $errorResult = pg_query_params($db, $errorSQL, $errorParams);
		  echo "{\"error\":\"Routing error.\"}";
		  exit;
		}
	      $printString.="{\"type\": \"FeatureCollection\", \"features\": [{\"type\": \"Feature\", \"properties\": {";
	      $printString.= "\"restriction\":\"".$firstRow[$restrictionField]."\",";
	      $printString.= "\"streetName\":\"".$firstRow[$streetNameField]."\",";
	      if (in_array($firstRow[$truckRouteTypeField], $truckRouteTypes)){
		$truckRouteCount+=1;
		$truckRouteBool = true;
	      }
	      if (in_array($firstRow[$truckRouteTypeField], $nonTruckRouteTypes) && $truckRouteBool==true){
		$nonTruckRouteBool = true;
	      }
	      if ($nonTruckRouteBool == true && $truckRouteBool == true && in_array($firstRow[$truckRouteTypeField],$truckRouteTypes)){
		#error
		echo "{\"Error\": \"Route not possible\"}";
		exit;
	      }
	      $printString.= "\"truckRouteType\":\"".$firstRow[$truckRouteTypeField]."\",";
	      $printString.= "\"city\":\"".$firstRow[$cityField]."\"";
	      $printString.= "}, \"geometry\":";
	      while ($row3 = pg_fetch_assoc($startSubStringResult)){
		$startSubString = $row3['geojson'];
	      }
	      $printString.=$startSubString;
	      $printString.="}]}";
	    }
	    if ($count==pg_num_rows($result1)-1){
	      $secondLastVertex = $row['id1'];
	      $endEndMeasureSQL = "SELECT ST_LineLocatePoint(ST_LineMerge(ST_Transform(".$routeGeom.",".$finalProjection.")), (SELECT ST_Transform(".$verticesGeom.",".$finalProjection.") FROM ".$verticesTable." WHERE ".$verticesId."=".$secondLastVertex.")) as measure, ".$routeId." from ".$routeTable." WHERE ".$routeId."=".$endEdge;
	      $endEndMeasureResult = pg_query($db, $endEndMeasureSQL);
	      if ($endEndMeasureResult == false){
	      #insert into tracking success = false;
		$errorParams = array($startLat, $startLong, $endLat, $endLong, $truckWeight, $truckHeight, 'FALSE', "End end measure error");
		$errorSQL = "INSERT INTO tracking (startlat, startlong, endlat, endlong, truckweight, truckheight, success, errordescription) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
		$errorResult = pg_query_params($db, $errorSQL, $errorParams);
		echo "{\"error\":\"Routing error.\"}";
		exit;
	      }
	      while($row4=pg_fetch_assoc($endEndMeasureResult)){
		$endEndMeasure = $row4['measure'];
	      }
	      if ($endEndMeasure>$endMeasure){
		$temp = $endMeasure;
		$endMeasure = $endEndMeasure;
		$endEndMeasure = $temp;
	      }
	      $endSubStringSQL = "SELECT ST_AsGeoJSON(ST_Transform(ST_LineSubstring(st_force2d,".$endEndMeasure.",".$endMeasure."),".$finalProjection.")) as geojson FROM ".$routeTable." WHERE ".$routeId."=".$endEdge;
	      $endSubStringResult = pg_query($db, $endSubStringSQL);
	      if ($endSubStringResult == false){
		#insert into tracking success = false;
		$errorParams = array($startLat, $startLong, $endLat, $endLong, $truckWeight, $truckHeight, 'FALSE', "End sub string error");
		$errorSQL = "INSERT INTO tracking (startlat, startlong, endlat, endlong, truckweight, truckheight, success, errordescription) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
		$errorResult = pg_query_params($db, $errorSQL, $errorParams);
		echo "{\"error\":\"Routing error.\"}";
		exit;
	      }
	      while ($row4 = pg_fetch_assoc($endSubStringResult)){
		$endSubString = $row4['geojson'];
	      }
	    }
	    if ($count>0){
	      $printString.=",";
	    }
	    $printString.="{\"type\": \"FeatureCollection\", \"features\": [{\"type\": \"Feature\", \"properties\": {";
	    $printString.= "\"restriction\":\"".$row[$restrictionField]."\",";
	    $printString.= "\"streetName\":\"".$row[$streetNameField]."\",";
	    if (in_array($row[$truckRouteTypeField], $truckRouteTypes)){
	      $truckRouteCount+=1;
	      $truckRouteBool = true;
	    }
	    if (in_array($row[$truckRouteTypeField], $nonTruckRouteTypes) && $truckRouteBool==true){
		$nonTruckRouteBool = true;
	    }
	    if ($nonTruckRouteBool == true && $truckRouteBool == true && in_array($row[$truckRouteTypeField],$truckRouteTypes)){
	      #echo "{\"Error\": \"Route not possible\"}";
	      #exit;
	    }

	    $printString.= "\"truckRouteType\":\"".$row[$truckRouteTypeField]."\",";
	    $printString.= "\"city\":\"".$row[$cityField]."\"";
	    $printString.= "}, \"geometry\":";
	    if ($count==pg_num_rows($result1)-1){
	      $printString.=$endSubString;
	    }
	    else{
	      $printString.=$row['geojson'];
	    }

	    $printString.="}]}";
	  }
	  $count++;
	}
	$printString.="]";
	if ($truckRouteCount>0){
	  $errorParams = array($startLat, $startLong, $endLat, $endLong, $truckWeight, $truckHeight, true, $printString);
	  $errorSQL = "INSERT INTO tracking (startlat, startlong, endlat, endlong, truckweight, truckheight, success, geojsonresult) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
	  $errorResult = pg_query_params($db, $errorSQL, $errorParams);
	  #insert into tracking success = true;
	  echo $printString;
	}
	else{
	  $errorParams = array($startLat, $startLong, $endLat, $endLong, $truckWeight, $truckHeight, 'FALSE', "Local delivery");
	  $errorSQL = "INSERT INTO tracking (startlat, startlong, endlat, endlong, truckweight, truckheight, success, errordescription) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
	  $errorResult = pg_query_params($db, $errorSQL, $errorParams);
	  #insert into tracking success = false;
	  echo "{\"error\":\"Local Delivery.\"}";
	}
      }
    }
  }
  else{
    #insert into tracking success = false;
    $errorParams = array($startLat, $startLong, $endLat, $endLong, $truckWeight, $truckHeight, 'FALSE', "Invalid parameters");
    $errorSQL = "INSERT INTO tracking (startlat, startlong, endlat, endlong, truckweight, truckheight, success, errordescription) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
    $errorResult = pg_query_params($db, $errorSQL, $errorParams);
    echo "{\"error\": \"Invalid parameters\"}";
  }
}
function withinArea($lat,$long,$tableName,$geomColumn,$db,$finalProjection,$routeTable){
#return true if lat,long is within the envelope of geomColumn from tableName
  $withinSQL = "SELECT ST_Within(ST_SetSRID(ST_MakePoint(".$long.",".$lat."),".$finalProjection."),ST_Transform(ST_setSRID(ST_Extent(".$geomColumn."),26910),".$finalProjection.")) FROM ".$routeTable;
  $withinResult = pg_query($db, $withinSQL);
  #if ($withinResult == false){
  #  echo "{\"error\":\"Routing error.\"}";
  #  exit;
  #}
  $row = pg_fetch_row($withinResult)[0];
  if ($row == 't'){
    return true;
  }
  else{
    return false;
  }
}
?>
