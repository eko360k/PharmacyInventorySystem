<?php
require_once('functions.php');
$fn = new Functions();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo SITE_NAME; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Global CSS -->
    <link rel="stylesheet" href="../assets/css/main.css">

    <!-- Page-specific CSS -->
    <?php if(isset($page_css)) { ?>
        <link rel="stylesheet" href="../assets/css/<?php echo $page_css; ?>">
    <?php } ?>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Chart.js for Charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="app-container">