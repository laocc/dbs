<?php
declare(strict_types=1);

namespace esp\dbs\mongodb;

use Error;
use esp\dbs\Pool;
use MongoDB\Driver\Manager;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\Query;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\Regex;

/**
 * https://www.mongodb.com/download-center#community
 *
 * 老版本手册：   http://php.net/manual/zh/book.mongo.php
 * 新版本手册：   http://php.net/manual/zh/set.mongodb.php
 *20160621
 */
class Mongodb
{
    const _TIME_OUT = 1000;

    private $_conn;
    private $_db;
    private $_table;
    private $_where = array();
    private $_order = ['_id' => -1];
    private $_select;
    public $_build_where = '';

    private $_skip = 0;
    private $_limit = 0;

    /**
     * @var $pool Pool
     */
    private $pool;

    private $filter = [
        '>' => '$gt',
        '<' => '$lt',
        'in' => '$in',
        '!in' => '$nin',
        '>=' => '$gte',
        '>>' => '$gte',
        '<=' => '$lte',
        '<<' => '$lte',
        '!=' => '$ne',
    ];


    public function __construct(Pool $pool, array $conf, $db = null)
    {
        $this->pool = &$pool;
        $conf += [
            'host' => '127.0.0.1',
            'port' => 27017,
            'db' => 'test',
            'username' => null,
            'password' => null,
            'timeout' => 500,
        ];

        $this->_db = (string)($db ?: ($conf['db'] ?: 'test'));
        $option = [
            'authSource' => $this->_db,
            'username' => $conf['username'],
            'password' => $conf['password'],
            'socketTimeoutMS' => $conf['timeout'],
        ];
        if ($option['socketTimeoutMS'] < 500) $option['socketTimeoutMS'] = 500;
        if (is_null($option['username']) or empty($option['username'])) {
            unset($option['username'], $option['password']);
        }
        if ($conf['port'] == 0) {
            $this->_conn = new Manager("mongodb://" . rawurlencode($conf['host']), $option);
        } else {
            $this->_conn = new Manager("mongodb://{$conf['host']}:{$conf['port']}", $option);
        }
    }

    /**
     * @param null $table
     * @return $this
     * MongoDB7选择库表是用[库名.表名]的方式
     */
    public function table($table = null): Mongodb
    {
        $this->_table = $this->_db . '.' . ($table ?: 'test');
        $this->_where = array();
        $this->_select = null;
        $this->_order = ['_id' => -1];
        return $this;
    }

    /**
     * @param array $value
     * @param bool $batch 批量插入
     * @return array|mixed
     */
    public function insert(array $value, bool $batch = false)
    {
        if (!$this->_table) throw new Error('未指定表名', 1);
        $bulk = new BulkWrite;
        $writeConcern = new WriteConcern(WriteConcern::MAJORITY, self::_TIME_OUT);

        if ($batch) {
            return array_map(array($this, 'insert'), $value);
        } else {
            $_id = $bulk->insert($value);
            $this->_conn->executeBulkWrite($this->_table, $bulk, $writeConcern);
            $_id = json_decode(json_encode($_id), true);
            return $_id['$oid'] ?? $_id;
        }
    }

    /**
     * @param array $value
     * @param string $action ，操作方式：$set赋值，$inc为数值增减
     * @return int
     */
    public function update(array $value, string $action = '$set'): int
    {
        $bulk = new BulkWrite;
        $bulk->update($this->builder_where(), [$action => $value], ['multi' => true, 'upsert' => false]);

        $writeConcern = new WriteConcern(WriteConcern::MAJORITY, self::_TIME_OUT);
        $this->_conn->executeBulkWrite($this->_table, $bulk, $writeConcern);
        return $bulk->count();
    }


    /**
     * @param int $limit
     * @return int
     */
    public function delete(int $limit = 0): int
    {
        $bulk = new BulkWrite;
        $bulk->delete($this->builder_where(), ['limit' => $limit]);   // limit 为 0 时，删除所有匹配数据
        $writeConcern = new WriteConcern(WriteConcern::MAJORITY, self::_TIME_OUT);
        $this->_conn->executeBulkWrite($this->_table, $bulk, $writeConcern);
        return $bulk->count();
    }

    /**
     * @param $key
     * @param array ...$val
     * @return $this
     */
    public function where_in($key, ...$val): Mongodb
    {
        if (is_array($val[0])) $val = $val[0];
        $this->_where[$key] = ['$in' => array_values($val)];
        return $this;
    }

    /**
     * @param $key
     * @param null $type
     * @param null $val
     * @return $this
     */
    public function where($key, $type = null, $val = null): Mongodb
    {
        //直接指定全部条件
        if (is_array($key)) {
            $this->_where += $key;
            return $this;
        }

        $oid = strval($val ?: $type);

        //_id合法性检查，并同时转换为ObjectID
        if ($key === '_id' and preg_match('/^[a-f0-9]{24}$/i', $oid)) {
            $this->_where['_id'] = new ObjectID($oid);
            return $this;
        }
        //完全等于
        if ($val === null) {
            $this->_where[$key] = $type;
            return $this;
        }
        //无任何条件
        if ($key === '*') {
            $this->_where = '*';
            return $this;
        }
//
//        if (in_array($t = substr($key, -2), $this->filter)) {
//            list($key, $type) = [substr($key, 0, -2), $this->filter[$t]];
//        } else if (in_array($t = substr($key, -1), $this->filter)) {
//            list($key, $type) = [substr($key, 0, -1), $this->filter[$t]];
//        }

        $type = $this->filter[$type] ?? '$in';
        if (($type === '$in' or $type === '$nin') and !is_array($val)) $val = [$val];

        if (isset($this->_where[$key])) {
            $this->_where[$key] += [$type => $val];
        } else {
            $this->_where[$key] = [$type => $val];
        }
        return $this;
    }

