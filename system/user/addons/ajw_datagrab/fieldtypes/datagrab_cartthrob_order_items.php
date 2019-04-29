<?php

/**
 * DataGrab cartthrob_order_items fieldtype class
 *
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 */
class Datagrab_cartthrob_order_items extends Datagrab_fieldtype {

	function register_setting( $field_name ) {
		return array( 
			$field_name . "_cartthrob_title", 
			$field_name . "_cartthrob_quantity",
			$field_name . "_cartthrob_price",
			$field_name . "_cartthrob_size",
			$field_name . "_cartthrob_modifier"
		);
	}

	function display_configuration( $field_name, $field_label, $field_type, $field_required, $data ) {
		$config = array();
		$config["label"] = "<p>" .
		form_label($field_label);
		/*  . NBS .
		anchor("http://brandnewbox.co.uk/support/details/importing_into_playa_fields_with_datagrab", "(?)", 'class="ajw_datagrab_help"');
		*/
		$config["value"] = "Entry ID: " . NBS . form_dropdown( 
			$field_name, $data["data_fields"], 
			isset( $data["default_settings"]["cf"][$field_name] ) ? 
				$data["default_settings"]["cf"][$field_name] : '' 
			) . 
			"</p><p>" . "Title: " . NBS .
			form_dropdown( 
				$field_name . "_cartthrob_title", 
				$data["data_fields"], 
				(isset($data["default_settings"]["cf"][$field_name . "_cartthrob_title"]) ? 
					$data["default_settings"]["cf"][$field_name . "_cartthrob_title" ]: '' )
			) .
			"</p><p>" . "Qunatity: " . NBS .
			form_dropdown( 
				$field_name . "_cartthrob_quantity", 
				$data["data_fields"], 
				(isset($data["default_settings"]["cf"][$field_name . "_cartthrob_quantity"]) ? 
					$data["default_settings"]["cf"][$field_name . "_cartthrob_quantity" ]: '' )
			) .
			"</p><p>" . "Price: " . NBS .
			form_dropdown( 
				$field_name . "_cartthrob_price", 
				$data["data_fields"], 
				(isset($data["default_settings"]["cf"][$field_name . "_cartthrob_price"]) ? 
					$data["default_settings"]["cf"][$field_name . "_cartthrob_price" ]: '' )
			) .
			"</p><p>" . "Size: " . NBS .
			form_dropdown( 
				$field_name . "_cartthrob_size", 
				$data["data_fields"], 
				(isset($data["default_settings"]["cf"][$field_name . "_cartthrob_size"]) ? 
					$data["default_settings"]["cf"][$field_name . "_cartthrob_size" ]: '' )
			) .
			"</p><p>" . "Modifier: " . NBS .
			form_dropdown( 
				$field_name . "_cartthrob_modifier", 
				$data["data_fields"], 
				(isset($data["default_settings"]["cf"][$field_name . "_cartthrob_modifier"]) ? 
					$data["default_settings"]["cf"][$field_name . "_cartthrob_modifier" ]: '' )
			) .
			"</p>";
		return $config;
	}


	function prepare_post_data( $DG, $item, $field_id, $field, &$data, $update = FALSE ) {
	}

	function final_post_data( $DG, $item, $field_id, $field, &$data, $update = FALSE ) {

	/*
    [field_id_19] => Array
            (
                [0] => Array
                    (
                        [entry_id] => 1
                        [title] => Test
                        [quantity] => 1
                        [price] => 12
                        [size] => M
                        [modifier] => UNBOXED
                        [weight] => 
                        [shipping] => 
                        [no_tax] => 
                        [no_shipping] => 
                        [row_id] => 
                    )
    
            )
       */
   
		// Initialise data
		$data[ "field_id_" . $field_id ] = array();
		$first_row = 0;
   	
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
   					"entry_id" => $subitem
   				);
					$data[ "field_id_" . $field_id ][ $count++ ] = $row;
   			}
   		}

			foreach( $this->register_setting( $field ) as $fname ) {

				$shortname = substr( $fname, strlen($field."_cartthrob_") );

				$count = $first_row;
				$data[ "field_id_" . $field_id ][ $count ][ $shortname ] = "";
				if( $DG->datatype->initialise_sub_item( 
					$item, $DG->settings["cf"][ $fname ], $DG->settings, $field ) ) {
	
					$subitem = $DG->datatype->get_sub_item( 
						$item, $DG->settings["cf"][ $fname ], $DG->settings, $field );
					while( $subitem !== FALSE ) {   				
						$data[ "field_id_" . $field_id ][ $count++ ][ $shortname ] = $subitem;
						$subitem = $DG->datatype->get_sub_item( 
							$item, $DG->settings["cf"][ $fname ], $DG->settings, $field );
					}
				}
		
			}

   	}

		// print_r( $data["field_id_" . $field_id] ); exit;

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