<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Low Search Tag Extension class
 *
 * @package        low_search_tag
 * @author         Lodewijk Schutte ~ Low <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-search
 * @copyright      Copyright (c) 2013, Low
 */
class Low_search_tag_ext {

	// --------------------------------------------------------------------
	// PROPERTIES
	// --------------------------------------------------------------------

	/**
	 * Extension settings
	 *
	 * @access      public
	 * @var         array
	 */
	public $settings = array();

	/**
	 * Extension name
	 *
	 * @access      public
	 * @var         string
	 */
	public $name = 'Low Search Tag';

	/**
	 * Extension version
	 *
	 * @access      public
	 * @var         string
	 */
	public $version = '0.9.1';

	/**
	 * Extension description
	 *
	 * @access      public
	 * @var         string
	 */
	public $description = 'Enables Low Search and Solspace Tag compatibility';

	/**
	 * Do settings exist?
	 *
	 * @access      public
	 * @var         bool
	 */
	public $settings_exist = FALSE;

	/**
	 * Documentation link
	 *
	 * @access      public
	 * @var         string
	 */
	public $docs_url = '#';

	// --------------------------------------------------------------------

	/**
	 * EE Instance
	 *
	 * @access      private
	 * @var         object
	 */
	private $EE;

	/**
	 * Current class name
	 *
	 * @access      private
	 * @var         string
	 */
	private $class_name;

	/**
	 * Current site id
	 *
	 * @access      private
	 * @var         int
	 */
	private $site_id;

	/**
	 * Hooks used
	 *
	 * @access      private
	 * @var         array
	 */
	private $hooks = array(
		'low_search_pre_search'
	);

	/**
	 * Default settings
	 *
	 * @access      private
	 * @var         array
	 */
	private $default_settings = array();

	/**
	 * Parameters
	 *
	 * @access      private
	 * @var         array
	 */
	private $params = array();

	// --------------------------------------------------------------------
	// METHODS
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access     public
	 * @param      mixed     Array with settings or FALSE
	 * @return     null
	 */
	public function __construct($settings = array())
	{
		// Get global instance
		$this->EE =& get_instance();

		// Get site id
		$this->site_id = $this->EE->config->item('site_id');

		// Set Class name
		$this->class_name = ucfirst(get_class($this));

		// Set settings
		$this->settings = array_merge($this->default_settings, $settings);
	}

	// --------------------------------------------------------------------

	/**
	 * Check for Solspace Tag parameters
	 *
	 * @access     public
	 * @param      array
	 * @return     array
	 */
	public function low_search_pre_search($params)
	{
		// -------------------------------------------
		// Get the latest version of $params
		// -------------------------------------------

		if ($this->EE->extensions->last_call !== FALSE)
		{
			$params = $this->EE->extensions->last_call;
		}

		// Set class property
		$this->params = $params;

		// -------------------------------------------
		// Get all tag(_id): parameters
		// -------------------------------------------

		$tag_names = $tag_ids = array();

		foreach ($this->params AS $key => $val)
		{
			if (preg_match('/^(tag_(id|name)):?([a-z0-9\-_]*)$/i', $key, $match))
			{
				$val = $this->_require_all_value($key, $val);
				$val = $this->_exclude_value($key, $val);

				$array = ($match[2] == 'id') ? 'tag_ids' : 'tag_names';

				${$array}[] = $val;
			}
		}

		// If no events params exist, bail out again
		if (empty($tag_names) && empty($tag_ids)) return $this->params;

		// -------------------------------------------
		// Initiate the entry ids
		// -------------------------------------------

		$entry_ids = NULL;

		// -------------------------------------------
		// Check tag names and convert to tag IDs
		// -------------------------------------------

		if ($tag_names)
		{
			$unique_tags = array();

			foreach ($tag_names AS $val)
			{
				// Get the tags
				list($tags, $in) = low_explode_param($val);

				$unique_tags = array_merge($unique_tags, $tags);
			}

			// Remove duplicates and convert
			$unique_tags = array_unique($unique_tags);
			$unique_tags = array_map(array($this, '_convert_tag'), $unique_tags);

			// Get IDs for unique tags
			$query = $this->EE->db->select('tag_id, tag_name')
			       ->from('tag_tags')
			       ->where('site_id', $this->site_id)
			       ->where_in('tag_name', $unique_tags)
			       ->get();

			// clean up
			unset($unique_tags);

			// Get tag map: [tag name] => tag_id
			$tag_map = low_flatten_results($query->result_array(), 'tag_id', 'tag_name');

			// Now, loop through original tags thing and convert to tag IDs
			foreach ($tag_names AS $val)
			{
				// Initiate tag ids
				$ids = array();

				// Read parameter value
				list($tags, $in) = low_explode_param($val);

				// Loop through tags and map them to IDs
				foreach ($tags AS $tag)
				{
					$tag = $this->_convert_tag($tag);

					if (isset($tag_map[$tag]))
					{
						$ids[] = $tag_map[$tag];
					}
				}

				if ($ids)
				{
					// Check separator and implode back to parameter
					$sep = (strpos($val, '&') === FALSE) ? '|' : '&';
					$str = implode($sep, $ids);

					// Add negator back
					if ( ! $in) $str = 'not '.$ids;

					// Add final parameter string to IDs
					$tag_ids[] = $str;
				}
			}
		}

		// -------------------------------------------
		// Check tag IDs
		// -------------------------------------------

		if ($tag_ids)
		{
			$where = array();

			$sql_select = "SELECT DISTINCT(entry_id) FROM `exp_tag_entries` WHERE ";
			$sql_any = $sql_select . 'tag_id %s (%s)';
			$sql_all = $sql_select . 'tag_id IN (%s) GROUP BY entry_id HAVING COUNT(entry_id) = %s';

			// Loop through groups
			foreach ($tag_ids AS $i => $val)
			{
				// Get the parameter
				list($ids, $in) = low_explode_param($val);

				$sql_in = $in ? 'IN' : 'NOT IN';
				$sql_ids = implode(',', $ids);

				// Inclusive?
				$all = (bool) strpos($val, '&');

				if ($all)
				{
					$where[] = "entry_id {$sql_in} (". sprintf(
						$sql_all,
						$sql_ids,
						count($ids)
					) .')';
				}
				else
				{
					$where[] = "entry_id IN (". sprintf(
						$sql_any,
						$sql_in,
						$sql_ids
					) .')';
				}
			}

			// Check existing entry_id parameter
			if ( ! empty($this->params['entry_id']))
			{
				// Get the parameter value
				list($ids, $in) = low_explode_param($this->params['entry_id']);

				$where[] = sprintf(
					'entry_id %s (%s)',
					($in ? 'IN' : 'NOT IN'),
					implode(',', $ids)
				);
			}

			// Remove unecessary subselect, Compose final query
			if (count($where) == 1 && strpos($where[0], 'HAVING') === FALSE)
			{
				$sql = preg_replace('/^entry_id (NOT )?IN \((.*)\)$/', '$2', $where[0]);
			}
			else
			{
				$sql = $sql_select . implode(' AND ', $where);
			}

			// Query it
			$query = $this->EE->db->query($sql);

			// And add results to the internal array
			$entry_ids = low_flatten_results($query->result_array(), 'entry_id');
		}

		// -------------------------------------------
		// Check entry IDs
		// -------------------------------------------

		if (is_array($entry_ids))
		{
			// Are we left with entry ids?
			if (empty($entry_ids))
			{
				// No matching entries for selected tags
				$this->EE->TMPL->log_item('Low Search Tag: No matching entries for selected tags');
				$this->EE->TMPL->tagdata = $this->EE->TMPL->no_results();
				$this->EE->extensions->end_script = TRUE;
			}
			else
			{
				$this->EE->TMPL->log_item('Low Search Tag: Limiting entry_ids by selected tags');
				$this->params['entry_id'] = implode('|', $entry_ids);
			}
		}

		// Play nice, return it
		return $this->params;
	}


