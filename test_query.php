<?php
$mysqli = new mysqli('localhost', 'root', '', 'controlhorario_cmw');
if ($mysqli->connect_error) {
    die('connect error: ' . $mysqli->connect_error . "\n");
}
$sql = "SELECT full_name, date, clock_in, clock_out, COALESCE(TIME_TO_SEC(TIMEDIFF(clock_out, clock_in))/60, COALESCE(horas,0)*60) AS mins, GREATEST(0, COALESCE(TIME_TO_SEC(TIMEDIFF(clock_out, clock_in))/60, COALESCE(horas,0)*60) - 480) AS exceso, GREATEST(0, 480 - COALESCE(TIME_TO_SEC(TIMEDIFF(clock_out, clock_in))/60, COALESCE(horas,0)*60)) AS faltante FROM empleados_anual WHERE full_name = 'Feliciana Cantón Amaya' LIMIT 1";
$res = $mysqli->query($sql);
if (!$res) {
    die('query error: ' . $mysqli->error . "\n");
}
while ($row = $res->fetch_assoc()) {
    var_dump($row);
}
$mysqli->close();
