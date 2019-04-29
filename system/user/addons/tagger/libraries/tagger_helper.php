<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tagger AHelper File
 *
 * @package         DevDemon_Tagger
 * @version         2.1.2
 * @author          DevDemon <http://www.devdemon.com> - Lead Developer @ Parscale Media
 * @copyright       Copyright (c) 2007-2010 Parscale Media <http://www.parscale.com>
 * @license         http://www.devdemon.com/license/
 * @link            http://www.devdemon.com
 */
class Tagger_helper
{

    private $package_name = 'tagger';

    public function __construct()
    {
        $this->site_id = ee()->config->item('site_id');
    }

    // ********************************************************************************* //

    /**
     * Url Safe Tag
     *
     * This method is responsible for encode/decode tags for URL's
     *
     * @param string $tag - The original Tag
     * @param bool $encode[optional] - Are we encoding or decoding
     * @return string - The processed Tag
     */
    public function urlsafe_tag($tag, $encode=TRUE)
    {
        $conf = ee('tagger:Settings')->settings;

        if ($encode == true)
        {
            switch($conf['urlsafe_separator'])
            {
                case 'plus':
                    $tag = str_replace(' ', '+', $tag);
                    break;
                case 'space':
                    $tag = str_replace(' ', '%20', $tag);
                    break;
                case 'dash':
                    $tag = str_replace(' ', '-', $tag);
                    break;
                case 'underscore':
                    $tag = str_replace(' ', '_', $tag);
                    break;
            }

            $tag = str_replace(' ', '%20', $tag);
            $tag = str_replace('&', '%26', $tag);
            $tag = htmlentities($tag, ENT_QUOTES, 'UTF-8');
        }
        else
        {
            switch($conf['urlsafe_separator'])
            {
                case 'plus':
                    $tag = str_replace('+', ' ', $tag);
                    break;
                case 'dash':
                    $tag = str_replace('-', ' ', $tag);
                    break;
                case 'underscore':
                    $tag = str_replace('_', ' ', $tag);
                    break;
            }

            $tag = str_replace('%20', ' ', $tag );
            $tag = str_replace('%26', '&', $tag );
            $tag = html_entity_decode($tag);
        }

        return $tag;
    }

    // ********************************************************************************* //

    public function unitag($tag_id, $tag)
    {
        // Strip all weird chars from the tag
        //$tag = preg_replace("/[^a-zA-Z0-9]/", "", $tag);
        $tag = preg_replace("/[^A-Za-z0-9\s\s+\-]/", "", $tag);
        $tag = $this->urlsafe_tag($tag);
        $tag = $tag_id . '-' . $tag;

        return $tag;
    }

    // ********************************************************************************* //


    // -----------------------------------------
    // Support filedir tags in entries.
    // -----------------------------------------
    public function file_dir_parse($str)
    {
        $file_dirs = ee()->functions->fetch_file_paths();

        foreach($file_dirs AS $key => $row)
        {
            $str = str_ireplace("{filedir_$key}", $row, $str);
        }

        return $str;
    }

    // ********************************************************************************* //

    //public function getRouterUrl($type='url', $method='actionGeneralRouter')
    public function getRouterUrl($type='url', $method='tagger_router')
    {
        // -----------------------------------------
        // Grab action_id
        // -----------------------------------------
        if (isset(ee()->session->cache[$this->package_name]['router_url'][$method]['action_id']) === false) {
            ee()->db->select('action_id');
            ee()->db->where('class', ucfirst($this->package_name));
            ee()->db->where('method', $method);
            $query = ee()->db->get('exp_actions');

            if ($query->num_rows() == 0) {
                return false;
            }

            $action_id = $query->row('action_id');
        } else {
            $action_id = ee()->session->cache[$this->package_name]['router_url'][$method]['action_id'];
        }

        // -----------------------------------------
        // Return FULL action URL
        // -----------------------------------------
        if ($type == 'url') {
            // Grab Site URL
            $url = ee()->functions->fetch_site_index(0, 0);

            if (defined('MASKED_CP') == false OR MASKED_CP == false) {
                // Replace site url domain with current working domain
                $server_host = (isset($_SERVER['HTTP_HOST']) == true && $_SERVER['HTTP_HOST'] != false) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
                $url = preg_replace('#http\://(([\w][\w\-\.]*)\.)?([\w][\w\-]+)(\.([\w][\w\.]*))?\/#', "http://{$server_host}/", $url);
            }

             // Create new URL
            $ajax_url = $url.QUERY_MARKER.'ACT=' . $action_id;

            // Config Overrife for action URLs?
            $config = ee()->config->item('credits');
            $over = isset($config['action_url']) ? $config['action_url'] : array();

            if (is_array($over) === true && isset($over[$method]) === true) {
                $url = $over[$method];
            }

            // Protocol Relative URL
            $ajax_url = str_replace(array('https://', 'http://'), '//', $ajax_url);

            return $ajax_url;
        }

        return $action_id;
    }

