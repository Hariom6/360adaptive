<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class TaggerUpdate_20105
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

    public function update()
    {
        //----------------------------------------
        // EXP_TAGGER_GROUPS
        //----------------------------------------
        $tagger = array(
            'group_id'      => array('type' => 'INT',       'unsigned' => true, 'auto_increment' => true),
            'group_title'   => array('type' => 'VARCHAR',   'constraint' => 255),
            'group_name'    => array('type' => 'VARCHAR',   'constraint' => 255),
            'group_desc'    => array('type' => 'VARCHAR',   'constraint' => 255),
            'parent_id'     => array('type' => 'INT',       'unsigned' => true, 'default' => 0),
            'site_id'       => array('type' => 'TINYINT',   'unsigned' => true, 'default' => 1),
            '`order`'       => array('type' => 'MEDIUMINT', 'unsigned' => true, 'default' => 1),
        );

        ee()->dbforge->add_field($tagger);
        ee()->dbforge->add_key('group_id', true);
        ee()->dbforge->create_table('tagger_groups', true);

        //----------------------------------------
        // EXP_TAGGER_GROUPS_ENTRIES
        //----------------------------------------
        $tagger = array(
            'rel_id'        => array('type' => 'INT',       'unsigned' => true, 'auto_increment' => true),
            'tag_id'        => array('type' => 'INT',       'unsigned' => true, 'default' => 0),
            'group_id'      => array('type' => 'INT',       'unsigned' => true, 'default' => 0),
            '`order`'       => array('type' => 'INT',       'unsigned' => true, 'default' => 1),
        );

        ee()->dbforge->add_field($tagger);
        ee()->dbforge->add_key('rel_id', true);
        ee()->dbforge->add_key('group_id');
        ee()->dbforge->add_key('tag_id');
        ee()->dbforge->create_table('tagger_groups_entries', true);
    }

    // ********************************************************************************* //

}

/* End of file 20105.php */
/* Location: ./system/user/addons/tagger/updates/20105.php */