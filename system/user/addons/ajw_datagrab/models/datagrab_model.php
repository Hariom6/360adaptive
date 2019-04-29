<?php
/**
 * DataGrab Model Class
 *
 * Handles the DataGrab import process
 * 
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 */
class Datagrab_model extends CI_Model {
	
	var $datatypes = array();
	
	var $settings;
	var $datatype;
	var $channel_defaults;
	var $batch_limit_completed;
	var $entries;
	var $field_settings;
	var $valid_statuses;
	
	var $check_memory = FALSE;
	var $mem_usage = 0;
	var $current_user = 0;
	
	var $is_saved_import = FALSE;
	var $is_batch_import = FALSE;
	var $is_first_run = TRUE;
	var $saved_import = array();

	var $return_data = "";
	
	function __construct() {
		parent::__construct(); 
	}

	function fetch_datatype_names() {
		
		$this->initialise_types();

		$types = array();
		foreach( $this->datatypes as $type_name => $type ) {
			$types[ $type_name ] = $type->display_name();
		}
		
		return $types;
	}


	function initialise_types() {
		
		if ( ! class_exists('Datagrab_type') ) {
			require_once PATH_THIRD.'ajw_datagrab/libraries/Datagrab_type'.".php";
		}	
		
		$path = PATH_THIRD.'ajw_datagrab/datatypes/';
		
		$dir = opendir($path);

		while (($folder = readdir($dir)) !== FALSE) {
			if( is_dir($path.$folder) 
				&& $folder != "." && $folder != ".." 
				&& substr( $folder, 0, 1) != "_" ) {
				$filename = "/dt." . $folder . ".php";
				if ( ! class_exists( $folder ) ) {
					if( file_exists( $path.$folder.$filename ) ) {
						include($path.$folder.$filename);
						if (class_exists($folder)) {
							$this->datatypes[$folder] = new $folder();
						}
					}
				}
			}
		}
		closedir($dir);
		
		ksort( $this->datatypes );
		
		return count( $this->datatypes );
		
	}

