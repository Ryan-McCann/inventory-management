<?php

require("includes/util.php");

$config = loadConfig('config.ini');

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

if(isset($_POST['email']) && isset($_POST['password']))
{
	$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
	$stmt->execute($_POST['email']);
	
	if(!$stmt->rowCount())
	{
		$hash = password_hash($_POST['password']);
		$stmt = $pdo->prepare("INSERT INTO users (email, password, enabled) VALUES (?, ?, FALSE)");
		$stmt->execute([$_POST['email'], $hash]);
	}
	else
	{
		// return an error that user already exists to registration page
	}
}

?>
