<?php

use lib\Curl;
use lib\Model;
use lib\ErrorLog;

define('ROOT', dirname(__DIR__));

require ROOT.'/lib/autoload.php';
require ROOT.'/model.php';

env_load(ROOT);

$server_config_list = parse_ini_file(ROOT.'/config.ini', true);

$dsn = "mysql:host=$_ENV[db_host];dbname=$_ENV[db_name];charset=utf8";
$username = $_ENV['db_username'];
$password = $_ENV['db_password'];
$db = new Pdo($dsn, $username, $password);

Model::$db = $db;

$site_list = site::sqlBuilder()->getAll();
$status_list = [];
foreach ($site_list as $site) {
  $status_list [$site['site_url']] = status_log::getLatest($site['id'], 3);
}

?>
<!DOCTYPE html>
<html lang="zh-cmn-Hans">
<head>

<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>value</title>

<link rel="shortcut icon" type="image/ico" href="uri" />

<meta name="HandheldFriendly" content="true">

<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

<style media="screen">
  .status-code {
    color: red;
  }
  .status-code-200 {
    color: green;
  }
</style>

</head>

<body>
  <table class="table">
    <thead>
      <tr>
        <th scope="col">#</th>
        <th scope="col">地址</th>
        <th scope="col">状态</th>
      </tr>
    </thead>
    <tbody>
      <?php $i=0; foreach ($status_list as $site_url => $site_status): ?>
        <tr>
          <th scope="row"><?= ++$i ?></th>
          <td>
            <a href="<?= htmlentities($site_url) ?>" target="_blank"><?= htmlspecialchars($site_url) ?></a>
          </td>
          <td>
            <ul>
              <?php foreach ($site_status as $key => $status_point): ?>
                <li class="status-code-<?= $status_point['code'] ?> status-code"
                  title="<?= $status_point['fetch_time'] ?> <?= $status_point['code'] ?> <?= $status_point['length'] ?>B">
                  <?= $status_point['code'] ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  
</body>
</html>