	// --------------------------------------------------------------------

	/**
	 * Activate extension
	 *
	 * @access     public
	 * @return     null
	 */
	public function activate_extension()
	{
		foreach ($this->hooks AS $hook)
		{
			$this->_add_hook($hook);
		}
	}

	/**
	 * Update extension
	 *
	 * @access     public
	 * @param      string    Saved extension version
	 * @return     null
	 */
	public function update_extension($current = '')
	{
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}

		// init data array
		$data = array();

		// Update to 1.0.0
		// if (version_compare($current, '1.0.0', '<'))
		// {
		// }

		// Add version to data array
		$data['version'] = $this->version;

		// Update records using data array
		$this->EE->db->where('class', $this->class_name);
		$this->EE->db->update('extensions', $data);
	}

	/**
	 * Disable extension
	 *
	 * @access     public
	 * @return     null
	 */
	public function disable_extension()
	{
		// Delete records
		$this->EE->db->where('class', $this->class_name);
		$this->EE->db->delete('extensions');
	}

	// --------------------------------------------------------------------
	// PRIVATE METHODS
	// --------------------------------------------------------------------

	/**
	 * Check if given key is in the exclude="" parameter
	 */
	private function _exclude_value($key, $val)
	{
		if ( ! empty($this->params['exclude']))
		{
			list($fields, $in) = low_explode_param($this->params['exclude']);

			if (in_array($key, $fields) && substr($val, 0, 4) != 'not ')
			{
				$val = 'not '.$val;
			}
		}

		return $val;
	}

	/**
	 * Check if given key is in the require_all="" parameter
	 */
	private function _require_all_value($key, $val)
	{
		if ( ! empty($this->params['require_all']))
		{
			list($fields, $in) = low_explode_param($this->params['require_all']);

			if (in_array($key, $fields))
			{
				$amp = (substr($key, 0, 7) == 'search:') ? '&&' : '&';
				$val = str_replace('|', $amp, $val);
			}
		}

		return $val;
	}

	/**
	 * Convert websave tag
	 */
	private function _convert_tag($str)
	{
		// Get the websafe separator
		$sep = isset($this->params['websafe_separator'])
			? $this->params['websafe_separator']
			: '+';

		return str_replace($sep, ' ', $str);
	}

	/**
	 * Add hook to table
	 *
	 * @access     private
	 * @param      string
	 * @return     void
	 */
	private function _add_hook($hook)
	{
		$this->EE->db->insert('extensions', array(
			'class'    => $this->class_name,
			'method'   => $hook,
			'hook'     => $hook,
			'settings' => serialize($this->settings),
			'priority' => 5,
			'version'  => $this->version,
			'enabled'  => 'y'
		));
	}

}
// END CLASS

/* End of file ext.low_search_tag.php */