	function do_import( $datatype, $settings ) {
	
		$this->datatype = $datatype;
		$this->settings = $settings;
		
		// Initialise
		// $this->load->library('api');
		// $this->legacy_api->instantiate('channel_fields');
		// $this->api_channel_fields->fetch_custom_channel_fields();
		// $this->legacy_api->instantiate('channel_entries');
		
		$this->load->library('addons');
		$this->load->helper('date');
		$this->load->language('content');
		
		/*
		if (function_exists('date_default_timezone_set')) { 
			date_default_timezone_set( $this->config->item('default_site_timezone') );
		}
		*/
		date_default_timezone_set( 'UTC' );

		// Set up the data source
		$this->initialise_types();
		$datatype->initialise( $this->settings );
		$datatype->fetch();

		// Get custom fields from database
		$custom_fields = $this->_fetch_custom_fields_from_channel( $this->settings["import"]["channel"] );

		// Get channel details for default values
		$this->channel_defaults = $this->_fetch_channel_defaults( $this->settings["import"]["channel"] );
		
		// Can the current member use the Channel API (the import might not be running from
		// the Control Panel)?
		$this->_check_member_status();

		// Is this a saved import?
		if( isset( $settings["import"]["id"] ) ) {

			// Fetch import details
			$this->is_saved_import = $settings["import"]["id"];
			$this->db->where( "id", $this->is_saved_import );
			$query = $this->db->get( "exp_ajw_datagrab" );
			if( $query->num_rows() ) {
				$this->saved_import = $query->row_array();
			}

			// Is this a batch import?
			if( isset( $this->settings["import"]["batch"] ) && $this->settings["import"]["batch"] == "yes" ) {

				$this->is_batch_import = TRUE;

				// Is the existing import still running? If so, abort...
				if( $this->saved_import["import_status"] == "RUNNING" || 
						$this->saved_import["import_status"] == "COMPLETE" ) {
					print "Import already running...";
					exit;
				}

				// Else, continue an existing import
				if( $this->saved_import["import_lastrecord"] != 0 ) {
					$this->settings["datatype"]["skip"] = $this->saved_import["import_lastrecord"];
				}
				$this->db->where("id", $this->is_saved_import );
				$this->db->update( "exp_ajw_datagrab", 
					array( 
						"import_status" => "RUNNING"
					) 
				);

				// Is this a new import?
				if( $this->saved_import["import_status"] != "WAITING" ) {
					$total_rows = $datatype->total_rows();
					$datatype->fetch();
					$this->db->where("id", $this->is_saved_import );
					$this->db->update( "exp_ajw_datagrab", 
						array( 
							"import_totalrecords" => $total_rows
						) 
					);				
				} else {
					$this->is_first_run = FALSE;
				}

			} else {

				$total_rows = $datatype->total_rows();
				$datatype->fetch();

				// Not a batch import, so reset the status
				$this->db->where("id", $this->is_saved_import );
				$this->db->update( "exp_ajw_datagrab", 
					array( 
						"import_status" => "RUNNING",
						"import_lastrecord" => 0,
						"import_totalrecords" => $total_rows
					) 
				);				
			}

			// print "<pre>"; print_r( $this->settings ); exit;
		}

		// Set up initial variables
		$entries_added = 0;
		$entries_updated = 0;
		$entries_error = 0;
		$row_num = 0;
		if( $this->is_saved_import && $this->is_first_run ) {
			$timestamp = time();
			$import_id = md5( uniqid( mt_rand(), true ) );
		} else {			
			$timestamp = isset( $this->saved_import["last_started"] ) ? $this->saved_import["last_started"] : time();
			$import_id = isset( $this->saved_import["import_id"] ) ? $this->saved_import["import_id"] : md5( uniqid( mt_rand(), true ) );
		}
		$this->entries = array(); // Used to store a list of which entries have been imported/updated
		$this->batch_limit_completed = FALSE;

		$this->_tic("import");

		// -------------------------------------------
		// 'ajw_datagrab_pre_import' hook.
		//  - Perform actions before the import is run
		//
			if ($this->extensions->active_hook('ajw_datagrab_pre_import') === TRUE)
			{
				$edata = $this->extensions->call('ajw_datagrab_pre_import', $this);
				if ($this->extensions->end_script === TRUE) return;
			}
		//
		// -------------------------------------------

		// Don't save queries
		$this->db->save_queries = FALSE;

		// Loop over items
		while( $item = $datatype->next() ) {

			// Reset time out
			set_time_limit(30);

			if( $this->check_memory ) { $this->_check_mem_usage("Start"); }

			// Check whether to skip this row or not
			$row_num++;
			if( isset($this->settings["datatype"]["skip"]) && is_numeric($this->settings["datatype"]["skip"]) ) {
				if ( $row_num <= $this->settings["datatype"]["skip"] ) {
					continue;
				}
			}

			if( isset($this->settings["import"]["limit"]) && is_numeric($this->settings["import"]["limit"]) && $this->settings["import"]["limit"] > 0 ) {
				if( !isset( $this->settings["datatype"]["skip"] ) ) {
					$this->settings["datatype"]["skip"] = 0;
				}
				if ( $row_num - $this->settings["datatype"]["skip"] > $this->settings["import"]["limit"] ) {
					$this->batch_limit_completed = TRUE;
					break;
				}
			}

			// -------------------------------------------
			// 'ajw_datagrab_modify_data' hook.
			//  - Perform actions before the import is run
			//
				if ($this->extensions->active_hook('ajw_datagrab_modify_data') === TRUE)
				{
					$item = $this->extensions->call('ajw_datagrab_modify_data', $item);
					if ($this->extensions->end_script === TRUE) return;
				}
			//
			// -------------------------------------------

			// Initialise array to store entry data
			$data = array();

			// Get title
			$data["title"] = $datatype->get_item( $item, $this->settings["config"][ "title" ] );
		
			// Get date
			$date = isset( $this->settings["config"][ "date" ] ) ? $this->settings["config"][ "date" ] : '';
			$data[ "entry_date" ] = $this->_parse_date( $datatype->get_item( $item, $date ) );

			// Get date
			$expiry_date = isset( $this->settings["config"][ "expiry_date" ] ) ? $this->settings["config"][ "expiry_date" ] : '';
			If( $datatype->get_item( $item, $expiry_date ) != "" ) {
				$data[ "expiration_date" ] = $this->_parse_date( $datatype->get_item( $item, $expiry_date ) );
			}
			
			// Get URL title
			// $data["url_title"] =  url_title( strtolower( $data["title"] ) );
			$data["url_title"] = ee('Format')->make('Text', $data["title"])->urlSlug();
			if( isset( $this->settings["config"][ "url_title" ] ) ) {
				// Get URL title from data source
				$url_title = $datatype->get_item( $item, $this->settings["config"][ "url_title" ] );
				if( $url_title != "" ) {
					$data["url_title"] =  $url_title;
				}
			}

			if( $this->channel_defaults["url_title_prefix"] != "" ) {
				$url_title = ee('Format')->make('Text', $data["title"])->urlSlug();
				// $url_title = url_title( strtolower( $data["title"] ) );
				$url_title = $this->channel_defaults["url_title_prefix"] . $url_title;
				$data["url_title"] =  $url_title;
			}
			
			$data["url_title"] = str_replace("#", "", $data["url_title"]);

			// @todo: Load static data

			// Loop over all custom fields in this channel
			foreach( $custom_fields as $field => $field_data ) {

				// print "<p>$field " .  $this->settings["cf"][ $field ] . "</p>";

				if( $field_data["type"] == "toggle" ) {
					$data[ "field_id_" . $field_data["id"] ] = 0;
				}

				// Should we import anything into this field?
				if( isset( $this->settings["cf"][ $field ] ) && $this->settings["cf"][ $field ] != "" ) {

					// Check to see if a handler exists for this field type
					if ( ! class_exists('Datagrab_fieldtype') ) {
						require_once PATH_THIRD.'ajw_datagrab/libraries/Datagrab_fieldtype'.".php";
					}	
					if ( ! class_exists('Datagrab_'.$field_data[ "type" ] ) ) {
						if( file_exists( PATH_THIRD.'ajw_datagrab/fieldtypes/datagrab_'.$field_data[ "type" ].".php" ) ) {
							require_once PATH_THIRD.'ajw_datagrab/fieldtypes/datagrab_'.$field_data[ "type" ].".php";
						}
					}	
					
					// If so, call the prepare_post_data method to format the $data array
					if ( class_exists('Datagrab_'.$field_data[ "type" ]) ) {
						$classname = "Datagrab_".$field_data[ "type" ];
						$ft = new $classname();
						$ft->prepare_post_data( $this, $item, $field_data["id"], $field, $data );
					} else {
						// If no handler exists, just use the value
						$data[ "field_id_" . $field_data["id"] ] = $datatype->get_item( $item, $this->settings["cf"][ $field ] );
					}

					$data[ "field_ft_" . $field_data["id"] ] = $field_data[ "format" ];

				}
			}

			// Set timestamp field if one is set
			if( isset( $this->settings["config"]["timestamp"] ) && $this->settings["config"]["timestamp"] != "" ) {
				$field_id = $custom_fields[ $this->settings["config"]["timestamp"] ][ "id"];
				$data[ "field_id_".$field_id ] = $timestamp;
			}
			
			// No of seconds to offset dates/times
			$time_offset = $this->settings["config"]["offset"]; 
			$data["entry_date"] += $time_offset;

			// Fetch author id
			$author_id = $this->_fetch_author( $item );

			// Get status
			$status = $this->_fetch_status( $item );

			// Handle Structure module fields
			// @todo: check whether Structure now can handle API entry
			if( $this->db->table_exists('exp_structure_channels') ) {
				// If the structure module tables exists, try and get template id
				$this->db->select( 'template_id' );
				$this->db->from( 'exp_structure_channels' );
				$this->db->where( 'channel_id', $this->channel_defaults["channel_id"] );
				// print( $this->db->_compile_select() ); exit;
				$query = $this->db->get();
				if( $query->num_rows() > 0 ) {

					$row = $query->row_array();

					$data["cp_call"] = TRUE;
					$data["structure__uri"] = url_title( strtolower( $data["title"] ) );
					$data["structure__template_id"]= $row["template_id"];
					$data["structure__parent_id"] = 0;
					
					// Not needed?
					// $data["structure__listing_channel"] = 0;					
					
					// Eek! Workaround Structure 'bug' that expects post data
					$_POST["channel_id"] = $this->channel_defaults["channel_id"];
					$_POST["template_id"] = $data['structure__template_id'];
					$_POST["parent_id"] = $data["structure__parent_id"];
					
					// Structure uses config variable to get site_pages
					// This only gets updated on page load (ie, once at the start
					// of the import) so we have to keep updating it here...
					$this->db->select('site_pages');
					$this->db->where('site_id', $this->config->item('site_id'));
					$query = $this->db->get('sites');
					$site_pages = unserialize(base64_decode($query->row('site_pages')));
					$this->config->config["site_pages"] = $site_pages;
					
					// print_r( $site_pages );
					// print_r( $this->config->config["site_pages"] );
				}
			}

			// Handle Pages module
			if( array_key_exists('pages', $this->addons->get_installed('modules')) && 
				isset( $this->settings["cf"]["ajw_pages"]) && 
			 	$this->settings["cf"]["ajw_pages"] == "y" ) {
				$data["cp_call"] = TRUE;
				if( $this->settings["cf"]["ajw_pages_url"] == "" ) {
					$data["pages__pages_uri"] = url_title( strtolower( $data["title"] ) );
					$_POST["pages__pages_uri"] = url_title( strtolower( $data["title"] ) );
				} else {
					$data["pages__pages_uri"] = $datatype->get_item( $item, $this->settings["cf"]["ajw_pages_url"] );
					$_POST["pages__pages_uri"] = $datatype->get_item( $item, $this->settings["cf"]["ajw_pages_url"] );
				}
				$default_template = 1;
				$this->db->select( "configuration_value" );
				$this->db->where( "configuration_name", "template_channel_".$this->channel_defaults["channel_id"] );
				$query = $this->db->get( "exp_pages_configuration" );
				if( $query->num_rows() > 0 ) {
					$row = $query->row_array();
					$default_template = $row["configuration_value"];
				} else {
					$this->db->select( "exp_templates.template_id" );
					$this->db->from( "exp_templates" );
					$this->db->join( "exp_template_groups", "exp_template_groups.group_id = exp_templates.group_id" );
					$this->db->where( "is_site_default", "y" );
					$this->db->where( "template_name", "index" );
					$query = $this->db->get();
					$row = $query->row_array();
					$default_template = $row["template_id"];
				}
				if( $this->settings["cf"]["ajw_pages_template"] == "" ) {
					$data["pages__pages_template_id"] = $default_template;
					$_POST["pages__pages_template_id"] = $default_template;
				} else {
					$template = $datatype->get_item( $item, $this->settings["cf"]["ajw_pages_template"] );					
					$data["pages__pages_template_id"] = $default_template;
					$_POST["pages__pages_template_id"] = $default_template;
					$template_segments = explode("/", $template);
					if( count($template_segments) == 2 ) {
						$this->db->select( "exp_templates.template_id" );
						$this->db->from( "exp_templates" );
						$this->db->join( "exp_template_groups", "exp_template_groups.group_id = exp_templates.group_id" );
						$this->db->where( "group_name", $template_segments[0] );
						$this->db->where( "template_name", $template_segments[1] );
						$query = $this->db->get();
						if( $query->num_rows() > 0 ) {
							$row = $query->row_array();
							$default_template = $row["template_id"];
						}
					}
				}
				$data["pages__pages_template_id"] = $default_template;
				$_POST["pages__pages_template_id"] = $default_template;
			}

			// Handle SEO Lite
			if( isset( $this->settings["cf"]["ajw_seo_lite_title"] ) ) {
				$data["seo_lite__seo_lite_title"] = $datatype->get_item( $item, $this->settings["cf"]["ajw_seo_lite_title"] );
				$data["seo_lite__seo_lite_keywords"] = $datatype->get_item( $item, $this->settings["cf"]["ajw_seo_lite_keywords"] );
				$data["seo_lite__seo_lite_description"] = $datatype->get_item( $item, $this->settings["cf"]["ajw_seo_lite_description"] );
				$data["cp_call"] = TRUE;
			}

			// Handle Transcribe 
			if( array_key_exists('transcribe', $this->addons->get_installed('modules') ) ) {

				if( isset( $this->settings["config"]["ajw_transcribe_language"] ) && 
					$this->settings["config"]["ajw_transcribe_language"] != 0 ) {
					// $data["transcribe__transcribe_language"] = $this->settings["config"]["ajw_transcribe_language"];
					$_POST["transcribe__transcribe_language"] = $this->settings["config"]["ajw_transcribe_language"];
					$transcribe__transcribe_language = $this->settings["config"]["ajw_transcribe_language"];
				} else {
					// Find default language
				 	$this->db->select( "language_id" );
					$this->db->where( "site_id", $this->config->item('site_id') );
					$query = $this->db->get( "exp_transcribe_settings" );
					if( $query->num_rows() ) {
						$row = $query->row_array();
						// $data["transcribe__transcribe_language"] = $row["language_id"];
						$_POST["transcribe__transcribe_language"] = $row["language_id"];
						$transcribe__transcribe_language = $row["language_id"];
					}
				}

				if( isset( $this->settings["config"]["ajw_transcribe_related_entry"] ) && 
					$this->settings["config"]["ajw_transcribe_related_entry"] != "" ) {
					$field_id = $custom_fields[ $this->settings["config"]["ajw_transcribe_related_entry"] ][ "id" ];
					$rel_field_id = $this->settings["cf"][ $this->settings["config"]["ajw_transcribe_related_entry"] ];

					$this->db->select('t.relationship_id');
					$this->db->from('exp_transcribe_entries_languages t');
					$this->db->join('exp_channel_data d','d.entry_id = t.entry_id');
					$this->db->where( "field_id_".$field_id, $datatype->get_item( $item, $rel_field_id ) );
					$this->db->where( "language_id !=", $transcribe__transcribe_language );
					$query = $this->db->get();
					if( $query->num_rows() ) {
						$row = $query->row_array();
						$_POST["transcribe__transcribe_related_entries"] = $transcribe__transcribe_language . "__" . $row["relationship_id"];
					}
				}
				$data["cp_call"] = TRUE;
				// print_r( $data );
			}

			// Check for duplicate entry
			if( ! isset( $this->settings["config"][ "unique" ] ) ) {
				$this->settings["config"]["unique"] = '';
			}

			// Check whether this entry is a duplicate
			$entry_id = $this->_is_entry_unique( $data, $this->settings["config"]["unique"], $custom_fields );

			// Do entry_id field check here
			if( isset( $this->settings["config"]["ajw_entry_id"] ) && $this->settings["config"]["ajw_entry_id"] != "" ) {
				// Check whether entry_id exists
				$e_id = $datatype->get_item( $item, $this->settings["config"]["ajw_entry_id"] );
				$this->db->select( "entry_id" );
				$this->db->where( "entry_id", $e_id );
				$this->db->where( "channel_id", $this->channel_defaults["channel_id"] );
				$query = $this->db->get( "exp_channel_titles" );
				if( $query->num_rows() > 0 ) {
					$entry_id = $e_id;
				}
			}

			// Do extra processing now we know if it is an update or not
			foreach( $custom_fields as $field => $field_data ) {

				// Should we import anything into this field?
				if( isset( $this->settings["cf"][ $field ] ) && $this->settings["cf"][ $field ] != "" ) {

					// Check to see if a handler exists for this field type
					if ( ! class_exists('Datagrab_fieldtype') ) {
						require_once PATH_THIRD.'ajw_datagrab/libraries/Datagrab_fieldtype'.".php";
					}	
					if ( ! class_exists('Datagrab_'.$field_data[ "type" ] ) ) {
						if( file_exists( PATH_THIRD.'ajw_datagrab/fieldtypes/datagrab_'.$field_data[ "type" ].".php" ) ) {
							require_once PATH_THIRD.'ajw_datagrab/fieldtypes/datagrab_'.$field_data[ "type" ].".php";
						}
					}	
					
					// If so, call the prepare_post_data method to format the $data array
					if ( class_exists('Datagrab_'.$field_data[ "type" ]) ) {
						$classname = "Datagrab_".$field_data[ "type" ];
						$ft = new $classname();
						// $update = $entry_id > 0 && isset( $this->settings["config"]["update"] ) && $this->settings["config"]["update"] == "y";
						$ft->final_post_data( $this, $item, $field_data["id"], $field, $data, $entry_id );
					} 
					
				}
			}
			$data["cp_call"] = TRUE;

			// Handle NSM Better Meta
			if( isset( $this->settings["cf"]["ajw_nsm_better_meta_title"] ) ) {
				$nsm_data = array(
					"id" => "",
					"entry_default" => "",
					"language_id" => "",
					"title" => $datatype->get_item( $item, $this->settings["cf"]["ajw_nsm_better_meta_title"] ),
					"keywords" => $datatype->get_item( $item, $this->settings["cf"]["ajw_nsm_better_meta_keywords"] ),
					"description" => $datatype->get_item( $item, $this->settings["cf"]["ajw_nsm_better_meta_description"] ),
					"keywords_append_default" => "y"
				);
				if( $entry_id != 0 ) {
					$this->db->select( "id" );
					$this->db->where( "entry_id", $entry_id );
					$query = $this->db->get( "exp_nsm_better_meta" );
					if( $query->num_rows() ) {
						$row = $query->row_array();
						$nsm_data["id"] = $row["id"];
					}
				}
				$data["nsm_better_meta__meta"] = array( $nsm_data );
				$data["cp_call"] = TRUE;
			}

			
// print "<pre>"; print_r( $data ); exit;


			// If it is a new entry...
			if (  $entry_id == 0 ) {
				
				// Insert (titles, data, categories)
			
				$this->return_data .= "<p>Found new entry: " . $data[ "title" ] . "</p>\n";
		
				// Add entry using EE channel API
				
				// $data["channel_id"] = $this->channel_defaults["channel_id"]; // Shouldn't need this... (http://expressionengine.com/bug_tracker/bug/13483/)

				$data["channel_id"] = (string)$this->channel_defaults["channel_id"];

				$data["status"] = $status;
				$data["author_id"] = $author_id;
				$data["ping_servers"] = array(); // API bug (http://expressionengine.com/bug_tracker/bug/14008/)
				$data["allow_comments"] = $this->channel_defaults["deft_comments"];

				// Find and create categories
				$entry_categories = $this->_setup_categories( $item );
				$data["categories"] = $entry_categories;

				// $this->api_channel_entries->entry_id = 0; // to work around bug in API (http://expressionengine.com/bug_tracker/bug/13549/)

				// Stop numeric url titles
				if( is_numeric( $data["title"] ) && ! isset( $data["url_title"] ) ) {
					$data["url_title"] = "event-".url_title( $data["title"], $this->config->item('word_separator'), TRUE );
				}

				// Disable notices while running this. Too many 3rd party fieldtypes
				// giving notices
				$errorlevel = error_reporting();
				error_reporting( $errorlevel & ~E_NOTICE );


				$entry = ee('Model')->make('ChannelEntry');
				$entry->entry_id = 0;
				$entry->author_id = $author_id;
				$entry->sticky = "n";

				$channel = ee('Model')->get('Channel', $this->channel_defaults["channel_id"])
							->first();

				$entry->Channel = $channel;
				$entry->site_id = ee()->config->item('site_id');
				$entry->set($data);

// print "<pre>"; print_r( $entry ); exit;

				$result = $entry->validate();

				// print_r( $result ); exit;
				if( $result->isValid() ) {

					$entry->save();

					$entry_id = $entry->entry_id;
					$this->return_data .= "<p>Entry added</p>\n";
					$entries_added++;

					if( isset( $this->settings["config"]["import_comments"] ) && $this->settings["config"]["import_comments"] == "y") {
						$no_comments = $this->_import_comments( $entry_id, $item );
						if( $no_comments > 0 ) {
							$this->return_data .= "<p>Added " . $no_comments . " comments.</p>\n";
						}
					}

				} else {

					// $this->return_data .= "<p>Error in entry: " . $data["title"] . "</p>\n";

					$errors = $result->getAllErrors();
					foreach( $errors as $err_field => $error ) {
						foreach( $error as $err_rule => $err_message ) {
								$this->return_data .= '<p class="ajw_datagrab_error">ERROR in ' . $err_field .': ' . lang($err_message) . "</p>\n";
						}
					}

					$entries_error++;

				}

				error_reporting( $errorlevel );
					
			} else {

				if( $data["title"] != "" ) {
					$this->return_data .= "<p>" . $data[ "title" ] . " already exists.</p>\n";
				} else {
					$this->return_data .= "<p>Entry already exists.</p>\n";
				}

				if( isset( $this->settings["config"]["update"] ) && $this->settings["config"]["update"] == "y") {

					// Update entry using EE channel API

					// $data["channel_id"] = $this->channel_defaults["channel_id"]; // Shouldn't need this...

					$data["channel_id"] = (string)$this->channel_defaults["channel_id"];

					$data["status"] = $status;
					$data["author_id"] = $author_id;
					$data["ping_servers"] = array();
					$data["allow_comments"] = $this->channel_defaults["deft_comments"];

					// Find and create categories
					$entry_categories = $this->_setup_categories( $item, $entry_id );

					// Add entry to categories
					$data["categories"] = $entry_categories;

					$this->_prepare_update_data( $entry_id, $data, $custom_fields );

					if( array_key_exists('transcribe', $this->addons->get_installed('modules') ) ) {
						if( isset( $data["transcribe__transcribe_related_entries"] ) ) {
							// $data["transcribe__transcribe_related_entries"] = "none";
							$_POST["transcribe__transcribe_related_entries"] = "none";
						}
					}

					// Disable notices while running this. Too many 3rd party fieldtypes
					// giving notices
					$errorlevel = error_reporting();
					error_reporting( $errorlevel & ~E_NOTICE) ;

					$entry = ee('Model')->get('ChannelEntry', $entry_id)
								->with('Channel')
								->first();
					$entry->author_id = (int)$author_id;

					$entry->set($data);
					$result = $entry->validate();

					// print_r( $result ); exit;
					if( $result->isValid() ) {

						$entry->save();

						$this->return_data .= "<p>Entry updated</p>\n";
						$entries_updated++;

					} else {

						$errors = $result->getAllErrors();
						foreach( $errors as $err_field => $error ) {
							foreach( $error as $err_rule => $err_message ) {
								$this->return_data .= '<p class="ajw_datagrab_error">ERROR in ' . $err_field .': ' . lang($err_message) . "</p>\n";
							}
						}

						$entries_error++;

					}

					
					// if ($this->api_channel_entries->update_entry( 
					// 			$entry_id, 
					// 			$data) === FALSE) {
					// 	$this->return_data .= "<p>Could not update entry: " . $data[ "title" ] . "</p>\n";
					// 	foreach( $this->api_channel_entries->errors as $error ) {
					// 		$this->return_data .= "<p>" . $error . "</p>\n";
					// 	}
					// } else {
					// 	$this->return_data .= "<p>Entry updated</p>\n";
					// 	$entries_updated++;
					// }
					
					error_reporting( $errorlevel );
					
					// @todo: do comments

				}

			}

			// Do extra processing now we know if it is an update or not
			foreach( $custom_fields as $field => $field_data ) {

				// Should we import anything into this field?
				if( isset( $this->settings["cf"][ $field ] ) && $this->settings["cf"][ $field ] != "" ) {

					// Check to see if a handler exists for this field type
					if ( ! class_exists('Datagrab_fieldtype') ) {
						require_once PATH_THIRD.'ajw_datagrab/libraries/Datagrab_fieldtype'.".php";
					}	
					if ( ! class_exists('Datagrab_'.$field_data[ "type" ] ) ) {
						if( file_exists( PATH_THIRD.'ajw_datagrab/fieldtypes/datagrab_'.$field_data[ "type" ].".php" ) ) {
							require_once PATH_THIRD.'ajw_datagrab/fieldtypes/datagrab_'.$field_data[ "type" ].".php";
						}
					}	
					
					// If so, call the prepare_post_data method to format the $data array
					if ( class_exists('Datagrab_'.$field_data[ "type" ]) ) {
						$classname = "Datagrab_".$field_data[ "type" ];
						$ft = new $classname();
						$ft->post_process_entry( $this, $item, $field_data["id"], $field, $data, $entry_id );
						//unset( $ft );
					} 
					
				}
			}

			// Is this a good idea? Figure there are loads of db updates per entry, so why not one more?
			if( $this->is_saved_import ) {
				$this->db->where("id", $this->is_saved_import );
				$this->db->update( "exp_ajw_datagrab", 
					array( 
						"import_lastrecord" => $row_num - 1
					) 
				);
			}
			
			$this->entries[] = $entry_id;
			
		} // End of main loop
		
		$import_has_finished = TRUE;
		if( $this->batch_limit_completed ) {
			$import_has_finished = FALSE;
		}

		if( $import_has_finished ) {

			// Delete entries not updated by this import
			// todo: need to do this after final batch import (if using batch)
			// todo: need to find all entries updated, not just from the last batch
			$no_deleted = 0;
			if( isset( $this->settings["config"]["delete_old"] ) && $this->settings["config"]["delete_old"] == "y") {
				$no_deleted += $this->_delete_old_entries( $this->entries );
			}

			if( isset( $this->settings["config"]["delete_by_timestamp"] ) 
				&& $this->settings["config"]["delete_by_timestamp"] == "y") {

				// Find timestamp field
				if( isset( $this->settings["config"]["timestamp"] ) ) {
					$field = "field_id_".$custom_fields[ $this->settings["config"]["timestamp"] ][ "id"];				
				} else {
					$field = "entry_date";
				}

				// Find duration
				if( isset( $this->settings["config"]["delete_by_timestamp_duration"] ) ) {
					$duration = $this->settings["config"]["delete_by_timestamp_duration"];
				} else {
					$duration = 86400;
				}

				// $no_deleted += $this->_delete_by_timestamp( $timestamp, $field, $duration );			
			}		

			// Report deleted entries
			if( $no_deleted > 0 ) {
				$this->return_data .= "<p>Deleted " . $no_deleted . " old entries.</p>";
			}
			
		}
		
		// -------------------------------------------
		// 'ajw_datagrab_post_import' hook.
		//  - Perform actions before the import is run
		//
			if ($this->extensions->active_hook('ajw_datagrab_post_import') === TRUE)
			{
				$edata = $this->extensions->call('ajw_datagrab_post_import', $this);
				if ($this->extensions->end_script === TRUE) return;
			}
		//
		// -------------------------------------------
		
		// Report and update caches
		if ($entries_added > 0) {
			$this->return_data .= "<p>New entries: " . $entries_added . "</p>";
			if ($this->config->item('new_posts_clear_caches') == 'y') {
				$this->functions->clear_caching('all');
			} else {
				$this->functions->clear_caching('sql_cache');
			}
			
		}

		if ($entries_updated > 0) {
			$this->return_data .= "<p>Updated entries: " . $entries_updated . "</p>";
		}

		if ($entries_error > 0) {
			$this->return_data .= '<p class="ajw_datagrab_error">Entries with errors: ' . $entries_error . "</p>";
		}

		
		if( $this->batch_limit_completed ) {
			// Import has finished a batch of imports - ie, the full import has not finished
			if( $this->is_saved_import ) {
				$this->db->where("id", $this->is_saved_import );
				$this->db->update( "exp_ajw_datagrab", 
					array( 
						"import_status" => "WAITING",
						"import_lastrecord" => $row_num - 1,
						"last_started" => $timestamp,
						"import_id" => $import_id
					) 
				);
			}
			$this->return_data .= "<p>Completed batch of " . $this->settings["import"]["limit"] . " entries (offset by " . $this->settings["datatype"]["skip"] . ")</p>";
		}
		
		if( $this->is_saved_import ) {
			if( isset( $this->settings["import"]["batch"] ) && $this->settings["import"]["batch"] == "yes" ) {
				// If this is a saved, batch import, has the import finished?
				if( $this->batch_limit_completed == FALSE ) {
					// It has reache the end of the import before the end of the batch, so the import has finished
					$this->db->where("id", $this->is_saved_import );
					$this->db->update( "exp_ajw_datagrab", 
						array( 
							"import_status" => "COMPLETED",
							"import_lastrecord" => $row_num,
							"last_run" => now()
						) 
					);
				}
			} elseif( ! isset( $this->settings["import"]["limit"] ) ) {
				// This is a save import, but not batched
				$this->db->where("id", $this->is_saved_import );
				$this->db->update( "exp_ajw_datagrab", 
					array( 
						"import_status" => "COMPLETED",
						"import_lastrecord" => $row_num,
						"import_id" => $import_id,
						"last_started" => $timestamp,
						"last_run" => now()
					) 
				);				
			} elseif( $this->settings["import"]["limit"] == 0 ) {
				// This is a save import, but not batched
				$this->db->where("id", $this->is_saved_import );
				$this->db->update( "exp_ajw_datagrab", 
					array( 
						"import_status" => "COMPLETED",
						"import_lastrecord" => $row_num,
						"import_id" => $import_id,
						"last_started" => $timestamp,
						"last_run" => now()
					) 
				);
			}
		}

		if( $this->is_saved_import ) {
			$this->db->where("id", $this->is_saved_import );
			$this->db->update( "exp_ajw_datagrab", 
				array( 
					"last_run" => now()
				) 
			);
		}

		$this->return_data .= $datatype->clean_up( $this->entries, $this->settings );
		
		$time = $this->_toc("import");
		$this->return_data .= "<p>Time taken: " . $time . " seconds.</p>";
		$this->_check_mem_usage( "", FALSE );
		$this->return_data .= "<p>Memory used: " . number_format($this->mem_usage, 0, ".", ",") . " bytes.</p>";		

		if( $this->current_user == 0 ) {
			$this->session->destroy();
		}
		
		return $this->return_data;
	}
		
	
	/*
		INTERNAL METHODS
	*/

