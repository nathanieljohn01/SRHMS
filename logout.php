<?php 
session_start();

// Check if the session variable 'name' is set
if(!empty($_SESSION['name'])) {
	// Unset all session variables
	session_unset();
	
	// Destroy the session
	session_destroy();
	
	// Redirect to the index page
	header('Location: index.php');
	exit();
}
?>