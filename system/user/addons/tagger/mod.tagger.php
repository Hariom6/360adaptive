<?php if (!defined('BASEPATH')) die('No direct script access allowed');

/**
 * Tagger Module Tag Methods
 *
 * @package         DevDemon_Tagger
 * @author          DevDemon <http://www.devdemon.com> - Lead Developer @ Parscale Media
 * @copyright       Copyright (c) 2007-2016 DevDemon <http://www.devdemon.com>
 * @license         http://www.devdemon.com/license/
 * @link            http://www.devdemon.com
 * @see             https://ellislab.com/expressionengine/user-guide/development/modules.html
 */
class Tagger
{
    /**
     * Allowed Order by keywords
     *
     * @access private
     * @var array
     */
    private $orderby_list       = array('tag_name', 'entry_date', 'hits', 'total_entries', 'tag_order');

    /**
     * Constructor
     *
     * @access public
     * @return void
     */
    public function __construct()
    {
        $this->EE =& get_instance();
        $this->site_id = ee()->config->item('site_id');
        ee()->load->library('tagger_helper');
        ee()->load->helper('string');
    }

    // ********************************************************************************* //

    /**
     * Display a list of tags
     *
     * @access public
     * @return string
     */
    public function tags()
    {
        // Variable prefix
        $prefix = ee()->TMPL->fetch_param('prefix', 'tagger') . ':';

        // -----------------------------------------
        // We need an entry_id
        // -----------------------------------------
        $entry_id = ee()->tagger_helper->get_entry_id_from_param();

        if (! $entry_id) {
            ee()->TMPL->log_item('TAGGER: Entry ID could not be resolved');
            return ee()->tagger_helper->custom_no_results_conditional($prefix.'no_tags', ee()->TMPL->tagdata);
        }

        // -----------------------------------------
        // Some Params
        // -----------------------------------------
        $orderby = (in_array(ee()->TMPL->fetch_param('orderby'), $this->orderby_list)) ? 't.'.ee()->TMPL->fetch_param('orderby'): 'tl.tag_order';
        $limit = (ee()->tagger_helper->is_natural_number(ee()->TMPL->fetch_param('limit')) != FALSE) ? ee()->TMPL->fetch_param('limit') : 30;
        $sort = (ee()->TMPL->fetch_param('sort') == 'desc' ) ? 'DESC': 'ASC';
        $backspace = (ee()->tagger_helper->is_natural_number(ee()->TMPL->fetch_param('backspace')) === TRUE) ? ee()->TMPL->fetch_param('backspace') : 0;

        // Get Fields
        $field_id = ee()->tagger_helper->get_fields_from_params(ee()->TMPL->tagparams);

        // -----------------------------------------
        // Start SQL
        // -----------------------------------------
        ee()->db->select('t.*');
        ee()->db->from('exp_tagger t');
        ee()->db->join('exp_tagger_links tl', 'tl.tag_id = t.tag_id', 'left');
        ee()->db->where('tl.entry_id', $entry_id);
        ee()->db->where('tl.type', 1);

        // Field ID
        if ($field_id !== FALSE)
        {
            if (is_array($field_id) === TRUE)
            {
                ee()->db->where_in('tl.field_id', $field_id);
            }
            else
            {
                ee()->db->where('tl.field_id', $field_id);
            }
        }

        ee()->db->order_by($orderby, $sort);
        ee()->db->limit($limit);
        $query = ee()->db->get();

        // -----------------------------------------
        // No Tags?
        // -----------------------------------------
        if ($query->num_rows() == 0)
        {
            ee()->TMPL->log_item('TAGGER: No tags found.');
            return ee()->tagger_helper->custom_no_results_conditional($prefix.'no_tags', ee()->TMPL->tagdata);
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

            $out .= ee()->TMPL->parse_variables_row(ee()->TMPL->tagdata, $vars);
        }

        // Apply Backspace
        $out = ($backspace > 0) ? substr($out, 0, - $backspace): $out;

        return $out;
    }

    // ********************************************************************************* //

    /**
     * Related Tags
     *
     * @access public
     * @return string
     */
    public function related()
    {
        // Variable prefix
        $this->prefix = ee()->TMPL->fetch_param('prefix', 'tagger') . ':';

        // -----------------------------------------
        // What Entry ID?
        // -----------------------------------------
        $entry_id = FALSE;

        if (ee()->TMPL->fetch_param('entry_id') != FALSE && ee()->tagger_helper->is_natural_number(ee()->TMPL->fetch_param('entry_id')) != FALSE)
        {
            $entry_id = ee()->TMPL->fetch_param('entry_id');
        }
        elseif (ee()->TMPL->fetch_param('url_title') != FALSE)
        {
            $query = ee()->db->select('entry_id')->from('exp_channel_titles')->where('url_title', ee()->TMPL->fetch_param('url_title'))->limit(1)->get();
            if ($query->num_rows() > 0) $entry_id = $query->row('entry_id');
        }

        // -----------------------------------------
        // Nothing Found?
        // -----------------------------------------
        if ($entry_id == FALSE)
        {
            ee()->TMPL->log_item('TAGGER: Entry ID could not be resolved');
            return ee()->tagger_helper->custom_no_results_conditional($this->prefix.'no_entries', ee()->TMPL->tagdata);
        }

        // -----------------------------------------
        // Lets start on the SQL then.
        // -----------------------------------------
        ee()->db->select('t.*');
        ee()->db->from('exp_tagger t');
        ee()->db->join('exp_tagger_links tl', 'tl.tag_id = t.tag_id', 'left');
        ee()->db->where('tl.entry_id', $entry_id);
        ee()->db->where('tl.type', 1);
        $query = ee()->db->get();

        // -----------------------------------------
        // No Tags
        // -----------------------------------------
        if ($query->num_rows() == 0)
        {
            ee()->TMPL->log_item('TAGGER: No tags found.');
            return ee()->tagger_helper->custom_no_results_conditional($this->prefix.'no_entries', ee()->TMPL->tagdata);
        }


        // -----------------------------------------
        // Loop through the result
        // -----------------------------------------
        $tags = array();

        foreach ($query->result() as $row)
        {
            $tags[] = $row->tag_name;
        }

        // For Re-Use Later
        $this->tags = $tags;
        $this->skip_entry = $entry_id;

        return $this->entries();
    }

    // ********************************************************************************* //

