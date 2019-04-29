<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class TaggerUpdate_30200
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

/* End of file 30200.php */
/* Location: ./system/user/addons/tagger/updates/30200.php */