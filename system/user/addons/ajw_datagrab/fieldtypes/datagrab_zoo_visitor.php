<?php
/**
 * DataGrab Fieldtype Class
 * 
 * Provides methods to interact with EE fieldtypes 
 *
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 **/

class Datagrab_zoo_visitor extends Datagrab_fieldtype {
			
	/**
	 * Fetch a list of configuration settings that this field type can use
	 *
	 * @param string $name the field name
	 * @return array of configuration setting names
	 * @author Andrew Weaver
	 */
	function register_setting( $field_name ) {
		return array( 
			$field_name . "_zoo_password", 
			$field_name . "_zoo_email", 
			$field_name . "_zoo_username", 
			$field_name . "_zoo_screen_name", 
			$field_name . "_zoo_member_group"
		);
	}
	
	/**
	 * Generate the form elements to configure this field
	 *
	 * @param string $field_name the field's name
	 * @param string $field_label the field's label
	 * @param string $field_type the field's type
	 * @param string $data array of data that can be used to select from
	 * @return array containing form's label and elements
	 * @author Andrew Weaver
	 */
	function display_configuration( $field_name, $field_label, $field_type, $field_required, $data ) {
		$config = array();
		$hidden = form_hidden( $field_name, "1" );

		ee()->db->select( "group_id, group_title" );
		ee()->db->from( "exp_member_groups" );
		ee()->db->order_by( "group_id ASC" );
		$query = ee()->db->get(); 
		$groups = array();
		foreach( $query->result_array() as $row ) {
			$groups[ $row["group_id"] ] = $row["group_title"];
		}

		$username = "<p>Username: " . NBS . form_dropdown( $field_name.'_zoo_username', $data["data_fields"], 
			isset($data["default_settings"]["cf"][$field_name.'_zoo_username']) ? 
			$data["default_settings"]["cf"][$field_name.'_zoo_username'] : '' )."</p>";

		$screen_name = "<p>Screen name: " . NBS . form_dropdown( $field_name.'_zoo_screen_name', $data["data_fields"], 
			isset($data["default_settings"]["cf"][$field_name.'_zoo_screen_name']) ? 
			$data["default_settings"]["cf"][$field_name.'_zoo_screen_name'] : '' )."</p>";

		$password = "<p>Password: " . NBS . form_dropdown( $field_name.'_zoo_password', $data["data_fields"], 
			isset($data["default_settings"]["cf"][$field_name.'_zoo_password']) ? 
			$data["default_settings"]["cf"][$field_name.'_zoo_password'] : '' )."</p>";
	
		$email = "<p>Email address: " . NBS . form_dropdown( $field_name.'_zoo_email', $data["data_fields"], 
			isset($data["default_settings"]["cf"][$field_name.'_zoo_email']) ? 
			$data["default_settings"]["cf"][$field_name.'_zoo_email'] : '' )."</p>";
	
		$group = "<p>Member group: " . NBS . form_dropdown( $field_name.'_zoo_member_group', $groups, 
			isset($data["default_settings"]["cf"][$field_name.'_zoo_member_group']) ? 
			$data["default_settings"]["cf"][$field_name.'_zoo_member_group'] : '' )."</p>";

		$config["label"] = form_label($field_label);
				if( $field_required ) {
			$config["label"] .= ' <span class="ajw_datagrab_required">*</span>';
		}
		$config["label"] .= '<div class="ajw_datagrab_subtext">' . $field_type . "</div>";
		 // . BR .
			// '<a href="http://brandnewbox.co.uk/support/details/" class="ajw_datagrab_help">Zoo Visitor notes</a>';
		$config["value"] = $hidden . $email . $username  . $screen_name . $password . $group;
			
		return $config;
	}
	
	/**
	 * Prepare data for posting
	 *
	 * @param object $DG The DataGrab model object
	 * @param string $item The current row of data from the data source
	 * @param string $field_id The id of the field
	 * @param string $field The name of the field
	 * @param string $data The data array to insert into the channel
	 * @param string $update Update or insert?
	 */
	function prepare_post_data( $DG, $item, $field_id, $field, &$data, $update = FALSE ) {
	}
	
