<?php

/*
Name		: MySQL Manipulation Class
Version		: 0.2-alpha
Published	: 09.05.2012
Author		: Elmacik Bilgisayar
Web Site	: http://www.elmacik.com
Explanation	:

Changelog:

0.2-alpha (private-alpha:09.05.2012)
	+ Added array or object returning types for fetching functions
	^ Moved options into one global variable for easier usage
	^ A set of very little improvements
	

0.1-alpha (private-alpha:19.10.2011)
	% First private release, alpha state


USAGE NOTES:

	* If you are going to return the fetched results as an object,
	you will need to call $db->free_result() in an appropriate place.
*/

class MySQL
{
	protected $connection;
	protected $query_count = 0;
	private $db_env = array(
		'connected' => false,
		'conn_error' => '',
		'current_db' => '', // Active database, changes according to select_db() function
		'debug_msgs' => array(),
	);

	private $options = array(
		'api_type' => 'mysqli', // In which method this class will interact to DB server. mysql & mysqli are supported now
		'auto_connect' => true, // Connect to DB server as soon as an instance of the class is created. SERVER PARAMS MUST BE SET
		'auto_select_db' => true, // Automatically select the specified database if a connection is established. MAINLY FOR mysql API
		'auto_escape' => true, // Automatically escape query strings for use with SQL. RECOMMENDED! Disable if you use your own methods
		'new_link' => false, // Create a new connection to DB even if there is still an alive one. DEV NOTE: IMPLEMENT BELOW!
		'disconnect_on_exit' => false, // Disconnect from the DB server when the script ends. NOT REALLY NECESSARY
		'persistent' => false, // Create a persistent connection to the MySQL server. NOT RECOMMENDED!!
		'halt_on_fail' => true, // Halt the script and cast an error if a connection to the server cannot be established.
		'debug_mode' => true, // Output some debugging info that might help to developer to overcome some issues
		// Server parameters for connection
		'server' => 'localhost',
		'user' => 'root',
		'password' => '',
		'server_port' => '', // Ignored if server_socket is present in mysql api_type
		'server_socket' => '',
		'database' => 'isletme',
		'charset' => 'latin5', // Leave empty to use default charset of the server. Must be a valid MySQL charset
	);

	private $bool_vars	= array(
		'auto_connect', 'auto_select_db', 'auto_escape', 'new_link',
		'disconnect_on_exit', 'persistent', 'halt_on_fail', 'debug_mode'
	);
	private $str = array();

	//TODO: Add flag support

	/* $connection_parameters is an array consisting of server credentials
	and other options that can be set during runtime.
	Any options passed to this function override the global variables */
	function __construct($connection_parameters = array())
	{
		// Initialize lang strings..
		$this->str += array(
			'mysqli_na' => 'API type is set to MySQL Improved Extension (mysqli)<br>but it is not available on this server, therefore "api_type" option should be set to "mysql"<br>',
			'api_na' => 'An unimplemented api_type "%s" is set',
			'no_auto_db' => 'It is set to connect automatically but no default database is specified.',
			'no_conn_error'	=> 'A connection to the server couldn\'t be established, program halted!<br><b>Reason:</b> %s',
			'no_conn_warning' => 'A connection to the server couldn\'t be established, database functions will not work',
			'no_db_to_select' => 'There is no database to work with, program halted',
			'conn_not_dead' => 'There was an error occured while trying to close DB connection',
			'api_change_forbidden' => 'Api type cannot change while a connection is alive',
			'unknown_option' => 'Unknown option "%s" was casted and it was ignored',
			'query_error' => 'SQL query was NOT executed successfully.<br>Errenous query was executed in file <b>%1s</b> on line <b>%2d</b><br>Query was: %3s<br>Result was: %4s',
			'db_selection_fail' => 'Cannot work on "%1s" database. Reason: %2s',
		);

		if (!empty($connection_parameters))
			foreach ($connection_parameters as $key => $value)
				$this->set($key, $value);

		// mysqli is not installed, revert back to mysql
		if ($this->options['api_type'] == 'mysqli' && !function_exists('mysqli_connect'))
		{
			$this->options['api_type'] = 'mysql';
			if ($this->options['debug_mode'])
				$this->warn('mysqli_na', E_USER_NOTICE);
		}

		// Default connection, if possible
		if ($this->options['auto_connect'])
			$this->connect(); // Disconnect?? Or this->connect :)
	}

	function connect()
	{
		// If already connected, do nothing
		if ($this->connection)
			return $this->connection;

		// Valid api_types are mysql and mysqli. DEV NOTE: PDO_MySQL to add later
		if (!in_array($this->options['api_type'], array('mysql', 'mysqli')))
			$this->warn(array('api_na', $this->options['api_type']), E_USER_ERROR);
		$this->{$this->options['api_type'] . 'connect'}();

		if ($this->db_env['connected'])
		{
			// No database specified
			if ($this->options['auto_select_db'] && empty($this->options['database']))
				$this->warn('no_auto_db', E_USER_NOTICE);
			if (!empty($this->options['charset']))
				$this->set_charset($this->options['charset']);
		}
		elseif ($this->options['halt_on_fail'])
			$this->warn(array('no_conn_error', $this->db_env['conn_error']), E_USER_ERROR);
		elseif ($this->options['debug_mode'])
			$this->warn('no_conn_warning', E_USER_WARNING);
	}

