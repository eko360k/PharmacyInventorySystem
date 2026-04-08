<?php
// Start PHP Session
session_start();

// Include the AmazonFunctions class (which extends AmazonDatabase)
include('functions.php');

// Set default timezone — adjust as needed for your location
date_default_timezone_set('Africa/Accra');

// Create a new object of AmazonFunctions
$fn = new Functions();

// Connect to the database (this happens automatically via __construct, but optional if needed separately)
// $conn = $obj->db_connect(); // no need since the connection is now in __construct

?>
