<?php

namespace DevDemon\Tagger\Service;

class Helper {

    protected $package_name = TAGGER_CLASS_NAME;
    protected $actionUrlCache = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->site_id = ee()->config->item('site_id');
    }

    public function getRouterUrl($type='url', $method='tagger_router')
    {
        // -----------------------------------------
        // Grab action_id
        // -----------------------------------------
        if (isset($this->actionUrlCache[$method]['action_id']) === false) {
            $action = ee('Model')->get('Action')
            ->filter('class', ucfirst($this->package_name))
            ->filter('method', $method)
            ->fields('action_id')
            ->first();

            if (!$action) {
                return false;
            }

            $action_id = $action->action_id;
        } else {
            $action_id = $this->actionUrlCache[$method]['action_id'];
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

            // Config Override for action URLs?
            $config = ee()->config->item($this->package_name);
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
}

/* End of file Helper.php */
/* Location: ./system/user/addons/tagger/Service/Helper.php */