	private function mysqlconnect()
	{
		$connection_function = !$this->options['persistent'] ? 'mysql_connect' : 'mysql_pconnect';
		$connection_parameters = array(
			$this->options['server'] . ':' . esor($this->options['server_socket'], $this->options['server_port']),
			$this->options['user'],
			$this->options['password']
		);
		$this->connection = @call_user_func_array($connection_function, $connection_parameters);

		if ($this->connection)
		{
			if ($this->options['auto_select_db'] && !empty($this->options['database']))
				$this->select_db();
			return ($this->db_env['connected'] = true);
		}
		else return !($this->db_env['conn_error'] = mysql_error());
	}

	private function mysqliconnect()
	{
		$connection_parameters = array(
			(!$this->options['persistent'] ? '' : 'p:') . $this->options['server'],
			$this->options['user'],
			$this->options['password'],
			$this->options['auto_select_db'] ? $this->options['database'] : '',
			esor($this->options['server_port'], 3306), // Back  to default port if not specified
			$this->options['server_socket']
		);

		$this->connection = @call_user_func_array('mysqli_connect', $connection_parameters);
		$this->db_env['conn_error'] = mysqli_connect_error();

		if (empty($this->db_env['conn_error']) && $this->connection)
		{
			$this->db_env['connected'] = true;
			if ($this->options['auto_select_db'] && !empty($this->options['database'] ))
				$this->db_env['current_db'] = $this->options['database'];
			return true;
		}
		else return false;
	}

	// Anonymous function to call built-in functions
	function __()
	{
		$parameters = func_get_args();
		$function = $this->options['api_type'] . '_' . $parameters[0];

		// If there is only one parameter, then its a function needing the link as its parameter
		func_num_args() < 2 ? $parameters = array($this->connection) : array_shift($parameters);
		return call_user_func_array($function, $parameters);
	}

	function select_db($database = '')
	{
		$params = array('select_db');
		$db = esor($database, $this->options['database']);

		if (empty($db))
			return $this->warn('no_db_to_select', E_USER_ERROR);
		if ($this->options['api_type'] == 'mysqli')
			$params[] = $this->connection;
		$params[] = $db;

		if (!call_user_func_array(array($this, '__'), $params))
			return $this->warn(array('db_selection_fail', $db, call_user_func($this->options['api_type'] . '_error')), E_USER_WARNING);
		$this->db_env['current_db'] = $db;
	}

	function set($option, $value)
	{
		// Set if it is a valid option
		if (in_array($option, array_keys($this->options)))
		{
			// Boolean values can be set in different formats
			if (in_array($option, $this->bool_vars) && !is_bool($value))
				$this->options[$option] = in_array(strtolower(trim($value)), array('on', 1, '1', 'yes', 'true', 'y', 'enable')) ? true : false;
			else
			{
				// Ignore api_type changes during runtime; this option can be set only before any transaction occurs.
				if ($option == 'api_type' && $this->db_env['connected'])
					$this->warn('api_change_forbidden', E_USER_NOTICE);
				else
					$this->options[$option] = $value;
				if ($option == 'charset' && $this->db_env['connected']) // Dynamic charset changing
					$this->set_charset($value);
			}
		}
		else
			$this->warn(array('unknown_option', $option), E_USER_NOTICE);
	}

	function set_charset($charset)
	{
		$params['mysql'] = array('set_charset', $charset, $this->connection);
		$params['mysqli'] = array('set_charset', $this->connection, $charset);

		// This is the easier and recommended way
		if (version_compare($this->__('get_server_info'), '5.0.7') >= 0)
			return call_user_func_array(array($this, '__'), $params[$this->options['api_type']]);
		else // This is the old way
			return $this->query('SET NAMES ' . $charset);
	}

	function get($variable)
	{
		$allowed_direct_vars = array('query_count', 'db_env'); // Public & private variables that user is allowed to get
		$forbidden_vars = array('password'); // Forbidden vars to get

		if (in_array($variable, $allowed_direct_vars))
			return $this->{$variable};
		elseif (!in_array($variable, $forbidden_vars) && isset($this->options[$variable]))
			return $this->options[$variable];
		elseif ($this->options['debug_mode'])
			trigger_error('A non-set or forbidden variable was tried to be read', E_USER_WARNING);
	}

	// Type can be "row", "array" or "assoc" for respectively the functions in the same name
	function fetch($result, $type = 'assoc', $return_as_object = false)
	{
		if ($return_as_object)
			return $this->__('fetch_' . $type, $result);

		$fetch = array();
		while ($row = $this->__('fetch_' . $type, $result))
			$type != 'row' ? $fetch[] = $row : $fetch = $row;
		$this->free_result($result);
		return $fetch;
	}

