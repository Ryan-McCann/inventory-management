<?php
require("Item.php");

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

/* Check if token is set and check if a request was made
   Will need to authenticate token against list of allowed users
   Using Google login 
*/
if(isset($_POST['token']) && isset($_POST['requestType']))
{
	switch($_POST['requestType'])
	{
		case 'item':
			if(isset($_POST['barcode']))
			{
				$item = new Item();
				// search alias table for barcode and get associated item id
				$stmt = $pdo->prepare('SELECT item_id FROM aliases WHERE UPC = ?');
				$stmt->execute([$_POST['barcode']+0]);
				
				if($stmt->rowCount())
				{
					$item_id = $stmt->fetch(PDO::FETCH_ASSOC)['item_id'];
					$item.$id = $item_id;
					
					// get item by id
					$stmt = $pdo->prepare('SELECT * from items WHERE id = ?');
					$stmt->execute([$item_id]);
					$itemResult = $stmt->fetch();
					
					// use result to build item object
					$item.$description = $itemResult['description'];
					$item.$minimum = $itemResult['minimum'];
					$item.$maximum = $itemResult['maximum'];
					
					// check if item is in inventory
					$stmt = $pdo->prepare('SELECT * from inventory WHERE item_id = ?');
					$stmt->execute([$item_id]);
					$quantities = $stmt->fetch();
					
					$item.$quantity = 0;
					if($stmt->rowCount())
					{
						// iterate over rows and get the total quantity of item in inventory
					}
					
					$stmt = $pdo->prepare('SELECT * from aliases WHERE item_id = ?');
					$stmt->execute([$item_id]);
					if($stmt->rowCount())
					{
						// add all barcodes to item
					}
				}
				else
				{
					$item.$id = -1;
				}
			}
			else if(isset($_POST['id']))
			{
				// search item table for item with id
				// if found, return associated item as json
				// otherwise, return item with id -1 if not found
				echo('Made it to item search by id');
			}
			else
			{
				echo('Item not found');
			}
			
			break;
		case 'shelf':
			if(isset($_POST['barcode']))
			{
				// search shelf table for shelf with matching barcode
				
				// return shelf as json or return shelf with id -1 if not found
				echo('Made it to shelf search by barcode');
			}
			else if(isset($_POST['label']))
			{
				// search shelf table for shelf with matching label
				
				// return shelf in json or return shelf with id -1 if not found
				echo('Made it to shelf search by label');
			}
			else if(isset($_POST['id']))
			{
				// search shelf table for shelf with matching id
				
				// return shelf in json or return shelf with id -1 if not found
				echo('Made it to shelf search by id');
			}
			else
			{
				echo('Shelf not found');
			}
			break;
		case 'createShelf':
			if(isset($_POST['label']))
			{
				// check if label already exists
				
				// if not, generate barcode number
				
				// add to shelf table
				echo('Create Shelf');
			}
			break;
		case 'createItem':
			if(isset($_POST['description']) && isset($_POST['barcode']))
			{
				$minimum = 0;
				$maximum = 0;
				
				// if minimum is set
				if(isset($_POST['minimum']))
					$minimum = $_POST['minimum'];
				
				// if maximum is set
				if(isset($_POST['maximum']))
					$maximum = $_POST['maximum'];
				
				// create an item with description
				
				// create an alias with barcode and item id
			}
			break;
		case 'createAlias':
			if(isset($_POST['id']) && isset($_POST['barcode']))
			{
				// check if barcode already exists in alias table
				
				// if not, add alias of barcode to id to alias table
			}
			echo('Create Alias');
			break;
		case 'updateItem':
			if(isset($_POST['id']))
			{
				// check if item exists in table
				
				if(isset($_POST['description'])) {}
					// update description
				
				if(isset($_POST['minimum'])) {}
					// update minimum
				
				if(isset($_POST['maximum'])) {}
					// update maximum
			}
			break;
		case 'addItem':
			if(isset($_POST['id']) && isset($_POST['quantity']))
			{
				// check if shelf id is supplied
				if(isset($_POST['shelfId']))
				{
					// add item by quantity to shelf
				}
				else
				{
					// add item by quantity to null shelf
				}
			}
		case 'updateShelf':
			if(isset($_POST['id']))
			{
				// check if shelf exists in table
				
				if(isset($_POST['label'])) {}
					// update label
				
				if(isset($_POST['barcode'])) {}
					// update barcode
			}
			break;
		case 'deleteItem':
			if(isset($_POST['id']))
			{
				// find any aliases matching id and remove
				// delete from inventory
				// remove from item table by id
			}
			break;
		case 'deleteShelf':
			if(isset($_POST['id']))
			{
				// remove from shelf table by id
			}
			break;
		case 'deleteAlias':
			if(isset($_POST['barcode']))
			{
				// remove from alias table by barcode
			}
			break;
		default:
			echo('Invalid request type.');
			break;
	}
}

// function to load configuration from text file, with config options separated as key: value pairs
function loadConfig($filename)
{
	$kvp_array = array();
	$config_file = file($filename);
	
	foreach($config_file as $line)
	{
		$key_value = explode(':', $line);
		$kvp_array[trim($key_value[0])] = trim($key_value[1]);
	}
	
	return $kvp_array;
}
?>
