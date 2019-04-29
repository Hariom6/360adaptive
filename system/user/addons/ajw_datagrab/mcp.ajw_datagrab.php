<?php

define('DATAGRAB_URL', BASE.AMP.'?/cp/addons/settings/ajw_datagrab');
define('DATAGRAB_PATH', '?/cp/addons/settings/ajw_datagrab');

/**
 * DataGrab MCP Class
 *
 * DataGrab Module Control Panel class to handle all CP requests
 * 
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 */
class Ajw_datagrab_mcp {
	
	var $version = '3.0.3';
	var $module_name = "AJW_Datagrab";
	
	var $settings;
	
	function __construct() {
		
		ee()->load->model('datagrab_model', 'datagrab');
		
		// Global right hand side navigation
		// ee()->cp->set_right_nav(array(
		// 	'Documentation' => "http://brandnewbox.co.uk/support/category/datagrab"
		// ));

	}
	
	/*
	
	CONTROLLER FUNCTIONS
	
	*/
	
	function index() {
		
		// Clear session data
		$this->_get_session('settings');

		// Set page title
		if(version_compare(APP_VER, '2.6', '>=')) {
			ee()->view->cp_page_title = "DataGrab";
		} else {
			ee()->cp->set_variable('cp_page_title', "DataGrab");
		}
		
		// Load helpers
		ee()->load->library('table');
		ee()->load->helper('form');
		ee()->load->library('relative_date');

		// Set data
		$data["title"] = "DataGrab";
		$data["content"] = 'index';
		
		$data["types"] = ee()->datagrab->fetch_datatype_names();

		$data["action_url"] = ee()->functions->fetch_site_index(0, 0) . QUERY_MARKER . 'ACT=' . ee()->cp->fetch_action_id('Ajw_datagrab', 'run_action') . AMP . "id=";

		ee()->db->select('id, name, description, passkey, import_status, import_lastrecord, import_totalrecords, last_run, settings');
		ee()->db->where('site_id', ee()->config->item('site_id') );
		ee()->db->order_by('id ASC');
		$query = ee()->db->get('exp_ajw_datagrab');
		$data["saved_imports"] = array();
		foreach($query->result_array() as $row) {
			$id = $row["id"];

			$import_settings = unserialize($row["settings"]);

			$row["name"] = '<a href="'.ee('CP/URL')->make( 'addons/settings/ajw_datagrab/save', array('id' => $row["id"] ) ).'">' . $row["name"] . '</a>';
			if( strlen( $row["description"] ) > 32 ) {
				$row["description"] = substr( $row["description"], 0, 32 ) . "...";
			}
			unset( $row["description"] );
			$row[] = $data["types"][ $import_settings["import"]["type"] ];
			// $row[] = '<a href="' . ee('CP/URL')->make( 'addons/settings/ajw_datagrab/load', array( "id" => $row["id"] ) ) . '">Configure</a>';
			// $row[] = '<a href="'.ee('CP/URL')->make( 'addons/settings/ajw_datagrab/run', array('id' => $row["id"] ) ).'">Run import</a>';
			// $row[] = '<a class="passkey" href="'.$data["action_url"].$id.( $row["passkey"] != '' ? AMP.'passkey='.$row["passkey"] : '' ).'">Import URL</a>';

			$row['toolbar'] = '<ul class="toolbar">';
			$row['toolbar'] .= '<li class="sync"><a title="Start import" href="'.ee('CP/URL')->make( 'addons/settings/ajw_datagrab/run', array('id' => $row["id"] ) ).'"></a></li>';
			$row['toolbar'] .= '<li class="edit"><a title="Edit saved import name/description" href="'.ee('CP/URL')->make( 'addons/settings/ajw_datagrab/save', array('id' => $row["id"] ) ).'"></a></li>';
			$row['toolbar'] .= '<li class="settings"><a title="Configure import" href="' . ee('CP/URL')->make( 'addons/settings/ajw_datagrab/load', array( "id" => $row["id"] ) ) . '"></a></li>';
			$row['toolbar'] .= '<li class="txt-only"><a class="passkey" title="Display URL to run import from outside Control Panel" onclick="alert(\''.$data["action_url"].$id.( $row["passkey"] != '' ? AMP.'passkey='.$row["passkey"] : '' ).'\');return false;" href="'.$data["action_url"].$id.( $row["passkey"] != '' ? AMP.'passkey='.$row["passkey"] : '' ).'">Import URL</a></li>';
			$row['toolbar'] .= '</ul>';

			if( $row["import_status"] == "WAITING" ) {
				$row[] = '<span class="st-pending">WAITING</span> ' . $row["import_lastrecord"] . "/" . $row["import_totalrecords"] . " records (" . round($row["import_lastrecord"]/$row["import_totalrecords"]*100) . "%)" . 
					' <ul style="display:inline" class="toolbar"><li class="txt-only"><a href="' . ee('CP/URL')->make( 'addons/settings/ajw_datagrab/run', array('id' => $row["id"], 'batch' => 'yes' ) ) . '">Import next batch</a></li></ul>';
			} elseif ( $row["import_status"] == "RUNNING" ) {
				$row[] = '<span class="st-locked">RUNNING</span> ' . $row["import_lastrecord"] . "/" . $row["import_totalrecords"] . " records (" . round($row["import_lastrecord"]/$row["import_totalrecords"]*100) . "%)";
			} elseif ( $row["import_status"] == "" ) {
				$row[] = '<span class="st-draft">NEW</span>';
			} else {
				$row[] = '<span class="st-open">' . $row["import_status"] . '</span>';
			}
			// $row[] = ee()->relative_date->create( $row["last_run"] )->render(); //ee()->localize->human_time( $row["last_run"] );
			$row[] = ee()->localize->human_time( $row["last_run"] );
			// $row[] = '<a href="'.ee('CP/URL')->make( 'addons/settings/ajw_datagrab/delete', array('id' => $row["id"] ) ).'">Delete</a>';

			$row['delete'] = '<ul class="toolbar">';
			$row['delete'] .= '<li class="remove"><a title="Delete saved import" href="'.ee('CP/URL')->make( 'addons/settings/ajw_datagrab/delete', array('id' => $row["id"] ) ).'"></a></li>';
			$row['delete'] .= '</ul>';


			unset( $row["passkey"] );
			unset( $row["import_status"] );
			unset( $row["import_totalrecords"] );
			unset( $row["import_lastrecord"] );
			unset( $row["last_run"] );
			unset( $row["settings"] );
			$data["saved_imports"][ $id ] = $row;
		}

		// $data["form_action"] = DATAGRAB_PATH.'/settings';
		$data["form_action"] = ee('CP/URL', 'addons/settings/ajw_datagrab/settings');
		
		// Load view
		return ee()->load->view('_wrapper', $data, TRUE);
	}

