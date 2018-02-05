<?php
namespace lib;

/**
 *
 */
class LogSys
{
  public static $db_getter;
  public static $db;
  public function __call($name, $args) {
    error_log("$name\t".call_user_func_array('sprintf', $args));
  }

}
