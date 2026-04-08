<?php
// Include the AmazonFunctions class (which extends AmazonDatabase)
include('functions.php');

// Set default timezone — adjust as needed for your location
date_default_timezone_set('Africa/Accra');

// Create a new object of AmazonFunctions
$fn = new Functions();

// Note: session_start() is called only after successful login in login.php
// For protected pages, session_start() is handled in header.php with proper checks

// Connect to the database (this happens automatically via __construct, but optional if needed separately)
// $conn = $obj->db_connect(); // no need since the connection is now in __construct

?>