	/**
	 * Get an array of custom field data for a selected channel
	 *
	 * @param string $channel channel_id
	 * @return array $custom_fields array containing custom field data for selected channel
	 */
	function _fetch_custom_fields_from_channel( $channel_id ) {

		$channel = ee('Model')->get('Channel', (int) $channel_id)->first();
			
		$custom_fields = array();
		foreach ( $channel->getAllCustomFields() as $row ) {
			$custom_fields[ $row->field_name ][ 'id' ] = $row->field_id;
			$custom_fields[ $row->field_name ][ 'format' ] = $row->field_fmt;
			$custom_fields[ $row->field_name ][ 'type' ] = $row->field_type;
		}
		
		return $custom_fields;
	}

	/**
	 * Get default settings for a channel
	 *
	 * @param string $channel channel_id
	 * @return array $channel_default array of channel default settings
	 */
	function _fetch_channel_defaults( $channel_id ) {

		$channel = ee('Model')->get('Channel', (int) $channel_id)->first();

		$channel_defaults = array(
			"channel_id" => $channel->channel_id, 
			"site_id" => $channel->site_id, 
			"channel_title" => $channel->channel_title, 
			"deft_comments" => $channel->deft_comments,
			"deft_status" => $channel->deft_status,
			// cat_group, 
			// field_group, 
			"url_title_prefix" => $channel->url_title_prefix
		);

		return $channel_defaults;
	}

