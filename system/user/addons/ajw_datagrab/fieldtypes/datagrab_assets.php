<?php

/**
 * DataGrab Assets fieldtype class
 *
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 */
class Datagrab_assets extends Datagrab_fieldtype {

	function prepare_post_data( $DG, $item, $field_id, $field, &$data, $update = FALSE ) {
		
		$data[ "field_id_" . $field_id ] = array();
		
		// Can the current datatype handle sub-loops (eg, XML)?
		if( $DG->datatype->datatype_info["allow_subloop"] ) {
		
			// Check this field can be a sub-loop
			if( $DG->datatype->initialise_sub_item( 
				$item, $DG->settings["cf"][ $field ], $DG->settings, $field ) ) {
		
				// Loop over sub items
				while( $subitem = $DG->datatype->get_sub_item( 
					$item, $DG->settings["cf"][ $field ], $DG->settings, $field ) ) {
		
					if( preg_match('/{filedir_([0-9]+)}/', $subitem, $matches) ) {
						$file = array(
							"filedir" => $matches[1],
							"filename" => str_replace($matches[0], '', $subitem )
						);
		
						ee()->db->select( "file_id" );
						ee()->db->where( "file_name", $file["filename"] );
						ee()->db->where( "filedir_id", $file["filedir"] );
						$query = ee()->db->get( "exp_assets_files" );
						if( $query->num_rows() > 0 ) {
							$row = $query->row_array();				
							$data[ "field_id_" . $field_id ][] = $row["file_id"];
						}
					} else {
						ee()->db->select( "file_id" );
						ee()->db->where( "file_name", $subitem );
						$query = ee()->db->get( "exp_assets_files" );
						if( $query->num_rows() > 0 ) {
							$row = $query->row_array();				
							$data[ "field_id_" . $field_id ][] = $row["file_id"];
						}
					}
		
				}
			}
		}
				
		/*
		[field_id_40] => Array
		        (
		            [0] => 4
		            [1] => 2
		            [2] => 3
		            [3] => 
		        )
		*/		
	}

	function rebuild_post_data( $DG, $field_id, &$data, $existing_data ) {

		$data[ "field_id_" . $field_id ] = array();

		ee()->db->select( "file_id" );
		ee()->db->from( "exp_assets_selections" );
		ee()->db->where( "entry_id", $existing_data["entry_id"] );
		ee()->db->where( "field_id", $field_id );
		ee()->db->order_by( "sort_order" );
		$query = ee()->db->get();
		
		foreach( $query->result_array() as $row ) {
			$data[ "field_id_" . $field_id ][] = $row["file_id"];
		}

	}

}

?>