    // ********************************************************************************* //

    function define_theme_url()
    {
        if (defined('URL_THIRD_THEMES') === TRUE)
        {
            $theme_url = URL_THIRD_THEMES;
        }
        else
        {
            $theme_url = ee()->config->item('theme_folder_url').'third_party/';
        }

        // Are we working on SSL?
        if (isset($_SERVER['HTTP_REFERER']) == TRUE AND strpos($_SERVER['HTTP_REFERER'], 'https://') !== FALSE)
        {
            $theme_url = str_replace('http://', 'https://', $theme_url);
        }

        if (! defined('TAGGER_THEME_URL')) define('TAGGER_THEME_URL', $theme_url . 'tagger/');

        return TAGGER_THEME_URL;
    }

    // ********************************************************************************* //

    /**
     * Format tags
     *
     * Cleans up the tag, by removing unwanted characters
     *
     * @param string $str[optional] - The unformatted tag
     * @return string The formatted tag
     */
    public function format_tag($str='')
    {
        ee()->load->helper('text');

        $not_allowed = array('$', '?', ')', '(', '!', '<', '>', '/', '\\');

        $str = str_replace($not_allowed, '', $str);

        //$str  = ( $this->convert_case === true ) ? $this->_strtolower( $str ): $str;

        $str    = ee()->security->xss_clean($str);

        return trim($str);
    }

    // ********************************************************************************* //

    /**
     * Insert Tag in DB
     *
     * @param string $tag - The tag
     * @return int - The tag ID
     */
    public function create_tag($tag)
    {
        // Data array for insertion
        $data = array(  'tag_name'  =>  $tag,
                        'site_id'   =>  $this->site_id,
                        'author_id' =>  ee()->session->userdata['member_id'],
                        'entry_date'=>  ee()->localize->now,
                        'edit_date' =>  ee()->localize->now,
                        'total_entries' => 0,
                );

        ee()->db->insert('exp_tagger', $data);

        return ee()->db->insert_id();

    }

    // ********************************************************************************* //

    public function generate_json($obj)
    {
        if (function_exists('json_encode') === FALSE)
        {
            if (class_exists('Services_JSON') === FALSE) include 'JSON.php';
            $JSON = new Services_JSON();
            return $JSON->encode($obj);
        }
        else
        {
            return json_encode($obj);
        }
    }

    // ********************************************************************************* //

    function shuffle_assoc($list) {
      if (!is_array($list)) return $list;

      $keys = array_keys($list);
      shuffle($keys);
      $random = array();
      foreach ($keys as $key)
        $random[$key] = $list[$key];

      return $random;
    }

    // ********************************************************************************* //

    /**
     * Is a Natural number  (0,1,2,3, etc.)
     *
     * @access  public
     * @param   string
     * @return  bool
     */
    public function is_natural_number($str)
    {
        return (bool)preg_match( '/^[0-9]+$/', $str);
    }

    // ********************************************************************************* //

    /**
     * Get Entry_ID from tag paramaters
     *
     * Supports: entry_id="", url_title="", channel=""
     *
     * @return mixed - INT or BOOL
     */
    public function get_entry_id_from_param($get_channel_id=FALSE)
    {
        $entry_id = FALSE;
        $channel_id = FALSE;

        ee()->load->helper('number');

        if (ee()->TMPL->fetch_param('entry_id') != FALSE && $this->is_natural_number(ee()->TMPL->fetch_param('entry_id')) != FALSE)
        {
            $entry_id = ee()->TMPL->fetch_param('entry_id');
        }
        elseif (ee()->TMPL->fetch_param('url_title') != FALSE)
        {
            $channel = FALSE;
            $channel_id = FALSE;

            if (ee()->TMPL->fetch_param('channel') != FALSE)
            {
                $channel = ee()->TMPL->fetch_param('channel');
            }

            if (ee()->TMPL->fetch_param('channel_id') != FALSE && $this->is_natural_number(ee()->TMPL->fetch_param('channel_id')))
            {
                $channel_id = ee()->TMPL->fetch_param('channel_id');
            }

            ee()->db->select('exp_channel_titles.entry_id');
            ee()->db->select('exp_channel_titles.channel_id');
            ee()->db->from('exp_channel_titles');
            if ($channel) ee()->db->join('exp_channels', 'exp_channel_titles.channel_id = exp_channels.channel_id', 'left');
            ee()->db->where('exp_channel_titles.url_title', ee()->TMPL->fetch_param('url_title'));
            if ($channel) ee()->db->where('exp_channels.channel_name', $channel);
            if ($channel_id) ee()->db->where('exp_channel_titles.channel_id', $channel_id);
            ee()->db->limit(1);
            $query = ee()->db->get();

            if ($query->num_rows() > 0)
            {
                $channel_id = $query->row('channel_id');
                $entry_id = $query->row('entry_id');
                $query->free_result();
            }
            else
            {
                return FALSE;
            }
        }

        if ($get_channel_id != FALSE)
        {
            if (ee()->TMPL->fetch_param('channel') != FALSE)
            {
                $channel_id = ee()->TMPL->fetch_param('channel_id');
            }

            if ($channel_id == FALSE)
            {
                ee()->db->select('channel_id');
                ee()->db->where('entry_id', $entry_id);
                ee()->db->limit(1);
                $query = ee()->db->get('exp_channel_titles');
                $channel_id = $query->row('channel_id');

                $query->free_result();
            }

            $entry_id = array( 'entry_id'=>$entry_id, 'channel_id'=>$channel_id );
        }



        return $entry_id;
    }