	/**
	 * Check to see if the current user is logged in with the privileges to perform import, 
	 * if not create a dummy user (used when import is not run from the Control Panel)
	 *
	 */
	function _check_member_status() {

		// If not currently logged in, create a dummy session
		// @todo: currently hard-coded, need to search db for admin user?
		$this->current_user = $this->session->userdata['member_id'];
		if( $this->session->userdata['member_id'] == 0) {
			$this->session->create_new_session(1, TRUE);
			//$this->session->userdata['username']  = "dummy";
			$this->session->userdata['group_id']  = 1;
			$this->session->userdata['can_edit_other_entries'] = 'y';
			$this->session->userdata['can_delete_self_entries'] = 'y';
			$this->session->userdata['can_delete_all_entries'] = 'y';
		}

	}

	/**
	 * Find which author to assign to this entry
	 *
	 * @param array $item current row of data from data source
	 * @return integer $author_id the id of the author to assign to this entry
	 */
	function _fetch_author( $item ) {
		
		// Default author
		$author_id = $this->settings["config"]["author"]; 
		
		// Data field that contains author information
		$author_field = isset( $this->settings["config"]["author_field"] ) ? 
			$this->settings["config"]["author_field"] : ''; 

		// Which field to check: screen name, username, email?		
		$author_check = isset( $this->settings["config"]["author_check"] ) ? 
			$this->settings["config"]["author_check"] : ''; 

		if( $author_check == "member_id" ) {
			$author_check = "exp_members.member_id";
		}

		// Get author id from data if specified
		if ( $author_field != "" && $author_check != "" ) {
			$this->db->select( 'exp_members.member_id' );
			$this->db->from( 'exp_members' );
			$this->db->join( 'exp_member_data', 'exp_members.member_id = exp_member_data.member_id' );
			$this->db->where( $author_check, $this->datatype->get_item( $item, $author_field ) );
			$query = $this->db->get();
			if( $query->num_rows() > 0 ) {
				$row = $query->row_array();
				$author_id = $row["member_id"];
			}
		}
		
		return $author_id;
	}

