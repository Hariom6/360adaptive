<?php

namespace DevDemon\Tagger\Model;

use EllisLab\ExpressionEngine\Service\Model\Model;

class Tag extends Model {

    protected static $_primary_key = 'tag_id';
    protected static $_table_name = 'tagger_tags';

    protected static $_typed_columns = array(
        'tag_id'        => 'int',
        'site_id'       => 'int',
        'author_id'     => 'int',
        'entry_date'    => 'timestamp',
        'edit_date'     => 'timestamp',
        'hits'          => 'int',
        'total_entries' => 'int',
    );

    protected static $_validation_rules = array(
        'group_title' => 'required',
    );

    protected static $_relationships = array(
        'Groups' => array(
            'type' => 'hasAndBelongsToMany',
            'model' => 'Group',
            'pivot' => array(
                'table' => 'tagger_groups_entries',
                'left'  => 'tag_id',
                'right' => 'group_id',
            )
        ),
    );

    protected $tag_id;
    protected $tag_name;
    protected $site_id;
    protected $author_id;
    protected $entry_date;
    protected $edit_date;
    protected $hits;
    protected $total_entries;



} // END CLASS

/* End of file Group.php */
/* Location: ./system/user/addons/tagger/Model/Group.php */