    /**
     * Grouped Tags
     *
     * @access public
     * @return string
     */
    public function groups()
    {
        // Variable prefix
        $this->prefix = ee()->TMPL->fetch_param('prefix', 'tagger') . ':';

        $groups = NULL;

        // -----------------------------------------
        // Which Groups
        // -----------------------------------------
        if (ee()->TMPL->fetch_param('groups') != FALSE)
        {
            $group = ee()->TMPL->fetch_param('groups');

            // Multiple Groups?
            if (strpos($group, '|') !== FALSE)
            {
                $group = explode('|', $group);
                $groups = array();

                foreach ($group as $name)
                {
                    $groups[] = $name;
                }
            }
            else
            {
                $groups = ee()->TMPL->fetch_param('groups');
            }
        }


        // Grab group ids
        ee()->db->select('group_id, group_name, group_title, group_desc');
        ee()->db->from('exp_tagger_groups');
        if (is_array($groups) == TRUE) ee()->db->where_in('group_name', $groups);
        else if (is_string($groups) == TRUE) ee()->db->where('group_name', $groups);

        // -----------------------------------------
        // Order By & Sort
        // -----------------------------------------
        $sort = 'asc';
        if (ee()->TMPL->fetch_param('sort') == 'desc') $sort = 'desc';

        switch (ee()->TMPL->fetch_param('orderby'))
        {
            case 'group_title' :
                ee()->db->order_by('group_title', $sort);
            default :
                ee()->db->order_by('group_title', $sort);
        }

        $query = ee()->db->get();

        // No Groups?
        if ($query->num_rows() == 0)
        {
            ee()->TMPL->log_item('TAGGER: No Groups Could be Found');
            return ee()->tagger_helper->custom_no_results_conditional($this->prefix.'no_groups', ee()->TMPL->tagdata);
        }

        // Harvest them
        $groups = array();
        $group_ids = array();
        foreach ($query->result() as $row)
        {
            $groups[$row->group_id] = $row;
            $group_ids[] = $row->group_id;
        }

        // Resources are not free
        $query->free_result();

        // Lets grab all tags within those groups
        ee()->db->select('tge.tag_id, tge.group_id, t.tag_name')
                ->from('tagger_groups_entries tge')->join('exp_tagger t', 'tge.tag_id = t.tag_id', 'left')
                ->where('t.tag_id', 'tge.tag_id', FALSE)
                ->where_in('tge.group_id', $group_ids);

        // -----------------------------------------
        // Order By & Sort
        // -----------------------------------------
        $sort = 'asc';
        if (ee()->TMPL->fetch_param('tag_sort') == 'desc') $sort = 'desc';

        switch (ee()->TMPL->fetch_param('tag_orderby'))
        {
            case 'tag_name' :
                ee()->db->order_by('t.tag_name', $sort);
            default :
                ee()->db->order_by('t.tag_name', $sort);
        }

        $query = ee()->db->get();


        // Add them to the groups array
        foreach ($query->result() as $row)
        {
            if (isset($groups[$row->group_id]->tags) == FALSE) $groups[$row->group_id]->tags = array($row);
            else $groups[$row->group_id]->tags[] = $row;
        }


        // Grab Relations Tagdata
        $tags_tagdata = ee()->tagger_helper->fetch_data_between_var_pairs($this->prefix.'tags', ee()->TMPL->tagdata);


        $out = '';

        // -----------------------------------------
        // Loop through all groups
        // -----------------------------------------
        foreach ($groups as $group_id => $group)
        {
            $gtemp = '';

            // Parse Group Info
            $vars = array();
            $vars[$this->prefix.'group_title']      = $group->group_title;
            $vars[$this->prefix.'group_name']       = $group->group_name;
            $vars[$this->prefix.'group_desc']       = $group->group_desc;

            // Replace all group info
            $gtemp = ee()->TMPL->parse_variables_row(ee()->TMPL->tagdata, $vars);


            // Any relations from this group?
            if (isset($group->tags) == FALSE OR empty($group->tags) == TRUE)
            {
                ee()->TMPL->log_item('TAGGER: No Tags in Group: ' . $group->group_title);
                $temp = ee()->tagger_helper->custom_no_results_conditional($this->prefix.'no_tags', $tags_tagdata);
                $gtemp = ee()->tagger_helper->swap_var_pairs($this->prefix.'tags', $temp, $gtemp);
                $out .= $gtemp;
                continue;
            }

            $inner_final = '';

            // -----------------------------------------
            // Loop through all tags and parse
            // -----------------------------------------
            foreach ($group->tags as $tagcount => $tag)
            {
                $vars = array();
                $vars[$this->prefix.'tag_name']         = $tag->tag_name;
                $vars[$this->prefix.'urlsafe_tagname']  = ee()->tagger_helper->urlsafe_tag($tag->tag_name);


                $inner_final .= ee()->TMPL->parse_variables_row($tags_tagdata, $vars);
            }

            $gtemp = ee()->tagger_helper->swap_var_pairs($this->prefix.'tags', $inner_final, $gtemp);

            $out .= $gtemp;
        }


        // Apply Backspace
        //$out = ($backspace > 0) ? substr($out, 0, - $backspace): $out;



        return $out;
    }

    // ********************************************************************************* //

    public function ungrouped_tags()
    {
        // Variable prefix
        $prefix = ee()->TMPL->fetch_param('prefix', 'tagger') . ':';

        // Some Params
        $orderby = (in_array(ee()->TMPL->fetch_param('orderby'), $this->orderby_list)) ? 't.'.ee()->TMPL->fetch_param('orderby'): 't.tag_name';
        $limit = (ee()->tagger_helper->is_natural_number(ee()->TMPL->fetch_param('limit')) != FALSE) ? ee()->TMPL->fetch_param('limit') : 30;
        $sort = (ee()->TMPL->fetch_param('sort') == 'desc' ) ? 'DESC': 'ASC';
        $backspace = (ee()->tagger_helper->is_natural_number(ee()->TMPL->fetch_param('backspace')) === TRUE) ? ee()->TMPL->fetch_param('backspace') : 0;

        // Lets start on the SQL then.
        ee()->db->select('t.*');
        ee()->db->from('exp_tagger t');
        ee()->db->join('exp_tagger_groups_entries tge', 'tge.tag_id = t.tag_id', 'left outer');
        ee()->db->where('tge.rel_id is null');
        ee()->db->order_by($orderby, $sort);
        ee()->db->limit($limit);
        $query = ee()->db->get();

        // No tags?
        if ($query->num_rows() == 0)
        {
            ee()->TMPL->log_item('TAGGER: No tags found.');
            return ee()->tagger_helper->custom_no_results_conditional($prefix.'no_tags', ee()->TMPL->tagdata);
        }

        $out = '';
        $count = 0;
        $total = $query->num_rows();

        // Loop through the result
        foreach ($query->result() as $row)
        {
            $count++;
            $vars = array(  $prefix.'tag_name'      => $row->tag_name,
                            $prefix.'urlsafe_tagname' => ee()->tagger_helper->urlsafe_tag($row->tag_name),
                            $prefix.'total_hits'    => $row->hits,
                            $prefix.'total_items'   => $row->total_entries,
                            $prefix.'count'         => $count,
                            $prefix.'total_tags'    => $total,
                        );

            $out .= ee()->TMPL->parse_variables_row(ee()->TMPL->tagdata, $vars);
        }

        // Apply Backspace
        $out = ($backspace > 0) ? substr($out, 0, - $backspace): $out;

        // Resources are not free
        $query->free_result();

        return $out;
    }

