<?php
// Check if server is already configured.
if(file_exists('config.ini'))
	include("includes/inventory-management.php");
// If not, run first-time setup
else
	include("includes/setup.php");
?>