	function settings() {
		
		// Handle form input
		$this->_get_input();

		// Set page title
		// if(version_compare(APP_VER, '2.6', '>=')) {
		// 	ee()->view->cp_page_title = "Settings";
		// } else {
		// 	ee()->cp->set_variable('cp_page_title', "Settings");
		// }

		// Set breadcrumb
		// ee()->cp->set_breadcrumb( DATAGRAB_URL, ee()->lang->line('ajw_datagrab_module_name') );
		
		// $this->cp->add_to_head('<style type="text/css">.tablesize{height:45px!important;}</style>');
		
		// Load helpers
		ee()->load->library('table');
		ee()->load->helper('form');

		// Set data
		$data["title"] = "Settings";
		$data["content"] = 'settings';
		
		// Fetch channel name
		ee()->db->select('channel_id, channel_title');
		ee()->db->where('site_id', ee()->config->item('site_id') );
		$query = ee()->db->get('exp_channels');
		$data["channels"] = array();
		foreach($query->result_array() as $row) {
			$data["channels"][$row["channel_id"]] = $row["channel_title"];
		}
		$data["channel"] = isset( $this->settings["import"]["channel"] ) ? 
			$this->settings["import"]["channel"] : '';
		
		// Get settings form for type
		ee()->datagrab->initialise_types();
		$data["settings"] = ee()->datagrab->datatypes[ 
			$this->settings["import"]["type"] ]->settings_form( $this->settings );

		// Form action URL
		// $data["form_action"] = DATAGRAB_PATH.AMP.'method=check_settings';
		$data["form_action"] = ee('CP/URL', 'addons/settings/ajw_datagrab/check_settings');
		
		// Get datatype details
		$data["datatype"] = ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->datatype_info;

		// Load view
		return array(
			'body' => ee()->load->view('_wrapper', $data, TRUE),
			'breadcrumb' => array( ee('CP/URL', 'addons/settings/ajw_datagrab')->compile() => ee()->lang->line('ajw_datagrab_module_name') ),
			'heading' => 'Settings'
		);
	}

	function check_settings() {

		// Handle form input
		$this->_get_input();

		// Set page title
		if(version_compare(APP_VER, '2.6', '>=')) {
			ee()->view->cp_page_title = "Check Settings";
		} else {
			ee()->cp->set_variable('cp_page_title', "Check Settings");
		}

		// Set breadcrumb
		// ee()->cp->set_breadcrumb( DATAGRAB_URL, ee()->lang->line('ajw_datagrab_module_name') );

		// Load helpers
		ee()->load->library('table');
		ee()->load->helper('form');

		// Set data
		$data["title"] = "Check Settings";
		$data["content"] = 'check_settings';

		$data["rows"] = array();
		$data["errors"] = array();

		ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->initialise( $this->settings );
		$ret = ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->fetch();
		if( $ret != -1 ) {
			$titles = ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->fetch_columns();
			if( $titles === FALSE ) {
				$data["errors"] = ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->errors;			
			}
			if( $titles != "" ) {
				foreach( $titles as $key => $value ) {
					$data["rows"][] = array( $value );
				}
			}
		} else {
			$data["errors"] = ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->errors;
		}

		// Form action URL
		// $data["form_action"] = DATAGRAB_PATH.AMP.'method=configure_import';
		$data["form_action"] = ee('CP/URL', 'addons/settings/ajw_datagrab/configure_import');

		// Load view
		return array(
			'body' => ee()->load->view('_wrapper', $data, TRUE),
			'breadcrumb' => array( ee('CP/URL', 'addons/settings/ajw_datagrab')->compile() => ee()->lang->line('ajw_datagrab_module_name') ),
			'heading' => 'Check settings'
		);
	}

