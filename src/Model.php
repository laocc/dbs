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

    public function Redis(int $db = 0)
    {
        return $this->Pool()->redis($db);
    }

    public function Hash()
    {
        $this->Pool();

    }

    public function Mongodb()
    {
        $this->Pool();

    }

    public function Sqlite()
    {
        $this->Pool();

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

    private function Pool(): Pool
    {
        if (is_null($this->_controller->_pool)) {
            $this->_controller->_pool = new Pool($this->_controller->_config);
        }
        return $this->_controller->_pool;
    }


}