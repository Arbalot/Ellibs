<?php

function dbquery($query,$pre=null)
{
	global $connection, $settings;

	if(isset($pre) && $pre == 1)
		pre($query,1);

	$lang_aware_tables = array('content', 'name_details', 'tags', 'events', 'origins', 'languages');
	preg_match_all('~\{table\}(\w+)~', $query, $query_tables);

	foreach ($query_tables[1] as $index => $table)
		if (in_array($table, $lang_aware_tables))
			$query = str_replace($query_tables[0][$index], $settings['lang_prefix'] . '_' . $table, $query);

	// DEV NOTE: Prefix eklenecekse burada islem yapilabilir
	$query = str_replace('{table}', '', $query);

	//$q = mysql_query("insert into sorgular (sorgu) values('$query')");

	$dbquery = mysql_query($query, $connection) or die(mysql_error());

	return $dbquery;
}

// This is not associative for more than 2 level deep arrays!
function secure_input(&$inputs)
{
	foreach ($inputs as $key => $values)
	{
		if (is_array($values))
			foreach ($value as $vkey => $value)
				$values[utf8_encode(htmlentities($vkey, ENT_QUOTES))] = utf8_encode(htmlentities($value, ENT_QUOTES));

		$key = utf8_encode(htmlentities($key, ENT_QUOTES));

		if (!is_array($values))
			$values = utf8_encode(htmlentities($values, ENT_QUOTES));

		$inputs[$key] = $values;
	}
	return $inputs;
}

function show_notification($msg, $type = 'success', $width = 100)
{
	global $txt;

	$types = array('success', 'information', 'attention', 'error');

	if (!in_array($type, $types))
		$type = 'attention';

	echo '
		<div class="notification ' . $type . ' png_bg" style="width: ' . $width . ' !important;">
			<a href="#" class="close"><img src="style/images/icons/cross_grey_small.png" title="' . $txt['close_notification'] . '" alt="close" /></a>
			<div>
				' . (isset($txt[$msg]) ? $txt[$msg] : $msg) . '
			</div>
		</div>';
}

// For shortening string by the given number; a fast and nasty go for substr
function shorten($string, $url = false, $lenght = 100)
{
	// Any speed benefit?
	if (strlen($string) < $lenght)
		return $string;

	return mb_substr($string, 0, $lenght);
}

?>