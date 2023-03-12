<?php

session_start();

$loggedin = $_SESSION["login"] === true;

if($loggedin)
{
	include("inventory.html");
}
else
	include("login.html");

?>
