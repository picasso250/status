<?php

function tuhu_get_work_type_str($WorkType) {
  $WorkType = array_filter($WorkType, function ($e) {
    return !$e['IsDelete'];
  });
  $gz = implode(',', array_map(function ($e) {
    return $e['WorkTypeName'];
  }, $WorkType));
  return $gz;
}
function tuhu_get_post_str($Post) {
  $Post = array_filter($Post, function ($e) {
    return !$e['IsDelete'];
  });
  $gw = implode(',', array_map(function ($e) {
    return $e['RoleName'];
  }, $Post));
  return $gw;
}