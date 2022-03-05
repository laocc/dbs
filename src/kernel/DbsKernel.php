<?php

namespace esp\dbs\kernel;

use esp\dbs\mysql\Mysql;
use esp\dbs\redis\Redis;
use esp\dbs\redis\RedisHash;


/**
 * 以下方法调用的都是Mysql中的方法
 *
 * @method void insert(...$params) 执行插入
 * @method void delete(...$params) 执行删除
 * @method void update(...$params) 执行更新
 *
 * @method Array call(...$params) 执行存储过程
 *
 * @method Array get(...$params) 读取一条记录
 * @method Array all(...$params) 读取多条记录
 * @method Array list(...$params) 读取多条记录，分页
 *
 * @method Mysql paging(...$params) 设置分页
 * @method Mysql select(...$params) 选择字段
 * @method Mysql join(...$params) Join表
 *
 * Class ModelPdo
 * @package esp\core
 */
trait DbsKernel
{

    /**
     * @param string|null $table
     * @return Mysql
     */
    public function Mysql(string $table = null): Mysql
    {
        if (is_null($table)) {
            if (isset($this->_table)) $table = $this->_table;
            if (!$table) {
                preg_match('/(.+\\\)?(\w+)Model$/i', get_class($this), $mac);
                if (!$mac) throw new \Error('未指定表名');
                $table = $mac[2];
            }
        }

        return $this->Pool()->mysql($table);
    }

    /**
     * @param string|null $table
     * @return Mysql
     */
    final public function table(string $table = null): Mysql
    {
        return $this->Pool()->mysql($table);
    }

    /**
     * @param int $db
     * @param int $traceLevel
     * @return Redis
     */
    public function Redis(int $db = 0, int $traceLevel = 0): Redis
    {
        return $this->Pool()->redis($db);
    }

    /**
     * @param string $table
     * @return RedisHash
     */
    public function Hash(string $table)
    {
        return $this->Redis()->hash($table);
    }

    /**
     * @param string $table
     * @return \esp\dbs\mongodb\Mongodb
     */
    public function Mongodb(string $table)
    {
        return $this->Pool()->mongodb($table);
    }

    public function Sqlite()
    {
//        return $this->Pool()->Sqlite;
    }

    public function Yac()
    {
        $this->Pool();
    }


    /**
     * 针对Mysql
     *
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->Mysql()->{$name}(...$arguments);
    }


}