	/**
	 * Find status to assign to this entry
	 *
	 * @param array $item current row of data from data source
	 * @return string $status status to assign to entry
	 */
	function _fetch_status( $item ) {
		$status = $this->channel_defaults["deft_status"];
		if( isset( $this->settings["config"][ "status" ] ) ) {
			switch( $this->settings["config"]["status"] ) {
				case "default":
				$status = $this->channel_defaults["deft_status"];
				break;
				case "open":
				case "closed":
				$status = $this->settings["config"]["status"];
				break;
				default:
				$status = $this->datatype->get_item( $item, $this->settings["config"]["status"] );
			}
			
			$channel = ee('Model')->get('Channel', $this->settings["import"]["channel"] )->first();

			// fetch valid settings from db
			if( !is_array( $this->valid_statuses ) ) {
				$this->valid_statuses = array();
				foreach( $channel->Statuses as $row ) {
					$this->valid_statuses[ $row->status ] = $row->status;
				}
			}
			
			// check id setting is a valid custom status for this channel
			if( in_array( $this->settings["config"]["status"], $this->valid_statuses ) ) {
				$status = $this->settings["config"]["status"];
			}
			
		}
		return $status;
	}

	/**
	 * Find a list of categories to assign to this entry and create any that don't exist
	 *
	 * @param array $item current row of data from data source
	 * @return array $entry_categories list of category ids
	 */
	function _setup_categories( $item, $entry_id = FALSE ) {
	
		$c_groups = ''; // pipe delimited list of category groups for this channel
		if( isset( $this->settings["config"][ "c_groups" ] ) && $this->settings["config"][ "c_groups" ] != "" ) {
			$c_groups = $this->settings["config"][ "c_groups" ];
		}
				
		$entry_categories = array();
		
		// Loop over all category groups for this channel
		$used_groups = array();
		foreach( explode("|", $c_groups) as $cat_group_id ) {
		
			// Find categories from custom field and create if necessary
			$cat_field = '';
			if( isset( $this->settings["config"][ "cat_field_".$cat_group_id ] ) && $this->settings["config"][ "cat_field_".$cat_group_id ] != "" ) {

				// todo: handle repeating elements
				$cat_field = $this->datatype->get_item( $item, $this->settings["config"][ "cat_field_".$cat_group_id ] );

				$used_groups[] = $cat_group_id; // Make note of which category groups are used
			}

			// Split categrory field by delimiter to find multiple catgories
			$cat_delimiter = '';
			if( isset( $this->settings["config"][ "cat_delimiter_".$cat_group_id ] ) && $this->settings["config"][ "cat_delimiter_".$cat_group_id ] != "" ) {
				$cat_delimiter = $this->settings["config"][ "cat_delimiter_".$cat_group_id ];
			}

			// Find existing category id's and create new categories if necessary
			$new = $this->_find_and_create_categories( 
				isset( $this->settings["config"][ "cat_default_".$cat_group_id ] ) ? $this->settings["config"][ "cat_default_".$cat_group_id ] : '',
				$cat_field,
				$cat_delimiter,
				$cat_group_id,
				$this->channel_defaults["site_id"]
			);
		
			if( count($entry_categories) || count($new) ) {
				$entry_categories["cat_group_id_".$cat_group_id] = array_merge( $entry_categories, $new );
			}
		
		}
		
		if( $entry_id !== FALSE ) {
			// Doing an update, so find existing categories
			$this->db->select( "exp_category_posts.cat_id, exp_categories.group_id" );
			$this->db->where( "entry_id", $entry_id );
			if( count( $used_groups ) > 0 ) {
				$this->db->where_not_in( "group_id", $used_groups );
			}
			$this->db->join("exp_categories", "exp_category_posts.cat_id = exp_categories.cat_id");
			$query = $this->db->get( "exp_category_posts" );
			foreach( $query->result_array() as $row ) {
				$entry_categories["cat_group_id_".$row["group_id"]][] = $row["cat_id"];
			}

		}

		return $entry_categories;
	}

