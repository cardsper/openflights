<?php
session_start();
header("Content-type: text/html; charset=iso-8859-1");

include 'helper.php';

$uid = $_SESSION["uid"];
if(!$uid or empty($uid)) {
  // If not logged in, default to demo mode
  $uid = 1;
}
// This applies only when viewing another's flights
$user = $HTTP_POST_VARS["user"];
if(! $user) {
  $user = $HTTP_GET_VARS["user"];
}

// Filter
$trid = $HTTP_POST_VARS["trid"];
$alid = $HTTP_POST_VARS["alid"];
$year = $HTTP_POST_VARS["year"];

$db = mysql_connect("localhost", "openflights");
mysql_select_db("flightdb",$db);

// Set up filtering clause and verify that this trip and user are public
$filter = "f.uid=" . $uid;

if($trid && $trid != "0") {
  // Verify that we're allowed to access this trip
  $sql = "SELECT * FROM trips WHERE trid=" . mysql_real_escape_string($trid);
  $result = mysql_query($sql, $db);
  if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    if($row["uid"] != $uid and $row["public"] == "N") {
      die('Error;This trip is not public.');
    } else {
      $uid = $row["uid"];
    }
  }
  $filter = $filter . " AND trid= " . mysql_real_escape_string($trid);
}
if($user && $user != "0") {
  // Verify that we're allowed to view this user's flights
  $sql = "SELECT uid,public FROM users WHERE name='" . mysql_real_escape_string($user) . "'";
  $result = mysql_query($sql, $db);
  if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    if($row["public"] == "N") {
      die('Error;This user\'s flights are not public.');
    } else {
      $uid = $row["uid"];
    }
  }
}

if($alid && $alid != "0") {
  $filter = $filter . " AND f.alid= " . mysql_real_escape_string($alid);
}
if($year && $year != "0") {
  $filter = $filter . " AND YEAR(src_time)='" . mysql_real_escape_string($year) . "'";
}

// unique airports, airlines, total distance
$sql = "SELECT COUNT(DISTINCT src_apid,dst_apid) AS num_airports, COUNT(DISTINCT alid) AS num_airlines, COUNT(DISTINCT plid) AS num_planes, SUM(distance) AS distance FROM flights AS f WHERE " . $filter;
$result = mysql_query($sql, $db);
if($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  printf ("%s,%s,%s,%s", $row["num_airports"], $row["num_airlines"], $row["num_planes"], $row["distance"]);
}
printf ("\n");

// longest and shortest
$sql = "(SELECT 'Longest flight',f.distance,DATE_FORMAT(duration, '%H:%i') AS duration,s.iata,s.apid,d.iata,d.apid FROM flights AS f,airports AS s,airports AS d WHERE f.src_apid=s.apid AND f.dst_apid=d.apid AND " . $filter . " ORDER BY distance DESC LIMIT 1) UNION " .
  "(SELECT 'Shortest flight',f.distance,DATE_FORMAT(duration, '%H:%i') AS duration,s.iata,s.apid,d.iata,d.apid FROM flights AS f,airports AS s,airports AS d WHERE f.src_apid=s.apid AND f.dst_apid=d.apid AND " . $filter . " ORDER BY distance ASC LIMIT 1)";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
  if($first) {
    $first = false;
  } else {
    printf(";");
  }
  printf ("%s,%s,%s,%s,%s,%s,%s", $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6]);
}
printf ("\n");

// North, South, West, East
// 0 desc, 1 iata, 2 icao, 3 apid, 4 x, 5 y
$sql = "(SELECT 'Northernmost',a.iata,a.icao,a.apid,x,y FROM airports AS a, flights AS f WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND " . $filter . " ORDER BY y DESC LIMIT 1) UNION " .
  "(SELECT 'Southernmost',a.iata,a.icao,a.apid,x,y FROM airports AS a, flights AS f WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND " . $filter . " ORDER BY y ASC LIMIT 1) UNION " .
  "(SELECT 'Westernmost',a.iata,a.icao,a.apid,x,y FROM airports AS a, flights AS f WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND " . $filter . " ORDER BY x ASC LIMIT 1) UNION " .
  "(SELECT 'Easternmost',a.iata,a.icao,a.apid,x,y FROM airports AS a, flights AS f WHERE (f.src_apid=a.apid OR f.dst_apid=a.apid) AND " . $filter . " ORDER BY x DESC LIMIT 1)";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }
  $code = $row[1];
  if(!$code || $code == "") {
    $code = $row[2];
  }
  printf ("%s,%s,%s,%s,%s", $row[0], $code, $row[3], $row[4], $row[5]);
}
printf ("\n");

// Classes
$sql = "SELECT DISTINCT class,COUNT(*) FROM flights AS f WHERE " . $filter . " GROUP BY class";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }
  printf ("%s,%s", $row[0], $row[1]);
}
printf ("\n");

// Reason
$sql = "SELECT DISTINCT reason,COUNT(*) FROM flights AS f WHERE " . $filter . " GROUP BY reason";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }
  printf ("%s,%s", $row[0], $row[1]);
}
printf ("\n");

// Reason
$sql = "SELECT DISTINCT seat_type,COUNT(*) FROM flights AS f WHERE " . $filter . " AND seat_type != '' GROUP BY seat_type";
$result = mysql_query($sql, $db);
$first = true;
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
  if($first) {
    $first = false;
  } else {
    printf(":");
  }
  printf ("%s,%s", $row[0], $row[1]);
}
printf ("\n");

?>
