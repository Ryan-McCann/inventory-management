<?php

session_start();

if(isset($_SESSION["login"]))
	$loggedin = $_SESSION["login"] === true;
else
	$loggedin = false;

if($loggedin)
{
	include("inventory.html");
}
else
	include("login.html");

?>
