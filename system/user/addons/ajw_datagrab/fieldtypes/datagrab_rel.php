<?php

/**
 * DataGrab Relationship fieldtype class
 *
 * @package   DataGrab
 * @author    Andrew Weaver <aweaver@brandnewbox.co.uk>
 * @copyright Copyright (c) Andrew Weaver
 */
class Datagrab_rel extends Datagrab_fieldtype {

	function prepare_post_data( $DG, $item, $field_id, $field, &$data, $update = FALSE ) {
		
		ee()->db->where( 'channel_id', ee()->api_channel_fields->settings[ $field_id ][ 'field_related_id' ] );
		ee()->db->where( 'title', $DG->datatype->get_item( $item, $DG->settings["cf"][ $field ] ) );
		ee()->db->select( 'entry_id' );
		$query = ee()->db->get( 'exp_channel_titles' );
		if( $query->num_rows() > 0 ) {
			$row = $query->row_array();
			$data[ "field_id_" . $field_id ] = $row[ "entry_id" ];
		}

	}

	function rebuild_post_data( $DG, $field_id, &$data, $existing_data ) {

		$rel_id = $existing_data["field_id_".$field_id];

		if( $rel_id != "" ) {

			// Fetch relationships from exp_relationships
			ee()->db->select( "rel_child_id" );
			ee()->db->where( "rel_id", $rel_id );
			$query = ee()->db->get( "exp_relationships" );

			if( $query->num_rows() > 0 ) {
				$row = $query->row_array();

				// Rebuild selections array
				$data[ "field_id_".$field_id ] = $row["rel_child_id"];
			}
		}
		
	}

}

?>