	/**
	 * As prepare_post_data but set after the check for existing entries
	 *
	 * @param object $DG The DataGrab model object
	 * @param string $item The current row of data from the data source
	 * @param string $field_id The id of the field
	 * @param string $field The name of the field
	 * @param string $data The data array to insert into the channel
	 * @param string $update Update or insert?
	 */
	function final_post_data( $DG, $item, $field_id, $field, &$data, $update = FALSE ) {

		// Need to prevent Zoo Visitor from creating 2 entries by disabling this extension for this call
		unset( ee()->extensions->extensions["cp_members_member_create"][1]["Zoo_visitor_ext"] );

		// ZV will handle the hashing
		$password = $DG->datatype->get_item( $item, $DG->settings["cf"][ $field."_zoo_password" ] );

		if( ! $update ) {

			$_POST["EE_group_id"] = $DG->settings["cf"][ $field."_zoo_member_group" ]; // Hard code for now
		    $_POST["EE_member_id"] = "";
		    $_POST["EE_username"] = $DG->datatype->get_item( $item, $DG->settings["cf"][ $field."_zoo_username" ] ); // "test@brandnewbox.co.uk";
		    $_POST["EE_email"] = $DG->datatype->get_item( $item, $DG->settings["cf"][ $field."_zoo_email" ] );
		    $_POST["EE_screen_name"] = $DG->datatype->get_item( $item, $DG->settings["cf"][ $field."_zoo_screen_name" ] );
		    $_POST["EE_password"] = $password;
		    $_POST["EE_new_password_confirm"] = $password;

		    //$_POST[ "title" ] = $DG->datatype->get_item( $item, $DG->settings["cf"][ $field."_zoo_email" ] );
		    //$_POST[ "status" ] = "Members-id5";

		} else {

			ee()->db->select( "author_id" );
			ee()->db->from( "exp_channel_titles" );
			ee()->db->where( "entry_id", $update );
			$query = ee()->db->get(); 

			if( $query->num_rows() ) {
				$row = $query->row_array();
				$_POST["EE_group_id"] = $DG->settings["cf"][ $field."_zoo_member_group" ]; // Hard code for now
			    $_POST["EE_member_id"] = $row["author_id"];
			    $_POST["EE_username"] = $DG->datatype->get_item( $item, $DG->settings["cf"][ $field."_zoo_username" ] ); // "test@brandnewbox.co.uk";
			    $_POST["EE_email"] = $DG->datatype->get_item( $item, $DG->settings["cf"][ $field."_zoo_email" ] );
			    $_POST["EE_screen_name"] = $DG->datatype->get_item( $item, $DG->settings["cf"][ $field."_zoo_screen_name" ] );
			    $_POST["EE_password"] = $password;
			    $_POST["EE_new_password_confirm"] = $password;
			}

		}

		// print_r( $_POST ); exit;

	    /*
	    $_POST[ "email"] = "test@brandnewbox.co.uk";
	    $_POST[ "username"] = "test@brandnewbox.co.uk";
	    $_POST[ "current_username"] = "";
	    $_POST[ "password"] = "andrew";
	    $_POST[ "password_confirm"] = "andrew";
	    $_POST[ "current_password"] = "";
	    $_POST[ "screen_name"] = "test@brandnewbox.co.uk";
	    $_POST[ "group_id"] = "5";
		*/
	
	}
	
	/**
	 * As prepare_post_data but set after entry has been added
	 *
	 * @param object $DG The DataGrab model object
	 * @param string $item The current row of data from the data source
	 * @param string $field_id The id of the field
	 * @param string $field The name of the field
	 * @param string $data The data array to insert into the channel
	 * @param string $entry_id Update or insert?
	 */
	function post_process_entry( $DG, $item, $field_id, $field, &$data, $entry_id = FALSE ) {
	}
	
	/**
	 * Rebuild the POST data of from existing entry
	 *
	 * @param string $DG 
	 * @param string $field_id 
	 * @param string $data 
	 * @param string $existing_data 
	 * @return void
	 * @author Andrew Weaver
	 */
	function rebuild_post_data( $DG, $field_id, &$data, $existing_data ) {
	}
	
}

?>