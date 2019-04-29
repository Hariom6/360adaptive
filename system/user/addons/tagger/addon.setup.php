<?php

if ( ! defined('TAGGER_NAME')) {
    define('TAGGER_NAME',         'Tagger');
    define('TAGGER_CLASS_NAME',   'tagger');
    define('TAGGER_VERSION',      '4.0.0');
}

if ( ! function_exists('dd')) {
    function dd()
    {
        array_map(function($x) { var_dump($x); }, func_get_args()); die;
    }
}

return array(
    'author'         => 'DevDemon',
    'author_url'     => 'https://devdemon.com/',
    'docs_url'       => 'http://www.devdemon.com/docs/',
    'name'           => TAGGER_NAME,
    'description'    => 'Tag Channel Entries and more',
    'version'        => TAGGER_VERSION,
    'namespace'      => 'DevDemon\Tagger',
    'settings_exist' => true,
    'models' => array(
        'Group' => 'Model\Group',
        'Tag'   => 'Model\Tag',
    ),
    'services'       => array(),
    'services.singletons' => array(
        'Settings' => function($addon) {
            return new DevDemon\Tagger\Service\Settings($addon);
        },
        'Helper' => function($addon) {
            return new DevDemon\Tagger\Service\Helper($addon);
        }
    ),

    //----------------------------------------
    // Default Module Settings
    //----------------------------------------
    'settings_module' => array(
        'urlsafe_separator' => 'plus',
        'lowercase_tags'    => 'yes',
    ),

    //----------------------------------------
    // Default Fieldtype Settings
    //----------------------------------------
    'settings_fieldtype' => array(
        'show_most_used'    => 'yes',
        'single_field'      => 'no',
        'auto_assign_group' => '0',
    ),


);

/* End of file addon.setup.php */
/* Location: ./system/user/addons/tagger/addon.setup.php */