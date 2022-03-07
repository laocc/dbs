<?php

namespace esp\dbs\kernel;

use esp\dbs\mongodb\Mongodb;
use esp\dbs\mysql\Builder;
use esp\dbs\mysql\Mysql;
use esp\dbs\library\Paging;
use esp\dbs\redis\Redis;
use esp\dbs\redis\RedisHash;


/**
 * 以下方法调用的都是Mysql中的方法，由__call转发
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
 * @method bool|Builder trans(...$params) 启动一个事务
 *
 * @method Mysql select(...$params) 选择字段
 * @method Mysql join(...$params) Join表
 *
 * @method Mysql paging(...$params) 设置分页
 * @method Mysql pagingIndex(...$params) 设置分页码
 * @method Mysql pagingSize(...$params) 设置分页每页记录数
 *
 * Class ModelPdo
 * @package esp\core
 */
trait DbsKernel
{
    /**
     * @var Paging $paging 分页对象，实际上这个变量是无值的的，这里定义一下，只是为了让调用的地方不显示异常，最终调用时还是要经过__get()处理
     */
    protected $paging;

    private $alias = [
        'pagingSet' => 'paging',
        'pageSet' => 'paging',
        'trans_cache' => 'delete_cache',
    ];

    /**
     * 针对Mysql
     *
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (isset($this->alias[$name])) $name = $this->alias[$name];
        return $this->Mysql()->{$name}(...$arguments);
    }

    public function __get($name)
    {
        switch ($name) {
            case 'paging':
                return $this->Pool()->paging;
            case 'table':
                return $this->Pool()->_mysql->_table;
            case 'id':
                return 0;
        }

        return null;
    }


    public function __set($name, $value)
    {
        switch ($name) {
            case 'paging':
                $size = 10;
                $index = 1;
                $recode = null;
                if (is_int($value)) $size = $value;
                else if (is_array($value)) {
                    $size = $value[0] ?? 10;
                    $index = $value[1] ?? 1;
                    $recode = $value[2] ?? null;
                }
                $this->Pool()->paging = new Paging($size, $index, $recode);
                break;
            case 'index':
                $paging = &$this->Pool()->paging;
                if (is_null($paging)) $paging = new Paging();
                $paging->index(intval($value));
                break;
            case 'table':
                $this->Pool()->_mysql->_table = $value;
                break;
        }
    }


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
    public function Hash(string $table): RedisHash
    {
        return $this->Redis()->hash($table);
    }

    /**
     * @param string $table
     * @return Mongodb
     */
    public function Mongodb(string $table): Mongodb
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


}