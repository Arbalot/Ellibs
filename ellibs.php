<?php


class Ellibs
{

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





?>