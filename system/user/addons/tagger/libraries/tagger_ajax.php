<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tagger AJAX File
 *
 * @package			DevDemon_Tagger
 * @version			3.2.2
 * @author			DevDemon <http://www.devdemon.com> - Lead Developer @ Parscale Media
 * @copyright 		Copyright (c) 2007-2010 Parscale Media <http://www.parscale.com>
 * @license 		http://www.devdemon.com/license/
 * @link			http://www.devdemon.com
 */
class Tagger_ajax
{

	public function __construct()
	{
		$this->site_id = ee('Request')->get('site_id') ?: ee()->config->item('site_id');
	}

	// ********************************************************************************* //

	public function tag_search()
	{
		header('Content-Type: text/html; charset=UTF-8');

		ee()->db->select('tag_name, tag_id');
		ee()->db->from('exp_tagger');
		ee()->db->like('tag_name', ee()->input->get('term'), 'both');
		ee()->db->where('site_id', $this->site_id);
		ee()->db->order_by('tag_name');
		ee()->db->limit(30);
		$query = ee()->db->get();

		$tags = array();
		foreach ($query->result() as $row)
		{
			$tags[] = array('id' => $row->tag_id, 'value' => $row->tag_name, 'label' => $row->tag_name);
		}

		exit(json_encode($tags));
	}

	// ********************************************************************************* //

	public function add_to_group()
	{
		$groups = ee()->input->post('groups');
		$tag_id = ee()->input->post('tag_id');

		// First check if groups is empty
		if (is_array($groups) == FALSE OR empty($groups) == TRUE OR $groups == FALSE)
		{
			echo "Groups is Empty \n";

			// Delete all groups from this Tag
			ee()->db->where('tag_id', $tag_id)->delete('tagger_groups_entries');
		}
		else
		{
			echo "Groups Found \n";

			// Delete all groups from this Tag
			ee()->db->where('tag_id', $tag_id)->delete('tagger_groups_entries');

			// Then add only what we need
			foreach ($groups as $group_id)
			{
				ee()->db->insert('tagger_groups_entries', array('tag_id' => $tag_id, 'group_id' => $group_id));
			}

		}

		echo 'DONE';
		exit();
	}

	// ********************************************************************************* //

	public function tags_dt()
	{
		ee()->load->helper('form');

		//----------------------------------------
		// Grab All Groups
		//----------------------------------------
		$groups = array();
		ee()->db->select('group_id, group_title');
		ee()->db->from('exp_tagger_groups');
		ee()->db->where('site_id', $this->site_id);
		$query = ee()->db->get();
		foreach($query->result() as $row) $groups[$row->group_id] = $row->group_title;



		$data = array();
		$data['aaData'] = array();
		$data['iTotalDisplayRecords'] = 0; // Total records, after filtering (i.e. the total number of records after filtering has been applied - not just the number of records being returned in this result set)
		$data['sEcho'] = ee()->input->get_post('sEcho');

		// Total records, before filtering (i.e. the total number of records in the database)
		$data['iTotalRecords'] = ee()->db->count_all('exp_tagger');

		//----------------------------------------
		// Column Search
		//----------------------------------------
		$tag_search = FALSE;
		if (ee()->input->get_post('sSearch') != FALSE)
		{
			$tag_search = ee()->input->get_post('sSearch');
		}

		//----------------------------------------
		// Total after filter
		//----------------------------------------
		ee()->db->select('COUNT(*) as total_records', FALSE);
		ee()->db->from('exp_tagger tg');
		ee()->db->where('tg.site_id', $this->site_id);
		if ($tag_search != FALSE) ee()->db->like('tg.tag_name', $tag_search, 'both');
		$query = ee()->db->get();
		$data['iTotalDisplayRecords'] = $query->row('total_records');
		$query->free_result();

		//----------------------------------------
		// Real Query
		//----------------------------------------
		ee()->db->select('tg.*');
		ee()->db->from('exp_tagger tg');
		ee()->db->where('tg.site_id', $this->site_id);

		//----------------------------------------
		// Sort By
		//----------------------------------------
		$sort_cols = ee()->input->get_post('iSortingCols');

		for ($i = 0; $i < $sort_cols; $i++)
		{
			$col = ee()->input->get_post('iSortCol_'.$i);
			$sort =  ee()->input->get_post('sSortDir_'.$i);

			switch ($col)
			{
				case 1: // Tag Name
					ee()->db->order_by('tg.tag_name', $sort);
					break;
				case 2: // Total Entries
					ee()->db->order_by('tg.total_entries', $sort);
					break;
			}
		}

		//----------------------------------------
		// Limit
		//----------------------------------------
		$limit = 10;
		if (ee()->input->get_post('iDisplayLength') !== FALSE)
		{
			$limit = ee()->input->get_post('iDisplayLength');
			if ($limit < 1) $limit = 999999;
		}

		//----------------------------------------
		// Offset
		//----------------------------------------
		$offset = 10;
		if (ee()->input->get_post('iDisplayStart') !== FALSE)
		{
			$offset = ee()->input->get_post('iDisplayStart');
		}

		if ($tag_search != FALSE) ee()->db->like('tg.tag_name', $tag_search, 'both');

		ee()->db->limit($limit, $offset);
		$query = ee()->db->get();


		//----------------------------------------
		// Loop Over all
		//----------------------------------------
		foreach ($query->result() as $row)
		{
			//----------------------------------------
			// Group Relationships
			//----------------------------------------
			$selected = array();
			$temp = ee()->db->select('group_id')->from('exp_tagger_groups_entries')->where('tag_id', $row->tag_id)->get();
			foreach($temp->result() as $sel) $selected[] = $sel->group_id;

			//----------------------------------------
			// Create Group TD
			//----------------------------------------


			if (empty($groups) == FALSE)
			{
				$td = form_multiselect('group[]', $groups, $selected, 'class="gSel" rel="'.$row->tag_id.'"');
				foreach($selected as $group_id) $td .= '<small>' . $groups[$group_id] . '</small>';
			}
			else
			{
				$td = '&nbsp;';
			}


			//----------------------------------------
			// Create TR row
			//----------------------------------------
			$trow = array();
			$trow[] = $row->tag_id;
			$trow[] = $row->tag_name;
			$trow[] = $row->total_entries;
			$trow[] = $td;
			$trow[] = '<div class="toolbar-wrap">
							<ul class="toolbar">
								<li class="edit EditTag"><a href="#"></a></li>
								<li class="remove DelTag"><a href="#"></a></li>
							</ul>
						</div>';
			$data['aaData'][] = $trow;
		}

		exit(json_encode($data));
	}