    // ********************************************************************************* //

    /**
     * Generate a Tag Cloud
     *
     * @return string - The Tag Cloud
     */
    public function cloud()
    {
        // -----------------------------------------
        // Parameters
        // -----------------------------------------
        $params = array();
        $params['rankby']   = ee()->TMPL->fetch_param('rankby', 'total_entries');
        $params['max_size'] = (ee()->tagger_helper->is_natural_number(ee()->TMPL->fetch_param('max_size')))  ? ee()->TMPL->fetch_param('max_size'): 32;
        $params['min_size'] = (ee()->tagger_helper->is_natural_number(ee()->TMPL->fetch_param('max_size')))  ? ee()->TMPL->fetch_param('min_size'): 12;
        $params['orderby']  = (in_array(ee()->TMPL->fetch_param('orderby'), array('tag_name', 'random', 'total_entries'))) ? ee()->TMPL->fetch_param('orderby'): 'tag_name';
        $params['sort']     = (ee()->TMPL->fetch_param('sort') == 'asc' ) ? 'ASC': 'DESC';
        $params['limit']    = (ee()->tagger_helper->is_natural_number(ee()->TMPL->fetch_param('limit')) != FALSE) ? ee()->TMPL->fetch_param('limit') : 50;
        $params['backspace'] = (ee()->tagger_helper->is_natural_number(ee()->TMPL->fetch_param('backspace')) === TRUE) ? ee()->TMPL->fetch_param('backspace') : 0;
        $prefix = ee()->TMPL->fetch_param('prefix', 'tagger') . ':';

        // -----------------------------------------
        // The SQL
        // -----------------------------------------
        ee()->db->select('tg.tag_id, tg.tag_name, tg.hits, tg.total_entries, COUNT(tl.tag_id) as count', FALSE);
        ee()->db->from('exp_tagger tg');
        ee()->db->join('exp_tagger_links tl', 'tl.tag_id = tg.tag_id', 'left');
        ee()->db->where('tg.total_entries >', 0);
        ee()->db->limit($params['limit']);
        ee()->db->group_by('tg.tag_id');


        // -----------------------------------------
        // Only From Certain Groups?
        // -----------------------------------------
        if (ee()->TMPL->fetch_param('groups') != FALSE)
        {
            $group = ee()->TMPL->fetch_param('groups');

            // Added By Loren
            $group_not = FALSE;
            if (strncmp($group, 'not', 3) == 0)
            {
                $group_not = TRUE;
                $group = trim(substr($group, 3));
            }
            // End Addition

            // Added by Loren
            if ($group_not) {
                // We need to include the null rows for not
                ee()->db->join('exp_tagger_groups_entries tge', 'tge.tag_id = tg.tag_id', 'left');
            } else {
                ee()->db->join('exp_tagger_groups_entries tge', 'tge.tag_id = tg.tag_id', 'inner');
            }
            // End Addition

            // -----------------------------------------
            // Multiple Groups?
            // -----------------------------------------
            if (strpos($group, '|') !== FALSE)
            {
                $group = explode('|', $group);
                $groups = array();

                foreach ($group as $name)
                {
                    $groups[] = "'".$name."'";
                }
            }
            else
            {
                // Added by Loren (removed refetching the template parameter
                $groups = array("'".$group."'");
                // End Addition
            }

            // -----------------------------------------
            // Grab group id's
            // -----------------------------------------

            // Added by Loren (added in a site_id check)
            $temp = ee()->db->query('SELECT group_id FROM exp_tagger_groups WHERE group_name IN ('.implode(',', $groups).') AND site_id IN (' . implode(',', ee()->TMPL->site_ids) . ')');
            // End Addition

            $groups = array();

            // -----------------------------------------
            // No Group? Quit
            // -----------------------------------------
            if ($temp->num_rows() == 0)
            {
                ee()->TMPL->log_item('TAGGER: No groups found.');
                ee()->db->_reset_select();
                return ee()->tagger_helper->custom_no_results_conditional($prefix.'no_tags', ee()->TMPL->tagdata);
            }

            // -----------------------------------------
            // Harvest Group Ids
            // -----------------------------------------
            foreach ($temp->result() as $row)
            {
                $groups[] = $row->group_id;
            }

            $temp->free_result();

            // Added by Loren
            if ($group_not) {
                // Needs to be wrapped in parenthesis and select null
                ee()->db->where('(tge.group_id NOT IN ('.implode(',', $groups).') or tge.group_id IS NULL)');
            } else {
                ee()->db->where_in('tge.group_id', $groups);
            }
            // End Addition

        }

        // -----------------------------------------
        // Only From Certain Channels
        // -----------------------------------------
        if (ee()->TMPL->fetch_param('channel') != FALSE)
        {
            $channels = ee()->TMPL->fetch_param('channel');

            // -----------------------------------------
            // Multiple Channels?
            // -----------------------------------------
            if (strpos($channels, '|') !== FALSE)
            {
                $temp = explode('|', $channels);
                $channels = array();

                foreach ($temp as $name) $channels[] = "'" . $name . "'";

                // Grab IDS
                $temp = ee()->db->query('SELECT channel_id FROM exp_channels WHERE channel_name IN (' . implode(',', $channels) . ') AND site_id IN (' . implode(',', ee()->TMPL->site_ids) . ')' );

                if ($temp->num_rows() > 0)
                {
                    $channels = array();
                    foreach($temp->result() as $row) $channels[] = $row->channel_id;

                    // Add to MAIN SQL
                    ee()->db->where_in('tl.channel_id', $channels);
                }

            }

            // -----------------------------------------
            // Single Channel then
            // -----------------------------------------
            else
            {
                // Grab ID
                $temp = ee()->db->query("SELECT channel_id FROM exp_channels WHERE channel_name = '{$channels}' AND site_id IN (" . implode(',', ee()->TMPL->site_ids) . ") LIMIT 1");

                if ($temp->num_rows() > 0)
                {
                    // Add to MAIN SQL
                    ee()->db->where('tl.channel_id', $temp->row('channel_id'));
                }
            }

        }


        // -----------------------------------------
        // Rank By
        // -----------------------------------------
        switch ($params['rankby'])
        {
            case 'entries':
                $params['rankby'] = 'tg.total_entries';
                break;
            case 'hits':
                $params['rankby'] = 'tg.hits';
                break;
            default: $params['rankby'] = 'tg.total_entries';
        }

        ee()->db->order_by($params['rankby'], 'DESC');

        // Grab it!
        $query = ee()->db->get();

        // -----------------------------------------
        // No Tags?
        // -----------------------------------------
        if ($query->num_rows() == 0)
        {
            ee()->TMPL->log_item('TAGGER: No tags found.');
            ee()->db->_reset_select();
            return ee()->tagger_helper->custom_no_results_conditional($prefix.'no_tags', ee()->TMPL->tagdata);
        }

        // Lets make a new array, actually 2
        $tags = array();
        $tag_info = array();

        foreach ($query->result() as $row)
        {
            $tags[ $row->tag_name ] = $row->count;
            $tag_info[ $row->tag_name ] = $row;
        }

        // largest and smallest array values
        $max_qty = max(array_values($tags));
        $min_qty = min(array_values($tags));

        // find the range of values
        $spread = $max_qty - $min_qty;
        if ($spread == 0) $spread = 1; // we don't want to divide by zero

        // set the font-size increment
        $step = ($params['max_size'] - $params['min_size']) / ($spread);

        // -----------------------------------------
        // Orderby
        // -----------------------------------------
        if ($params['orderby'] == 'random')
        {
            $tags = ee()->tagger_helper->shuffle_assoc($tags);
        }
        elseif ($params['orderby'] == 'tag_name')
        {
            if ($params['sort'] == 'ASC')
            {
                $temp = $tags; // Create Temp arr
                $keys = array_keys($temp); // Get the Keys
                natcasesort($keys); // Sort Them
                $tags = array(); // Empty the Tags array (create new array)

                foreach ($keys as $k) $tags[$k] = $temp[$k]; // Loop through the sorted array and fill
                unset($temp, $keys); // Remove temp stuff
                /*
                $tags = array_flip();
                natcasesort($tags);
                $tags = array_flip($tags);
                */
            }
            else krsort($tags);
        }
        elseif ($params['orderby'] == 'total_entries')
        {
            if ($params['sort'] == 'ASC') asort($tags);
            else arsort($tags);;
        }

        // -----------------------------------------
        // Loop through the results
        // -----------------------------------------

        $out = '';
        $count = 0;
        $total = $query->num_rows();

        // Loop through the results
        foreach ($tags as $tag => $value)
        {
            $count++;

            // calculate font-size
            // find the $value in excess of $min_qty
            // multiply by the font-size increment ($size)
            // and add the $params['min_size'] set above

            $vars = array(  $prefix.'tag_name'      => $tag,
                            $prefix.'urlsafe_tagname' => ee()->tagger_helper->urlsafe_tag($tag),
                            $prefix.'unitag'        => ee()->tagger_helper->unitag($tag_info[$tag]->tag_id, $tag),
                            $prefix.'size'  => round($params['min_size'] + (($value - $min_qty) * $step)),
                            $prefix.'total_items'   => $value,
                            $prefix.'count'         => $count,
                            $prefix.'total_tags'    => $total,
                        );

            $out .= ee()->TMPL->parse_variables_row(ee()->TMPL->tagdata, $vars);
        }

        // Apply Backspace
        $out = ($params['backspace'] > 0) ? substr($out, 0, - $params['backspace']): $out;

        // Resources are not free
        $query->free_result();

        return $out;
    }

