<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class TaggerUpdate_30000
{

    /**
     * Constructor
     *
     * @access public
     *
     * Calls the parent constructor
     */
    public function __construct()
    {

    }

    // ********************************************************************************* //

    public function do_update()
    {
        // Rename: order=>tag_order,
        if (ee()->db->field_exists('tag_order', 'tagger_links') == false) {
            $fields = array( '`order`' => array('name' => 'tag_order', 'type' => 'SMALLINT', 'unsigned' => true, 'default' => 0));
            ee()->dbforge->modify_column('tagger_links', $fields);
        }

        // Rename: item_id=>entry_id
        if (ee()->db->field_exists('item_id', 'tagger_links') == true) {
            $fields = array( 'item_id' => array('name' => 'entry_id', 'type' => 'INT', 'unsigned' => true, 'default' => 0));
            ee()->dbforge->modify_column('tagger_links', $fields);
        }

        // Add the channel_id
        if (ee()->db->field_exists('channel_id', 'tagger_links') == false) {
            $fields = array( 'channel_id'   => array('type' => 'SMALLINT',  'unsigned' => true, 'default' => 0) );
            ee()->dbforge->add_column('tagger_links', $fields, 'entry_id');
        }

        // Add the field_id
        if (ee()->db->field_exists('field_id', 'tagger_links') == false) {
            $fields = array( 'field_id' => array('type' => 'MEDIUMINT', 'unsigned' => true, 'default' => 0) );
            ee()->dbforge->add_column('tagger_links', $fields, 'channel_id');
        }

        // Grab all tags (for channel_id)
        $query = ee()->db->select('tl.rel_id, ct.channel_id')->from('tagger_links tl')->join('channel_titles ct', 'ct.entry_id = tl.entry_id', 'left')->get();

        foreach ($query->result() as $row) {
            ee()->db->where('rel_id', $row->rel_id);
            ee()->db->update('tagger_links', array('channel_id' => $row->channel_id));
        }

        $query->free_result();

        // Fill in field_id
        $query = ee()->db->select('group_id, field_id')->from('channel_fields')->where('field_type', 'tagger')->get();

        foreach ($query->result() as $field) {
            // Grab Field Group data
            $q2 = ee()->db->select('channel_id')->from('channels')->where('field_group', $field->group_id)->get();
            if ($q2->num_rows() == 0) continue;
            $channel_id = $q2->row('channel_id');

            ee()->db->where('channel_id', $channel_id);
            ee()->db->update('tagger_links', array('field_id' => $field->field_id));

            $q2->free_result();
        }

        $query->free_result();
    }

    // ********************************************************************************* //

}

/* End of file 30000.php */
/* Location: ./system/user/addons/tagger/updates/30000.php */