	/**
	 * Import comments
	 *
	 * @param integer $entry_id id of the new entry
	 * @param array $item current row of data from data source
	 * @return integer the number of comments added
	 */
	function _import_comments( $entry_id, $item ) {
		
		// @note: xml only at the moment?
		// @todo: add more error checking here, eg, missing/empty fields
		// @todo: only works for new imports, not updates at the moment

		// Are there any comments for this entry?
		$no_comments = $this->datatype->get_item( $item, $this->settings["config"]["comment_body"] . "#" );
		
		if( $no_comments > 0 ) {
		
			// If so, loop over the XML and insert as new comments
			for( $i=0; $i<$no_comments; $i++ ) {
				$field = $this->settings["config"]["comment_body"];
				if ( $i > 0 ) {
					$suffix = '#' . ($i+1);
				} else {
					$suffix = '';
				}
				
				$name = $this->datatype->get_item( $item, $this->settings["config"]["comment_author"] . $suffix);
				if( is_array( $name ) ) {
					$name="";
				}
				
				$data = array(
					"site_id" => $this->channel_defaults["site_id"],
					"entry_id" => $entry_id,
					"channel_id" => $this->channel_defaults["channel_id"],
					"author_id" => 0,
					"status" => "o",
					"name" => $name,
					"email" => $this->datatype->get_item( $item, $this->settings["config"]["comment_email"] . $suffix ),
					"url" => $this->datatype->get_item( $item, $this->settings["config"]["comment_url"] . $suffix ),
					"location" => "",
					"ip_address" => "127.0.0.1",
					"comment_date" => $this->_parse_date( 
						$this->datatype->get_item( $item, $this->settings["config"]["comment_date"] . $suffix )
					),
					"comment" => $this->datatype->get_item( $item, $this->settings["config"]["comment_body"] . $suffix )
				);
				$sql = $this->db->insert_string('exp_comments', $data);
				$this->db->query($sql);
			}
			
			// Do stats
			
			$this->db->select( "COUNT(comment_id) as count" );
			$this->db->where( "status", "o" );
			$this->db->where( "entry_id", $entry_id );
			$channel_comments_count = $this->db->get( "exp_comments" );
			
			$this->db->select( "MAX(comment_date) as date" );
			$this->db->where( "status", "o" );
			$this->db->where( "entry_id", $entry_id );
			$this->db->order_by( "comment_date", "desc" );
			$channel_comments_recent = $this->db->get( "exp_comments" );
			
			$data = array();
			if ($channel_comments_count->num_rows() > 0) {
				$row = $channel_comments_count->row_array();
				$data["comment_total"] = $row["count"];
			}
			if ($channel_comments_recent->num_rows() > 0) {
				$row = $channel_comments_recent->row_array();
				$data["recent_comment_date"] = $row["date"];
			}
			if( count( $data ) > 0 ) {
				$this->db->where('entry_id', $entry_id );
				$this->db->update('exp_channel_titles', $data );
			}
			
		}
		
		return $no_comments;
	}

	/**
	 * Delete any old entries
	 *
	 * @param array $entries a list of entries to keep (ie, have just been added)
	 * @return integer the number of deleted entries
	 */
	function _delete_old_entries( $entries ) {
		
		if( count( $entries ) == 0 ) {
			return 0;
		}

		$this->db->select( "entry_id" );
		$this->db->where_not_in( "entry_id", $entries ); 
		$this->db->where( 'channel_id = ', $this->channel_defaults["channel_id"] ); 
		$query = $this->db->get( "exp_channel_titles" );
	
		$delete_ids = array();
		foreach ($query->result() as $row)
		{
		    $delete_ids[] = $row->entry_id;
		}
	
		if( count( $delete_ids ) ) {
			$this->legacy_api->instantiate('channel_entries');
			$ret = $this->api_channel_entries->delete_entry( $delete_ids );
			if( $ret == FALSE ) {
				foreach( $this->api_channel_entries->errors as $eid => $error ) {
					$this->return_data .= "<p>Error: " . $error . " " . $eid . "</p>";
				}
			}
		}
		
		return count( $delete_ids );
	}

