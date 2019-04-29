<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use EllisLab\ExpressionEngine\Library\CP\Table;

/**
 * Tagger Module Control Panel Class
 *
 * @package         DevDemon_Tagger
 * @author          DevDemon <http://www.devdemon.com> - Lead Developer @ Parscale Media
 * @copyright       Copyright (c) 2007-2016 DevDemon <http://www.devdemon.com>
 * @license         http://www.devdemon.com/license/
 * @link            http://www.devdemon.com
 * @see             https://ellislab.com/expressionengine/user-guide/development/modules.html
 */
class Tagger_mcp
{
    /**
     * Views Data
     * @access private
     */
    private $vdata = array();

    /**
     * Constructor
     *
     * @access public
     * @return void
     */
    public function __construct()
    {
        $this->baseUrl = ee('CP/URL', 'addons/settings/tagger');
        $this->site_id = ee()->config->item('site_id');
        $this->vdata['baseUrl']  = $this->baseUrl->compile();
        $this->vdata['themeUrl'] = ee('tagger:Helper')->getThemeUrl();
        $this->vdata['ajaxUrl']  = ee('tagger:Helper')->getRouterUrl('url');

        //----------------------------------------
        // CSS/JS
        //----------------------------------------
        ee()->cp->add_to_foot("<script type='text/javascript'>
            var Tagger = Tagger ? Tagger : {};
            Tagger.AJAX_URL = '{$this->vdata['ajaxUrl']}&site_id={$this->site_id}';
            Tagger.THEME_URL = '{$this->vdata['themeUrl']}';
        </script>");
        ee()->cp->add_to_foot('<script src="' . $this->vdata['themeUrl'] . 'tagger_mcp.js?v='.TAGGER_VERSION.' type="text/javascript"></script>');
        ee()->cp->add_to_foot('<script src="' . $this->vdata['themeUrl'] . 'jquery.multiselect.js?v='.TAGGER_VERSION.' type="text/javascript"></script>');
        ee()->cp->add_to_foot('<script src="' . $this->vdata['themeUrl'] . 'jquery.dataTables.js?v='.TAGGER_VERSION.' type="text/javascript"></script>');
        ee()->cp->add_to_head('<link rel="stylesheet" href="' . $this->vdata['themeUrl'] . 'tagger_mcp.css?v='.TAGGER_VERSION.'" type="text/css" media="print, projection, screen">');

        //----------------------------------------
        // Sidebar
        //----------------------------------------
        $this->sidebar   = ee('CP/Sidebar')->make();
        $this->navTags   = $this->sidebar->addHeader(lang('tagger:tags'), ee('CP/URL', 'addons/settings/tagger'));
        $this->navGroups = $this->sidebar->addHeader(lang('tagger:groups'), ee('CP/URL', 'addons/settings/tagger/groups'))->withButton(lang('new'), ee('CP/URL', 'addons/settings/tagger/create-group'));
        $this->navImport = $this->sidebar->addHeader(lang('tagger:import'), ee('CP/URL', 'addons/settings/tagger/import'));
        $this->navDocs   = $this->sidebar->addHeader(lang('tagger:docs'), ee()->cp->masked_url('http://www.devdemon.com/tagger/docs/'))->urlIsExternal(true);

        ee()->view->header = array(
            'title' => lang('tagger'),
            'toolbar_items' => array(
                'settings' => array(
                    'href' => ee('CP/URL', 'addons/settings/tagger/settings'),
                    'title' => lang('settings')
                )
            )
        );
    }

    // ********************************************************************************* //

    /**
     * MCP PAGE: Index
     *
     * @access public
     * @return string
     */
    public function index()
    {
        return array(
            'heading' => lang('tagger:tags'),
            'body' => ee('View')->make('tagger:mcp_index')->render(array()),
            'sidebar' => $this->sidebar,
            'breadcrumb'    => array(
                $this->baseUrl->compile() => lang('tagger')
            )
        );
    }

    // ********************************************************************************* //

    /**
     * MCP PAGE: Tag Groups
     * @access public
     * @return string
     */
    public function groups()
    {
        // Mark the sidebar menu as active
        $this->navGroups->isActive();

        // Use default options
        $table = ee('CP/Table', array('autosort' => true, 'autosearch' => false));
        $table->setNoResultsText('tagger:no_groups', 'tagger:create_group', ee('CP/URL', 'addons/settings/tagger/create-group'));
        $table->setColumns(
            array(
                'tagger:group_title',
                'tagger:group_name',
                'tagger:group_desc',
                'manage' => array(
                    'type'  => Table::COL_TOOLBAR
                ),
                array(
                    'type'  => Table::COL_CHECKBOX
                )
            )
        );

        // Add the confirm_remove JS script
        ee()->cp->add_js_script(array(
            'file' => array('cp/confirm_remove'),
        ));

        $data = array();
        $groups = ee('Model')->get('tagger:Group')->all();

        foreach ($groups as $group) {
            $editUrl = ee('CP/URL', 'addons/settings/tagger/edit-group/' . $group->group_id);
            $deleteUrl = ee('CP/URL', 'addons/settings/tagger/update-group/&delete=yes&group_id=' . $group->group_id);

            $data[] = array(
                $group->group_title,
                $group->group_name,
                $group->group_desc,
                array('toolbar_items' => array(
                        'edit' => array(
                            'href' => $editUrl,
                            'title' => lang('edit')
                        )
                    )
                ),
                array(
                    'name' => 'groups[]',
                    'value' => $group->group_id,
                    'data'  => array(
                        'confirm' => lang('group') . ': <b>' . htmlentities($group->group_title, ENT_QUOTES) . '</b>'
                    )
                )
            );
        }

        $table->setData($data);

        $base_url = ee('CP/URL', 'addons/settings/tagger/groups');
        $this->vdata['table'] = $table->viewData($base_url);

        // Return the view array
        return array(
            'heading' => lang('tagger:groups'),
            'body'    => ee('View')->make('tagger:mcp_groups')->render($this->vdata),
            'sidebar' => $this->sidebar,
            'breadcrumb'    => array(
                $this->baseUrl->compile() => lang('tagger')
            )
        );
    }

    // ********************************************************************************* //

    public function createGroup($group_id=null)
    {
        // Mark the sidebar menu as active
        $this->navGroups->isActive();

        if ($group_id) {
            $group = ee('Model')->get('tagger:Group')->filter('group_id', $group_id)->first();
        } else {
            $group = ee('Model')->make('tagger:Group');
        }

        // Form definition array
        $this->vdata['sections'] = array(
            array(
                array(
                    'hide'   => true,
                    'fields' => array(
                        'group_id' => array(
                            'type'  => 'hidden',
                            'value' => $group->group_id,
                        )
                    )
                ),
                array(
                    'title'  => 'tagger:group_title',
                    'fields' => array(
                        'group_title' => array(
                            'type'  => 'text',
                            'value' => $group->group_title,
                        )
                    )
                ),
                array(
                    'title'  => 'tagger:group_name',
                    'desc'   => 'tagger:group_name_exp',
                    'fields' => array(
                        'group_name' => array(
                            'type'  => 'text',
                            'value' => $group->group_name,
                        )
                    )
                ),
                array(
                    'title'  => 'tagger:group_desc',
                    'fields' => array(
                        'group_desc' => array(
                            'type'  => 'text',
                            'value' => $group->group_desc,
                        )
                    )
                ),
            ),
        );

        // Final view variables we need to render the form
        $this->vdata += array(
            'base_url'      => $this->baseUrl->compile() . '/update-group',
            'cp_page_title' => $group->isNew() ? lang('tagger:create_group') : lang('tagger:edit_group'),
            'save_btn_text' => sprintf(lang('btn_save'), lang('tagger:group')),
            'save_btn_text_working' => 'btn_saving',
        );

        return array(
            'heading'   => $group->isNew() ? lang('tagger:create_group') : lang('tagger:edit_group'),
            'body'      => ee('View')->make('tagger:mcp_settings')->render($this->vdata),
            'breadcrumb'=> array(
                $this->baseUrl->compile() => lang('tagger')
            )
        );
    }

    // ********************************************************************************* //

    public function editGroup($id)
    {
        return $this->createGroup($id);
    }

    // ********************************************************************************* //

    public function updateGroup()
    {
        $id = ee('Request')->post('group_id');

        if ($id) {
            $group = ee('Model')->get('tagger:Group')->filter('group_id', $id)->first();
        } else {
            $group = ee('Model')->make('tagger:Group');
            $group->site_id = $this->site_id;
        }

        $group->group_title = ee('Request')->post('group_title');
        $group->group_name  = ee('Request')->post('group_name');
        $group->group_desc  = ee('Request')->post('group_desc');
        $group->save();

        ee('CP/Alert')->makeInline('groups-table')
        ->asSuccess()
        ->withTitle(lang('tagger:updated_group'))
        ->defer();

        ee()->functions->redirect($this->baseUrl.'/groups');
    }

    // ********************************************************************************* //

    public function removeGroup()
    {
        if (!ee()->input->post('groups')) {
            ee()->functions->redirect($this->baseUrl.'/groups');
        }

        $groups = ee('Model')->get('tagger:Group')->filter('group_id', 'IN', ee('Request')->post('groups'))->all();

        foreach ($groups as $group) {
            $group->Tags = null;
            $group->save();

            $group->delete();
        }

        ee('CP/Alert')->makeInline('groups-table')
        ->asSuccess()
        ->withTitle(lang('tagger:deleted_group'))
        ->defer();

        ee()->functions->redirect($this->baseUrl.'/groups');
    }

    // ********************************************************************************* //

    /**
     * MCP PAGE: Import
     * @access public
     * @return string
     */
    public function import()
    {
        $this->vdata['solspace_tags'] = ee()->db->table_exists('tag_tags');
        $this->vdata['taggable_tags'] = ee()->db->table_exists('taggable_tags');

        // Grab all channels
        $channels = array();
        $query = ee()->db->query("SELECT channel_id, channel_title FROM exp_channels WHERE site_id = {$this->site_id}");
        foreach($query->result() as $row) $channels[$row->channel_id] = $row->channel_title;
        $this->vdata['channels'] = $channels;

        // Grab all fields
        $this->vdata['fields_normal'] = array('');

        $query = ee()->db->query("SELECT cf.field_id, cf.field_label, fg.group_name FROM exp_channel_fields as `cf` LEFT JOIN exp_field_groups AS `fg` ON fg.group_id = cf.group_id WHERE cf.field_type != 'tagger' AND cf.site_id = {$this->site_id}");
        foreach ($query->result() as $row)
        {
            $this->vdata['fields_normal'][$row->group_name][$row->field_id] = $row->field_label;
        }

        $this->vdata['fields_tagger'] = array('');

        $query = ee()->db->query("SELECT cf.field_id, cf.field_label, fg.group_name FROM exp_channel_fields as `cf` LEFT JOIN exp_field_groups AS `fg` ON fg.group_id = cf.group_id WHERE cf.field_type = 'tagger' AND cf.site_id = {$this->site_id}");
        foreach ($query->result() as $row)
        {
            $this->vdata['fields_tagger'][$row->group_name][$row->field_id] = $row->field_label;
        }

        return array(
            'heading' => lang('tagger:import'),
            'body' => ee('View')->make('tagger:mcp_import')->render($this->vdata),
            'sidebar' => $this->sidebar,
            'breadcrumb'    => array(
                $this->baseUrl->compile() => lang('tagger')
            )
        );
    }

    // ********************************************************************************* //

    /**
     * MCP PAGE: Do Import
     * @access public
     * @return string
     */
    public function do_import_solspace()
    {
        $channels = ee()->input->get_post('channels');

        if ($channels == FALSE or empty($channels) == TRUE)
        {
            ee()->functions->redirect($this->base . '&method=import');
        }

        // Grab All Tags
        $query = ee()->db->query("SELECT tag_id, tag_name, author_id, entry_date, clicks, channel_entries FROM exp_tag_tags WHERE site_id = {$this->site_id}");

        // Loop Over all tags
        foreach($query->result() as $tag)
        {
            // Does it already exist?
            ee()->db->select('tag_id');
            ee()->db->from('exp_tagger');
            ee()->db->where('tag_name', $tag->tag_name);
            ee()->db->where('site_id', $this->site_id);
            ee()->db->limit(1);
            $q2 = ee()->db->get();

            // Create the TAG!
            if ($q2->num_rows() == 0)
            {
                // Data array for insertion
                $data = array(  'tag_name'  =>  $tag->tag_name,
                                'author_id' =>  $tag->author_id,
                                'entry_date'=>  $tag->entry_date,
                                'hits' => $tag->clicks,
                                'total_entries' => $tag->channel_entries,
                        );

                ee()->db->insert('exp_tagger', $data);

                $tag_id = ee()->db->insert_id();
            }
            else
            {
                $tag_id = $q2->row('tag_id');
            }

            $q2->free_result();

            // Grab all relations!
            $q3 = ee()->db->query("SELECT entry_id, channel_id, author_id FROM exp_tag_entries WHERE tag_id = {$tag->tag_id} AND site_id = {$this->site_id}");
            foreach($q3->result() as $order => $row)
            {
                // In the Channel?
                if (in_array($row->channel_id, $channels) == FALSE) continue;

                // Does this relationship already exist?
                $q4 = ee()->db->query("SELECT rel_id FROM exp_tagger_links WHERE tag_id = {$tag_id} AND entry_id = {$row->entry_id}");
                if ($q4->num_rows() > 0) continue;

                // Data array for insertion
                $data = array(  'entry_id'  =>  $row->entry_id,
                                'channel_id'=>  $row->channel_id,
                                'tag_id'    =>  $tag_id,
                                'site_id'   =>  $this->site_id,
                                'author_id' =>  $row->author_id,
                                'type'      => 1,
                                'tag_order' =>  $order,
                        );

                ee()->db->insert('exp_tagger_links', $data);

                $q4->free_result();
            }

            $q3->free_result();
        }

        $this->recount_totals();

        ee()->functions->redirect($this->base);
    }

    // ********************************************************************************* //

    /**
     * MCP PAGE: Do Import
     * @access public
     * @return string
     */
    public function do_import_taggable()
    {
        $channels = ee()->input->get_post('channels');

        if ($channels == FALSE or empty($channels) == TRUE)
        {
            ee()->functions->redirect($this->base . '&method=import');
        }

        // Grab All Tags
        $query = ee()->db->query("SELECT id, name FROM exp_taggable_tags WHERE site_id = {$this->site_id}");

        // Loop Over all tags
        foreach($query->result() as $tag)
        {
            // Does it already exist?
            ee()->db->select('tag_id');
            ee()->db->from('exp_tagger');
            ee()->db->where('tag_name', $tag->name);
            ee()->db->where('site_id', $this->site_id);
            ee()->db->limit(1);
            $q2 = ee()->db->get();

            // Create the TAG!
            if ($q2->num_rows() == 0)
            {
                // Data array for insertion
                $data = array(  'tag_name'  =>  $tag->name,
                                'author_id' =>  ee()->session->userdata('member_id'),
                                'entry_date'=>  ee()->localize->now,
                                'hits' => 0,
                                'total_entries' => 0,
                        );

                ee()->db->insert('exp_tagger', $data);

                $tag_id = ee()->db->insert_id();
            }
            else
            {
                $tag_id = $q2->row('tag_id');
            }

            $q2->free_result();

            // Grab all relations!
            $q3 = ee()->db->query("SELECT ct.entry_id, tt.tag_id, ct.channel_id, ct.author_id FROM exp_taggable_tags_entries AS `tt` LEFT JOIN exp_channel_titles as `ct` ON ct.entry_id = tt.entry_id WHERE tag_id = {$tag->id}");
            foreach($q3->result() as $order => $row)
            {
                // In the Channel?
                if (in_array($row->channel_id, $channels) == FALSE) continue;

                // Does this relationship already exist?
                $q4 = ee()->db->query("SELECT rel_id FROM exp_tagger_links WHERE tag_id = {$tag_id} AND entry_id = {$row->entry_id}");
                if ($q4->num_rows() > 0) continue;

                // Data array for insertion
                $data = array(  'entry_id'  =>  $row->entry_id,
                                'channel_id'=>  $row->channel_id,
                                'tag_id'    =>  $tag_id,
                                'site_id'   =>  $this->site_id,
                                'author_id' =>  $row->author_id,
                                'type'      => 1,
                                'tag_order' =>  $order,
                        );

                ee()->db->insert('exp_tagger_links', $data);

                $q4->free_result();
            }

            $q3->free_result();
        }

        $this->recount_totals();

        ee()->functions->redirect($this->base);
    }

    // ********************************************************************************* //

    /**
     * MCP PAGE: Do Import
     * @access public
     * @return string
     */
    public function do_import_text()
    {
        $source = trim(ee()->input->get_post('source'));
        $dest = trim(ee()->input->get_post('dest'));
        $sep = trim(ee()->input->get_post('sep'));

        if ($sep == FALSE || $dest == FALSE || $source == FALSE)
        {
            show_error('Missing Parameters');
        }

        // Get all entries
        $query = ee()->db->query("SELECT ct.entry_id, ct.channel_id, ct.author_id, ct.entry_date, cd.field_id_{$source} AS field_tags FROM exp_channel_titles AS `ct` LEFT JOIN exp_channel_data as `cd` ON ct.entry_id = cd.entry_id WHERE cd.field_id_{$source} != '' ");

        if ($query->num_rows() == 0) {
            show_error('No entries found');
        }

        // Loop Over all tags
        foreach($query->result() as $entry)
        {
            $tags = explode($sep, $entry->field_tags);

            foreach ($tags as $order => $tag)
            {
                $tag = trim($tag);

                // Does it already exist?
                ee()->db->select('tag_id');
                ee()->db->from('exp_tagger');
                ee()->db->where('tag_name', $tag);
                ee()->db->where('site_id', $this->site_id);
                ee()->db->limit(1);
                $q2 = ee()->db->get();

                // Create the TAG!
                if ($q2->num_rows() == 0)
                {
                    // Data array for insertion
                    $data = array(  'tag_name'  =>  $tag,
                                    'author_id' =>  $entry->author_id,
                                    'entry_date'=>  $entry->entry_date,
                                    'hits' => 0,
                                    'total_entries' => 0,
                            );

                    ee()->db->insert('exp_tagger', $data);

                    $tag_id = ee()->db->insert_id();
                }
                else
                {
                    $tag_id = $q2->row('tag_id');
                }

                // Does this relationship already exist?
                $q4 = ee()->db->query("SELECT rel_id FROM exp_tagger_links WHERE tag_id = {$tag_id} AND entry_id = {$entry->entry_id}");
                if ($q4->num_rows() > 0) continue;

                // Data array for insertion
                $data = array(  'entry_id'  =>  $entry->entry_id,
                                'channel_id'=>  $entry->channel_id,
                                'tag_id'    =>  $tag_id,
                                'site_id'   =>  $this->site_id,
                                'author_id' =>  $entry->author_id,
                                'type'      => 1,
                                'tag_order' =>  $order,
                        );

                ee()->db->insert('exp_tagger_links', $data);
            }


        }

        $this->recount_totals();

        ee()->functions->redirect($this->base);
    }

    // ********************************************************************************* //

    private function recount_totals()
    {
        $query = ee()->db->select('tag_id')->from('exp_tagger')->get();

        foreach ($query->result() as $row)
        {
            // Get total
            $q = ee()->db->query("SELECT COUNT(*) as total FROM exp_tagger_links WHERE tag_id = {$row->tag_id}");

            ee()->db->set('total_entries', $q->row('total'));
            ee()->db->where('tag_id', $row->tag_id);
            ee()->db->update('exp_tagger');
        }
    }

    // ********************************************************************************* //

    public function settings()
    {
        $settings = ee('tagger:Settings')->settings;

        // Form definition array
        $this->vdata['sections'] = array(
            array(
                array(
                    'title' => 'tagger:lowercase_tags',
                    'fields' => array(
                        'lowercase_tags' => array(
                            'type'    => 'inline_radio',
                            'value'   => $settings['lowercase_tags'],
                            'choices' => array(
                                'yes' => lang('yes'),
                                'no'  => lang('no'),
                            )
                        )
                    )
                ),
                array(
                    'title' => 'tagger:urlsafe_separator',
                    'fields' => array(
                        'urlsafe_separator' => array(
                            'type'    => 'inline_radio',
                            'value'   => $settings['urlsafe_separator'],
                            'choices' => array(
                                'plus'       => lang('tagger:plus'),
                                'space'      => lang('tagger:space'),
                                'dash'       => lang('tagger:dash'),
                                'underscore' => lang('tagger:underscore'),
                            )
                        )
                    )
                ),
            ),
        );

        // Final view variables we need to render the form
        $this->vdata += array(
            'base_url'      => $this->baseUrl->compile() . '/save-settings',
            'cp_page_title' => lang('tagger:settings'),
            'save_btn_text' => 'btn_save_settings',
            'save_btn_text_working' => 'btn_saving',
        );

        return array(
            'heading'   => lang('tagger:settings'),
            'body'      => ee('View')->make('tagger:mcp_settings')->render($this->vdata),
            'breadcrumb'=> array(
                $this->baseUrl->compile() => lang('tagger')
            )
        );
    }

    // ********************************************************************************* //

    public function saveSettings()
    {
        $settings = ee('tagger:Settings')->settings;
        $settings['lowercase_tags']    = ee('Request')->post('lowercase_tags');
        $settings['urlsafe_separator'] = ee('Request')->post('urlsafe_separator');

        ee('tagger:Settings')->saveModuleSettings($settings);

        ee('CP/Alert')->makeInline('shared-form')
        ->asSuccess()
        ->withTitle(lang('settings_saved'))
        ->addToBody(sprintf(lang('settings_saved_desc'), TAGGER_NAME))
        ->defer();

        ee()->functions->redirect($this->baseUrl . '/settings');
    }

    // ********************************************************************************* //


} // END CLASS

/* End of file mcp.tagger.php */
/* Location: ./system/user/addons/tagger/mcp.tagger.php */