    // ********************************************************************************* //

    public function get_fields_from_params($params)
    {
        $fields = array();
        $site_id = isset($params['site_id']) ? $params['site_id'] : $this->site_id;

        if (isset($params['field_id']) === TRUE)
        {
            // Multiple fields?
            if (strpos($params['field_id'], '|') !== FALSE)
            {
                return explode('|', $params['field_id']);
            }
            else
            {
                return $params['field_id'];
            }
        }

        if (isset($params['field']) === TRUE)
        {
            // Multiple fields?
            if (strpos($params['field'], '|') !== FALSE)
            {
                $pfields = explode('|', $params['field']);

                foreach($pfields as $field)
                {
                    if (isset(ee()->session->cache['channel']['custom_channel_fields'][$site_id][ $field ]) === FALSE)
                    {
                        // Grab the field id
                        $query = ee()->db->query("SELECT field_id FROM exp_channel_fields WHERE field_name = '{$field}' AND site_id = {$site_id} ");
                        if ($query->num_rows() == 0)
                        {
                            if (isset(ee()->TMPL) === TRUE) ee()->TMPL->log_item('TAGGER: Could not find field : ' . $field);
                            return FALSE;
                        }
                        else
                        {
                            ee()->session->cache['channel']['custom_channel_fields'][$site_id][ $field ] = $query->row('field_id');
                        }
                    }

                    $fields[] = ee()->session->cache['channel']['custom_channel_fields'][$site_id][ $field ];
                }
            }
            else
            {
                if (isset(ee()->session->cache['channel']['custom_channel_fields'][$site_id][ $params['field'] ]) === FALSE)
                {
                    // Grab the field id
                    $query = ee()->db->query("SELECT field_id FROM exp_channel_fields WHERE field_name = '{$params['field']}' AND site_id = {$site_id} ");
                    if ($query->num_rows() == 0)
                    {
                        if (isset(ee()->TMPL) === TRUE) ee()->TMPL->log_item('TAGGER: Could not find field : ' . $params['field']);
                        return FALSE;
                    }
                    else
                    {
                        ee()->session->cache['channel']['custom_channel_fields'][$site_id][ $params['field'] ] = $query->row('field_id');
                    }
                }

                return ee()->session->cache['channel']['custom_channel_fields'][$site_id][ $params['field'] ];
            }
        }

        if (empty($fields) === TRUE) return FALSE;

        return $fields;
    }

    // ********************************************************************************* //

    /**
     * Custom No_Result conditional
     *
     * Same as {if no_result} but with your own conditional.
     *
     * @param string $cond_name
     * @param string $source
     * @param string $return_source
     * @return unknown
     */
    public function custom_no_results_conditional($cond_name, $source, $return_source=FALSE)
    {
        if (strpos($source, LD."if {$cond_name}".RD) !== FALSE)
        {
            if (preg_match('/'.LD."if {$cond_name}".RD.'(.*?)'.LD.'\/'.'if'.RD.'/s', $source, $cond))
            {
                return $cond[1];
            }

        }

        if ($return_source !== FALSE)
        {
            return $source;
        }

        return;
    }

    // ********************************************************************************* //

    /**
     * Fetch data between var pairs
     *
     * @param string $open - Open var (with optional parameters)
     * @param string $close - Closing var
     * @param string $source - Source
     * @return string
     */
    function fetch_data_between_var_pairs($varname='', $source = '')
    {
        if ( ! preg_match('/'.LD.($varname).RD.'(.*?)'.LD.'\/'.$varname.RD.'/s', $source, $match))
               return;

        return $match['1'];
    }

    // ********************************************************************************* //

