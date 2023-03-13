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
				
				// store token in tokens table
				$stmt = $pdo->prepare('INSERT INTO tokens (token, user_id) VALUES (?, ?)');
				$stmt->execute([$token, $user_result['id']]);
				
				setcookie('token', $token);
			}
			else
			{
				// return error that user is not enabled
			}
		}
		else
		{
			// return error incorrect password
		}
	}
	else
	{
		// return an error that user does not exist to login page
	}
}

?> 
