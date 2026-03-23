<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "localhost";
$user = "root";
$password = "";
$database = "nk_ent";
$result = "";
$resultClass = "";

$connect2db = mysqli_connect($host, $user, $password, $database);

// if ($connect2db) {
//     $result = "Database connected successfully";
//     $resultClass = "success";
   
// } else {
//     $result = "Database connection failed";
//     $resultClass = "error";
//     die("Database connection failed: " . mysqli_connect_error());
// }


?>