<?php

/**
 * DataGrab Module Class
 *
 * DataGrab Module class used in front end templates
 * 
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 */
class Ajw_datagrab {

	var $return_data    = ''; 

	function __construct()
	{
		// Make a local reference to the ExpressionEngine super object
		// 
		
		// Load datagrab model
		ee()->load->model('datagrab_model', 'datagrab');
	}

	/**
	 * Run an import via an action
	 *
	 * @return void
	 * @author Andrew Weaver
	 */
	function run_action() {
		
		$id = "";
		if( ee()->input->get("id") != "" ) {
			$id = ee()->input->get("id");
		} 

		if( $id == "" ) {
			exit;
		}

		ee()->load->helper('url');
		ee()->load->library('javascript'); 
		ee()->load->model('template_model'); 

		// Fetch import settings
		ee()->db->where('id', $id );
		$query = ee()->db->get('exp_ajw_datagrab');
		if( $query->num_rows() == 0 ) {
			exit;
		}
		$row = $query->row_array();
		$this->settings = unserialize($row["settings"]);

		if( $row["passkey"] != "" ) {
			if( $row["passkey"] != ee()->input->get("passkey") ) {
				exit;
			}
		}

		// Initialise
		ee()->datagrab->initialise_types();

		// Check for modifiers
		if( ee()->input->get('filename') !== FALSE ) {
			if( ee()->input->get('filename') == "POST" ) {
				if( ee()->input->post('data') !== FALSE ) {
					$data = ee()->input->post('data');
					// write to cache file
					// set filename to cache file
					// clean up cache
					print tempnam("/tmp", ''); exit;
				}
			}
			$this->settings["datatype"]["filename"] = ee()->input->get('filename');
		}
		if( ee()->input->get('id') !== FALSE ) {
			$this->settings["import"]["id"] = ee()->input->get('id');
		}
		if( ee()->input->get('batch') !== FALSE ) {
			$this->settings["import"]["batch"] = ee()->input->get('batch');
		}
		if( ee()->input->get('skip') !== FALSE ) {
			$this->settings["datatype"]["skip"] = ee()->input->get('skip');
		}
		if( ee()->input->get('limit') !== FALSE ) {
			$this->settings["import"]["limit"] = ee()->input->get('limit');
		}
		if( ee()->input->get('author_id') !== FALSE ) {
			$this->settings["config"]["author"] = ee()->input->get('author_id');
		}

		// Do import
		$this->return_data .= ee()->datagrab->do_import( 
			ee()->datagrab->datatypes[ $this->settings["import"]["type"] ], 
			$this->settings 
		);

		$this->return_data .= "<p>Import has finished.</p>";
		
		print $this->return_data;
		exit;
	}

	/**
	 * Run an import from a front end template
	 *
	 * @return void
	 * @author Andrew Weaver
	 */
	function run_saved_import() {
		
		$id = ee()->TMPL->fetch_param('id');

		ee()->load->helper('url');
		// Needed for the Pages module
		ee()->load->library('javascript'); 
		ee()->load->model('template_model'); 

		// Fetch import settings
		if ( $id != "" ) {
			ee()->db->where('id', $id );
			$query = ee()->db->get('exp_ajw_datagrab');
			$row = $query->row_array();
			$this->settings = unserialize($row["settings"]);
		}

		// Initialise
		ee()->datagrab->initialise_types();

		// Check for template modifiers
		if( ee()->TMPL->fetch_param('id') !== FALSE ) {
			$this->settings["import"]["id"] = ee()->TMPL->fetch_param('id');
		}
		if( ee()->TMPL->fetch_param('filename') !== FALSE ) {
			$this->settings["datatype"]["filename"] = ee()->TMPL->fetch_param('filename');
		}
		if( ee()->TMPL->fetch_param('batch') !== FALSE ) {
			$this->settings["import"]["batch"] = ee()->TMPL->fetch_param('batch');
		}
		if( ee()->TMPL->fetch_param('skip') !== FALSE ) {
			$this->settings["datatype"]["skip"] = ee()->TMPL->fetch_param('skip');
		}
		if( ee()->TMPL->fetch_param('limit') !== FALSE ) {
			$this->settings["import"]["limit"] = ee()->TMPL->fetch_param('limit');
		}
		if( ee()->TMPL->fetch_param('author_id') !== FALSE ) {
			$this->settings["config"]["author"] = ee()->TMPL->fetch_param('author_id');
		}

		// Do import
		$this->return_data .= ee()->datagrab->do_import( 
			ee()->datagrab->datatypes[ $this->settings["import"]["type"] ], 
			$this->settings 
			);

		$this->return_data .= "<p>Import has finished.</p>";
		
		return $this->return_data;
		// exit;
	}

}

/* End of file mod.ajw_datagrab.php */