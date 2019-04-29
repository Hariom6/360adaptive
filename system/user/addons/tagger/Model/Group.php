<?php

namespace DevDemon\Tagger\Model;

use EllisLab\ExpressionEngine\Service\Model\Model;

class Group extends Model {

    protected static $_primary_key = 'group_id';
    protected static $_table_name = 'tagger_groups';

    protected static $_typed_columns = array(
        'group_id'  => 'int',
        'parent_id' => 'int',
        'site_id'   => 'int',
        'order'     => 'int',
    );

    protected static $_validation_rules = array(
        'group_title' => 'required',
    );

    protected static $_relationships = array(
        'Tags' => array(
            'type' => 'hasAndBelongsToMany',
            'model' => 'Tag',
            'pivot' => array(
                'table' => 'tagger_groups_entries',
                'left'  => 'group_id',
                'right' => 'tag_id',
            )
        ),
    );

    protected $group_id;
    protected $group_title;
    protected $group_name;
    protected $group_desc;
    protected $parent_id;
    protected $site_id;
    protected $order;



} // END CLASS

/* End of file Group.php */
/* Location: ./system/user/addons/tagger/Model/Group.php */