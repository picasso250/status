<?php

namespace lib;

use stdClass;

class Encrypt {
  static function getSign($params)
  {
    $params['timestamp'] = date('Y-m-d H:i:s');
    $params= array_change_key_case($params,CASE_LOWER);
    ksort($params);
    $s = [];
    foreach ($params as $key => $value) {
      if ($value !== "")
        $s[] = "$key=$value";
    }
    $ss = implode("", $s).$_ENV['S_SYS_SECRET'];
    return strtolower(md5($ss));
  }
}
