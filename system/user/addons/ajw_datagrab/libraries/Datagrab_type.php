<?php
/**
 * Datagrab Type Class
 * 
 * Provides the basic methods to create an import type
 *
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 **/

class Datagrab_type {
	
	var $settings = array();
	var $config_defaults = array();
	
	var $handle;
	var $titles;
	
	var $errors = array();
	
	/**
	 * Constructor
	 *
	 * @return void
	 */
	function __construct() {
		
	}
	
	function display_name() {
		return $this->datatype_info["name"];
	}
	
	function settings_form() {
		return "<p>This data type has no settings.</p>";
	}
	
	function initialise( $settings ) {

		if( $settings != NULL ) {
			$this->settings = $settings["datatype"];
		}

	}
	
	function fetch() {
	}

	function next() {
		return FALSE;
	}

	function fetch_columns() {
	}

	function total_rows() {
		$count = 0;
		while( $this->next() ) {
			$count++;
		}
		return $count;
	}

	function clean_up( $entries, $settings ) {
	}
	
	function get_item( $items, $id ) {

		if( isset( $items[ $id ] ) ) {
			return stripcslashes( $items[ $id ] );
		} else {
			return "";
		}
		
	}
	
	function get_value( $values, $field ) {
		return isset( $values["datatype"][ $field ] ) ? $values["datatype"][ $field ] : '';
	}

	function initialise_sub_item( $item, $id, $config, $field ) {
		return FALSE;
	}

	function get_sub_item( $item, $id, $config, $field ) {
	}

}
