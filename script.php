<?php

use lib\Curl;
use lib\Model;

define('ROOT', __DIR__);

require ROOT.'/lib/autoload.php';
require ROOT.'/model.php';

env_load(ROOT);

$server_config_list = parse_ini_file(ROOT.'/config.ini', true);

$dsn = "mysql:host=$_ENV[db_host];dbname=$_ENV[db_name];charset=utf8";
$username = $_ENV['db_username'];
$password = $_ENV['db_password'];
$db = new Pdo($dsn, $username, $password);

Model::$db = $db;

// run every minutes
$res = [];
foreach ($server_config_list as $key => $server_conf) {
  $url = $server_conf['url'];
  list($code, $content) = $res[$url] = Curl::GET($url);
  echo "GET $url => $code\n";
  status_log::add_entry($url, ['code' => $code, 'length' => strlen($content)]);
}
