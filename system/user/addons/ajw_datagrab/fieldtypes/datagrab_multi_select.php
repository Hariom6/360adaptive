<?php

/**
 * DataGrab Multiselect fieldtype class
 *
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 */
class Datagrab_multi_select extends Datagrab_fieldtype {

	function prepare_post_data( $DG, $item, $field_id, $field, &$data, $update = FALSE ) {
		
		$values = array();

		// Can the current datatype handle sub-loops (eg, XML)?
		if( $DG->datatype->datatype_info["allow_subloop"] ) {
		
			// Check this field can be a sub-loop
			if( $DG->datatype->initialise_sub_item( 
				$item, $DG->settings["cf"][ $field ], $DG->settings, $field ) ) {
		
				// Loop over sub items
				while( $subitem = $DG->datatype->get_sub_item( 
					$item, $DG->settings["cf"][ $field ], $DG->settings, $field ) ) {
				
						$subitem = str_replace("|", ",", $subitem);
						foreach( explode(",", $subitem) as $titem ) {
							$values[] = trim($titem);
						}

					}

				}
				
		} else {

			$subitem = $DG->datatype->get_item( $item, $DG->settings["cf"][ $field ] );
			$subitem = str_replace("|", ",", $subitem);

			foreach( explode(",", $subitem) as $titem ) {
				$values[] = trim($titem);
			}

		}

		$data[ "field_id_" . $field_id ] = $values;

	}

}

?>