<?php

require("includes/util.php");

session_start();

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

// Request to check that token is still valid
if(isset($_POST['token']))
{
	$stmt = $pdo->prepare('SELECT * FROM tokens WHERE token = ?');
	$stmt->execute([$_POST['token']]);
	
	if($stmt->rowCount())
	{
		$token_row = $stmt->fetch();
		
		$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
		$stmt->execute([$token_row['user_id']]);
		
		if($stmt->rowCount())
		{
			$user_row = $stmt->fetch();
			echo($user_row['email']);
		}
		else
			echo("invalid-token");
	}
	else
		echo("invalid-token");
}

else if(isset($_POST['email']) && isset($_POST['password']))
{
	$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
	$stmt->execute([$_POST['email']]);
	
	if($stmt->rowCount())
	{
		$user_result = $stmt->fetch();
		if(password_verify($_POST['password'], $user_result['password']))
		{
			if($user_result['enabled'])
			{
				// generate a token and pass as cookie
				$token = base64_encode(bin2hex(random_bytes(8)));
				
				// store token in tokens table with 30 day expiration date
				$stmt = $pdo->prepare('INSERT INTO tokens (token, user_id, expires) VALUES (?, ?, NOW() + INTERVAL 30 DAY)');
				$stmt->execute([$token, $user_result['id']]);
				
				// echo token as text
				if(isset($_POST['result']) && $_POST['result'] == 'text')
					echo($token);
				// set cookie with 30 day expiration date
				else
					setcookie('token', $token, time()+60*60*24*30);
			}
			else
			{
				// echo user-disabled as text
				if(isset($_POST['result']) && $_POST['result'] == 'text')
					echo("user-disabled");
				// return error that user is not enabled
				else
					$_SESSION['login-error'] = 'disabled';
			}
		}
		else
		{
			if(isset($_POST['result']) && $_POST['result'] == 'text')
				echo("invalid-password");
			// return error incorrect password
			else
				$_SESSION['login-error'] = 'password';
		}
	}
	else
	{
		if(isset($_POST['result']) && $_POST['result'] == 'text')
			echo("invalid-user");
		// return an error that user does not exist to login page
		else
			$_SESSION['login-error'] = 'user';
	}
	
	if(!isset($_POST['result']) || $_POST['result'] != 'text')
		header('Location: index.php');
}

?> 
