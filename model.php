<?php

use lib\Model;

class site extends Model {
  static function ensure_url($site_url, $add_time) {
    $row = self::sqlBuilder()->where(["site_url=$site_url"])->getOne();
    if ($row) {
      return $row['id'];
    }
    return self::add(compact('site_url', 'add_time'));
  }
}
class status_log extends Model {
  static function add_entry($url, $data) {
    $now = date('Y-m-d H:i:s');
    $site_id = site::ensure_url($url, $now);
    $data['site_id'] = $site_id;
    $data['fetch_time'] = $now;
    self::add($data);
  }
  static function getLatest($site_id, $n) {
    return self::sqlBuilder()
      ->where(["site_id=$site_id"])
      ->orderBy(['fetch_time' => 'DESC'])
      ->limit($n)
      ->getAll();
  }
}