    // ********************************************************************************* //

    public function entries()
    {
        // Variable prefix
        $prefix = ee()->TMPL->fetch_param('prefix', 'tagger') . ':';

        $tags = ee()->TMPL->fetch_param('tag');
        $unitag = ee()->TMPL->fetch_param('unitag');

        // We need to keep in mind that related() also calls this function
        if ( isset($this->tags) == TRUE)
        {
            $tags = implode('|', $this->tags);

        }

        // -----------------------------------------
        // Parse Unitag
        // -----------------------------------------
        $tag_id = FALSE;
        if ($unitag != FALSE)
        {
            $tags = FALSE;

            $unitag = explode('-', $unitag);
            if (is_numeric($unitag[0]) == TRUE)
            {
                $tag_id = $unitag[0];
            }
        }

        // -----------------------------------------
        // No Tags?
        // -----------------------------------------
        if ($tags == FALSE && $tag_id == FALSE)
        {
            ee()->db->_reset_select();
            ee()->TMPL->log_item('TAGGER: No missing tag/unitag parameter');
            return ee()->tagger_helper->custom_no_results_conditional($prefix.'no_entries', ee()->TMPL->tagdata);
        }

        //----------------------------------------
        // Pagination Enabled?
        //----------------------------------------
        if (preg_match('/'.LD."{$prefix}paginate(.*?)".RD."(.+?)".LD.'\/'."{$prefix}paginate".RD."/s", ee()->TMPL->tagdata, $match))
        {
            $paginate = TRUE;
        }
        else
        {
            $paginate = FALSE;
        }

        // -----------------------------------------
        // Multiple Tags? & Decode them!
        // -----------------------------------------
        if ($tags != FALSE)
        {
            $temp = $tags;

            // Multiple Tags?
            if (strpos($temp, '|') !== FALSE)
            {
                $temp = explode('|', $temp);
                $tags = array();

                foreach ($temp as $name)
                {
                    $tags[] = ee()->tagger_helper->urlsafe_tag($name, FALSE);
                }
            }
            else
            {
                $tags = ee()->tagger_helper->urlsafe_tag($temp, FALSE);
            }
        }

        $limit = (ee()->tagger_helper->is_natural_number(ee()->TMPL->fetch_param('limit')) != FALSE) ? ee()->TMPL->fetch_param('limit') : 30;
        $offset = (ee()->tagger_helper->is_natural_number(ee()->TMPL->fetch_param('offset')) != FALSE) ? ee()->TMPL->fetch_param('offset') : 0;
        $backspace = (ee()->tagger_helper->is_natural_number(ee()->TMPL->fetch_param('backspace')) === TRUE) ? ee()->TMPL->fetch_param('backspace') : 0;

        // -----------------------------------------
        // Custom Fields?
        // -----------------------------------------
        if (ee()->TMPL->fetch_param('custom_fields') != FALSE)
        {
            $fields = explode( '|', ee()->TMPL->fetch_param('custom_fields') );
            $query = ee()->db->select('field_id, field_name')->from('exp_channel_fields')->where_in('field_name', $fields)->get();

            $fields = array();

            foreach ($query->result() as $row)
            {
                $fields[ $row->field_id ] = $row->field_name;
            }
        }


        // -----------------------------------------
        //  Grab all entries with these tags
        // -----------------------------------------
        ee()->db->select('tl.entry_id, tl.tag_id, ct.title, ct.url_title, ct.entry_date, ct.channel_id, m.username, m.member_id, m.screen_name');
        ee()->db->from('exp_tagger_links tl');
        ee()->db->join('exp_tagger t', 't.tag_id = tl.tag_id', 'left');
        ee()->db->join('exp_channel_titles ct', 'ct.entry_id = tl.entry_id', 'left');
        ee()->db->join('exp_members m', 'ct.author_id = m.member_id', 'left');

        // -----------------------------------------
        //  Any Fields?
        // -----------------------------------------
        if ( isset($fields) == TRUE AND is_array($fields) == TRUE )
        {
            ee()->db->join('exp_channel_data cd', 'cd.entry_id = ct.entry_id', 'left');

            foreach ($fields as $key => $val)
            {
                ee()->db->select("cd.field_id_{$key}");
            }
        }

        // -----------------------------------------
        // Limit By Channel
        // -----------------------------------------
        if (ee()->TMPL->fetch_param('channel') != FALSE)
        {
            $channels = ee()->TMPL->fetch_param('channel');

            // -----------------------------------------
            // Multiple Channels?
            // -----------------------------------------
            if (strpos($channels, '|') !== FALSE)
            {
                $temp = explode('|', $channels);
                $channels = array();

                foreach ($temp as $name) $channels[] = "'" . $name . "'";

                // Grab IDS
                $temp = ee()->db->query('SELECT channel_id FROM exp_channels WHERE channel_name IN (' . implode(',', $channels) . ') AND site_id IN (' . implode(',', ee()->TMPL->site_ids) . ') ');

                if ($temp->num_rows() > 0)
                {
                    $channels = array();
                    foreach($temp->result() as $row) $channels[] = $row->channel_id;

                    // Add to MAIN SQL
                    ee()->db->where_in('ct.channel_id', $channels);
                }

            }

            // -----------------------------------------
            // Single Channel then
            // -----------------------------------------
            else
            {
                // Grab ID
                $temp = ee()->db->query("SELECT channel_id FROM exp_channels WHERE channel_name = '{$channels}' AND site_id IN (" . implode(',', ee()->TMPL->site_ids) . ") LIMIT 1");

                if ($temp->num_rows() > 0)
                {
                    // Add to MAIN SQL
                    ee()->db->where('ct.channel_id', $temp->row('channel_id'));
                }
            }
        }

        // -----------------------------------------
        //  Limit by tags?
        // -----------------------------------------
        if ($tags != FALSE)
        {
            if (is_array($tags) == TRUE) ee()->db->where_in('t.tag_name', $tags);
            else ee()->db->where('t.tag_name', $tags);
        }

        // -----------------------------------------
        //  Limit By Tag IDS
        // -----------------------------------------
        if ($tag_id != FALSE)
        {
            if (is_array($tag_id) == TRUE) ee()->db->where_in('t.tag_id', $tag_id);
            else ee()->db->where('t.tag_id', $tag_id);
        }

        // -----------------------------------------
        //  Entry Status
        // -----------------------------------------
        if (ee()->TMPL->fetch_param('status') != FALSE)
        {
            $status = explode('|', ee()->TMPL->fetch_param('status'));
            ee()->db->where_in('ct.status', $status);
        }
        else
        {
            ee()->db->where('ct.status', 'open');
        }

        // -----------------------------------------
        //  Show Future Entries
        // -----------------------------------------
        if (ee()->TMPL->fetch_param('show_future_entries') != 'yes')
        {
            ee()->db->where('ct.entry_date <', ee()->localize->now);
        }

        // -----------------------------------------
        // Should we skip an entry?
        // -----------------------------------------
        if (isset($this->skip_entry) == TRUE) ee()->db->where('tl.entry_id !=', $this->skip_entry);

        // -----------------------------------------
        // Order By
        // -----------------------------------------
        $sort = (ee()->TMPL->fetch_param('sort') == 'asc' ) ? 'ASC': 'DESC';

        switch (ee()->TMPL->fetch_param('orderby'))
        {
            case 'entry_title':
                ee()->db->order_by('ct.title', $sort);
                break;
            case 'random':
                ee()->db->order_by('RAND()', FALSE);
                break;
            default:
                ee()->db->order_by('ct.entry_date', $sort);
                break;
        }



        // Group BY :)
        ee()->db->group_by('tl.entry_id');

        //----------------------------------------
        // Pagination
        //----------------------------------------
        if ($paginate == TRUE)
        {
            // Pagination variables
            $paginate_data  = $match['2'];
            $current_page   = 0;
            $total_pages    = 1;
            $qstring        = ee()->uri->query_string;
            $uristr         = ee()->uri->uri_string;
            $pagination_links = '';
            $page_previous = '';
            $page_next = '';

            // Get total Count!
            $sql = ee()->db->query(ee()->db->_compile_select('SELECT COUNT(*)'));
            $total = $sql->num_rows();
            $sql->free_result(); unset($sql);


            // We need to strip the page number from the URL for two reasons:
            // 1. So we can create pagination links
            // 2. So it won't confuse the query with an improper proper ID

            if (preg_match("#(^|/)TG(\d+)(/|$)#", $qstring, $match))
            {
                $current_page = $match['2'];
                $uristr  = reduce_double_slashes(str_replace($match['0'], '/', $uristr));
                $qstring = trim(reduce_double_slashes(str_replace($match['0'], '/', $qstring)), '/');
            }

            // Remove the {paginate}
            ee()->TMPL->tagdata = preg_replace("/".LD."{$prefix}paginate.*?".RD.".+?".LD.'\/'."{$prefix}paginate".RD."/s", "", ee()->TMPL->tagdata);

            // What is the current page?
            $current_page = ($current_page == '' OR ($limit > 1 AND $current_page == 1)) ? 0 : $current_page;

            if ($current_page > $total)
            {
                $current_page = 0;
            }

            $t_current_page = floor(($current_page / $limit) + 1);
            $total_pages    = intval(floor($total / $limit));

            if ($total % $limit) $total_pages++;

            if ($total > $limit)
            {
                ee()->load->library('pagination');

                $deft_tmpl = '';

                if ($uristr == '')
                {
                    if (ee()->config->item('template_group') == '')
                    {
                        $query = ee()->db->query("SELECT group_name FROM template_groups WHERE is_site_default = 'y' ");
                        $deft_tmpl = $query->row('group_name') .'/index';
                    }
                    else
                    {
                        $deft_tmpl  = ee()->config->item('template_group').'/';
                        $deft_tmpl .= (ee()->config->item('template') == '') ? 'index' : ee()->config->item('template');
                    }
                }

                $basepath = reduce_double_slashes(ee()->functions->create_url($uristr, FALSE).'/'.$deft_tmpl);

                if (ee()->TMPL->fetch_param('paginate_base'))
                {
                    // Load the string helper
                    ee()->load->helper('string');

                    $pbase = trim_slashes(ee()->TMPL->fetch_param('paginate_base'));

                    $pbase = str_replace("/index", "/", $pbase);

                    if ( ! strstr($basepath, $pbase))
                    {
                        $basepath = reduce_double_slashes($basepath.'/'.$pbase);
                    }
                }

                // Load Language
                ee()->lang->loadfile('tagger');

                $config['first_url']    = rtrim($basepath, '/');
                $config['base_url']     = $basepath;
                $config['prefix']       = 'TG';
                $config['total_rows']   = $total;
                $config['per_page']     = $limit;
                $config['cur_page']     = $current_page;
                $config['suffix']       = '';
                $config['first_link']   = ee()->lang->line('tagger:pag_first_link');
                $config['last_link']    = ee()->lang->line('tagger:pag_last_link');
                $config['full_tag_open']        = '<span class="tg_paginate_links">';
                $config['full_tag_close']       = '</span>';
                $config['first_tag_open']       = '<span class="tg_paginate_first">';
                $config['first_tag_close']      = '</span>&nbsp;';
                $config['last_tag_open']        = '&nbsp;<span class="tg_paginate_last">';
                $config['last_tag_close']       = '</span>';
                $config['cur_tag_open']         = '&nbsp;<strong class="tg_paginate_current">';
                $config['cur_tag_close']        = '</strong>';
                $config['next_tag_open']        = '&nbsp;<span class="tg_paginate_next">';
                $config['next_tag_close']       = '</span>';
                $config['prev_tag_open']        = '&nbsp;<span class="tg_paginate_prev">';
                $config['prev_tag_close']       = '</span>';
                $config['num_tag_open']         = '&nbsp;<span class="tg_paginate_num">';
                $config['num_tag_close']        = '</span>';

                // Allows $config['cur_page'] to override
                $config['uri_segment'] = 0;

                ee()->pagination->initialize($config);
                $pagination_links = ee()->pagination->create_links();
                ee()->pagination->initialize($config); // Re-initialize to reset config
                $page_array = ee()->pagination->create_link_array();

                if ((($total_pages * $limit) - $limit) > $current_page)
                {
                    $page_next = reduce_double_slashes($basepath.$config['prefix'].($current_page + $limit).'/');
                }

                if (($current_page - $limit ) >= 0)
                {
                    $page_previous = reduce_double_slashes($basepath.$config['prefix'].($current_page - $limit).'/');
                }
            }
            else
            {
                $current_page = 0;
            }

            //$entries = array_slice($entries, $current_page, $limit);
        }

        //----------------------------------------
        // Limit
        //----------------------------------------
        if ($paginate == TRUE)
        {
            ee()->db->limit($limit, $current_page);
        }
        else
        {
            ee()->db->limit($limit, $offset);
        }


        // Site ID
        ee()->db->where_in('tl.site_id' , ee()->TMPL->site_ids);

        // Fetch!!
        $query = ee()->db->get();
        ee()->db->_reset_select();

        // Did we find anything
        if ($query->num_rows() == 0)
        {
            ee()->db->_reset_select();
            ee()->TMPL->log_item('TAGGER: No channel entries found');
            return ee()->tagger_helper->custom_no_results_conditional($prefix.'no_entries', ee()->TMPL->tagdata);
        }


        // Loop through the results
        $out = '';
        $count = 0;

        if ($paginate == FALSE)
        {
            $total = $query->num_rows();
        }

        // -----------------------------------------
        // Use Channel??
        // -----------------------------------------
        if (isset(ee()->TMPL->tagparams['native_channel']) === TRUE && ee()->TMPL->tagparams['native_channel'] == 'yes')
        {
            ee()->TMPL->tagparams['dynamic'] = 'off';

            // --------------------------------------
            // Take care of related entries
            // --------------------------------------

            // We must do this, 'cause the template engine only does it for
            // channel:entries or search:search_results. The bastard.
            ee()->TMPL->tagdata = ee()->TMPL->assign_relationship_data(ee()->TMPL->tagdata);

            // Add related markers to single vars to trigger replacement
            foreach (ee()->TMPL->related_markers AS $var)
            {
                ee()->TMPL->var_single[$var] = $var;
            }

            $entries = array();
            foreach ($query->result() as $row) $entries[] = $row->entry_id;
            ee()->TMPL->tagparams['entry_id'] = implode('|', $entries);

            // --------------------------------------
            // Include channel module
            // --------------------------------------
            if ( ! class_exists('channel')) require_once PATH_MOD.'channel/mod.channel'.EXT;
            $channel = new Channel();
            return $channel->entries();
        }


        // -----------------------------------------
        // Grab Channels
        // -----------------------------------------
        $channels = array();
        $temp = ee()->db->select('*')->from('exp_channels')->get();

        foreach ($temp->result() as $row)
        {
            $channels[$row->channel_id] = $row;
        }

        //----------------------------------------
        // Switch=""
        //----------------------------------------
        $parse_switch = FALSE;
        $switch_matches = array();
        if ( preg_match_all( "/".LD."({$prefix}switch\s*=.+?)".RD."/is", ee()->TMPL->tagdata, $switch_matches ) > 0 )
        {
            $parse_switch = TRUE;

            // Loop over all matches
            foreach($switch_matches[0] as $key => $match)
            {
                $switch_vars[$key] = ee()->functions->assign_parameters($switch_matches[1][$key]);
                $switch_vars[$key]['original'] = $switch_matches[0][$key];
            }
        }

        // -----------------------------------------
        // Loop through all entries
        // -----------------------------------------
        foreach ($query->result() as $row)
        {
            $count++;
            $vars = array(  $prefix.'channel_id'    => $row->channel_id,
                            $prefix.'entry_id'      => $row->entry_id,
                            $prefix.'entry_title'   => $row->title,
                            $prefix.'entry_url_title' => $row->url_title,
                            $prefix.'entry_date'    => $row->entry_date,
                            $prefix.'author_id'     => $row->member_id,
                            $prefix.'author_screen_name'=> $row->screen_name,
                            $prefix.'author_username'   => $row->username,
                            $prefix.'count'         => $count,
                            $prefix.'total_entries' => $total,
                            $prefix.'tags'          => (is_array($tags)) ? implode(',', $tags) : $tags,
                            $prefix.'page_number'   => (isset($t_current_page) === true) ? $t_current_page : '',
                        );


            // Channel Specific Data
            $vars[$prefix.'channel_name'] = $channels[$row->channel_id]->channel_name;
            $vars[$prefix.'channel_title'] = $channels[$row->channel_id]->channel_title;
            $vars[$prefix.'channel_url'] = $channels[$row->channel_id]->channel_url;
            $vars[$prefix.'channel_search_result_url'] = $channels[$row->channel_id]->search_results_url;
            $vars[$prefix.'channel_comment_url'] = $channels[$row->channel_id]->comment_url;

            // Any Custom Field?
            if ( isset($fields) == TRUE AND is_array($fields) == TRUE )
            {
                foreach ($fields as $field_id => $field_name)
                {
                    $field_id = 'field_id_'.$field_id;

                    $vars[$prefix.$field_name]  = ee()->tagger_helper->file_dir_parse($row->$field_id);
                }
            }

            $temp = ee()->TMPL->parse_variables_row(ee()->TMPL->tagdata, $vars);

            // -----------------------------------------
            // Parse Switch {switch="one|twoo"}
            // -----------------------------------------
            if ($parse_switch)
            {
                // Loop over all switch variables
                foreach($switch_vars as $switch)
                {
                    $sw = '';

                    // Does it exist? Just to be sure
                    if ( isset( $switch[$prefix.'switch'] ) !== FALSE )
                    {
                        $sopt = explode("|", $switch[$prefix.'switch']);
                        $sw = $sopt[($count + count($sopt) - 1) % count($sopt)];
                    }

                    $temp = str_replace($switch['original'], $sw, $temp);
                }
            }

            $out .= $temp;
        }

        //----------------------------------------
        // Add pagination to result
        //----------------------------------------
        if ($paginate == TRUE)
        {
            $parse_array = array();

            // Check to see if page_links is being used as a single
            // variable or as a variable pair
            if (isset(ee()->TMPL->var_pair[$prefix.'pagination_links']) === TRUE || isset(ee()->TMPL->var_pair['pagination_links']) === TRUE)
            {
                $parse_array[$prefix.'pagination_links'] = array($page_array);
                $parse_array['pagination_links'] = array($page_array);
            }
            else
            {
                $parse_array[$prefix.'pagination_links'] = $pagination_links;
                $parse_array['pagination_links'] = $pagination_links;
            }

            // ----------------------------------------------------------------

            // Parse current_page and total_pages by default
            $parse_array[$prefix.'current_page']    = $t_current_page;
            $parse_array[$prefix.'total_pages']     = $total_pages;
            $parse_array['current_page']    = $t_current_page;
            $parse_array['total_pages']     = $total_pages;



            // Parse current_page and total_pages
            $paginate_data = ee()->TMPL->parse_variables(
                $paginate_data,
                array($parse_array),
                FALSE // Disable backspace parameter so pagination markup is protected
            );

            // ----------------------------------------------------------------

            if (preg_match("/".LD."if {$prefix}previous_page".RD."(.+?)".LD.'\/'."if".RD."/s", $paginate_data, $match))
            {
                if ($page_previous == '')
                {
                     $paginate_data = preg_replace("/".LD."if {$prefix}previous_page".RD.".+?".LD.'\/'."if".RD."/s", '', $paginate_data);
                }
                else
                {
                    $match['1'] = str_replace(array(LD."{$prefix}path".RD, LD."{$prefix}auto_path".RD), $page_previous, $match['1']);

                    $paginate_data = str_replace($match['0'], $match['1'], $paginate_data);
                }
            }

            if (preg_match("/".LD."if {$prefix}next_page".RD."(.+?)".LD.'\/'."if".RD."/s", $paginate_data, $match))
            {
                if ($page_next == '')
                {
                     $paginate_data = preg_replace("/".LD."if {$prefix}next_page".RD.".+?".LD.'\/'."if".RD."/s", '', $paginate_data);
                }
                else
                {
                    $match['1'] = str_replace(array(LD."{$prefix}path".RD, LD."{$prefix}auto_path".RD), $page_next, $match['1']);

                    $paginate_data = str_replace($match['0'], $match['1'], $paginate_data);
                }
            }


            // ----------------------------------------------------------------

            // Parse {if previous_page} and {if next_page}
            $this->_parse_page_conditional('previous', $page_previous, $paginate_data);
            $this->_parse_page_conditional('next', $page_next, $paginate_data);

            // ----------------------------------------------------------------

            // Parse if total_pages conditionals
            $paginate_data = ee()->functions->prep_conditionals(
                $paginate_data,
                array(
                    $prefix.'total_pages' => $total_pages,
                    'total_pages' => $total_pages
                )
            );

            // ----------------------------------------------------------------

            $position = ( ! ee()->TMPL->fetch_param('paginate')) ? '' : ee()->TMPL->fetch_param('paginate');

            switch ($position)
            {
                case "top"  : $out  = $paginate_data.$out;
                    break;
                case "both" : $out  = $paginate_data.$out.$paginate_data;
                    break;
                default     : $out .= $paginate_data;
                    break;
            }
        }

        // Apply Backspace
        $out = ($backspace > 0) ? substr($out, 0, - $backspace): $out;

        // Update stats
        //ee()->db->set('hits', "(`hits` + 1)", FALSE)->where('tag_id', $query->row('tag_id'))->update('exp_tagger');

        // Resources are not free
        $query->free_result();
        unset($entries);

        return $out;
    }

