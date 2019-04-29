<?php

/**
 * DataGrab Relationship fieldtype class
 *
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 */
class Datagrab_relationship extends Datagrab_fieldtype {

	function register_setting( $field_name ) {
		return array( $field_name . "_relationship_field" );
	}

	function display_configuration( $field_name, $field_label, $field_type, $field_required, $data ) {
		$config = array();
		$config["label"] = form_label($field_label) /*. NBS .
		anchor("http://brandnewbox.co.uk/support/details/importing_into_relationship_fields_with_datagrab", "(?)", 'class="ajw_datagrab_help"') */;
		if( $field_required ) {
			$config["label"] .= ' <span class="ajw_datagrab_required">*</span>';
		}
		$config["label"] .= '<div class="ajw_datagrab_subtext">' . $field_type . "</div>";

		$config["value"] = "<p>" . form_dropdown( 
			$field_name, $data["data_fields"], 
			isset( $data["default_settings"]["cf"][$field_name] ) ? 
				$data["default_settings"]["cf"][$field_name] : '' 
			)
			. "</p><p>Field to match: " . NBS .
			form_dropdown( 
				$field_name . "_relationship_field", 
				$data["all_fields"], 
				(isset($data["default_settings"]["cf"][$field_name . "_relationship_field"]) ? 
					$data["default_settings"]["cf"][$field_name . "_relationship_field" ]: '' )
			) . "</p>";
		return $config;
	}

	function prepare_post_data( $DG, $item, $field_id, $field, &$data, $update = FALSE ) {
		
		// Fetch fieldtype settings
		// $fs = ee()->api_channel_fields->settings[ $field_id ]["field_settings"];
		// $field_settings = (unserialize(@base64_decode($fs)));

		$field_model = ee('Model')->get('ChannelField', $field_id)->first();
		$field_settings = $field_model->getSettingsValues();
				
		// print_r( $field_settings ); exit;

		$order = 1;
		// Can the current datatype handle sub-loops (eg, XML)?
		if( $DG->datatype->datatype_info["allow_subloop"] ) {
		
			// Check this field can be a sub-loop
			if( $DG->datatype->initialise_sub_item( 
				$item, $DG->settings["cf"][ $field ], $DG->settings, $field ) ) {
		
				$data[ "field_id_" . $field_id ] = array();
				$data[ "field_id_" . $field_id ]["sort"] = array();
				$data[ "field_id_" . $field_id ]["data"] = array();

				// Loop over sub items
				while( $subitem = $DG->datatype->get_sub_item( 
					$item, $DG->settings["cf"][ $field ], $DG->settings, $field ) ) {
				
					// // Check whether item matches a valid entry and create a relationship
					// ee()->db->select( 'exp_channel_titles.entry_id' );
					// ee()->db->join( 'exp_channel_data', 'exp_channel_titles.entry_id = exp_channel_data.entry_id' );
					// if( isset( $field_settings["channels"] ) && count( $field_settings["channels"] ) ) {
					// 	ee()->db->where_in( 'exp_channel_titles.channel_id', $field_settings["channels"] );
					// }
					// if( !isset( $DG->settings["cf"][ $field . "_relationship_field" ] ) ) {
					// 	ee()->db->where( 'title', $subitem );
					// } else {
					// 	ee()->db->where( $DG->settings["cf"][ $field . "_relationship_field" ], $subitem );
					// }
					// // print ee()->db->_compile_select(); exit;
					// $query = ee()->db->get( 'exp_channel_titles' );

					// if( $query->num_rows() > 0 ) {
					// 	$row = $query->row_array();
					// 	$data[ "field_id_" . $field_id ]["data"][] = $row[ "entry_id" ];
					// 	$data[ "field_id_" . $field_id ]["sort"][] = $order++;
					// }

					// Check whether item matches a valid entry and create a relationship
					// Check which field to compare
					if( !isset( $DG->settings["cf"][ $field . "_relationship_field" ] ) ) {
						// If not set (usually old saved import) then default to title
						$rel_field_name = 'title';
					} else {
						// Custom field
						$rel_field_name = $DG->settings["cf"][ $field . "_relationship_field" ];
					}

					$rel_field_name = str_replace("exp_channel_titles.", "", $rel_field_name );

					$entry = ee('Model')->get('ChannelEntry')
						->filter($rel_field_name, "==", $subitem )
						->first();

					if( isset( $entry->entry_id ) ) {
						$data[ "field_id_" . $field_id ]["data"][] = $entry->entry_id;
						$data[ "field_id_" . $field_id ]["sort"][] = $order++;
					}

				}
			}
	
		}


	}
	
	function rebuild_post_data( $DG, $field_id, &$data, $existing_data ) {		

		// Fetch relationships from exp_playa_relationships
		ee()->db->select( "child_id, order" );
		ee()->db->where( "parent_id", $existing_data["entry_id"] );
		ee()->db->where( "field_id", $field_id );
		ee()->db->order_by( "order" );
		$query = ee()->db->get( "exp_relationships" );

		$d = array();
		$sort = array();
		foreach( $query->result_array() as $row ) {
			$d[] = $row["child_id"];
			$sort[] = $row["order"];
		}

		// Rebuild selections array
		$data[ "field_id_".$field_id ] = array(
			"data" => $d,
			"sort" => $sort
		);

	}

}

?>