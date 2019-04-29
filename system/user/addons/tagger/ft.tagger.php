<?php if (!defined('BASEPATH')) die('No direct script access allowed');

/**
 * Tagger Module FieldType
 *
 * @package         DevDemon_Tagger
 * @author          DevDemon <http://www.devdemon.com> - Lead Developer @ Parscale Media
 * @copyright       Copyright (c) 2007-2016 DevDemon <http://www.devdemon.com>
 * @license         http://www.devdemon.com/license/
 * @link            http://www.devdemon.com
 * @see             https://ellislab.com/expressionengine/user-guide/development/fieldtypes.html
 */
class Tagger_ft extends EE_Fieldtype
{
    /**
     * Field info (Required)
     *
     * @var array
     * @access public
     */
    var $info = array(
        'name'      => TAGGER_NAME,
        'version'   => TAGGER_VERSION
    );

    public $has_array_data = TRUE;


    /**
     * Constructor
     *
     * @access public
     *
     * Calls the parent constructor
     */
    public function __construct()
    {
        parent::__construct();

        ee()->lang->loadfile('tagger');
        ee()->load->library('tagger_helper');

        $this->site_id = ee()->config->item('site_id');
        ee()->tagger_helper->define_theme_url();
    }

    // ********************************************************************************* //

    /**
     * Display the field in the publish form
     *
     * @access public
     * @param $data String Contains the current field data. Blank for new entries.
     * @return String The custom field HTML
     *
     * $this->settings =
     *  Array
     *  (
     *      [field_id] => nsm_better_meta__nsm_better_meta
     *      [field_label] => NSM Better Meta
     *      [field_required] => n
     *      [field_data] =>
     *      [field_list_items] =>
     *      [field_fmt] =>
     *      [field_instructions] =>
     *      [field_show_fmt] => n
     *      [field_pre_populate] => n
     *      [field_text_direction] => ltr
     *      [field_type] => nsm_better_meta
     *      [field_name] => nsm_better_meta__nsm_better_meta
     *      [field_channel_id] =>
     *  )
     */
    public function display_field($data)
    {

        // -----------------------------------------
        // Some Globals
        // -----------------------------------------
        $vData = array();
        $vData['themeUrl'] = ee('tagger:Helper')->getThemeUrl();
        $vData['ajaxUrl']  = ee('tagger:Helper')->getRouterUrl('url');
        $vData['assigned_tags'] = array();
        $vData['most_used_tags'] = array();
        $vData['field_name'] = $this->field_name;
        $vData['field_id'] = $this->field_id;
        $vData['channel_id'] = (ee()->input->get_post('channel_id') != FALSE) ? ee()->input->get_post('channel_id') : 0;
        $entry_id = $this->content_id();

        if (REQ == 'CP')
        {
            $vData['field_name'] = 'field_id_' .$this->field_id;
        }

        // Post DATA?
        if (isset($_POST[$this->field_name])) {
            $data = $_POST[$this->field_name];
        }

        //----------------------------------------
        // CSS/JS
        //----------------------------------------
        ee()->cp->add_to_foot("<script type='text/javascript'>
            var Tagger = Tagger ? Tagger : {};
            Tagger.AJAX_URL = '{$vData['ajaxUrl']}&site_id={$this->site_id}';
            Tagger.THEME_URL = '{$vData['themeUrl']}';
        </script>");
        ee()->cp->add_to_foot('<script src="' . $vData['themeUrl'] . 'jquery.tagsinput.js?v='.TAGGER_VERSION.' type="text/javascript"></script>');
        ee()->cp->add_to_foot('<script src="' . $vData['themeUrl'] . 'tagger_pbf.js?v='.TAGGER_VERSION.' type="text/javascript"></script>');
        ee()->cp->add_to_head('<link rel="stylesheet" href="' . $vData['themeUrl'] . 'tagger_pbf.css?v='.TAGGER_VERSION.'" type="text/css" media="print, projection, screen">');

        ee()->cp->add_js_script(array('ui' => array('sortable', 'menu', 'autocomplete')));

        // Defaults
        $vData['config'] = ee('tagger:Settings')->getFieldtypeSettings($this->field_id, $this->settings);

        // -----------------------------------------
        // Grab most used tags
        // -----------------------------------------
        ee()->db->select('tag_name');
        ee()->db->from('exp_tagger');
        ee()->db->where('total_entries >', 0);
        ee()->db->where('site_id', $this->site_id);
        ee()->db->order_by('total_entries', 'desc');
        ee()->db->limit(25);
        $query = ee()->db->get();

        foreach ($query->result() as $row)
        {
            $vData['most_used_tags'][] = $row->tag_name;
        }

        // Sometimes you forget to fill in field
        // and you will send back to the form
        // We need to fil lthe values in again.. *Sigh* (anyone heard about AJAX!)
        if (is_array($data) == TRUE && isset($data['tags']) == TRUE)
        {
            foreach ($data['tags'] as $tag)
            {
                $vData['assigned_tags'][] = $tag;
            }

            return ee()->load->view('pbf_field', $vData, TRUE);
        }

        // -----------------------------------------
        // Grab assigned tags
        // -----------------------------------------
        if ($entry_id)
        {
            ee()->db->select('t.tag_name');
            ee()->db->from('exp_tagger_links tp');
            ee()->db->join('exp_tagger t', 'tp.tag_id = t.tag_id', 'left');
            ee()->db->where('tp.entry_id', $entry_id);
            ee()->db->where('tp.field_id', $vData['field_id']);
            ee()->db->where('tp.site_id', $this->site_id);
            ee()->db->where('tp.type', 1);
            ee()->db->order_by('tp.tag_order');
            $query = ee()->db->get();

            foreach ($query->result() as $row)
            {
                $vData['assigned_tags'][] = $row->tag_name;
            }
        }

        return ee('View')->make('tagger:pbf_field')->render($vData);
    }

    // ********************************************************************************* //

    /**
     * Validates the field input
     *
     * @param $data Contains the submitted field data.
     * @return mixed Must return TRUE or an error message
     */
    public function validate($data)
    {
        // Is this a required field?
        if ($this->settings['field_required'] == 'y')
        {
            if (isset($data['tags']) == FALSE OR empty($data['tags']) == TRUE)
            {
                return ee()->lang->line('tagger:required_field');
            }
        }

        return TRUE;
    }

    // ********************************************************************************* //

    /**
     * Preps the data for saving
     *
     * @param $data Contains the submitted field data.
     * @return string Data to be saved
     */
    public function save($data)
    {
        // Single Field UI?
        if (isset($data['single_field'])) {
            $data['tags'] = explode('||', $data['single_field']);
        }

        ee()->cache->save('Tagger/FieldData/'.$this->field_id, $data, 500);

        if (!isset($data['tags']) || empty($data['tags'])) {
            return '';
        } else {
            return implode(',', $data['tags']);
        }
    }

    // ********************************************************************************* //

    /**
     * Handles any custom logic after an entry is saved.
     * Called after an entry is added or updated.
     * Available data is identical to save, but the settings array includes an entry_id.
     *
     * @param $data Contains the submitted field data. (Returned by save())
     * @return void
     */
    public function post_save($data)
    {
        // -----------------------------------------
        // Retrieve the cached item
        // -----------------------------------------
        $data = ee()->cache->get('Tagger/FieldData/'.$this->field_id);
        if ($data === false) return;

        // Delete it
        ee()->cache->delete('Tagger/FieldData/'.$this->field_id);

        // -----------------------------------------
        // Some Vars
        // -----------------------------------------
        $entry_id = $this->content_id();
        $channel_id = ee()->input->post('channel_id');
        $field_id = $this->field_id;
        $author_id = ee()->input->post('author') ? ee()->input->post('author') : ee()->session->userdata['member_id'];

        // -----------------------------------------
        // Grab all existing tag links
        // -----------------------------------------
        ee()->db->select('tag_id, rel_id');
        ee()->db->from('exp_tagger_links');
        ee()->db->where('entry_id', $entry_id);
        ee()->db->where('field_id', $field_id);
        $query = ee()->db->get();

        // -----------------------------------------
        // Load Settings
        // -----------------------------------------
        $conf = ee('tagger:Settings')->settings;

        // lowecase?
        $lc = ($conf['lowercase_tags'] == 'yes') ? TRUE : FALSE;

        // -----------------------------------------
        // Our array empty? Delete them all!!
        // -----------------------------------------
        if (isset($data['tags']) == FALSE OR empty($data['tags']) == TRUE)
        {
            foreach ($query->result() as $row)
            {
                // Delete tag association
                ee()->db->where('rel_id', $row->rel_id);
                ee()->db->delete('exp_tagger_links');

                // Update total_items
                ee()->db->set('total_entries', '(`total_entries` - 1)', FALSE);
                ee()->db->where('tag_id', $row->tag_id);
                ee()->db->where('site_id', $this->site_id);
                ee()->db->update('exp_tagger');
            }

            return;
        }

        // We Only Want Uniques
        $data['tags'] = array_unique($data['tags']);

        // -----------------------------------------
        // Store the ones we already have
        // -----------------------------------------
        $dbtags = array();

        foreach ($query->result() as $trow)
        {
            $dbtags[ $trow->rel_id ] = $trow->tag_id;
        }

        // -----------------------------------------
        // Loop over all assigned tags
        // -----------------------------------------
        foreach ($data['tags'] as $i => $tag)
        {
            // Format the tag
            $tag = ee()->tagger_helper->format_tag($tag);

            // No "empty" tags
            if ($tag == FALSE) continue;

            if ($lc == TRUE) $tag = mb_strtolower($tag, 'UTF-8');

            // -----------------------------------------
            // Does it already exist?
            // -----------------------------------------
            ee()->db->select('tag_id');
            ee()->db->from('exp_tagger');
            ee()->db->where('tag_name', $tag);
            ee()->db->where('site_id', $this->site_id);
            ee()->db->limit(1);
            $q2 = ee()->db->get();

            if ($q2->num_rows() == 0) $tag_id = ee()->tagger_helper->create_tag($tag);
            else $tag_id = $q2->row('tag_id');

            // -----------------------------------------
            // Is it already assigned (to this entry)
            // -----------------------------------------
            if (in_array($tag_id, $dbtags) == FALSE)
            {
                // -----------------------------------------
                // Data array for insert
                // -----------------------------------------
                $data = array(  'entry_id'  =>  $entry_id,
                                'channel_id'=>  $channel_id,
                                'field_id'  =>  $field_id,
                                'tag_id'    =>  $tag_id,
                                'site_id'   =>  $this->site_id,
                                'author_id' =>  $author_id,
                                'type'      =>  1,
                                'tag_order' =>  $i + 1
                        );

                // Insert
                ee()->db->insert('exp_tagger_links', $data);

                // -----------------------------------------
                // Update total_items
                // -----------------------------------------
                ee()->db->set('total_entries', '(`total_entries` + 1)', FALSE);
                ee()->db->where('tag_id', $tag_id);
                ee()->db->where('site_id', $this->site_id);
                ee()->db->update('exp_tagger');
            }
            else
            {
                // Get Rel_ID
                $rel_id = array_search($tag_id, $dbtags);

                // -----------------------------------------
                // Update
                // -----------------------------------------
                ee()->db->set('tag_order', $i + 1);
                ee()->db->where('rel_id', $rel_id);
                ee()->db->update('exp_tagger_links');

                // We need to unset the "dupe" tag
                unset($dbtags[$rel_id]);
            }

            // -----------------------------------------
            // Auto Assign Tags to group
            // -----------------------------------------
            if (isset($this->settings['tagger']['auto_assign_group']) === TRUE && $this->settings['tagger']['auto_assign_group'] > 0)
            {
                $group_id = $this->settings['tagger']['auto_assign_group'];

                // Does it already exists?
                $q = ee()->db->select('rel_id')->from('exp_tagger_groups_entries')->where('group_id', $group_id)->where('tag_id', $tag_id)->get();

                if ($q->num_rows() == 0)
                {
                    ee()->db->insert('tagger_groups_entries', array('tag_id' => $tag_id, 'group_id' => $group_id));
                }
            }
        }

        // -----------------------------------------
        // Remove Old Ones
        // -----------------------------------------
        foreach ($dbtags as $rel_id => $tag_id)
        {
            // -----------------------------------------
            // Delete tag association
            // -----------------------------------------
            ee()->db->where('rel_id', $rel_id);
            ee()->db->delete('exp_tagger_links');

            // -----------------------------------------
            // Update total_items
            // -----------------------------------------
            ee()->db->set('total_entries', '(`total_entries` - 1)', FALSE);
            ee()->db->where('tag_id', $tag_id);
            ee()->db->where('site_id', $this->site_id);
            ee()->db->update('exp_tagger');
        }

        return;
    }

    // ********************************************************************************* //

    /**
     * Handles any custom logic after an entry is deleted.
     * Called after one or more entries are deleted.
     *
     * @param $ids array is an array containing the ids of the deleted entries.
     * @return void
     */
    public function delete($ids)
    {
        foreach ($ids as $entry_id)
        {
            // Grab the Tag ID
            ee()->db->select('tag_id, rel_id');
            ee()->db->where('entry_id', $entry_id);
            ee()->db->where('type', 1);
            ee()->db->where('site_id', $this->site_id);
            $query = ee()->db->get('exp_tagger_links');

            foreach ($query->result() as $row)
            {
                // Delete tag association
                ee()->db->where('rel_id', $row->rel_id);
                ee()->db->delete('exp_tagger_links');

                // Update total_items
                ee()->db->set('total_entries', '(`total_entries` - 1)', FALSE);
                ee()->db->where('tag_id', $row->tag_id);
                ee()->db->where('site_id', $this->site_id);
                ee()->db->update('exp_tagger');
            }

            // Resources are not free
            $query->free_result();
        }
    }

    // ********************************************************************************* //

    /**
     * Replace the field tag on the frontend.
     *
     * @param $data mixed Contains the field data (or prepped data, if using pre_process)
     * @param $params array Contains field parameters (if any)
     * @param $tagdata string Contains data between tag (for tag pairs)
     * @return string
     */
    public function replace_tag($data, $params = array(), $tagdata = FALSE)
    {
        $data = explode(',', $data);

        // If no tagdata, return
        if ($tagdata == FALSE) return implode(', ', $data);

        // Have backspace?
        $backspace = (isset($params['backspace']) == TRUE) ? $params['backspace'] : 0;

        // Have prefix?
        $prefix = ((isset($params['prefix']) == TRUE && $params['prefix'] != FALSE) ? $params['prefix'] : 'tagger') . ':';

        $out = '';

        // Loop through the result
        foreach ($data as $tag)
        {
            $vars = array(  $prefix.'tag_name'      => $tag,
                            $prefix.'urlsafe_tagname' => ee()->tagger_helper->urlsafe_tag($tag),
                        );

            $out .= ee()->TMPL->parse_variables_row($tagdata, $vars);
        }

        // Apply Backspace
        $out = ($backspace > 0) ? substr($out, 0, - $backspace): $out;

        return $out;
    }

    // ********************************************************************************* //

    /**
     * Display the settings page. The default ExpressionEngine rows can be created using built in methods.
     * All of these take the current $data and the fieltype name as parameters:
     *
     * @param $data array
     * @access public
     * @return void
     */
    public function display_settings($data)
    {
        $settings = ee('tagger:Settings')->getFieldtypeSettings($this->field_id);

        // Get all groups
        $groups = ee('Model')->get('tagger:Group')->filter('site_id', $this->site_id)->order('group_title', 'asc')->all();

        // Create the options Array
        $options = array();
        $options['0'] = ee()->lang->line('tagger:select_group');

        foreach ($groups as $group) {
            $options[$group->group_id] = $group->group_title;
        }

        $fields = array(
            array(
                'title'  => lang('tagger:show_most_used'),
                'fields' => array(
                    'tagger[show_most_used]' => array(
                        'type'  => 'inline_radio',
                        'choices' => array(
                            'yes' => lang('yes'),
                            'no'  => lang('no'),
                        ),
                        'value' => $settings['show_most_used'],
                    )
                )
            ),
            array(
                'title'  => lang('tagger:single_field_input'),
                'fields' => array(
                    'tagger[single_field]' => array(
                        'type'  => 'inline_radio',
                        'choices' => array(
                            'yes' => lang('yes'),
                            'no'  => lang('no'),
                        ),
                        'value' => $settings['single_field'],
                    )
                )
            ),
            array(
                'title'  => lang('tagger:auto_assign_group'),
                'fields' => array(
                    'tagger[auto_assign_group]' => array(
                        'type'    => 'select',
                        'choices' => $options,
                        'value'   => $settings['auto_assign_group'],
                    )
                )
            )
        );

        return array('field_options_tagger' => array(
            'label'    => 'field_options',
            'group'    => 'tagger',
            'settings' => $fields
        ));
    }

    // ********************************************************************************* //
    /**
     * Save the fieldtype settings.
     *
     * @param $data array Contains the submitted settings for this field.
     * @access public
     * @return array
     */
    public function save_settings($data)
    {
        $post = ee('Request')->post('tagger');

        $settings = array();
        $settings['tagger'] = $post;

        // Force a wide field only if most used tags is enabled
        if ($post['show_most_used'] == 'yes') {
            $settings['field_wide'] = true;
        }

        return $settings;
    }

    // ********************************************************************************* //

    /**
     * Replace Tag - Replace the field tag on the frontend.
     *
     * @param  mixed   $data    contains the field data (or prepped data, if using pre_process)
     * @param  array   $params  contains field parameters (if any)
     * @param  boolean $tagdata contains data between tag (for tag pairs)
     * @return string           template data
     */
    public function replace_tag2($data, $params=array(), $tagdata = FALSE)
    {
        // Variable prefix
        $prefix = isset($params['params']) ? $params['params'] . ':' : 'tagger:';

        // We need an entry_id
        $entry_id = $this->row['entry_id'];
        $field_id = $this->field_id;
        $orderby_list       = array('tag_name', 'entry_date', 'hits', 'total_entries', 'tag_order');

        // -----------------------------------------
        // Some Params
        // -----------------------------------------
        $orderby = (isset($params['orderby']) === TRUE && in_array($params['orderby'], $orderby_list)) ? 't.'.$params['orderby'] : 'tl.tag_order';
        $limit = isset($params['limit']) ? $params['limit'] : 99;
        $sort = (isset($params['backspace']) === TRUE && $params['backspace'] == 'desc' ) ? 'DESC': 'ASC';
        $backspace = isset($params['backspace']) ? $params['backspace'] : 0;

        // -----------------------------------------
        // Start SQL
        // -----------------------------------------
        ee()->db->select('t.*');
        ee()->db->from('exp_tagger t');
        ee()->db->join('exp_tagger_links tl', 'tl.tag_id = t.tag_id', 'left');
        ee()->db->where('tl.entry_id', $entry_id);
        ee()->db->where('tl.field_id', $field_id);
        ee()->db->where('tl.type', 1);
        ee()->db->order_by($orderby, $sort);
        ee()->db->limit($limit);
        $query = ee()->db->get();

        // -----------------------------------------
        // No Tags?
        // -----------------------------------------
        if ($query->num_rows() == 0)
        {
            ee()->TMPL->log_item('TAGGER: No tags found.');
            return ee()->tagger_helper->custom_no_results_conditional($prefix.'no_tags', $tagdata);
        }

        $out = '';
        $count = 0;
        $total = $query->num_rows();

        // -----------------------------------------
        // Loop through all tags
        // -----------------------------------------
        foreach ($query->result() as $row)
        {
            $count++;
            $vars = array(  $prefix.'tag_name'      => $row->tag_name,
                            $prefix.'urlsafe_tagname' => ee()->tagger_helper->urlsafe_tag($row->tag_name),
                            $prefix.'unitag'        => ee()->tagger_helper->unitag($row->tag_id, $row->tag_name),
                            $prefix.'total_hits'    => $row->hits,
                            $prefix.'total_items'   => $row->total_entries,
                            $prefix.'count'         => $count,
                            $prefix.'total_tags'    => $total,
                        );

            $out .= ee()->TMPL->parse_variables_row($tagdata, $vars);
        }

        // Apply Backspace
        $out = ($backspace > 0) ? substr($out, 0, - $backspace): $out;

        // Resources are not free
        $query->free_result();

        return $out;
    }

    // ********************************************************************************* //


}

/* End of file ft.tagger.php */
/* Location: ./system/user/addons/tagger/ft.tagger.php */