    // ********************************************************************************* //

    /**
     * Entries Quick
     *
     * An easy method of getting the channel entry_id's associated with a particular tag.
     *
     * @return string
     */
    public function entries_quick()
    {
        // Variable prefix
        $prefix = ee()->TMPL->fetch_param('prefix', 'tagger') . ':';

        $tag = ee()->TMPL->fetch_param('tag');
        $unitag = ee()->TMPL->fetch_param('unitag');

        // -----------------------------------------
        // Parse Unitag
        // -----------------------------------------
        $tag_id = FALSE;
        if ($unitag != FALSE)
        {
            $tags = FALSE;

            $unitag = explode('-', $unitag);
            if (is_numeric($unitag[0]) == TRUE)
            {
                $tag_id = $unitag[0];
            }
        }

        // -----------------------------------------
        // No Tags?
        // -----------------------------------------
        if ($tag == FALSE && $tag_id == FALSE)
        {
            ee()->TMPL->log_item('TAGGER: No missing tag/unitag parameter');
            return ee()->tagger_helper->custom_no_results_conditional($prefix.'no_entries', ee()->TMPL->tagdata);
        }

        $tag = ee()->tagger_helper->urlsafe_tag($tag, FALSE);
        $limit = (ee()->tagger_helper->is_natural_number(ee()->TMPL->fetch_param('limit')) != FALSE) ? ee()->TMPL->fetch_param('limit') : 30;

        // Grab all entries with this tag
        ee()->db->select('tl.tag_id, tl.entry_id');
        ee()->db->from('exp_tagger_links tl');
        ee()->db->join('exp_tagger t', 't.tag_id = tl.tag_id', 'left');
        if ($tag_id != FALSE) ee()->db->where('t.tag_id', $tag_id);
        else ee()->db->where('t.tag_name', $tag);
        ee()->db->where_in('tl.site_id' , ee()->TMPL->site_ids);
        ee()->db->where('tl.type', 1);
        ee()->db->limit($limit);
        $query = ee()->db->get();

        // Did we find anything
        if ($query->num_rows() == 0)
        {
            ee()->TMPL->log_item('TAGGER: No channel entries found');
            return ee()->tagger_helper->custom_no_results_conditional($prefix.'no_entries', ee()->TMPL->tagdata);
        }

        // Loop through the results
        $items = array();

        foreach ($query->result() as $row)
        {
            $items[] = $row->entry_id;
        }

        $vars = array(  $prefix.'entry_ids' => implode('|', $items),
                        $prefix.'tag_name'  => $tag,
                    );

        ee()->TMPL->tagdata = ee()->TMPL->parse_variables_row(ee()->TMPL->tagdata, $vars);

        // Update stats
        ee()->db->set('hits', "(`hits` + 1)", FALSE);
        ee()->db->where('tag_id', $query->row('tag_id'));
        ee()->db->update('exp_tagger');

        // Resources are not free
        $query->free_result();

        return ee()->TMPL->tagdata;
    }