	/**
	 * Delete entries with a timestamp older than specified
	 *
	 * @param string $timestamp 
	 * @param string $duration 
	 * @return void
	 * @author Andrew Weaver
	 */
	function _delete_by_timestamp( $timestamp, $field, $duration=86400 ) {
		// Currently disabled
		return 0;
	}

	/**
	 * Try to read the date and return as timestamp
	 *
	 * @param string $datestr 
	 * @return int the date
	 */
	function _parse_date( $datestr ) {
		
		//$datestr = preg_replace("/^\s*([0-9]{1,2})[\/\. -]+([0-9]{1,2})[\/\. -]+([0-9]{2,4})/", "\\2/\\1/\\3", $datestr);
		
		// $datestr = str_replace('/', '-', $datestr);
		
		if( is_numeric($datestr) ) {
			return $datestr;
		}
		$date = strtotime( $datestr );
		if ( $date == -1 ) {
			$date = parse_w3cdtf( $datestr );
		}
		if ( $date == -1 || $date == "" ) {
			$date = time();
		}
		return( $date );
	}

	/**
	 * Test whether an entry is unique
	 *
	 * @param array $post 
	 * @param string $unique 
	 * @param array $weblog_to_feed 
	 * @return entry id or 0 if there is no match
	 */
	function _is_entry_unique( $data, $unique, $weblog_to_feed ) {
		
		// If no unique field is provided, always create a new entry

		if( is_array( $unique ) ) {	
			// Remove empty elements
			$unique = array_diff( $unique, array('') );
			if( count( $unique ) == 0 ) {
				return 0;
			}
		}
		
		if ( $unique == "" ) {
			return 0;
		} 

		if( $unique[0] == "title" ) {

			$field_name = "title";

		} else {

			$field = ee('Model')->get('ChannelField')
				->search('field_name', $unique[0])
				->first();

			$field_name = "field_id_".$field->field_id;

			// print $field_name . " = " . $data[ $field_name ];

		}

		$entry = ee('Model')->get('ChannelEntry')
			->filter($field_name, "==", $data[$field_name])
			->filter('channel_id', $this->channel_defaults["channel_id"])
			->first();

		if( isset($entry->entry_id ) ) {
			return $entry->entry_id;
		}

		return 0;
	}

	/**
	 * Create a list of categories for an entry
	 *
	 * @param string $cat_default Contains default category name or id
	 * @param string $cat_field Contains category values or ids
	 * @param string $cat_delimiter Delimiter used to split categories in single field
	 * @param string $cat_group Category group id
	 * @param string $site_id Site id
	 * @return array category ids to add to entry
	 * @author Andrew Weaver
	 */
	function _find_and_create_categories( $cat_default, $cat_field, $cat_delimiter="", $cat_group, $site_id ) {

		$entry_categories = array();
		
		if ( $cat_default != "" ) {
			if( is_numeric( $cat_default ) ) {
				// Assume numeric categories are cat_id's
				$entry_categories[] = $cat_default;
			} else {
				$entry_categories[] = $this->_create_category( $cat_default, $cat_group, $site_id );
			}
		}
		
		if ( $cat_field != '' ) {

			if ( $cat_delimiter != "" ) {
				$cats = explode( $cat_delimiter, $cat_field );
			} else {
				$cats = array( $cat_field );
			}
			
			// Remove duplicates (after trimming whitespace)
			foreach( $cats as $idx => $cat ) {
				$cats[ $idx ] = trim( $cat );
			}
			$cats = array_unique( $cats );
			
			// Add category to database
			foreach( $cats as $cat ) {
				if( is_numeric( $cat ) ) {
					// Assume numeric categories are cat_id's
					$entry_categories[] = $cat;
				} else {
					// $entry_categories[] = $this->_create_category( $cat, $cat_group, $site_id );
					// todo: allow sub-categories to created using another delimiter
					$delim_cats = explode('/', $cat);
					$parent_id = 0;
					if ( count( $delim_cats ) == 1 ) {
						$entry_categories[] = $this->_create_category( $cat, $cat_group, $site_id );
					}
					foreach( $delim_cats as $dcat ) {
						$c_id = $this->_create_category( $dcat, $cat_group, $site_id, $parent_id );
						$entry_categories[] = $c_id;
						$parent_id = $c_id;
					}
				}
			}
		}
		
		return $entry_categories;
	}
	
	/**
	 * Create a category
	 *
	 * @param string $category_name 
	 * @param string $category_group 
	 * @return void
	 */
	function _create_category( $category_name, $category_group="", $site_id=1, $parent_id=0 ) {

		// Does this category already exist?
		$category_name = trim($category_name);
		
		$this->db->select( "*" );
		$this->db->where( "cat_name", $category_name );
		$this->db->where( "group_id",  $category_group );
		if( $parent_id != 0 ) {
			$this->db->where( "parent_id",  $parent_id );
		}
		$query = $this->db->get( "exp_categories" );

		if ( $query->num_rows == 0) {
			
			// Category does not exist so create it
			// todo: use Category model here
			
			$insert_array = array(
				'group_id' => $category_group,
				'site_id' => $site_id,
				'cat_name' => $category_name,
				'cat_url_title' => url_title( $category_name, $this->config->item('word_separator'), TRUE ),
				'cat_image' => '',
				'parent_id' => $parent_id,
				'cat_order' => 1
				);
			$this->db->query($this->db->insert_string('exp_categories', $insert_array));
			$category_id = $this->db->insert_id();
			
			 $insert_array = array(
				'cat_id'  	=> $category_id,
				'site_id' 	=> $site_id,
				'group_id' 	=> $category_group
			);
			$this->db->query($this->db->insert_string('exp_category_field_data', $insert_array));
			
			$this->return_data .= "<p>Add category: " . $category_name . " to group " . $category_group . "</p>\n";
			
			return $category_id;
			
		} else {

			// Category already exists, so return its id
			$row = $query->row();
			return $row->cat_id;

		}
	}

	/**
	 * Add categories to an entry
	 *
	 * @param string $entry_id 
	 * @param string $cat_id 
	 * @return void
	 */
	function _add_entry_to_category( $entry_id, $cat_id ) {
		$this->db->query("INSERT IGNORE INTO exp_category_posts (entry_id, cat_id) VALUES ('".$entry_id."', '".$cat_id."')");
		if ($this->config->item('auto_assign_cat_parents') == 'y')
		{	
			$query = $this->db->query("SELECT parent_id FROM exp_categories WHERE cat_id = " . $cat_id);
			$row = $query->row();
			if( $row->parent_id != 0 ) {
				$this->_add_entry_to_category( $entry_id, $row->parent_id );
			}
		}
	}
	
