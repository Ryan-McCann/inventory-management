<?php
require("Item.php");
require("Shelf.php");

header('Content-Type: application/json');

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
if(isset($_POST['token']) && isset($_POST['type']))
{
	switch($_POST['type'])
	{
		case 'item':
			$item = new Item();
			
			if(isset($_POST['barcode']))
			{
				$item = getItemByBarcode($_POST['barcode'], $pdo);
			}
			else if(isset($_POST['id']))
			{
				$item = getItemById($_POST['id'], $pdo);
			}
			
			// echo result as json
			echo(json_encode(get_object_vars($item), JSON_PRETTY_PRINT));
			
			break;
		case 'shelf':
			$shelf = new Shelf();
			
			if(isset($_POST['barcode']))
			{
				$shelf = getShelfByBarcode($_POST['barcode']);
			}
			else if(isset($_POST['label']))
			{
				$shelf = getShelfByLabel($_POST['label']);
			}
			else if(isset($_POST['id']))
			{
				$shelf = getShelfById($_POST['id']);
			}
			
			// echo result as json
			echo(json_encode(get_object_vars($item), JSON_PRETTY_PRINT));
			
			break;
		case 'createShelf':
			if(isset($_POST['label']))
				createShelf($label, $pdo);
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
				$item_id = createItem($description, $minimum, $maximum, $pdo);
				
				// create an alias with barcode and item id
				createAlias($barcode, $item_id, $pdo);
			}
			break;
		case 'createAlias':
			if(isset($_POST['id']) && isset($_POST['barcode']))
				createAlias($_POST['barcode'], $_POST['id'], $pdo);
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
			if(isset($_POST['id']) && isset($_POST['quantity']) && isset($_POST['shelfId']))
				addItem($_POST['id'], $_POST['shelfId'], $_POST['quantity'], $pdo);
			break;
		case 'updateShelf':
			if(isset($_POST['id']))
			{
				if(isset($_POST['label'])) {}
					// update label
				
				if(isset($_POST['barcode'])) {}
					// update barcode
			}
			break;
		case 'removeItem':
			if(isset($_POST['id']) && isset($_POST['quantity']) && isset($_POST['shelfId']))
				removeItem($_POST['id'], $_POST['shelfId'], $_POST['quantity'], $pdo);
			break;
		case 'deleteItem':
			if(isset($_POST['id']))
				deleteItem($id, $pdo);
			break;
		case 'deleteShelf':
			if(isset($_POST['id']))
				deleteShelf($id, $pdo);
			break;
		case 'deleteAlias':
			if(isset($_POST['barcode']))
				deleteAlias($barcode, $pdo);
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

function createItem($description, $minimum, $maximum, $pdo)
{
	$stmt = $pdo->prepare('INSERT INTO items (description, minimum, maximum) VALUES (?, ?, ?)');
	$stmt->execute([$description, $minimum, $maximum]);
	return $pdo->lastInsertId();
}

function createShelf($label, $pdo)
{
	$barcode = 0;
	$row_count = 0;
	
	// pseudo-randomly generate an unused barcode
	do
	{
		$numbers = range(10000000, 99999999);
		$shuffle($numbers);
		$barcode = $numbers[0];
		
		$stmt = $pdo->prepare('SELECT * FROM shelves WHERE barcode = ?');
		$stmt->execute([$barcode]);
		$row_count = $stmt->rowCount();
	}
	while ($row_count);
	
	$stmt = $pdo->prepare('INSERT INTO shelves (label, barcode) VALUES (?, ?)');
	$stmt->execute([$label, $barcode]);
}

function createAlias($barcode, $item_id, $pdo)
{
	$stmt = $pdo->prepare('INSERT INTO aliases (barcode, item_id) VALUES (?, ?)');
	$stmt->execute([$barcode, $item_id]);
}

// function to load item by barcode from database
function getItemByBarcode($barcode, $pdo)
{
	$item = new Item();
	// search alias table for barcode and get associated item id
	$stmt = $pdo->prepare('SELECT item_id FROM aliases WHERE UPC = ?');
	$stmt->execute([$barcode+0]);
	
	if($stmt->rowCount())
	{
		$item_id = $stmt->fetch(PDO::FETCH_ASSOC)['item_id'];
		$item->id = $item_id;
		
		$item = getItemById($item_id, $pdo);
	}
	
	return $item;
}

function getItemById($item_id, $pdo)
{
	$item = new Item();
	
	// get item by id
	$stmt = $pdo->prepare('SELECT * FROM items WHERE id = ?');
	$stmt->execute([$item_id]);
	$item_result = $stmt->fetch();
	
	if($stmt->rowCount())
	{
		// set item id to id now that we know it's found
		$item->id = $item_id;
		
		// use result to build item object
		$item->description = $item_result['description'];
		$item->minimum = $item_result['minimum'];
		$item->maximum = $item_result['maximum'];
		
		// check if item is in inventory
		$stmt = $pdo->prepare('SELECT * FROM inventory WHERE item_id = ?');
		$stmt->execute([$item_id]);
		$quantities = $stmt->fetchAll();
		
		$item->quantity = 0;
		if($stmt->rowCount())
		{
			// iterate over rows and get the total quantity of item in inventory
			foreach($quantities as $row)
			{
				$item->quantity += $row['quantity'];
				array_push($item->shelves, $row['shelf_id']);
			}
		}
		
		
		$stmt = $pdo->prepare('SELECT * FROM shelves WHERE id = ?');
		
		$stmt = $pdo->prepare('SELECT * FROM aliases WHERE item_id = ?');
		$stmt->execute([$item_id]);
		
		$barcode_rows = $stmt->fetchAll();
		if($stmt->rowCount())
		{
			// add all barcodes to item
			foreach($barcode_rows as $row)
			{
				array_push($item->barcodes, $row['UPC']);
			}
		}
	}
	return $item;
}

function getShelfById($shelf_id, $pdo)
{
	$shelf = new Shelf();
	
	$stmt = $pdo->prepare('SELECT * FROM shelves WHERE id = ?');
	$stmt->execute([$shelf_id]);
	
	$shelf_result = $stmt->fetch();
	
	if($stmt->rowCount())
	{
		$shelf->id = $shelf_id;
		$shelf->label = $shelf_result['label'];
		$shelf->barcode = $shelf_result['barcode'];
	}
	
	return $shelf;
}

function getShelfByBarcode($barcode, $pdo)
{
	$shelf = new Shelf();
	
	$stmt = $pdo->prepare('SELECT * FROM shelves WHERE barcode = ?');
	$stmt->execute([$barcode]);
	
	$shelf_result = $stmt->fetch();
	
	if($stmt->rowCount())
	{
		$shelf->id = $shelf_result['id'];
		$shelf->label = $shelf_result['label'];
		$shelf->barcode = $barcode;
	}
	
	return $shelf;
}

function getShelfByLabel($label, $pdo)
{
	$shelf = new Shelf();
	
	$stmt = $pdo->preapare('SELECT * FROM shelves WHERE label = ?');
	$stmt->execute([$label]);
	
	$shelf_result = $stmt->fetch();
	
	if($stmt->rowCount())
	{
		$shelf->id = $shelf_result['id'];
		$shelf->label = label;
		$shelf->barcode = $shelf_result['barcode'];
	}
}

// This function is used to add the specified quantity of an item to a shelf
function addItem($item_id, $shelf_id, $quantity, $pdo)
{
	$stmt = $pdo->prepare('SELECT * FROM inventory WHERE item_id = ? AND shelf_id = ?');
	$stmt->execute([$item_id, $shelf_id]);
	$result = $stmt->fetch();
	
	// if row exists in inventory, add new quantity to current quantity of row
	if($stmt->rowCount())
	{
		$quantity += $result['quantity'];
		$stmt = $pdo->prepare('UPDATE inventory WHERE item_id = ? AND shelf_id = ? SET quantity = ?');
		$stmt->execute([$item_id, $shelf_id, $quantity]);
	}
	// if no row exists, check if item and shelf exist, then add to table
	else
	{
		$stmt = $pdo->prepare('SELECT * FROM items WHERE id = ?');
		$stmt->execute([$item_id]);
		$result = $stmt->fetch();
		
		// check if item id exists in items table
		if($stmt->rowCount())
		{
			$stmt = $pdo->prepare('SELECT * FROM shelves WHERE id = ?');
			$stmt->execute([$shelf_id]);
			$result = $stmt->fetch();
			
			// check if shelf id exists in shelves table
			if($stmt->rowCount())
			{
				$stmt = $pdo->prepare('UPDATE inventory WHERE item_id = ? AND shelf_id = ? SET quantity = ?');
				$stmt->execute([$item_id, $shelf_id, $quantity]);
			}
		}
	}
}

// This function is used to remove the specified quantity of an item from a shelf
function removeItem($item_id, $shelf_id, $quantity, $pdo)
{
	$stmt = $pdo->prepare('SELECT * FROM inventory WHERE item_id = ? AND shelf_id = ?');
	$stmt->execute([$item_id, $shelf_id]);
	
	$result = $stmt->fetch();
	
	if($stmt->rowCount())
	{
		$new_quantity = $result['quantity'] - $quantity;
		if($new_quantity < 0)
			$new_quantity = 0;
		
		$stmt = $pdo->prepare('UPDATE inventory WHERE item_id = ? AND shelf_id = ? SET quantity = ?');
		$stmt->execute([$item_id, $shelf_id, $new_quantity]);
	}
}

function updateItem($pdo)
{
	
}

function updateShelf($pdo)
{
	
}

function deleteItem($id, $pdo)
{
	$stmt = $pdo->prepare('DELETE FROM aliases WHERE item_id = ?');
	$stmt->execute([$id]);
	$stmt = $pdo->prepare('DELETE FROM inventory WHERE item_id = ?');
	$stmt->execute([$id]);
	$stmt = $pdo->prepare('DELETE FROM items WHERE id = ?');
	$stmt->execute([$id]);
}

function deleteShelf($id, $pdo)
{
	$stmt = $pdo->prepare('DELETE FROM inventory WHERE shelf_id = ?');
	$stmt->execute($id);
	$stmt = $pdo->prepare('DELETE FROM shelves WHERE id = ?');
	$stmt->execute([$id]);
}

function deleteAlias($barcode, $pdo)
{
	$stmt = $pdo->prepare('DELETE FROM aliases WHERE id = ?');
	$stmt->execute([$id]);
}

?>
