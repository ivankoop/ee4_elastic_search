<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Json_search_upd
{
	var $version = "1.0.0";

	function install()
	{
		// install module
		$data = array(
			'module_name' => 'Json_search',
			'module_version' => $this->version,
			'has_cp_backend' => 'n',
			'has_publish_fields' => 'n'
		);

		ee()->db->insert('modules', $data);

		// create action
		$data = array(
			'class' => 'Json_search',
			'method' => 'do_search',
		);

		ee()->db->insert('actions', $data);

		return TRUE;
	}

	function update($current = '')
	{
		if (version_compare($current, '1.0', '=')) {
			return FALSE;
		}

		return TRUE;
	}

	function uninstall()
	{
		// Delete from modules
		ee()->db
			->where('module_name', 'Json_search')
			->delete('modules');

		// Delete from actions
		ee()->db
			->where('class', 'Json_search')
			->delete('actions');

		return TRUE;
	}
}