	function configure_import() {
		
		// Handle form input
		
		$this->_get_input();
	
		// Set page title
		
		if(version_compare(APP_VER, '2.6', '>=')) {
			ee()->view->cp_page_title = "Configure Import";
		} else {
			ee()->cp->set_variable('cp_page_title', "Configure Import");
		}
		

		// Set breadcrumb
		// ee()->cp->set_breadcrumb( DATAGRAB_URL, ee()->lang->line('ajw_datagrab_module_name') );

		// Load helpers
		
		ee()->load->library('table');
		ee()->load->helper('form');

		// Set data
		
		$data["title"] = "Configure Import";
		$data["content"] = 'configure_import';

		$channel_id = $this->settings["import"]["channel"];
		$channel = ee('Model')->get('Channel', (int) $channel_id)->first();
	
		// Get custom fields for the selected channel
		
		// ee()->db->select("channel_title, field_group, cat_group");
		// if( is_numeric($this->settings["import"]["channel"]) ) {
		// 	ee()->db->where( 'channel_id', $this->settings["import"]["channel"] );
		// } else {
		// 	ee()->db->where( 'channel_name', $this->settings["import"]["channel"] );
		// 	ee()->db->where('site_id', ee()->config->item('site_id') );
		// }
		// $query = ee()->db->get( 'exp_channels' );
		// $row = $query->row_array();
		// 
		$data["channel_title"] = $channel->channel_title;
		// $field_group = $row["field_group"];
		// $cat_group = $row["cat_group"];

		// ee()->db->select( 'field_name, field_label, field_type, field_settings' );
		// ee()->db->where( 'group_id', $field_group );
		// ee()->db->order_by( 'field_order' );
		// $query = ee()->db->get( 'exp_channel_fields' );
		
		$data["custom_fields"] = array();
		$data["unique_fields"] = array();
		$data["field_settings"] = array();
		$data["field_required"] = array();
		$data["unique_fields"][ "" ] = "";
		$data["unique_fields"][ "title" ] = "Title";
		$data["field_types"] = array();

		foreach( $channel->getAllCustomFields() as $field ) {
			$data["custom_fields"][ $field->field_name ] = $field->field_label;
			$data["unique_fields"][ $field->field_name ] = $field->field_label;
			$data["field_types"][ $field->field_name ] = $field->field_type;
			$data["field_settings"][ $field->field_name ] = $field->field_settings;
			$data["field_required"][ $field->field_name ] = $field->field_required;
		}

		// ee()->db->select( 'field_name, field_label' );
		// ee()->db->where( 'group_id', $field_group );
		// $query = ee()->db->get( 'exp_channel_fields' );
		
		// Get category groups
		
		// ee()->db->select( 'group_id, group_name' );
		// ee()->db->where_in( 'group_id', explode( "|", $cat_group ) );
		// $query = ee()->db->get( 'exp_category_groups' );
		
		$data["category_groups"] = array();
		// $data["category_groups"][ 0 ] = "";
		foreach( $channel->getCategoryGroups() as $row ) {
			$data["category_groups"][ $row->group_id ] = $row->group_name;
		}
		
		// Get list of fields from the datatype

		// print_r( $this->settings );

		ee()->datagrab->initialise_types();
		ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->initialise( $this->settings );
		ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->fetch();
		$data["data_fields"][""] = "";
		$fields = ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->fetch_columns();
		if( is_array($fields)) {
			foreach( $fields as $key => $value ) {
				$data["data_fields"][ $key ] = $value;
			}
		}

		// Get list of authors
		// @todo: filter this list by member groups
		
		$data["authors"] = array();

		ee()->db->select( 'member_id, screen_name' );
		// ee()->db->where( 'group_id', "1" );
		$query = ee()->db->get( 'exp_members' );
		if( $query->num_rows() > 0 ) {
			foreach( $query->result_array() as $row ) {
				$data["authors"][ $row["member_id"] ] = $row["screen_name"];
			}
		}
		
		$data["author_fields"] = array(
			"member_id" => "ID",
			"username" => "Username",
			"screen_name" => "Screen Name",
			"email" => "Email address"
		);
		
		ee()->db->select( "m_field_id, m_field_label" );
		ee()->db->from( "exp_member_fields" );
		ee()->db->order_by( "m_field_order ASC" );
		$query = ee()->db->get();
		if( $query->num_rows() > 0 ) {
			$member_fields = array();
			foreach( $query->result_array() as $row ) {
				$member_fields["m_field_id_" . $row["m_field_id"] ] = $row["m_field_label"];
			}
			$data["author_fields"]["Custom Fields"] = $member_fields;
		}
		
		
		// Get statuses
		
		$data["status_fields"] = array(
			"default" => "Channel default"
		);
		
		// ee()->db->select( "status" );
		// ee()->db->from( "exp_statuses s" );
		// ee()->db->join( "exp_channels c", "c.status_group = s.group_id" );
		// if( is_numeric($this->settings["import"]["channel"]) ) {
		// 	ee()->db->where( 'c.channel_id', $this->settings["import"]["channel"] );
		// } else {
		// 	ee()->db->where( 'c.channel_name', $this->settings["import"]["channel"] );
		// 	ee()->db->where( 'c.site_id', ee()->config->item('site_id') );
		// }
		// ee()->db->order_by( "status_order ASC" );
		// $query = ee()->db->get();

		foreach( $channel->Statuses as $row ) {
			$data["status_fields"][ $row->status ] = $row->status;
		}
		
		$data["status_fields"] = array_merge( $data["status_fields"], $data["data_fields"] );
		
		// Allow comments - check datatype ?
		
		$allow_comments = isset( ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->datatype_info["allow_comments"] ) ? 
			ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->datatype_info["allow_comments"] : FALSE;

		// ee()->cp->load_package_js('ajw_datagrab');

		if( $allow_comments ) {			
			$data["allow_comments"] = TRUE;
		} else {
			$data["allow_comments"] = FALSE;
		}
		
		// Allow multiple fields?
		$data["allow_multiple_fields"] = isset( ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->datatype_info["allow_multiple_fields"] ) ? 
			ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->datatype_info["allow_multiple_fields"] : FALSE;
		
		// Pages module
		if (array_key_exists('pages', ee()->addons->get_installed('modules'))) {
			$data["pages_installed"] = TRUE;
		} else {
			$data["pages_installed"] = FALSE;
		}
		
		// Transcribe module
		if (array_key_exists('transcribe', ee()->addons->get_installed('modules'))) {
			ee()->db->select( "id, name" );
			$query = ee()->db->get( "exp_transcribe_languages" );
			$data["transcribe_language_fields"] = array();
			$data["transcribe_language_fields"][ 0 ] = "None";
			foreach( $query->result_array() as $row ) {
				$data["transcribe_language_fields"][ $row["id"] ] = ucfirst($row["name"]);
			}
			$data["transcribe_installed"] = TRUE;
		} else {
			$data["transcribe_installed"] = FALSE;
		}

		// SEO Lite
		if (array_key_exists('seo_lite', ee()->addons->get_installed('modules'))) {
			$data["seo_lite_installed"] = TRUE;
		} else {
			$data["seo_lite_installed"] = FALSE;
		}
		
		// Nsm Better Meta
		if (array_key_exists('nsm_better_meta', ee()->addons->get_installed('modules'))) {
			$data["nsm_better_meta_installed"] = TRUE;
		} else {
			$data["nsm_better_meta_installed"] = FALSE;
		}

		// ee()->db->select( 'field_id, field_label, group_name' );
		// ee()->db->join( 'exp_field_groups', 'exp_field_groups.group_id = exp_channel_fields.group_id' );
		// ee()->db->order_by( 'group_name, field_order' );
		// $query = ee()->db->get( 'exp_channel_fields' );
		
		$data["all_fields"] = array();
		$data["all_fields"]["title"] = "Title";
		$data["all_fields"]["exp_channel_titles.entry_id"] = "Entry ID";
		$data["all_fields"]["exp_channel_titles.url_title"] = "URL Title";

		$builder = ee('Model')->get('ChannelField')
			->filter('site_id', ee()->config->item('site_id'));
		$all_fields = $builder->all();


		foreach( $all_fields as $i => $row ) {
			$data["all_fields"][ "field_id_".$row->field_id ] = $row->field_label;
		}
		
		
		// Default settings
		
		if( isset ( ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->config_defaults ) ) {
			foreach( ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->config_defaults as $field => $value ) {
				if( !isset( $this->settings[ $field ] ) ) {
					$this->settings["config"][ $field ] = $value;
				}
			}
		}
		$data['default_settings'] = $this->settings;
		
		$data["cf_config"] = array();
		
		// Build configuration table for custom fields
		foreach( $data["custom_fields"] as $field_name => $field_label ) {

			$field_type = $data["field_types"][ $field_name ];
			$field_required = $data["field_required"][ $field_name ];

			if ( ! class_exists('Datagrab_fieldtype') ) {
				require_once PATH_THIRD.'ajw_datagrab/libraries/Datagrab_fieldtype'.".php";
			}	
			if ( ! class_exists('Datagrab_'.$field_type ) ) {
				if( file_exists( PATH_THIRD.'ajw_datagrab/fieldtypes/datagrab_'.$field_type.".php" ) ) {
					require_once PATH_THIRD.'ajw_datagrab/fieldtypes/datagrab_'.$field_type.".php";
				}
			}	
			
			if ( class_exists('Datagrab_'.$field_type) ) {
				$classname = "Datagrab_".$field_type;
			} else {
				$classname = "Datagrab_fieldtype";
			}
			$ft = new $classname();
			$data["cf_config"][] = $ft->display_configuration( 
				$field_name, $field_label, $field_type, $field_required, $data 
			);
		}
		
		// 	
		$data["datatype_info"] = ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->datatype_info;
		$data["datatype_settings"] = ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->settings;
		
		// Form action URL
		
		// $data["form_action"] = DATAGRAB_PATH.AMP.'method=import';
		$data["form_action"] = ee('CP/URL', 'addons/settings/ajw_datagrab/import');
		$data["back_link"] = ee('CP/URL')->make( 'addons/settings/ajw_datagrab/settings' );
		// $data["save_action"] = DATAGRAB_PATH.AMP.'method=save';
		$data["save_action"] = ee('CP/URL', 'addons/settings/ajw_datagrab/save');

		if( ee()->input->get( "id" ) ) {
			$data["id"] = ee()->input->get( "id" );
		}

		$data["errors"] = ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->errors;

		// Load view
		return array(
			'body' => ee()->load->view('_wrapper', $data, TRUE),
			'breadcrumb' => array( ee('CP/URL', 'addons/settings/ajw_datagrab')->compile() => ee()->lang->line('ajw_datagrab_module_name') ),
			'heading' => 'Configure import'
		);
	}


