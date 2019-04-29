<?php

/**
 * DataGrab Tagger fieldtype class
 *
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 */
class Datagrab_tagger extends Datagrab_fieldtype {

	function prepare_post_data( $DG, $item, $field_id, $field, &$data, $update = FALSE ) {
		
		/*
			[field_id_124] => Array (
	      [tags] => Array (
	        [0] => hello
	        [1] => another
	      )
			)
		*/
		
		$data[ "field_id_" . $field_id ]["tags"] = array();
		
		// Can the current datatype handle sub-loops (eg, XML)?
		if( $DG->datatype->datatype_info["allow_subloop"] ) {
		
			// Check this field can be a sub-loop
			if( $DG->datatype->initialise_sub_item( 
				$item, $DG->settings["cf"][ $field ], $DG->settings, $field ) ) {
		
				// Loop over sub items
				$tags = array();
				while( $subitem = $DG->datatype->get_sub_item( 
					$item, $DG->settings["cf"][ $field ], $DG->settings, $field ) ) {
				
						foreach( explode(",", $subitem) as $titem ) {
							$tags[] = trim($titem);
						}

					}
					$data[ "field_id_" . $field_id ]["tags"] = $tags;

				}
				
		} else {

			foreach( explode(",", $DG->datatype->get_item( $item, $DG->settings["cf"][ $field ] ) ) as $titem ) {
				$tags[] = trim($titem);
			}			

		}

	}

}

?>