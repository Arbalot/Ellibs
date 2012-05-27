<?php

error_reporting(E_ALL);

$start = microtime(true);

var_dump(esor(array(), array()));

function esor()
{
	$arg_num = func_num_args();
	// "No arguments" sets var to empty string
	if (!$arg_num) return '';
	$args = func_get_args();

	for ($i = 0; $i < $arg_num; $i++)
		if (!empty($args[$i]))
			return $args[$i];

	// Not found any filled var?
	return ''; // Empty string is what you get
}

function pre($arg, $die = false)
{
	echo '<br><pre>';
	if (is_array($arg))
		print_r($arg);
	else echo $arg;
	echo '</pre><br>';


}

$end = microtime(true);
$passedtime = $end - $start;

echo '<table border="1"><tr><td>Start Time</td><td>' . $start . '</td></tr><td>End Time</td><td>' . $end . '</td></tr><tr><td>Lolo</td><td>' . $passedtime . '</td></tr></table>';









?>