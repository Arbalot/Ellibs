<?php

/*
Name		: Form creator class
Version		: 0.2-alpha private snapshot
Published	: 19.10.2011 ....
Author		: Elmacik Bilgisayar
Web Site	: http://www.elmacik.com
*/

/* NOTES FOR USAGE:

	List type (select) elements may have an additional attrib key
	named "selected" to indicate which option(s) is/are selected as value.
	Because for this element, "value" key naturally does not contain the
	options that are selected. Instead, it stores all the possible
	values that this element might get.

	Form attributes can be controlled over options->attributes['form'] index.
	Therefore, "form" word must not be used as name for any element in the form,
	other than the form itself. Because it will overwrite the form attribs.
	It will also result form use the attribs of that element.

	Column count must be set before creating nodes.
	It can also be automatically adjusted by passing column var greater than 1.
*/

//TODO LIST:
/*
	- Implement into container template: alternative rows and datas
	- Multiple elements in one data_content
	- Implement insert_html() back

*/

/* CHANGELOG:
	0.2-alpha (Unstable private snapshot)
		+ Introduced form templating (use_table option is deprecated)
		+ Added option to divide form into columns
		+ Returning as a variable is possible now for easier template integrations
		- insert_html() function is omited in this version; will be added later.
		^ All selectable options are combined in one variable
		* A lot of re-working
	0.1-alpha (19.10.2011)
		First unstable private snapshot
*/

class Form
{
	public $options = array(
		'name' => '',
		'parser' => '', // action of the form
		'method' => 'post', // post or get
		'attributes' => array('form' => array()),
		'columns' => 1,
		'auto_column' => true,
		'use_names' => true, //TODO: Implement. If no lang defined, use node name instead
		'return_as_var' => true,
	);

	private $form;				private $counter = 0;
	private $nodes = array();	private $hidden_elements = array();

	public $container_template = array(
		'outer_main' => '',
		'inner_main' => '
		<table border="1" cellspacing="3" cellpadding="3" class="">
			{$CONTENT}
		</table>',
		'row' => '
			<tr>{$CONTENT}
			</tr>',
		'alt_row' => '',
		'data_subject' => '
				<td>{$CONTENT}</td>',
		'data_content' => '
				<td>{$CONTENT}
				</td>',
		'alt_data_sbj' => '',
		'alt_data_cnt' => '',
	);

	public function __construct($template = '')
	{
		// Just implement the template..
		if (!empty($template))
			$this->container_template = $template;
	}

	public function add_node($name, $type, $column = 1, $values = '', $attribs = array())
	{
		global $abc;

		// Automatically set column count according to passed vars
		if ($column > $this->options['columns'])
			$this->options['columns'] = $column;

		$this->options['attributes'][$name] = $attribs;

		// Hidden type needs no referring
		if ($type == 'hidden')
		{
			return $this->hidden_elements[$name] = '
			<input type="hidden" name="' . $name . '" value="' . $values . '" />';
		}

		$data_subject = isset($abc[$name]) ? $abc[$name] : $name;//ROLL'&nbsp;';
		$data_subject = str_replace('{$CONTENT}', $data_subject, $this->container_template['data_subject']);

		if ($type == 'longtext')
			$data_content = '<textarea name="' . $name . '" rows"5">' . $values . '</textarea>';

		elseif ($type == 'list')
		{
			if (!is_array($values))
				trigger_error('Liste tipinde element eklenirken $values degiskeni liste elemanlarini iceren bir dizi olmali', E_USER_ERROR);

			$selected_options = !isset($attribs['selected']) ? array() : arrayalize($attribs['selected']);

			$data_content = '
					<select name="' . $name . '">';
			if (!empty($values))
				foreach ($values as $var => $option)
					$data_content .= '
						<option value="' . $var . '"' . (in_array($var, $selected_options) ? ' selected="selected"' : '') . '>' . $option . '</option>';
			$data_content .= '
					</select>';
		}
		elseif ($type == 'radio')
		{
			arrayalize($values);
			$options = count($values);
			$data_content = '';

			for ($i = 0; $i < $options; $i++)
				$data_content .= '
					<input type="radio" name="'. $name . '" value="' . $values[$i] . '" /> ' . $abc[$name . '_' . $i] . '<br />';
		}
		elseif ($type == 'checkbox') //TODO: MULTI-CHECKBOX support to be tested
		{
			$data_content = '';
			if (!is_array($values))
				$data_content .= '
					<input type="checkbox" name="' . $name . '" value="' . $values . '" />';
			else
				foreach($values as $key => $val)
				{
					if(substr($key, 0, 2) == 'hr')
						$data_content .= '
						<br /><b>'. $val . '</b><hr />';
					else
						$data_content .= '
					<input type="checkbox" name="' . $name . '[]" value="' . $key . '" /> '. $val .'<br />';
				}
		}
		else
		  	$data_content = '
					<input type="' . $type . '" name="' . $name . '" value="' . $values . '" />';

		$data_content = str_replace('{$CONTENT}', $data_content, $this->container_template['data_content']);

		// Build the column or reserve the value to split columns later
		$this->nodes[$column][] = array('name' => $name, 'content' => $this->options['columns'] < 2 ? str_replace('{$CONTENT}', $data_subject . $data_content, $this->container_template['row']) : $data_subject . $data_content);
	}

