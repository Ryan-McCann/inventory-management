<?php

require("util.php");

$config = loadConfig('../config.ini');

// PDO parameters
$host = $config['host'];
$db = $config['db'];
$user = $config['user'];
$pass = $config['password'];
$charset = $config['charset'];

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
	PDO::ATTR_ERRMODE					=> PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE		=> PDO::FETCH_ASSOC,
	PDO::ATTR_EMULATE_PREPARES			=> false,
];


// Set up PDO instance
try
{
	$pdo = new PDO($dsn, $user, $pass, $options);
}
catch (\PDOException $e)
{
	throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

if(isset($_POST["token"]))
{
	$stmt = $pdo->prepare('SELECT * FROM tokens WHERE token = ?');
	$stmt->execute([$_POST["token"]]);
	
	if($stmt->rowCount())
		$loggedin = true;
	else
		$loggedin = false;
}
else if(isset($_COOKIE["token"]))
{
	$stmt = $pdo->prepare('SELECT * FROM tokens WHERE token = ?');
	$stmt->execute([$_COOKIE["token"]]);
	
	if($stmt->rowCount())
		$loggedin = true;
	else
		$loggedin = false;
}
else
	$loggedin = false;

if($loggedin)
{
	include("inventory.html");
}
else
	include("login.html");

?>
