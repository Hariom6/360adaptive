<?php

/**
 * DataGrab Toggle fieldtype class
 *
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 */
class Datagrab_toggle extends Datagrab_fieldtype {

	function prepare_post_data( $DG, $item, $field_id, $field, &$data, $update = FALSE ) {
		
		$value = $DG->datatype->get_item( $item, $DG->settings["cf"][ $field ] );
		$data[ "field_id_" . $field_id ] = 0;

		if( $value == "y" || $value == "yes" || $value == "true" || $value == 1 || $value == "on" ) {
			$data[ "field_id_" . $field_id ] = 1;
		}

		// print $data[ "field_id_" . $field_id ];
	}

}

?>