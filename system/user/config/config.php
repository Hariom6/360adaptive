<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$config['index_page'] = '';
$config['is_system_on'] = 'y';
$config['multiple_sites_enabled'] = 'n';
$config['show_ee_news'] = 'n';
// ExpressionEngine Config Items
// Find more configs and overrides at
// https://docs.expressionengine.com/latest/general/system_configuration_overrides.html

$config['app_version'] = '5.2.1';
$config['encryption_key'] = 'fa650a9b43e81a745b4f4a32e91ab4d93382889d';
$config['session_crypt_key'] = '9dc977a54efb41d917e1c7396e81efcc0644f453';
$config['database'] = array(
	'expressionengine' => array(
		'hostname' => 'localhost',
		'database' => '360adaptive',
		'username' => 'root',
		'password' => '',
		'dbprefix' => '360_',
		'char_set' => 'utf8mb4',
		'dbcollat' => 'utf8mb4_unicode_ci',
		'port'     => ''
	),
);

// EOF