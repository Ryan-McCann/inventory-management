<?php
require("includes/Item.php");
require("includes/Shelf.php");
require("includes/ShelfQuantity.php");
require("includes/util.php");

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

if(isset($_POST['type']))
{
	if(isset($_POST['token']))
		$token = $_POST['token'];
	else if(isset($_COOKIE['token']))
		$token = $_COOKIE['token'];
	
	$stmt = $pdo->prepare('DELETE FROM tokens WHERE expires < now()');
	$stmt->execute([]);
	$stmt = $pdo->prepare('SELECT * FROM tokens WHERE token = ?');
	$stmt->execute([$token]);
	
	if($stmt->rowCount())
	{
		switch($_POST['type'])
		{
			case 'item':
				$item = new Item();
				
				if(isset($_POST['barcode']))
				{
					$item = getItemByBarcode($_POST['barcode'], $pdo);
				}
				else if(isset($_POST['itemId']))
				{
					$item = getItemById($_POST['itemId'], $pdo);
				}
				
				// echo result as json
				echo(json_encode(get_object_vars($item), JSON_PRETTY_PRINT));
				
				break;
			case 'items':
				$items = getItems($pdo);
				
				echo(json_encode($items, JSON_PRETTY_PRINT));
				break;
			case 'shelf':
				$shelf = new Shelf();
				
				if(isset($_POST['barcode']))
				{
					$shelf = getShelfByBarcode($_POST['barcode'], $pdo);
				}
				else if(isset($_POST['label']))
				{
					$shelf = getShelfByLabel($_POST['label'], $pdo);
				}
				else if(isset($_POST['shelfId']))
				{
					$shelf = getShelfById($_POST['shelfId'], $pdo);
				}
				
				// echo result as json
				echo(json_encode(get_object_vars($shelf), JSON_PRETTY_PRINT));
				
				break;
			case 'shelves':
				$shelves = getShelves($pdo);
				
				echo(json_encode($shelves, JSON_PRETTY_PRINT));
				break;
			case 'shelfItems':
				$items = [];
				if(isset($_POST['shelfId']))
				{
					$items = getItemsByShelf($_POST['shelfId'], $pdo);
					
					echo(json_encode($items, JSON_PRETTY_PRINT));
				}
				break;
			case 'shelfItemQuantity':
				if(isset($_POST['itemId']) && isset($_POST['shelfId']))
					echo(getShelfItemQuantity($_POST['itemId'], $_POST['shelfId'], $pdo));
				break;
			case 'createShelf':
				if(isset($_POST['label']))
					createShelf($_POST['label'], $pdo);
				break;
			case 'createItem':
				if(isset($_POST['description']) && isset($_POST['barcode']))
				{
					$minimum = 0;
					$maximum = 0;
					
					$description = $_POST['description'];
					$barcode = $_POST['barcode'];
					
					$stmt = $pdo->prepare("SELECT * FROM aliases WHERE UPC = ?");
					$stmt->execute([$barcode]);
					
					// If barcode doesn't already exist in database
					if(!$stmt->rowCount())
					{
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
				}
				break;
			case 'createAlias':
				if(isset($_POST['itemId']) && isset($_POST['barcode']))
					createAlias($_POST['barcode'], $_POST['itemId'], $pdo);
				break;
			case 'updateItem':
				if(isset($_POST['itemId']) && isset($_POST['description']) && isset($_POST['minimum']) && isset($_POST['maximum']))
					updateItem($_POST['itemId'], $_POST['description'], $_POST['minimum'], $_POST['maximum'], $pdo);
				break;
			case 'addItem':
				if(isset($_POST['itemId']) && isset($_POST['quantity']) && isset($_POST['shelfId']))
					addItem($_POST['itemId'], $_POST['shelfId'], $_POST['quantity'], $pdo);
				break;
			case 'updateShelf':
				if(isset($_POST['shelfId']) && isset($_POST['label']) && isset($_POST['barcode']))
					updateShelf($_POST['shelfId'], $_POST['label'], $_POST['barcode'], $pdo);
				break;
			case 'removeItem':
				if(isset($_POST['itemId']) && isset($_POST['quantity']) && isset($_POST['shelfId']))
					removeItem($_POST['itemId'], $_POST['shelfId'], $_POST['quantity'], $pdo);
				break;
			case 'deleteItem':
				if(isset($_POST['itemId']))
					deleteItem($_POST['itemId'], $pdo);
				break;
			case 'deleteShelf':
				if(isset($_POST['shelfId']))
					deleteShelf($_POST['shelfId'], $pdo);
				break;
			case 'deleteAlias':
				if(isset($_POST['barcode']))
					deleteAlias($_POST['barcode'], $pdo);
				break;
			default:
				echo('Invalid request type.');
				break;
		}
	}
}

function createItem($description, $minimum, $maximum, $pdo)
{
	$stmt = $pdo->prepare('INSERT INTO items (description, minimum, maximum) VALUES (?, ?, ?)');
	$stmt->execute([$description, $minimum, $maximum]);
	return $pdo->lastInsertId();
}

function createShelf($label, $pdo)
{
	// Check if label already exists in shelf
	$stmt = $pdo->prepare('SELECT * FROM shelves WHERE label = ?');
	$stmt->execute([$label]);
	
	if(!$stmt->rowCount())
	{
	
		$barcode = 0;
		$row_count = 0;
		
		// pseudo-randomly generate an unused barcode
		do
		{
			$barcode = rand(pow(10, 7), pow(10, 8)-1);
			
			$stmt = $pdo->prepare('SELECT * FROM shelves WHERE barcode = ?');
			$stmt->execute([$barcode]);
			$row_count = $stmt->rowCount();
		}
		while ($row_count);
		
		$stmt = $pdo->prepare('INSERT INTO shelves (label, barcode) VALUES (?, ?)');
		$stmt->execute([$label, $barcode]);
	}
}

function createAlias($barcode, $item_id, $pdo)
{
	$stmt = $pdo->prepare('INSERT INTO aliases (UPC, item_id) VALUES (?, ?)');
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
				$shelf_quantity = new ShelfQuantity();
				$shelf = getShelfById($row['shelf_id']);
				
				$shelf_quantity->id = $shelf->id;
				$shelf_quantity->label = $shelf->label;
				$shelf_quantity->barcode = $shelf->barcode;
				$shelf_quantity->item_quantity = $row['quantity'];
				
				array_push($item->shelves, $shelf_quantity);
			}
		}
		
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

