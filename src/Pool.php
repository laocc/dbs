<?php
declare(strict_types=1);

namespace esp\dbs;

use Error;
use esp\core\Controller;
use esp\dbs\apcu\Apcu;
use esp\dbs\mongodb\Mongodb;
use esp\dbs\mysql\Cache;
use esp\dbs\mysql\Mysql;
use esp\dbs\redis\Redis;
use esp\dbs\sqlite\Sqlite;
use esp\dbs\yac\Yac;
use esp\debug\Counter;
use esp\helper\library\Paging;

final class Pool
{
    public $config;
    /**
     * @var Controller
     */
    public $controller;

    /**
     * @var $_mysql Mysql
     */
    public $_mysql;
    /**
     * @var $_cache Cache
     */
    private $_cache;
    /**
     * @var $_redis Redis
     */
    private $_redis;
    /**
     * @var $_mongodb Mongodb
     */
    private $_mongodb;
    /**
     * @var $_yac Yac
     */
    private $_yac;
    /**
     * @var $_apcu Apcu
     */
    private $_apcu;
    /**
     * @var $_sqlite Sqlite
     */
    private $_sqlite;
    /**
     * @var $paging Paging
     */
    public $paging;
    /**
     * 计数器
     * @var $counter Counter
     */
    public $counter;

    public function __construct(array $config, Controller $controller)
    {
        $this->config = &$config;
        $this->controller = &$controller;
        $this->counter = &$controller->_counter;
    }

    public function debug($data, int $lev = 1): void
    {
        $this->controller->_dispatcher->debug($data, $lev + 1);
    }

    public function error($data, int $lev = 1): void
    {
        $this->controller->_dispatcher->error($data, $lev + 1);
    }

    public function cache(string $hashKey): Cache
    {
        if (is_null($this->_cache)) {
            $this->_cache = new Cache($this->redis(0)->redis, $hashKey);
        }
        return $this->_cache;
    }

    /**
     * 释放链接
     *
     * @param string $db
     * @return bool
     */
    public function release(string $db): bool
    {
        switch ($db) {
            case 'mysql':
                $this->_mysql = null;
                break;
            case 'redis':
                $this->_redis = null;
                break;
            case 'mongodb':
                $this->_mongodb = null;
                break;
        }
        $this->debug("释放链接-{$db}");
        return true;
    }

    public function mysql(string $table): Mysql
    {
        if (is_null($this->_mysql)) {
            $conf = $this->config['mysql'] ?? null;
            if (is_null($conf)) throw new Error('创建Pool时指定的配置数据中没有(mysql)项');

            return $this->_mysql = new Mysql($this, $conf, $table);
        }
        return $this->_mysql->setTable($table);
    }

    public function redis(int $dbIndex): Redis
    {
        if (is_null($this->_redis)) {
            $conf = $this->config['redis'] ?? null;
            if (is_null($conf)) throw new Error('创建Pool时指定的配置数据中没有(redis)项');

            return $this->_redis = new Redis($conf, $dbIndex);
        }
        return $this->_redis;
    }

    public function mongodb(string $table): Mongodb
    {
        if (is_null($this->_mongodb)) {
            $conf = $this->config['mongodb'] ?? null;
            if (is_null($conf)) throw new Error('创建Pool时指定的配置数据中没有(mongodb)项');

            return $this->_mongodb = new Mongodb($this, $conf, $table);
        }
        return $this->_mongodb;
    }

    public function sqlite(): Sqlite
    {
        if (is_null($this->_sqlite)) {
            $conf = $this->config['sqlite'] ?? null;
            if (is_null($conf)) throw new Error('创建Pool时指定的配置数据中没有(sqlite)项');

            return $this->_sqlite = new Sqlite($this, $conf);
        }
        return $this->_sqlite;
    }

    public function yac(string $table): Yac
    {
        if (is_null($this->_yac)) {
            return $this->_yac = new Yac($table);
        }
        return $this->_yac;
    }

    public function apcu(string $table): Apcu
    {
        if (is_null($this->_apcu)) {
            return $this->_apcu = new Apcu($table);
        }
        return $this->_apcu;
    }


}