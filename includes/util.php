<?php 
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
