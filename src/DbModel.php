<?php
declare(strict_types=1);

namespace esp\dbs;

use esp\error\Error;
use esp\core\Library;
use esp\dbs\mongodb\Mongodb;
use esp\dbs\mysql\Builder;
use esp\dbs\mysql\Mysql;
use esp\dbs\redis\Redis;
use esp\dbs\redis\RedisHash;
use esp\dbs\sqlite\Sqlite;
use esp\dbs\yac\Yac;
use esp\helper\library\Paging;

/**
 * 非esp框架，可以自行实现此类，不需要扩展自esp\core\Library
 *
 * 1，在类中直接引用：use esp\dbs\DbModel
 * 2，在类中实现本类中的this->Pool()方法，返回\esp\Pool实例
 *      $this->_controller->_pool，建议是控制器中定义的一个变量
 *      控制器实例只会有一个，而Model不一定，所以，Pool要尽量保证在不同Model中是同一个实例对象
 *      new Pool($conf);中的$conf是包含mysql,redis等信息的数组
 *
 *
 * 以下方法调用的都是Mysql中的方法，由__call转发
 *
 * @method int|bool|string|array insert(...$params) 执行插入
 * @method int|bool|string|array delete(...$params) 执行删除
 * @method int|bool|string update(...$params) 执行更新
 *
 * @method Array call(...$params) 执行存储过程
 *
 * @method Array get(...$params) 读取一条记录
 * @method Array all(...$params) 读取多条记录
 * @method Array list(...$params) 读取多条记录，分页
 * @method Array count(...$params) 统计总数
 * @method Array rand(...$params) 随机取x条
 *
 * @method bool|Builder trans(...$params) 启动一个事务
 *
 * @method Mysql query(...$params) 直接执行SQL
 * @method Mysql cache(...$params) 启用缓存
 * @method Mysql decode(...$params) 输入解码字段
 * @method Mysql select(...$params) 选择字段
 * @method Mysql join(...$params) Join表
 *
 * @method Mysql paging(...$params) 设置分页
 * @method Mysql pagingIndex(...$params) 设置分页码
 * @method Mysql pagingSize(...$params) 设置分页每页记录数
 *
 * @property Paging $paging 控制器或Library子类中可以直接用：$this->_pool->paging，$this->_controller->_pool->paging
 * @property String $table 当前模块表名
 *
 * Class ModelPdo
 * @package esp\core
 */

abstract class DbModel extends Library
{
    public string $_dbs_label_ = '这只是一个标识，仅用于在Library中识别这是引用自DbModel的类，并创建_pool对象';

    private array $alias = [
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
    final public function __call($name, $arguments)
    {
        if (isset($this->alias[$name])) $name = $this->alias[$name];
        $mysql = $this->Mysql();
        if (method_exists($mysql, $name) and is_callable([$mysql, $name])) {
            return call_user_func_array([$mysql, $name], $arguments);
        }

        throw new Error("MYSQL::{$name}() methods not exists.");
    }

    final public function __get($name)
    {
        switch ($name) {
            case 'paging':
                return $this->_controller->_pool->paging;
            case 'table':
                return $this->_controller->_pool->_mysql->_table;
            case 'id':
                return 0;
        }

        return null;
    }


    final public function __set($name, $value)
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
                $this->_controller->_pool->paging = new Paging($size, $index, $recode);
                break;
            case 'index':
                $paging = &$this->_controller->_pool->paging;
                if (is_null($paging)) $paging = new Paging();
                $paging->index(intval($value));
                break;
            case 'table':
                $this->_controller->_pool->_mysql->_table = $value;
                break;
        }
    }


    /**
     * @param string|null $table
     * @return Mysql
     */
    final public function Mysql(string $table = null): Mysql
    {
        if (is_null($table)) {
            if (isset($this->_table)) $table = $this->_table;
            if (!$table) {
                preg_match('/(.+\\\)?(\w+)Model$/i', get_class($this), $mac);
                if (!$mac) throw new Error('未指定表名');
                $table = $mac[2];
            }
        }

        return $this->_controller->_pool->mysql($table);
    }

    /**
     * 释放链接
     *
     * @param string|null $db
     * @return Bool
     */
    final public function release(string $db = 'mysql'): bool
    {
        return $this->_controller->_pool->release($db);
    }

    /**
     * @param string|null $table
     * @return Mysql
     */
    final public function table(string $table = null): Mysql
    {
        return $this->_controller->_pool->mysql($table);
    }

    /**
     * @param int $db
     * @param int $traceLevel
     * @return Redis
     */
    final public function Redis(int $db = 0, int $traceLevel = 0): Redis
    {
        return $this->_controller->_pool->redis($db);
    }

    /**
     * @param string $table
     * @return RedisHash
     */
    final public function Hash(string $table): RedisHash
    {
        return $this->Redis()->hash($table);
    }

    /**
     * @param string $table
     * @return Mongodb
     */
    final public function Mongodb(string $table): Mongodb
    {
        return $this->_controller->_pool->mongodb($table);
    }

    final public function sqlite(): Sqlite
    {
        return $this->_controller->_pool->sqlite();
    }

    final public function yac(string $table): Yac
    {
        return $this->_controller->_pool->yac($table);
    }

}