	// ********************************************************************************* //

	public function del_tag()
	{
		$tag_id = ee()->input->post('tag_id');

		// Delete from exp_tagger
		ee()->db->where('tag_id', $tag_id)->delete('exp_tagger');

		//Delete from exp_tagger_links
		ee()->db->where('tag_id', $tag_id)->delete('exp_tagger_links');

		//Delete from exp_tagger_groups_entries
		ee()->db->where('tag_id', $tag_id)->delete('exp_tagger_groups_entries');
	}

	// ********************************************************************************* //

	public function edit_tag()
	{
		$tag_id = ee()->input->post('tag_id');
		$tag = ee()->input->post('tag');

		// -----------------------------------------
		// Load Settings
		// -----------------------------------------
		$conf = ee()->config->item('tagger_defaults');

		// Grab Settings
		$query = ee()->db->query("SELECT settings FROM exp_modules WHERE module_name = 'Tagger'");
		if ($query->row('settings') != FALSE)
		{
			$settings = @unserialize($query->row('settings'));
			if ($settings != FALSE && isset($settings['site:'.$this->site_id]))
			{
				$conf = array_merge($conf, $settings['site:'.$this->site_id]);
			}
		}

		// lowecase?
		$lc = ($conf['lowercase_tags'] == 'yes') ? TRUE : FALSE;

		if ($lc == TRUE) $tag = strtolower($tag);

		// Update Tag
		ee()->db->set('tag_name', $tag);
		ee()->db->where('tag_id', $tag_id);
		ee()->db->update('exp_tagger');
	}

	// ********************************************************************************* //

	public function merge_tags()
	{
		$tags = ee()->input->post('tags');
		$tags = explode(',', $tags);

		foreach ($tags as $key => $tag)
		{
			$tag = trim($tag);
			if (is_numeric($tag) == FALSE) unset($tags[$key]);
		}

		// Lets check
		if (count($tags) < 2) exit('Not Enough');

		// Grab the master
		$master = $tags[0];
		unset($tags[0]);

		foreach ($tags as $tag_id)
		{
			// Grab all rels
			$query = ee()->db->select('rel_id, entry_id')->from('exp_tagger_links')->where('tag_id', $tag_id)->get();

			// Check each for duplicates
			foreach($query->result() as $row)
			{
				$q2 = ee()->db->select('rel_id')->from('exp_tagger_links')->where('tag_id', $master)->where('entry_id', $query->row('entry_id'))->get();

				// Duplicate? Remove it!
				if ($q2->num_rows() > 0)
				{
					ee()->db->query("DELETE FROM exp_tagger_links WHERE rel_id = " . $query->row('rel_id'));
				}
				else
				{
					ee()->db->set('tag_id', $master)->where('rel_id', $query->row('rel_id'))->update('exp_tagger_links');
				}
			}

			// Delete the other tag
			ee()->db->query("DELETE FROM exp_tagger WHERE tag_id = " . $tag_id);
		}

		// Count!
		$query = ee()->db->query('SELECT COUNT(*) as count FROM exp_tagger_links WHERE tag_id = '.$master);
		ee()->db->set('total_entries', $query->row('count'))->where('tag_id', $master)->update('exp_tagger');
	}

	// ********************************************************************************* //

} // END CLASS

/* End of file tagger_ajax.php  */
/* Location: ./system/expressionengine/third_party/tagger/modules/libraries/tagger_ajax.php */
