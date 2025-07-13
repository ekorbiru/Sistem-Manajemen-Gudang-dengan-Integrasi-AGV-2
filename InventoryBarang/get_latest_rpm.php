<?php
require 'function.php';
header('Content-Type: application/json');

$query = "SELECT rpm1, rpm2, waktu FROM rpm_log ORDER BY waktu DESC LIMIT 1";
$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

echo json_encode($data);
?>