	function import() {

		if( ee()->input->post("save") !== FALSE ) {
			return $this->save();
			exit;
		}
		
		$this->_get_input();

		// Set page title
		if(version_compare(APP_VER, '2.6', '>=')) {
			ee()->view->cp_page_title = "Results";
		} else {
			ee()->cp->set_variable('cp_page_title', "Results");
		}
		

		// Set breadcrumb
		// ee()->cp->set_breadcrumb( DATAGRAB_URL, ee()->lang->line('ajw_datagrab_module_name') );

		// Load helpers
		ee()->load->library('table');
		ee()->load->helper('form');

		// Set data
		$data["title"] = "Results";
		$data["content"] = 'results';
		
		// $this->settings = array_merge( $this->settings, $_POST );

		// Allow modifications via get variables
		if( ee()->input->get('skip') !== FALSE ) {
			$this->settings["datatype"]["skip"] = ee()->input->get('skip');
		}
		if( ee()->input->get('limit') !== FALSE ) {
			$this->settings["import"]["limit"] = ee()->input->get('limit');
		}
		if( ee()->input->get('batch') !== FALSE ) {
			$this->settings["import"]["batch"] = ee()->input->get('batch');
		}
		
		ee()->datagrab->initialise_types();
		$data["results"] = ee()->datagrab->do_import( 
			ee()->datagrab->datatypes[ $this->settings["import"]["type"] ], 
			$this->settings 
			);

		// Set variables for batch imports
		$data["batch"] = ee()->datagrab->batch_limit_completed;
		$data["skip"] = isset($this->settings["datatype"]["skip"]) ? $this->settings["datatype"]["skip"] : 0;
		$data["limit"] = isset($this->settings["import"]["limit"]) ? $this->settings["import"]["limit"] : "";
		$data["batch_action"] = ee('CP/URL')->make( 'addons/settings/ajw_datagrab/import', array('batch' => 'yes' ) );
		$data['cp_theme_url'] = ee()->config->slash_item('theme_folder_url').'cp_themes/default/';

		// Form action URL
		if( isset( $this->settings["import"]["id"] ) ) {
			$data["id"] = $this->settings["import"]["id"];
		} else {
			$data["id"] = 0;
		}
		
		// Form action URL
		// $data["form_action"] = DATAGRAB_PATH.AMP.'method=save';
		$data["form_action"] = ee('CP/URL', 'addons/settings/ajw_datagrab/save');
		
		$data["errors"] = ee()->datagrab->datatypes[ $this->settings["import"]["type"] ]->errors;

		// Load view
		ee()->load->remove_package_path(PATH_THIRD.'ep_better_workflow/'); // Fixes issue if EP Better Workflow installed

		return array(
			'body' => ee()->load->view('_wrapper', $data, TRUE),
			'breadcrumb' => array( ee('CP/URL', 'addons/settings/ajw_datagrab')->compile() => ee()->lang->line('ajw_datagrab_module_name') ),
			'heading' => 'Results'
		);

	}