	//TODO: Re-work and re-build insert_html()
	/*public function insert_html($type, $code = '', $insideof = '', $attribs = array())
	{
		if (!$this->use_table && !empty($insideof))
			trigger_error('Tablo kullanylmayan formlarda enjeksiyon yapylamaz; $insideof de?i?keni bo? olmalydyr', E_USER_ERROR);

		$content = $type != 'raw' ? '<' . $type . ' rel="' . $this->counter . '"' . $this->attrib_query($attribs) . '>' . $code . '</' . $type . '>' : $code;

		if (!empty($insideof))
		{
			list ($name, $position) = explode(':', $insideof);
			$this->nodes[$column][$name] = str_replace('</td' . $position . '>', $content . '</td' . $position . '>', $this->nodes[$name]);
		}
		else
			$this->nodes[$this->counter] = $content;
		$this->counter++;
	}
	*/

	public function create_from_array($elements, $build = false)
	{
		foreach ($elements as $item => $element)
			$this->add_node(
				$item,
				(isset($element['type']) ? $element['type'] : 'text'),
				(isset($element['column']) ? $element['column'] : 1),
				(isset($element['value']) ? $element['value'] : ''),
				(isset($element['attribs']) ? $element['attribs'] : array())
			);

		if ($build)
			$this->build();
	}

	public function build()
	{
		pre($this->nodes);
		$this->form = '';

		if ($this->options['columns'] > 1)
		{
			// Find row count
			$rows = $row_count = array();
			foreach ($this->nodes as $col)
				$row_count += array_keys($col);
			$row_count = max($row_count) + 1;

			for ($ir = 0; $ir < $row_count + 1; $ir++)
			{
				$rows[$ir] = '';
				for ($ic = 1; $ic <= $this->options['columns']; $ic++)
				{
					if (isset($this->nodes[$ic][$ir]))
					{
						$name = $this->nodes[$ic][$ir]['name'];
						$node = $this->nodes[$ic][$ir]['content'];

						if (isset($this->options['attributes'][$name]))
						{
							$attrib_query = attrib_query($this->options['attributes'][$name]) . (!is_numeric($name) ? 'name="' : '');
							$search_str = !is_numeric($name) ? 'name="' : "rel=\"(\d+)\""; //TODO: Clean this code!
							$node = preg_replace("~$search_str~", $attrib_query, $node);
						}
						$rows[$ir] .= $node;
					}
					else
						$rows[$ir] .= 'TEST';
						//	str_replace('{$CONTENT}', '&nbsp;', $this->container_template['data_subject']) .
							//str_replace('{$CONTENT}', '&nbsp;', $this->container_template['data_content']);
				}
				$this->form .= str_replace('{$CONTENT}', $rows[$ir], $this->container_template['row']);
			}
		}
		else // Only one column, easy eh?
			foreach ($this->nodes[1] as $node)
				$this->form .= str_replace('{$CONTENT}', $node['content'], $this->container_template['row']);

		if (!empty($this->container_template['inner_main']))
			$this->form = str_replace('{$CONTENT}', $this->form, $this->container_template['inner_main']);

		$hidden_elements = '';
		if (!empty($this->hidden_elements))
			foreach ($this->hidden_elements as $name => $hidden_element)
				$hidden_elements .= str_replace('name="', @attrib_query($this->options['attributes'][$name]) . 'name="', $hidden_element);

		$this->form = '
		<form name="' . $this->options['name'] . '" action="' . $this->options['parser'] . '" method="' . $this->options['method'] . '" ' . attrib_query($this->options['attributes']['form']) . '>' .
			$hidden_elements . $this->form . '
		</form>';

		if (!empty($this->container_template['outer_main']))
			$this->form = str_replace('{$CONTENT}', $this->form, $this->container_template['outer_main']);

		//if ($this->options['return_as_var'])
		//	return 'aaa';//$this->form;
		//else
			echo $this->form;
	}

	function set($option, $value, $element = '')
	{
		if ($option == 'attributes')
		{
			list ($option, $value) = explode('=', $value);
			$this->options['attributes'][$element][$option] = $value;
		}
		else
			$this->options[$option] = $value;
	}
}

function attrib_query($attribs)
{
	$query = '';
	foreach ($attribs as $attrib => $value)
		$query .= "$attrib=\"$value\" ";
	return $query;
}

function arrayalize(&$parameter = array())
{
	if (!is_array($parameter))
		$parameter = array($parameter);
	return $parameter;
}

?>