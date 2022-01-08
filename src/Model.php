<?php
declare(strict_types=1);

namespace esp\dbs;

use esp\core\Library;
use esp\dbs\mysql\Mysql;


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
abstract class Model extends Library
{

    /**
     * @var $_mysqlOBJ Mysql
     */
    private $_mysqlOBJ;


    /**
     * @param string|null $table
     * @return Mysql
     */
    public function Mysql(string $table = null): Mysql
    {
        return $this->table($table);
    }

    public function Redis()
    {

    }

    public function Hash()
    {

    }

    public function Mongodb()
    {

    }

    public function Sqlite()
    {

    }

    public function Yac()
    {

    }


    /**
     * 指定当前模型的表
     * 或，返回当前模型对应的表名
     * @param string|null $table
     * @return Mysql
     */
    final public function table(string $table = null): Mysql
    {
        if (is_null($table)) {
            if (isset($this->_table)) $table = $this->_table;
            if (!$table) {
                preg_match('/(.+\\\)?(\w+)Model$/i', get_class($this), $mac);
                if (!$mac) throw new \Error('未指定表名');
                $table = $mac[2];
            }
        }

        if (is_null($this->_mysqlOBJ)) {
            $this->_mysqlOBJ = new Mysql($table, $this->_controller->_pool);

        } else {
            $this->_mysqlOBJ->table($table);
        }

        return $this->_mysqlOBJ;
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
        return $this->table()->{$name}(...$arguments);
    }
}