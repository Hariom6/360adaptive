<?php

/**
 * DataGrab fieldpack_checkboxes fieldtype class
 *
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 */
class Datagrab_fieldpack_checkboxes extends Datagrab_fieldtype {

	function prepare_post_data( $DG, $item, $field_id, $field, &$data, $update = FALSE ) {
		
		$data[ "field_id_" . $field_id ] = array();

		// Can the current datatype handle sub-loops (eg, XML)?
		if( $DG->datatype->datatype_info["allow_subloop"] ) {
			// Check this field can be a sub-loop
			if( $DG->datatype->initialise_sub_item( 
				$item, $DG->settings["cf"][ $field ], $DG->settings, $field ) ) {
				// Loop over sub items
				$tags = array();
				while( $subitem = $DG->datatype->get_sub_item( 
					$item, $DG->settings["cf"][ $field ], $DG->settings, $field ) ) {
						foreach( preg_split("/,|\|/", $subitem) as $tag ) {
							$tags[] = trim($tag);
						}
				}
				$data[ "field_id_" . $field_id ] = $tags;
			}
		} else {
			$tags = preg_split("/,|\|/", $DG->datatype->get_item( $item, $DG->settings["cf"][ $field ] ));
			foreach( $tags as $tag ){
				$data[ "field_id_" . $field_id ] = trim($tag);
			}
		}

	}

	/*
	function rebuild_post_data( $DG, $field_id, &$data, $existing_data ) {
		$data[ "field_id_".$field_id ] = $row["rel_child_id"];
	}
	*/

}

?>