function getItems($pdo)
{
	$items = [];
	
	$stmt = $pdo->prepare('SELECT * FROM items');
	$stmt->execute();
	$item_rows = $stmt->fetchAll();
	
	foreach($item_rows as $row)
	{
		$item = new Item();
		$item->id = $row['id'];
		$item->description = $row['description'];
		$item->minimum = $row['minimum'];
		$item->maximum = $row['maximum'];
		
		$stmt = $pdo->prepare('SELECT * FROM aliases WHERE item_id = ?');
		$stmt->execute([$item->id]);
		
		$barcode_rows = $stmt->fetchAll();
		
		foreach($barcode_rows as $barcode_row)
		{
			array_push($item->barcodes, $barcode_row['UPC']);
		}
		
		$stmt = $pdo->prepare('SELECT * FROM inventory WHERE item_id = ?');
		$stmt->execute([$item_id]);
		$quantities = $stmt->fetchAll();
		
		$item->quantity = 0;
		if($stmt->rowCount())
		{
			// iterate over rows and get the total quantity of item in inventory
			foreach($quantities as $quant_row)
			{
				$item->quantity += $quant_row['quantity'];
				$shelf_quantity = new ShelfQuantity();
				$shelf = getShelfById($quant_row['shelf_id']);
				
				$shelf_quantity->id = $shelf->id;
				$shelf_quantity->label = $shelf->label;
				$shelf_quantity->barcode = $shelf->barcode;
				$shelf_quantity->item_quantity = $quant_row['quantity'];
				
				array_push($item->shelves, $shelf_quantity);
			}
		}
		
		array_push($items, $item);
	}
	
	return $items;
}

function getItemsByShelf($shelf_id, $pdo)
{
	$items = [];
	$stmt = $pdo->prepare('SELECT * FROM inventory WHERE shelf_id = ?');
	$stmt->execute([$shelf_id]);
	
	$item_results = $stmt->fetchAll();
	
	if($stmt->rowCount())
	{
		foreach($item_results as $item_result)
		{
			$item = getItemById($item_result['item_id'], $pdo);
			$item->quantity = $item_result['quantity'];
			array_push($items, $item);
		}
	}
	
	return $items;
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

function getShelves($pdo)
{
	$shelves = [];
	
	$stmt = $pdo->prepare('SELECT * FROM shelves');
	$stmt->execute();
	
	$shelf_results = $stmt->fetchAll();
	
	foreach($shelf_results as $row)
	{
		$shelf = new Shelf();
		$shelf->id = $row['id'];
		$shelf->label = $row['label'];
		$shelf->barcode = $row['barcode'];
		
		array_push($shelves, $shelf);
	}
	
	return $shelves;
}

function getShelfByLabel($label, $pdo)
{
	$shelf = new Shelf();
	
	$stmt = $pdo->prepare('SELECT * FROM shelves WHERE label = ?');
	$stmt->execute([$label]);
	
	$shelf_result = $stmt->fetch();
	
	if($stmt->rowCount())
	{
		$shelf->id = $shelf_result['id'];
		$shelf->label = $label;
		$shelf->barcode = $shelf_result['barcode'];
	}
	
	return $shelf;
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
		$stmt = $pdo->prepare('UPDATE inventory SET quantity = ? WHERE item_id = ? AND shelf_id = ?');
		$stmt->execute([$quantity, $item_id, $shelf_id]);
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
				$stmt = $pdo->prepare('INSERT INTO inventory(item_id, shelf_id, quantity) VALUES (?, ?, ?)');
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
		if($new_quantity <= 0)
		{
			$stmt = $pdo->prepare('DELETE FROM inventory WHERE item_id = ? AND shelf_id = ?');
			$stmt->execute([$item_id, $shelf_id]);
		}
		else
		{
			$stmt = $pdo->prepare('UPDATE inventory SET quantity = ? WHERE item_id = ? AND shelf_id = ?');
			$stmt->execute([$new_quantity, $item_id, $shelf_id]);
		}
	}
}

function updateItem($id, $description, $minimum, $maximum, $pdo)
{
	$stmt = $pdo->prepare('UPDATE items SET description = ?, minimum = ?, maximum = ? WHERE id = ?');
	$stmt->execute([$description, $minimum, $maximum, $id]);
}

function updateShelf($id, $label, $barcode, $pdo)
{
	$stmt = $pdo->prepare('UPDATE shelves SET label = ?, barcode = ? WHERE id = ?');
	$stmt->execute([$label, $barcode, $id]);
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
	$stmt->execute([$id]);
	$stmt = $pdo->prepare('DELETE FROM shelves WHERE id = ?');
	$stmt->execute([$id]);
}

function deleteAlias($barcode, $pdo)
{
	$stmt = $pdo->prepare('DELETE FROM aliases WHERE UPC = ?');
	$stmt->execute([$barcode]);
}

?>