    /**
     * 正则表达式方式匹配
     * @param $key
     * @param $patten
     * @param string $flags
     * @return $this
     */
    public function preg($key, $patten, string $flags = 'is'): Mongodb
    {
        $this->_where[$key] = new Regex(trim($patten, '/'), $flags);
        return $this;
    }

    /**
     * 模糊匹配
     * @param $key
     * @param array ...$patten
     * @return $this
     */
    public function like($key, ...$patten): Mongodb
    {
        $pnt = $this->replace_patten(implode(',', $patten));
        $this->_where[$key] = new Regex("({$pnt})", 'is');
        return $this;
    }

    /**
     * 一组条件或
     * @param $array
     * @return $this
     * ->where_or(['title' => $key, 'text' => ['like'=>'abc']]);
     */
    public function where_or($array): Mongodb
    {
        $or = array();
        foreach ($array as $key => &$value) {
            if (!is_array($value)) {
                $or[$key] = $value;
            } else {
                foreach ($value as $type => &$val) {
                    if ($type === 'like') {//like
                        $val = $this->replace_patten($val);
                        $or[] = [$key => new Regex("({$val})", 'is')];

                    } elseif ($type === 'preg') {//指定正则
                        $or[] = [$key => new Regex(trim($val, '/'), 'is')];

                    } else {//其他大于小于等
                        $type = $this->filter[$type] ?? $type;
                        $or[] = [$key => [$type => $val]];
                    }
                }
            }
        }
        $this->_where['$or'] = $or;
        return $this;
    }

    private function replace_patten($val)
    {
        $val = preg_quote($val, '/');
        $val = str_replace(['.', ',', '_', '，', '。', '    ', ' ', '#', '$', '@', '&'], '|', $val);
        $val = str_replace(['|||', '|||', '||', '||', '||'], '|', $val);
        return $val;
    }


    /**
     * @param ...$key
     * @return $this
     */
    public function select(...$key): Mongodb
    {
        if ($key[0] === '*') $key = null;
        else if (is_array($key[0])) $key = $key[0];
        $this->_select = $key;
        return $this;
    }

    /**
     * 统计总数
     * @return int
     */
    public function count(): int
    {
        $where = $this->builder_where();
        $options = [
            'projection' => ['$count' => 1]
        ];
        $query = new Query($where, $options);
        $RS = $this->_conn->executeQuery($this->_table, $query)->toArray();
        return count($RS);
    }

    /**
     * @return array
     */
    private function builder_where(): array
    {
        $where = $this->_where;
        if (!$where or $where == '*' or !is_array($where)) return [];
        return $where;
    }

    /**
     * @param $key
     * @param string $asc
     * @return $this
     */
    public function order($key, string $asc = 'asc'): Mongodb
    {
        //1=asc,-1=desc
        $sort = function ($type) {
            return in_array($type, ['desc', -1]) ? -1 : 1;
        };
        if (is_array($key)) {
            foreach ($key as $k => &$v) $key[$k] = $sort($v);
            $this->_order = $key + $this->_order;
        } else {
            $this->_order = [$key => $sort($asc)] + $this->_order;
        }
        return $this;
    }

    public function skip($num): Mongodb
    {
        $this->_skip = intval($num);
        return $this;
    }

    public function limit($num): Mongodb
    {
        $this->_limit = intval($num);
        return $this;
    }

    /**
     * @return array|mixed
     */
    public function row()
    {
        return $this->get(0, 1);
    }

    /**
     * @param int $skip
     * @param null $limit
     * @return array|mixed
     */
    public function rows(int $skip = 0, $limit = null)
    {
        return $this->get($skip, $limit);
    }

    /**
     * @param int $skip
     * @param null $limit
     * @return array|mixed
     */
    public function get(int $skip = 0, $limit = null)
    {
        if ($limit === null) {
            list($skip, $limit) = [0, $skip];
        }
        $options = [
            'sort' => $this->_order,
            'skip' => $skip ?: $this->_skip,
            'limit' => $limit ?: $this->_limit,
        ];
        if (empty($this->_table)) throw new Error('未指定表名', 1);

        //过滤字段=0，仅过滤过该段，但若有任一个=1，则不显示其他没定义的字段
        if (!!$this->_select) {
            $options['projection'] = array_combine($this->_select, array_fill(0, count($this->_select), 1));
        }
        $where = $this->builder_where();
        $this->_build_where = $where;

        $query = new Query($where, $options);
        $RS = $this->_conn->executeQuery($this->_table, $query)->toArray();
        $value = array();
        foreach ($RS as &$rs) {
            $array = json_decode(json_encode($rs, 256), true);
            $array['_id'] = $rs->_id->__toString();
            $value[] = $array;
        }
        if (!$value) return [];
        return $limit === 1 ? $value[0] : $value;
    }

}