	function _prepare_update_data( $entry_id, &$data, $custom_fields ) {

		/* EE channel api blanks any fields that don't have a value set 
			
			"as part of the data normalization, custom data with a value of NULL 
			is transformed to an empty string before database insertion."
			
			From: http://expressionengine.com/user_guide/development/api/api_channel_entries.html
			
			Api_channel_entries.php fills the empty fields in 2 places: 
			_base_prep() and _update_entry()
			to obey mysql strict mode 
			(see: http://dev.mysql.com/doc/refman/5.0/en/server-sql-mode.html)

			Options:
			
			1) Don't allow partial updates (or rather use default EE behaviour of clearing data)
			2) Load data array with old data (requires knowledge of 3rd party fieldtypes and is
			   liable to change between fieldtype versions)
			3) Do some tricks with extensions (may not be possible)
			4) Overide update routine with DataGrab's own function 
			   (likely to be EE version specific)
			
		*/

		//print_r( $data ); print_r( $this->settings ); 
		//print_r( $custom_fields ); exit;
		
		$entry = ee('Model')->get('ChannelEntry', $entry_id)
					->with('Channel')
					->first();
		$row = $entry->getValues();

		if( !isset( $data["title"] ) || $data["title"] == "" ) {
			$data["title"] = $row["title"];
		}

		if( !isset($this->settings["config"][ "date" ]) || $this->settings["config"][ "date" ] == "" ) {
			$data["entry_date"] = $row["entry_date"];
		}

		if( !isset($this->settings["config"][ "url_title" ]) || $this->settings["config"][ "url_title" ] == "" ) {
			$data["url_title"] = $row["url_title"];
		}

		if( $this->settings["config"]["status"] == "default" ) {
			$data["status"] = $row["status"];
		}

		if( !isset( $this->settings["config"]["author_field"] ) || $this->settings["config"]["author_field"] == "" ) {
			$data["author_id"] = $row["author_id"];
		}
		
		foreach( $custom_fields as $field_name => $field ) {
			/*
			print_r( $field );
			Array(
			    [id] => 5
			    [format] => none
			    [type] => textarea
			) 
			*/
			
			if( !isset( $data[ "field_id_".$field["id"] ] ) ) {
				
				// Check to see if a handler exists for this field type
				if ( ! class_exists('Datagrab_fieldtype') ) {
					require_once PATH_THIRD.'ajw_datagrab/libraries/Datagrab_fieldtype'.".php";
				}	
				if ( ! class_exists('Datagrab_'.$field[ "type" ] ) ) {
					if( file_exists( PATH_THIRD.'ajw_datagrab/fieldtypes/datagrab_'.$field[ "type" ].".php" ) ) {
						require_once PATH_THIRD.'ajw_datagrab/fieldtypes/datagrab_'.$field[ "type" ].".php";
					}
				}	
			
				// If so, call the rebuild_post_data method to format the $data array
				if ( class_exists('Datagrab_'.$field[ "type" ]) ) {
					$classname = "Datagrab_".$field[ "type" ];
					$ft = new $classname();
					$ft->rebuild_post_data( $this, $field["id"], $data, $row );
				} else {
					// If no handler exists, just use the value
					if( isset( $row[ "field_id_".$field["id"] ] ) ) {
						$data[ "field_id_" . $field["id"] ] = $row[ "field_id_".$field["id"] ];
					}
				}
			
			}
		
		}
		// print_r( $data ); exit;
		
	}
	
	function _get_file( $filename, $filedir=1, $fetch_url=FALSE ) {
	
		// Is it in the correct format already?
		if( preg_match('/{filedir_([0-9]+)}/', $filename, $matches) ) {
			return $filename;
		}	
		
		// Is it a filename?
		if( ! preg_match('/http+/', $filename, $matches) ) {
			return "{filedir_" . $filedir . "}" . $filename;
		}	
		
		// Is it a url
		$url = parse_url( $filename );
		if( isset( $url["scheme"] ) ) {

			$this->load->library('filemanager');
			$this->filemanager->xss_clean_off();
		
			$basename = basename($filename);
			if( strpos( $basename, "?" ) ) {
				$basename = substr( $basename, 0, strpos($basename, "?") );
			}
			$basetitle = $basename;

			// todo: make this definable somewhere
			$rewrite_urls = FALSE;
			if( $rewrite_urls ) {
				// $content = @file_get_contents( $filename );
				$pathinfo = pathinfo( $basename );
				// $basename = md5( $content ) . '.' . $pathinfo['extension'];
				$extension = $pathinfo['extension'];
				$basename = md5( $filename ) . '.' . $extension;
			}

			$file_path = $this->filemanager->clean_filename(
				$basename, 
				$filedir,
				array('ignore_dupes' => TRUE)
			);
			// If ignore_dupes = FALSE, basename may change
			// $basename = basename($file_path);

			// If the file has no extension, then add jpg for now...
			// if( strlen( $file_path ) - strrpos( $file_path, "." ) == 1 ) {
			// 	$file_path .= "jpg";
			// 	$basename .= ".jpg";
			// }

			// Does file already exist?
			if( file_exists( $file_path ) ) {
				// File already exists
				// Test file size is the same
				//$remote = $this->_get_remote_file_details( $filename );
				// print "<p>". $filename . " (" . $basename . "): " . $remote["size"] . " vs " . filesize( $file_path ) . "</p>";
				//if( $remote["size"] == filesize( $file_path ) ) {
				//	return '{filedir_' . $filedir . '}' . $basename;
				//}
				return '{filedir_' . $filedir . '}' . $basename;
			}
		
			if( $fetch_url === TRUE ) {
				// Fetch contents of url
				if( !isset( $content ) ) {
					$content = @file_get_contents( $filename );
				}
				if( $content === FALSE ) {
					// cannot fetch file
					return FALSE;
				}
			
				if( file_put_contents($file_path, $content) === FALSE ) {
					// error copying file to filedir
					return FALSE;
				}
			
				$result = $this->filemanager->save_file(
					$file_path, 
					$filedir, 
					array(
						'title'     => $basetitle,
						'path'      => dirname($file_path),
						'file_name' => $basename
					)
				);
		
				// Check to see the result
				if ($result['status'] === FALSE) {
					// file not saved
					return FALSE;
				}
				
				return '{filedir_' . $filedir . '}' . $basename;
			}
					
		}	
		
		return FALSE;		
	}
	
	function _get_remote_file_details($url){  

	    $uh = curl_init();  
	    curl_setopt($uh, CURLOPT_URL, $url);  
	      
	    // set NO-BODY to not receive body part  
	    curl_setopt($uh, CURLOPT_NOBODY, 1);  
	      
	    // set HEADER to be false, we don't need header  
	    curl_setopt($uh, CURLOPT_HEADER, 0);  
	      
	    // retrieve last modification time  
	    curl_setopt($uh, CURLOPT_FILETIME, 1);  
	    curl_exec($uh);  
	      
	    // assign filesize into $filesize variable  
	    $filesize = curl_getinfo($uh,CURLINFO_CONTENT_LENGTH_DOWNLOAD);  
	      
	    // assign file modification time into $filetime variable  
	    $filetime = curl_getinfo($uh,CURLINFO_FILETIME);  
	    curl_close($uh);  
	      
	    // push out  
	    return array(
	    	"size" => $filesize,
	    	"time" => $filetime
	    );
	}  

	/*
		HELPER FUNCTIONS
	*/

	function _check_mem_usage( $label, $display=TRUE ) {
		$mem_usage = memory_get_usage();
		// print "<p>" . $label . ": " . $mem_usage . " (" . number_format( $mem_usage - $this->mem_usage, 0, '.', ',' ) . ")" . "</p>";
		if( $display ) {
			$this->TMPL->log_item('DataGrab: ' . $label . ": " . $mem_usage . " (" . number_format( $mem_usage - $this->mem_usage, 0, '.', ',' ) . ")" );
		}
		$this->mem_usage = $mem_usage;
	}
	
	function _tic( $timer="simple" ) {
		global $TIC;
		$TIC[ $timer ] = microtime(TRUE);
	}
	
	function _toc( $timer="simple" ) {
		global $TIC;
		if( isset( $TIC[ $timer ] ) ) {
			$time = microtime(TRUE) - $TIC[ $timer ];
			return $time;
		}
		return -1;
	}

}

?>
