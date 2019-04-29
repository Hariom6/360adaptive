<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Install / Uninstall and updates the modules
 *
 * @package         DevDemon_Tagger
 * @author          DevDemon <http://www.devdemon.com> - Lead Developer @ Parscale Media
 * @copyright       Copyright (c) 2007-2016 DevDemon <http://www.devdemon.com>
 * @license         http://www.devdemon.com/license/
 * @link            http://www.devdemon.com
 * @see             https://ellislab.com/expressionengine/user-guide/development/modules.html
 */
class Tagger_upd
{
    /**
     * Module version
     *
     * @var string
     * @access public
     */
    public $version = TAGGER_VERSION;

    /**
     * Module Short Name
     *
     * @var string
     * @access private
     */
    private $module_name = TAGGER_CLASS_NAME;

    /**
     * Constructor
     *
     * @access public
     * @return void
     */
    public function __construct()
    {
        // Load dbforge
        ee()->load->dbforge();
    }

    // ********************************************************************************* //

    /**
     * Installs the module
     *
     * Installs the module, adding a record to the exp_modules table,
     * creates and populates and necessary database tables,
     * adds any necessary records to the exp_actions table,
     * and if custom tabs are to be used, adds those fields to any saved publish layouts
     *
     * @access public
     * @return boolean
     **/
    public function install()
    {
        //----------------------------------------
        // EXP_MODULES
        //----------------------------------------
        $module = ee('Model')->make('Module');
        $module->module_name = ucfirst($this->module_name);
        $module->module_version = $this->version;
        $module->has_cp_backend = 'y';
        $module->has_publish_fields = 'n';
        $module->save();

        //----------------------------------------
        // EXP_TAGGER
        //----------------------------------------
        $tagger = array(
            'tag_id'        => array('type' => 'INT',       'unsigned' => true, 'auto_increment' => true),
            'tag_name'      => array('type' => 'VARCHAR',   'constraint' => 255),
            'site_id'       => array('type' => 'TINYINT',   'unsigned' => true, 'default' => 1),
            'author_id'     => array('type' => 'INT',       'unsigned' => true, 'default' => 1),
            'entry_date'    => array('type' => 'INT',       'unsigned' => true, 'default' => 1),
            'edit_date'     => array('type' => 'INT',       'unsigned' => true, 'default' => 1),
            'hits'          => array('type' => 'INT',       'unsigned' => true, 'default' => 0),
            'total_entries' => array('type' => 'INT',       'unsigned' => true, 'default' => 1),
        );

        ee()->dbforge->add_field($tagger);
        ee()->dbforge->add_key('tag_id', true);
        ee()->dbforge->add_key('tag_name');
        ee()->dbforge->create_table('tagger', true);

        //----------------------------------------
        // EXP_TAGGER_LINKS
        //----------------------------------------
        $tagger = array(
            'rel_id'        => array('type' => 'INT',       'unsigned' => true, 'auto_increment' => true),
            'site_id'       => array('type' => 'TINYINT',   'unsigned' => true, 'default' => 1),
            'entry_id'      => array('type' => 'INT',       'unsigned' => true, 'default' => 0),
            'channel_id'    => array('type' => 'SMALLINT',  'unsigned' => true, 'default' => 0),
            'field_id'      => array('type' => 'MEDIUMINT', 'unsigned' => true, 'default' => 0),
//          'item_id'       => array('type' => 'INT',       'unsigned' => true, 'default' => 0),
            'tag_id'        => array('type' => 'INT',       'unsigned' => true, 'default' => 0),
            'author_id'     => array('type' => 'INT',       'unsigned' => true, 'default' => 1),
            'type'          => array('type' => 'SMALLINT',  'unsigned' => true, 'default' => 1),
            'tag_order'     => array('type' => 'SMALLINT',  'unsigned' => true, 'default' => 0),
        );

        ee()->dbforge->add_field($tagger);
        ee()->dbforge->add_key('rel_id', true);
        ee()->dbforge->add_key('tag_id');
        ee()->dbforge->add_key('entry_id');
        ee()->dbforge->create_table('tagger_links', true);

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
            'rel_id'        => array('type' => 'INT',   'unsigned' => true, 'auto_increment' => true),
            'tag_id'        => array('type' => 'INT',   'unsigned' => true, 'default' => 0),
            'group_id'      => array('type' => 'INT',   'unsigned' => true, 'default' => 0),
            '`order`'       => array('type' => 'INT',   'unsigned' => true, 'default' => 1),
        );