    // ********************************************************************************* //

    public function tagname()
    {
        $tag = ee()->TMPL->fetch_param('tag');
        $unitag = ee()->TMPL->fetch_param('unitag');

        // -----------------------------------------
        // Unitag?
        // -----------------------------------------
        if ($unitag == TRUE)
        {
            $tag_id = 0;

            $unitag = explode('-', $unitag);
            if (is_numeric($unitag[0]) == TRUE)
            {
                $tag_id = $unitag[0];
            }

            $query = ee()->db->select('tag_name')->from('exp_tagger')->where('tag_id', $tag_id)->limit(1)->get();
            if ($query->num_rows() > 0) $tag = $query->row('tag_name');
            else return ee()->TMPL->fetch_param('unitag');
        }

        // -----------------------------------------
        // Output
        // -----------------------------------------
        if ($tag)
        {
            return ucwords(ee()->tagger_helper->urlsafe_tag($tag, FALSE));
        }
        else
        {
            ee()->TMPL->log_item('TAGGER: Missing tag="" parameter');
            return $tag;
        }
    }
    // ********************************************************************************* //

    public function tagger_router()
    {
        @header('Access-Control-Allow-Origin: *');
        @header('Access-Control-Allow-Credentials: true');
        @header('Access-Control-Max-Age: 86400');
        @header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        @header('Access-Control-Allow-Headers: Keep-Alive, Content-Type, User-Agent, Cache-Control, X-Requested-With, X-File-Name, X-File-Size');

        // -----------------------------------------
        // Ajax Request?
        // -----------------------------------------
        if ( ee()->input->get_post('ajax_method') != FALSE OR (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') )
        {
            // Load Library
            ee()->load->library('tagger_ajax');

            // Shoot the requested method
            $method = ee()->input->get_post('ajax_method');
            echo ee()->tagger_ajax->$method();
            exit();
        }
    }

    // ********************************************************************************* //

    /**
     * Parse {if previous_page} and {if next_page}
     *
     * @param Pagination_object $pagination Pagination_object that has been
     *      manipulated by the other pagination methods
     * @param string $type Either 'next' or 'previous' depending on the
     *      conditional you're looking for
     * @param string $replacement What to replace $type_page with
     */
    private function _parse_page_conditional($type, $replacement, $template_data)
    {
        if (preg_match_all("/".LD."if {$type}_page".RD."(.+?)".LD.'\/'."if".RD."/s", $template_data, $matches))
        {
            if ($replacement == '')
            {
                 return preg_replace("/".LD."if {$type}_page".RD.".+?".LD.'\/'."if".RD."/s", '', $template_data);
            }
            else
            {
                foreach($matches[1] as $count => $match)
                {
                    $match = preg_replace("/".LD.'path.*?'.RD."/", $replacement, $match);
                    $match = preg_replace("/".LD.'auto_path'.RD."/", $replacement, $match);

                    return  str_replace($matches[0][$count], $match, $template_data);
                }
            }
        }
    }


} // END CLASS

/* End of file mod.tagger.php */
/* Location: ./system/user/addons/tagger/mod.tagger.php */