	function save() {
		
		$id = ee()->input->get_post("id", 0);
		
		$this->_get_input();
		
		// Load helpers
		ee()->load->library('table');
		ee()->load->helper('form');

		// ee()->cp->set_breadcrumb( DATAGRAB_URL, ee()->lang->line('ajw_datagrab_module_name') );

		// Set data
		if ( $id == 0 ) {
			
			if(version_compare(APP_VER, '2.6', '>=')) {
				ee()->view->cp_page_title = "Save import";
			} else {
				ee()->cp->set_variable('cp_page_title', "Save import");
			}
			$data["title"] = "Save import";
			$name = "";
			$description = "";
			$passkey = "";

		} else {

			if(version_compare(APP_VER, '2.6', '>=')) {
				ee()->view->cp_page_title = "Update import";
			} else {
				ee()->cp->set_variable('cp_page_title', "Update import");
			}
			
			$data["title"] = "Update import";
			
			ee()->db->where('id', $id );
			$query = ee()->db->get('exp_ajw_datagrab');
			$row = $query->row_array();
			
			$name = $row["name"];
			$description = $row["description"];
			$passkey = $row["passkey"];
			
		}
		
		$data["content"] = 'save';
		
		$data["form"] = array(
			array( 
				'<em class="required">*</em> ' .
				form_label('Name', 'name') .
				'<div class="ajw_datagrab_subtext">A title for the import</div>',  
				form_input(
					array(
						'name' => 'name',
						'id' => 'name',
						'value' => $name,
						'size' => '50'
						)
					) 
				),
			array( 
				form_label('Description', 'description') .
				'<div class="ajw_datagrab_subtext">A description of the import</div>', 
				form_textarea(
					array(
						'name' => 'description',
						'id' => 'description',
						'value' => $description,
						'rows' => '4',
						'cols' => '64'
						)
					)
				),
			array( 
				form_label('Passkey', 'passkey') . 
				'<div class="ajw_datagrab_subtext">Add a passkey to increase security against<br/>saved imports being run inadvertently</div>', 
				form_input(
					array(
						'name' => 'passkey',
						'id' => 'passkey',
						'value' => $passkey,
						'style' => 'width:50%'
						)
					) . NBS . 
					form_button(
						array(
							'id' => 'generate', 
							'name' => 'generate',
							'content' => 'Generate random key',
							'class' => 'btn'
						) 
					)
				) 
		);

		$data["id"] = $id;
		
		// Form action URL
		// $data["form_action"] = DATAGRAB_PATH.AMP.'method=do_save';
		$data["form_action"] = ee('CP/URL', 'addons/settings/ajw_datagrab/do_save');
		
		ee()->load->library('javascript');
		ee()->javascript->output('
			var chars = "0123456789ABCDEF";
			var string_length = 32;
		$("#generate").click( function() {
			var randomstring = "";
			for (var i=0; i<string_length; i++) {
				var rnum = Math.floor(Math.random() * chars.length);
				randomstring += chars.substring(rnum,rnum+1);
			}
			$("#passkey").val(randomstring);
		});
		');
		ee()->javascript->compile();
		
		
		// Load view
		return array(
			'body' => ee()->load->view('_wrapper', $data, TRUE),
			'breadcrumb' => array( ee('CP/URL', 'addons/settings/ajw_datagrab')->compile() => ee()->lang->line('ajw_datagrab_module_name') ),
			'heading' => 'Save import'
		);
	}

	function do_save() {

		$this->_get_input();

		ee()->load->helper('date');

		$id = ee()->input->post("id");

		$data = array(
			'name' => ee()->input->post( "name" ),
			'description' => ee()->input->post( "description" ),
			'passkey' => ee()->input->post( "passkey" ),
			'last_run' => now()
		);
		
		if( isset( $this->settings["import"]["type"] ) ) {
			$data['settings'] = serialize( $this->settings );
		} else {
			// Fetch settings from database
			ee()->db->select('settings');
			ee()->db->where('id', $id);
			$query = ee()->db->get('exp_ajw_datagrab');
			$row = $query->row_array();
			$data['settings'] = $row["settings"];
			$this->settings = unserialize( $data['settings'] );
		}

		// Get site_id from channel label
		ee()->db->select('site_id');
		if( is_numeric($this->settings["import"]["channel"]) ) {
			ee()->db->where( 'channel_id', $this->settings["import"]["channel"] );
		} else {
			ee()->db->where( 'channel_name', $this->settings["import"]["channel"] );
			ee()->db->where('site_id', ee()->config->item('site_id') );
		}
		$query = ee()->db->get('exp_channels');
		$channel_defaults = $query->row_array();
		$data["site_id"] = $channel_defaults["site_id"];
		
		if( $id == "" OR $id == "0" ) {
			ee()->db->insert('exp_ajw_datagrab', $data);
		} else {
			ee()->db->where('id', $id );
			ee()->db->update('exp_ajw_datagrab', $data);	
		}

		ee()->session->set_flashdata('message_success', "Import saved.");

		ee()->functions->redirect(ee('CP/URL')->make( 'addons/settings/ajw_datagrab' ) ); 
		
	}

	function load() {

		if ( ee()->input->get( "id" ) != 0 ) {
			ee()->db->where('id', ee()->input->get( "id" ) );
			$query = ee()->db->get('exp_ajw_datagrab');
			$row = $query->row_array();
			$this->settings = unserialize($row["settings"]);
			$this->settings["import"]["id"] = ee()->input->get( "id" );
			$this->_set_session( 'settings', serialize( $this->settings ) );
		}

		ee()->functions->redirect( ee('CP/URL')->make( 'addons/settings/ajw_datagrab/configure_import', array( "id" => ee()->input->get( "id" ) ) ) ); 
		/// ee()->functions->redirect(DATAGRAB_URL.AMP."method=configure_import".AMP."id=".ee()->input->get( "id" )); 
	}

	function run() {

		if ( ee()->input->get( "id" ) != 0 ) {
			ee()->db->where('id', ee()->input->get( "id" ) );
			$query = ee()->db->get('exp_ajw_datagrab');
			$row = $query->row_array();
			$this->settings = unserialize($row["settings"]);
			$this->settings["import"]["id"] = ee()->input->get( "id" );
			$this->_set_session( 'settings', serialize( $this->settings ) );
		}

		if ( ee()->input->get( "batch" ) == "yes") {
			ee()->functions->redirect(ee('CP/URL')->make( 'addons/settings/ajw_datagrab/import', array('batch' => 'yes' ) ));
		} else {
			ee()->functions->redirect(ee('CP/URL')->make( 'addons/settings/ajw_datagrab/import', array('id' => $row["id"] ) )); 			
		}
	}

	function delete() {
		
		$id = ee()->input->get( "id" );

		// Set page title
		if(version_compare(APP_VER, '2.6', '>=')) {
			ee()->view->cp_page_title = "Confirm delete";
		} else {
			ee()->cp->set_variable('cp_page_title', "Confirm delete");
		}
		

		// Set breadcrumb
		// ee()->cp->set_breadcrumb( DATAGRAB_URL, ee()->lang->line('ajw_datagrab_module_name') );

		// Load helpers
		ee()->load->helper('form');

		// Set data
		$data["title"] = "Confirm delete";
		$data["content"] = 'delete';
		
		$data["id"] = $id;
		
		// Form action URL
		// $data["form_action"] = DATAGRAB_PATH.AMP.'method=do_delete';
		$data["form_action"] = ee('CP/URL', 'addons/settings/ajw_datagrab/do_delete');
		
		// Load view
		return array(
			'body' => ee()->load->view('_wrapper', $data, TRUE),
			'breadcrumb' => array( ee('CP/URL', 'addons/settings/ajw_datagrab')->compile() => ee()->lang->line('ajw_datagrab_module_name') ),
			'heading' => 'Delete import'
		);		
	}

	function do_delete() {
		
		$id = ee()->input->post("id");

		if( $id != "" && $id != "0" ) {
			ee()->db->where('id', $id );
			ee()->db->delete('exp_ajw_datagrab');	
		}
		
		ee()->session->set_flashdata('message_success', "Deleted");

		ee()->functions->redirect( ee('CP/URL')->make( 'addons/settings/ajw_datagrab' ) );
		
	}

	/* 
	
	HELPER FUNCTIONS
	
	*/

	/**
	 * Add $data to user session
	 *
	 * @param string $key 
	 * @param string $data 
	 * @return void
	 */
	function _set_session( $key, $data ) {
		@session_start();
		if ( !isset( $_SESSION[ $this->module_name ] ) ) {
			$_SESSION[ $this->module_name ] = array();
		}
		$_SESSION[ $this->module_name ][ $key ] = $data;
	}

	/**
	 * Retrieve data from session. Data is removed from session unless $keep is
	 * set to TRUE
	 *
	 * @param string $key 
	 * @param string $keep 
	 * @return void $data
	 */
	function _get_session( $key, $keep = FALSE ) {
		@session_start();  
		if( isset( $_SESSION[ $this->module_name ] ) ) {
			if( isset( $_SESSION[ $this->module_name ][ $key ] ) ) {
				$data = $_SESSION[ $this->module_name ][ $key ];
				if ( $keep != TRUE ) {
		    	unset($_SESSION[ $this->module_name ][ $key ]); 
		    	unset($_SESSION[ $this->module_name ]); 
				}
				return( $data );
			}
		}
		return "";
	}

	/**
	 * Handle input from forms, sessions
	 * 
	 * Collects data from forms, query strings and sessions. Only keeps relevant data
	 * for the current import data type. Stores in session to allow back-and-forth
	 * through 'wizard'
	 *
	 */
	function _get_input() {
		
		// Get current settings from session
		$this->settings = unserialize( $this->_get_session( 'settings' ) );

		$datagrab_step = ee()->input->get_post("datagrab_step", "default");
		switch( $datagrab_step ) {

			// Step 1: choose import type
			case "index": {
				$this->settings["import"]["type"] = ee()->input->get_post("type");
				break;
			}

			// Step 2: set up datatype
			case "settings": {
				$this->settings["import"]["channel"] = 
					ee()->input->get_post("channel");
				// Check datatype specific settings
				if( isset( $this->settings["import"]["type"] ) && 
					$this->settings["import"]["type"] != "" ) {
					ee()->datagrab->initialise_types();
					$datatype_settings = ee()->datagrab->datatypes[ 
						$this->settings["import"]["type"] ]->settings;
					foreach( $datatype_settings as $option => $value ) {
						if( ee()->input->get_post( $option ) !== FALSE ) {
							$this->settings["datatype"][ $option ] = 
								ee()->input->get_post( $option );
						}
					}
				}
				break;
			}
			
			case "configure_import": {
				
				$allowed_settings = array(
					"type",
					"channel",
					"update",
					"unique",
					"author",
					"author_field",
					"author_check",
					"offset",
					"title",
					"url_title",
					"date",
					"expiry_date",
					"timestamp",
					"delete_old",
					"delete_by_timestamp",
					"delete_by_timestamp_duration",
					"cat_default",
					"cat_field",
					"cat_group",
					"cat_delimiter",
					"id",
					"status",
					"import_comments",
					"comment_author",
					"comment_email",
					"comment_date",
					"comment_url",
					"comment_body",
					"ajw_entry_id",
					"c_groups"
					);

				// Look through permitted settings, check whether a new POST var exists, and update
				foreach( $allowed_settings as $setting ) {
					if( ee()->input->post( $setting ) !== FALSE ) {
						$this->settings["config"][ $setting ] = ee()->input->post( $setting );
					}
				}

				if( ee()->input->post( "limit" ) !== FALSE ) {
					$this->settings["import"][ "limit" ] = ee()->input->post( "limit" );
				}

				
				// Hack to handle checkboxes (whose post vars are not set if unchecked)
				// todo: improve this - use hidden field?
				if( ee()->input->get("method") == "import" ) {
					$checkboxes = array("update", "delete_old", "import_comments");
					foreach( $checkboxes as $check ) {
						if( !isset( $this->settings["config"][ $check ] ) ) {
							$this->settings["config"][ $check ] = ee()->input->post( $check );
						}
					}
				}
				
				// Get category group details
				$cat_settings = array(
					"cat_default",
					"cat_field",
					"cat_delimiter"
				);
				$c_groups = ee()->input->post("c_groups");
				foreach( explode("|", $c_groups) as $cat_group_id ) {
					foreach( $cat_settings as $cs ) {
						$setting = $cs . "_" . $cat_group_id;
						if( ee()->input->post( $setting ) !== FALSE ) {
							$this->settings["config"][ $setting ] = ee()->input->post( $setting );
						}
					}
				}
				
				// Check for custom field settings
				if( isset($this->settings["import"]["channel"]) && $this->settings["import"]["channel"] != "" ) {

					$channel = ee('Model')->get('Channel', $this->settings["import"]["channel"] )->first();

					// Look through field types and see if they need to register any extra variables
					foreach ( $channel->getAllCustomFields() as $row ) {

						if( ee()->input->post( $row->field_name ) !== FALSE ) {
							$this->settings["cf"][ $row->field_name ] = ee()->input->post( $row->field_name );
						}

						// Do we need to save any extra settings information?
						if ( ! class_exists('Datagrab_fieldtype') ) {
							require_once PATH_THIRD.'ajw_datagrab/libraries/Datagrab_fieldtype'.".php";
						}	
						if ( ! class_exists('Datagrab_'.$row->field_type ) ) {
							if( file_exists( PATH_THIRD.'ajw_datagrab/fieldtypes/datagrab_'.$row->field_type.".php" ) ) {
								require_once PATH_THIRD.'ajw_datagrab/fieldtypes/datagrab_'.$row->field_type.".php";
							}
						}	
						
						if ( class_exists('Datagrab_'.$row->field_type) ) {
							$classname = "Datagrab_".$row->field_type;
							$ft = new $classname();
							$type_settings = $ft->register_setting( $row->field_name );
							foreach( $type_settings as $fld ) {
								if( ee()->input->post( $fld ) !== FALSE ) {
									$this->settings["cf"][ $fld ] = ee()->input->post( $fld );
								}
							}
						} 

					}

				}

				// Pages configuration
				if (array_key_exists('pages', ee()->addons->get_installed('modules'))) {
					$this->settings["cf"][ "ajw_pages" ] = ee()->input->post( 'ajw_pages' );
					$this->settings["cf"][ "ajw_pages_url" ] = ee()->input->post( 'ajw_pages_url' );
					$this->settings["cf"][ "ajw_pages_template" ] = ee()->input->post( 'ajw_pages_template' );
				}

				// Transcribe configuration
				if (array_key_exists('transcribe', ee()->addons->get_installed('modules'))) {
					$this->settings["config"][ "ajw_transcribe_language" ] = ee()->input->post( 'ajw_transcribe_language' );
					$this->settings["config"][ "ajw_transcribe_related_entry" ] = ee()->input->post( 'ajw_transcribe_related_entry' );
				}

				// SEO Lite configuration
				if (array_key_exists('seo_lite', ee()->addons->get_installed('modules'))) {
					$this->settings["cf"][ "ajw_seo_lite_title" ] = ee()->input->post( 'ajw_seo_lite_title' );
					$this->settings["cf"][ "ajw_seo_lite_keywords" ] = ee()->input->post( 'ajw_seo_lite_keywords' );
					$this->settings["cf"][ "ajw_seo_lite_description" ] = ee()->input->post( 'ajw_seo_lite_description' );
				}

				if (array_key_exists('nsm_better_meta', ee()->addons->get_installed('modules'))) {
					$this->settings["cf"][ "ajw_nsm_better_meta_title" ] = ee()->input->post( 'ajw_nsm_better_meta_title' );
					$this->settings["cf"][ "ajw_nsm_better_meta_keywords" ] = ee()->input->post( 'ajw_nsm_better_meta_keywords' );
					$this->settings["cf"][ "ajw_nsm_better_meta_description" ] = ee()->input->post( 'ajw_nsm_better_meta_description' );
				}

				break;
			}
			

			default: {
			}

		}

		// Get saved import id
		if( ee()->input->get( "id" ) !== FALSE ) {
			$this->settings["import"][ "id" ] = ee()->input->get_post( "id" );
		}

		// print_r( $this->settings ); exit;

		// Store settings in session
		$this->_set_session( 'settings', serialize( $this->settings ) );
	}

	function clear() {
		$this->_set_session( 'settings', serialize( array() ) );
	}

}

/* End of file mcp.ajw_datagrab.php */