        ee()->dbforge->add_field($tagger);
        ee()->dbforge->add_key('rel_id', true);
        ee()->dbforge->add_key('group_id');
        ee()->dbforge->add_key('tag_id');
        ee()->dbforge->create_table('tagger_groups_entries', true);

        //----------------------------------------
        // EXP_ACTIONS
        //----------------------------------------
        $action = ee('Model')->make('Action');
        $action->class = ucfirst($this->module_name);
        $action->method = $this->module_name . '_router';
        $action->csrf_exempt = 0;
        $action->save();

        //----------------------------------------
        // EXP_MODULES
        // The settings column, Ellislab should have put this one in long ago.
        // No need for a seperate preferences table for each module.
        //----------------------------------------
        if (ee()->db->field_exists('settings', 'modules') == false) {
            ee()->dbforge->add_column('modules', array('settings' => array('type' => 'TEXT') ) );
        }

        return true;
    }

    // ********************************************************************************* //

    /**
     * Uninstalls the module
     *
     * @access public
     * @return Boolean false if uninstall failed, true if it was successful
     **/
    public function uninstall()
    {
        // Remove
        ee()->dbforge->drop_table('tagger');
        ee()->dbforge->drop_table('tagger_links');
        ee()->dbforge->drop_table('tagger_groups');
        ee()->dbforge->drop_table('tagger_groups_entries');

        ee('Model')->get('Action')->filter('class', ucfirst($this->module_name))->all()->delete();
        ee('Model')->get('Module')->filter('module_name', ucfirst($this->module_name))->all()->delete();

        return true;
    }

    // ********************************************************************************* //

    /**
     * Updates the module
     *
     * This function is checked on any visit to the module's control panel,
     * and compares the current version number in the file to
     * the recorded version in the database.
     * This allows you to easily make database or
     * other changes as new versions of the module come out.
     *
     * @access public
     * @return Boolean false if no update is necessary, true if it is.
     **/
    public function update($current = '')
    {
        // Are they the same?
        if (version_compare($current, $this->version) >= 0) {
            return false;
        }

        // Two Digits? (needs to be 3)
        if (strlen($current) == 2) $current .= '0';

        $update_dir = PATH_THIRD.strtolower($this->module_name).'/updates/';

        // Does our folder exist?
        if (@is_dir($update_dir) === true) {
            // Loop over all files
            $files = @scandir($update_dir);

            if (is_array($files) == true) {
                foreach ($files as $file) {
                    if (strpos($file, '.php') === false) continue;
                    if (strpos($file, '_') === false) continue; // For legacy: XXX.php
                    if ($file == '.' OR $file == '..' OR strtolower($file) == '.ds_store') continue;

                    // Get the version number
                    $ver = substr($file, 0, -4);
                    $ver = str_replace('_', '.', $ver);

                    // We only want greater ones
                    if (version_compare($current, $ver)  >= 0) continue;

                    require $update_dir . $file;
                    $class = 'TaggerUpdate_' . str_replace('.', '', $ver);
                    $UPD = new $class();
                    $UPD->update();
                }
            }
        }

        // Upgrade The Module
        $module = $module = ee('Model')->make('Module')->filter('module_name', ucfirst($this->module_name))->first();
        $module->module_version = $this->version;
        $module->save();

        return true;
    }

} // END CLASS

/* End of file upd.tagger.php */
/* Location: ./system/user/addons/tagger/upd.tagger.php */