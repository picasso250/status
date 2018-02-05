<?php

namespace lib;

use Pdo;
use BadMethodCallException;
use mysqli;

function _add_quote($key) {
  if (strpos($key, ".")) {
    return implode(".", array_map("lib\\_add_quote", explode(".", $key)));
  }
  return "`$key`";
}

function refValues($arr){
    if (strnatcmp(phpversion(),'5.3') >= 0) //Reference is required for PHP 5.3+
    {
        $refs = array();
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}

/**
 *
 */
class SqlBuilder
{
  private $_select = "*";
  private $_from = "";
  private $_join = "";
  private $_where = [];
  private $_having = [];
  private $_order_by = "";
  private $_limit = 100;
  private $_offset = 0;

  public $sql;
  public $stmt;

  public function __construct($db) {
    $this->_db = $db;
  }
  public function __call($name, $args) {
    if (in_array($name, ["limit", "offset"])) {
      $this->{"_".$name} = $args[0];
      return $this;
    }
    throw new BadMethodCallException("no method $name");
  }
  public function select(array $fields) {
    $this->_select = implode(",", array_map("lib\\_add_quote", $fields));
    return $this;
  }
  public function from($from, $as = "") {
    $this->_from = _add_quote($from).($as? (" "._add_quote($as)): "");
    return $this;
  }
  public function join(array $join_list) {
    $arr = [];
    foreach ($join_list as $join_info) {
      list($another, $on) = $join_info;
      $arr[] = "JOIN $another ON $on";
    }
    $this->_join = implode(" ", $arr);
    return $this;
  }
  private function _trim_table($key) {
    if (strpos($key, ".")) {
      $arr = explode(".", $key);
      return $arr[count($arr)-1];
    }
    return "$key";
  }
  public function where(array $where) {
    $where_str_arr = $where_params = [];
    foreach ($where as $w) {
      $key = self::_trim_table($w[0]);
      if (count($w) == 2) {
        $where_str_arr[] = _add_quote($w[0])."=:".$key;
        $where_params[$key] = $w[1];
      }
      if (count($w) == 3) {
        $where_str_arr[] = _add_quote($w[0])." $w[1] :".$key;
        $where_params[$key] = $w[2];
      }
    }
    $where_str = implode(" AND ", $where_str_arr);
    $this->_where = [ $where_str, $where_params ];
    return $this;
  }
  public function having(array $where) {
    $where_str_arr = $where_params = [];
    foreach ($where as $w) {
      if (count($w) == 2) {
        $where_str_arr[] = _add_quote($w[0])."=:$w[0]";
        $where_params[] = $w[1];
      }
      if (count($w) == 3) {
        $where_str_arr[] = _add_quote($w[0])." $w[1] :$w[0]";
        $where_params[] = $w[2];
      }
    }
    $where_str = implode(" AND ", $where_str_arr);
    $this->_having = [ $where_str, $where_params ];
    return $this;
  }
  public function orderBy(array $fields) {
    $order_by_str_arr = [];
    foreach ($fields as $field => $sort) {
      $order_by_str_arr[] = _add_quote($field)." $sort";
    }
    $this->_order_by = implode(",", $order_by_str_arr);
    return $this;
  }
  public function getOne() {
    $rows = $this->limit(1)->getAll();
    if ($rows) return $rows[0];
    return false;
  }
  public function getAll() {
    list($this->sql, $params) = $this->_build_select();
    // echo $this->sql,"\t",json_encode($params),PHP_EOL;
    if (!$this->execute($params)) {
      return false;
    }
    if ($this->_db instanceof mysqli) {
      $result = $this->stmt->get_result();
      return $result->fetch_all(MYSQLI_ASSOC);
    }
    return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  public function count() {
    $this->_select = "COUNT(*) c";
    $row = $this->getOne();
    return $row['c'];
  }
  public function _build_select()
  {
    $join = "";
    if ($this->_join) {
      $join = "$this->_join";
    }
    $where = "";
    $where_params = [];
    if ($this->_where) {
      $where_str = $this->_where[0];
      $where = "WHERE $where_str";
      $where_params = $this->_where[1];
    }
    $order_by = $this->_order_by ? "ORDER BY $this->_order_by" : "";
    return [
      "SELECT $this->_select FROM $this->_from $join $where $order_by LIMIT $this->_limit OFFSET $this->_offset",
      $where_params
    ];
  }
  public function execute($data) {
    $log = Service::get('log');
    if ($this->_db instanceof mysqli) {
      $arr = [];
      // PHP >= 5.3
      if (preg_match_all("/:([\w_]+)/", $this->sql, $m)) {
        foreach ($m[1] as $mm) {
          $arr[] = $mm;
        }
      }
      $params = [];
      if ($arr) {
        $type_arr = [];
        foreach ($arr as $key) {
          $v = $data[$key];
          // do not support big int
          if (is_numeric($v)) {
            if (strpos($v, ".") === false) {
              $type_arr[] = "i";
            } else {
              $type_arr[] = "d";
            }
          } else {
            $type_arr[] = "s";
          }
          $params[] = $v;
        }
        array_unshift($params, implode('', $type_arr));
        $sql = preg_replace("/:[\w_]+/", '?', $this->sql);
        $this->stmt = $this->_db->prepare($sql);
        if ($this->stmt == false) {
          return false;
        }
        // echo $sql,PHP_EOL;
        // print_r($params);
        call_user_func_array([$this->stmt, 'bind_param'], refValues($params));
      } else {
        $this->stmt = $this->_db->prepare($this->sql);
      }
      if ($log) $log->DEBUG("SQL mysqli %s %s", $this->sql, json_encode($params));
      return $this->stmt->execute();
    }
    if ($log) $log->DEBUG("SQL pdo_mysql %s %s", $this->sql, json_encode($data));
    $this->stmt = $this->_db->prepare($this->sql);
    return $this->stmt->execute($data);
  }
  public function update($data) {
    $_set_str_arr = [];
    foreach ($data as $k=>$v) {
      $_set_str_arr[] = "`$k`=:$k";
    }
    $_set = implode(",", $_set_str_arr);
    $where_str = $this->_where[0];
    $this->sql = "UPDATE $this->_from SET $_set WHERE $where_str LIMIT $this->_limit";
    $params = array_merge($data, $this->_where[1]);
    return $this->execute($params);
  }
  public function insert($data) {
    $_set_str_arr = [];
    foreach ($data as $k=>$v) {
      $_set_str_arr[] = "`$k`=:$k";
    }
    $_set = implode(",", $_set_str_arr);
    $this->sql = "INSERT INTO $this->_from SET $_set ";
    return $this->execute($data);
  }
  public function upsert($data) {
    $_set_str_arr = [];
    foreach ($data as $k=>$v) {
      $_set_str_arr[] = "`$k`=:$k";
    }
    $_set = implode(",", $_set_str_arr);
    $_set_str_arr = [];
    foreach ($data as $k=>$v) {
      $_set_str_arr[] = "`$k`=:${k}_2";
    }
    $_set2 = implode(",", $_set_str_arr);
    $data_big = $data;
    foreach ($data as $key => $value) {
      $data_big["${key}_2"] = $value;
    }
    $this->sql = "INSERT INTO $this->_from SET $_set ON DUPLICATE KEY UPDATE $_set2";
    return $this->execute($data_big);
  }
  public function delete() {
    $where_str = $this->_where[0];
    $this->sql = "DELETE FROM $this->_from WHERE $where_str LIMIT $this->_limit";
    return $this->execute($this->_where[1]);
  }

}