	function escape($query)
	{
		//TODO: Maybe call directly "api_type_"real_escape_string rather than "__"
		$params['mysql'] = array('real_escape_string', $query, $this->connection);
		$params['mysqli'] = array('real_escape_string', $this->connection, $query);
		return call_user_func_array(array($this, '__'), $params[$this->options['api_type']]);
	}

	function insert_row($table, $data, $method = 'INSERT')
	{
		if (!is_array($data) || empty($data))
			return false; // DEV NOTE: Implement warn()

		$methods = array('INSERT', 'INSERT IGNORE', 'REPLACE'); // Valid methods
		$types = array('str', 'txt', 'int', 'num', 'dbl', 'flt', 'raw'); // Valid data types
		$query = (in_array($method, $methods) ? $method : 'INSERT') . ' INTO ' . $table . ' (`';
		$query .= implode('`, `', array_keys($data)) . '`) VALUES(';

		/* $data must be formatted like:
			$data = array(
				'column_name' => 'type[int|flt|str|raw]:value',
				'other_column' => 'type:value',
				'another_column' => 'value' // Uses default type.
					...
			);
			NOTE: type defaults to string if not defined
			NOTE: Values must be escaped for SQL!
		*/
		foreach ($data as $dummy => $column)
		{
			$possible_info = str_split(substr($column, 0, 4), 3);
			list ($type, $value) = count($possible_info) > 1 ? ($possible_info[1] != ':' ? array('str', $column) : array($possible_info[0], substr($column, 4))) : array('str', $column);

			// False match possibilities
			if (!in_array($possible_info[1], $types))
				$type = 'str'; // Defaults back to string again

			switch ($type)
			{
				case 'str': case 'txt': default:
					$query .= "'$value', "; break;
				case 'num': case 'int':
					$query .= intval($value) . ', '; break;
				case 'dbl': case 'flt':
					$query .= floatval($value) . ', '; break;
				case 'raw': // DEV NOTE: Security issues!!!
					$query .= $value . ', '; break;
			}
		}
		$query = substr($query, 0, -2) . ')';
		return $this->query($query) ? $this->insert_id() : false;
	}

	function update_row($table, $data, $variable, $value)
	{
		$string = '';

		foreach($data as $key => $val)
		{
			if(!empty($val))
				$string .= $key.' = \''.$val.'\',';
		}

		$string = substr($string, 0, -1);
		$query = 'UPDATE ' . $table . ' SET ' . $string . ' WHERE ' . $variable . ' = ' . $value;
		$request = $this->query($query);

		return $request;
}

	function delete_row($table, $variable, $value, $condition = '')
	{
		//BUG: Table prefixes will not work here! And maybe elsewhere!
		$request = $this->query("
			DELETE FROM `$table`
			WHERE `$variable` = '$value'" . (!empty($condition) ? "
				AND $condition" : "")
		);
		return $this->affected_rows();
	}

	function query($query, $pre = false)
	{
		if($pre) pre($query,1);

		//TODO: Prefix adding operation is going to take place here
		//TODO: Lang aware prefixes to implement!
		$query = str_replace('{table}', '', $query);
		$params = array($query);

		if ($this->options['api_type'] == 'mysqli')
			array_unshift($params, $this->connection);
		if ($this->options['auto_escape'])
			$query = $this->escape($query);

		$dbquery = call_user_func_array($this->options['api_type'] . '_query', $params);
		$this->query_count++;

		$error = $this->__('error');
		if (!empty($error))
		{
			$trace = debug_backtrace();
			$this->warn(array('query_error', $trace[0]['file'], $trace[0]['line'], $query, $error), E_USER_WARNING);
		}
		return $dbquery;
	}

	function warn($code, $type = E_USER_NOTICE, $log = '')
	{
		if (is_array($code))
			$code[0] = $this->str[$code[0]];
		return trigger_error(!is_array($code) ? (isset($this->str[$code]) ? $this->str[$code] : $code) : call_user_func_array('sprintf', $code), $type);
	}

	function is_connected($strict = false) {
		return $strict ? $this->__('ping') : $this->db_env['connected'];
	}

	function free_result($result) {
		return $this->__('free_result', $result);
	}

	function affected_rows() {
		return $this->__('affected_rows');
	}

	function num_rows($result) {
		return $this->__('num_rows', $result);
	}

	function insert_id() {
		return $this->__('insert_id');
	}

	function close()
	{
		if (call_user_func($this->options['api_type'] . '_close', $this->connection))
			$this->db_env['connected'] = false;
		else // Really necessary?
			$this->warn('conn_not_dead', E_USER_ERROR);
	}

	function __destruction()
	{
		// Not so tricky, huh?
		if ($this->options['disconnect_on_exit'] && !$this->options['persistent'])
			$this->__('close');
	}

}

/*
Takes a number of variables and returns the first variable's value that is not empty
It is left associative and ignores other filled vars when it finds the first from left.
*/
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

?>