    /**
     * Fetch data between var pairs (including optional parameters)
     *
     * @param string $open - Open var (with optional parameters)
     * @param string $close - Closing var
     * @param string $source - Source
     * @return string
     */
    function fetch_data_between_var_pairs_params($open='', $close='', $source = '')
    {
        if ( ! preg_match('/'.LD.preg_quote($open).'.*?'.RD.'(.*?)'.LD.'\/'.$close.RD.'/s', $source, $match))
               return;

        return $match['1'];
    }

    // ********************************************************************************* //

    /**
     * Replace var_pair with final value
     *
     * @param string $open - Open var (with optional parameters)
     * @param string $close - Closing var
     * @param string $replacement - Replacement
     * @param string $source - Source
     * @return string
     */
    function swap_var_pairs($varname = '', $replacement = '\\1', $source = '')
    {
        return preg_replace("/".LD.$varname.RD."(.*?)".LD.'\/'.$varname.RD."/s", $replacement, $source);
    }

    // ********************************************************************************* //

    /**
     * Replace var_pair with final value (including optional parameters)
     *
     * @param string $open - Open var (with optional parameters)
     * @param string $close - Closing var
     * @param string $replacement - Replacement
     * @param string $source - Source
     * @return string
     */
    function swap_var_pairs_params($open = '', $close = '', $replacement = '\\1', $source = '')
    {
        return preg_replace("/".LD.preg_quote($open).RD."(.*?)".LD.'\/'.$close.RD."/s", $replacement, $source);
    }

    // ********************************************************************************* //

    public function formatDate($format='', $date=0, $localize=true)
    {
        if (method_exists(ee()->localize, 'format_date') === true) {
            return ee()->localize->format_date($format, $date, $localize);
        } else {
            return ee()->localize->decode_date($format, $date, $localize);
        }
    }

    // ********************************************************************************* //

    public function getThemeUrl($root=false)
    {
        if (defined('URL_THIRD_THEMES') === true) {
            $theme_url = URL_THIRD_THEMES;
        } else {
            $theme_url = ee()->config->slash_item('theme_folder_url').'third_party/';
        }

        $theme_url = str_replace(array('http://','https://'), '//', $theme_url);

        if ($root) return $theme_url;

        $theme_url .= $this->package_name . '/';

        return $theme_url;
    }

    // ********************************************************************************* //

    public function addMcpAssets($type='', $path='', $package='', $name='')
    {
        $theme_url = $this->getThemeUrl();
        $url = $this->getThemeUrl() . $path;

        $prefix = ($iecond) ? "<!--[if {$iecond}]>" : '';
        $suffix = ($iecond) ? '<![endif]-->' : '';

        // CSS
        if ($type == 'css') {
            if (isset(ee()->session->cache['css'][$package][$name]) === false) {
                ee()->cp->add_to_head($prefix.'<link rel="stylesheet" href="' . $url . '" type="text/css" media="print, projection, screen" />'.$suffix);
                ee()->session->cache['css'][$package][$name] = true;
            }
        }

        // JS
        if ($type == 'js') {
            if (isset(ee()->session->cache['javascript'][$package][$name]) === false) {
                ee()->cp->add_to_foot($prefix.'<script src="' . $url . '" type="text/javascript"></script>'.$suffix);
                ee()->session->cache['javascript'][$package][$name] = true;
            }
        }

        // Custom
        if ($type == 'custom') {
            $path = str_replace('{theme_url}', $theme_url, $path);
            ee()->cp->add_to_foot($path);
        }

        // Global Inline Javascript
        if ($type == 'gjs') {
            if ( isset(ee()->session->cache['inline_js'][$this->package_name]) == false ) {

                $ACT_url = $this->getRouterUrl('url');

                /*
                if (isset(ee()->updater->settings['action_url']['actionGeneralRouter']) === true && ee()->updater->settings['action_url']['actionGeneralRouter'] != false) {
                    $ACT_url = ee()->updater->settings['action_url']['actionGeneralRouter'];
                }*/

                // Remove those AMP!!!
                $ACT_url = str_replace('&amp;', '&', $ACT_url);
                $theme_url = str_replace('&amp;', '&', $theme_url);

                $js = " var Tagger = Tagger ? Tagger : {};
                        Tagger.ACT_URL = '{$ACT_url}';
                        Tagger.THEME_URL = '{$theme_url}';
                ";

                ee()->cp->add_to_foot('<script type="text/javascript">' . $js . '</script>');
                ee()->session->cache['inline_js'][$this->package_name] = true;
            }
        }
    }

    // ********************************************************************************* //

} // END CLASS

/* End of file tagger_helper.php  */
/* Location: ./system/expressionengine/third_party/tagger/modules/libraries/tagger_helper.php */
