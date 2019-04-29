<?php

/**
 * DataGrab cartthrob_price_quantity_thresholds fieldtype class
 *
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 */
class Datagrab_cartthrob_price_quantity_thresholds extends Datagrab_fieldtype {

	function register_setting( $field_name ) {
		return array( 
			$field_name . "_cartthrob_low", 
			$field_name . "_cartthrob_high"
		);
	}

	function display_configuration( $field_name, $field_label, $field_type, $field_required, $data ) {
		$config = array();
		$config["label"] = "<p>" .
		form_label($field_label);
		/*  . NBS .
		anchor("http://brandnewbox.co.uk/support/details/importing_into_playa_fields_with_datagrab", "(?)", 'class="ajw_datagrab_help"');
		*/
		$config["value"] = "Price: " . NBS . form_dropdown( 
			$field_name, $data["data_fields"], 
			isset( $data["default_settings"]["cf"][$field_name] ) ? 
				$data["default_settings"]["cf"][$field_name] : '' 
			) . 
			"</p><p>" . "Low: " . NBS .
			form_dropdown( 
				$field_name . "_cartthrob_low", 
				$data["data_fields"], 
				(isset($data["default_settings"]["cf"][$field_name . "_cartthrob_low"]) ? 
					$data["default_settings"]["cf"][$field_name . "_cartthrob_low" ]: '' )
			) .
			"</p><p>" . "High: " . NBS .
			form_dropdown( 
				$field_name . "_cartthrob_high", 
				$data["data_fields"], 
				(isset($data["default_settings"]["cf"][$field_name . "_cartthrob_high"]) ? 
					$data["default_settings"]["cf"][$field_name . "_cartthrob_high" ]: '' )
			) .
			"</p>";
		return $config;
	}


	function prepare_post_data( $DG, $item, $field_id, $field, &$data, $update = FALSE ) {
	}

	function final_post_data( $DG, $item, $field_id, $field, &$data, $update = FALSE ) {

	/*
		[field_id_72] => Array
        (
            [0] => Array
                (
                    [from_quantity] => 1
                    [up_to_quantity] => 3
                    [price] => 12
                )

            [1] => Array
                (
                    [from_quantity] => 4
                    [up_to_quantity] => 10
                    [price] => 10
                )

            [2] => Array
                (
                    [from_quantity] => 11
                    [up_to_quantity] => 100
                    [price] => 9
                )

        )
   */
   
   	// Is this an update? 
   	if( $update ) {
   		// If so, is this the first update of this import?
   		if( in_array( $update, $DG->entries ) ) {	
				$existing_data = array(
					"entry_id" => $update
				);
				$this->rebuild_post_data( $DG, $field_id, $data, $existing_data );
				$first_row = count( $data[ "field_id_" . $field_id ] );
				// $first_row = 0;
   		} else {
	   		// Initialise data
	   		$data[ "field_id_" . $field_id ] = array();
	   		$first_row = 0;
   		}
   	} else {
			// Initialise data
			$data[ "field_id_" . $field_id ] = array();
			$first_row = 0;
   	}
   	
   	// Can the current datatype handle sub-loops (eg, XML)?
   	if( $DG->datatype->datatype_info["allow_subloop"] ) {
   	
   		// Check this field can be a sub-loop
			$count = $first_row;
   		if( $DG->datatype->initialise_sub_item( 
   			$item, $DG->settings["cf"][ $field ], $DG->settings, $field ) ) {
   	
   			// Loop over sub items
   			while( $subitem = $DG->datatype->get_sub_item( 
   				$item, $DG->settings["cf"][ $field ], $DG->settings, $field ) ) {   				
   				$row = array(
   					"price" => $subitem
   				);
					$data[ "field_id_" . $field_id ][ $count++ ] = $row;
   			}
   		}

			$count = $first_row;
			if( $DG->datatype->initialise_sub_item( 
				$item, $DG->settings["cf"][ $field . "_cartthrob_low" ], $DG->settings, $field ) ) {

				while( $subitem = $DG->datatype->get_sub_item( 
					$item, $DG->settings["cf"][ $field . "_cartthrob_low" ], $DG->settings, $field ) ) {   				
					$data[ "field_id_" . $field_id ][ $count++ ][ "from_quantity" ] = $subitem;
				}
			}

			$count = $first_row;
			if( $DG->datatype->initialise_sub_item( 
				$item, $DG->settings["cf"][ $field . "_cartthrob_high"  ], $DG->settings, $field ) ) {

				while( $subitem = $DG->datatype->get_sub_item( 
					$item, $DG->settings["cf"][ $field . "_cartthrob_high" ], $DG->settings, $field ) ) {   				
					$data[ "field_id_" . $field_id ][ $count++ ][ "up_to_quantity" ] = $subitem;
				}

   		}				
   	}

	}
	
	function rebuild_post_data( $DG, $field_id, &$data, $existing_data ) {
		ee()->db->select( "field_id_".$field_id);
		ee()->db->where( "entry_id", $existing_data["entry_id"] );
		$query = ee()->db->get( "exp_channel_data" );
		if( $query->num_rows() > 0 ) {
			$row = $query->row_array();
			$data[ "field_id_".$field_id ] = unserialize( base64_decode( $row[ "field_id_".$field_id ] ) );
		}
	}
}

?>