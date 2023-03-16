<?php
declare(strict_types=1);

namespace esp\dbs;

use esp\error\Error;
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
    public array $config;
    public Controller $controller;
    public Mysql $_mysql;
    public Paging $paging;
    public Counter $counter;

    private Cache $_cache;
    private Redis $_redis;
    private Mongodb $_mongodb;
    private Yac $_yac;
    private Apcu $_apcu;
    private Sqlite $_sqlite;

    public function __construct(array $config, Controller $controller)
    {
        $this->config = &$config;
        $this->controller = &$controller;
        if (isset($controller->_counter)) $this->counter = &$controller->_counter;
    }

    public function debug($data, int $lev = 1): void
    {
        $this->controller->_dispatcher->debug($data, $lev + 1);
    }

    public function error($data, int $lev = 1): void
    {
        $this->controller->_dispatcher->error($data, $lev + 1);
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
                unset($this->_mysql);
                break;
            case 'redis':
                unset($this->_redis);
                break;
            case 'mongodb':
                unset($this->_mongodb);
                break;
        }
        $this->debug("释放链接-{$db}");
        return true;
    }

    public function cache(): Cache
    {
        if (isset($this->_cache)) return $this->_cache;
        return $this->_cache = new Cache($this->controller->_config->_Redis);
    }

    public function mysql(string $table): Mysql
    {
        if (isset($this->_mysql)) return $this->_mysql->setTable($table);

        $conf = $this->config['mysql'] ?? null;
        if (is_null($conf)) throw new Error('创建Pool时指定的配置数据中没有(mysql)项');

        return $this->_mysql = (new Mysql($this, $conf, $table));
    }

    public function redis(int $dbIndex): Redis
    {
        if (isset($this->_redis)) return $this->_redis;
        $conf = $this->config['redis'] ?? null;
        if (is_null($conf)) throw new Error('创建Pool时指定的配置数据中没有(redis)项');

        return $this->_redis = new Redis($conf, $dbIndex);
    }

    public function mongodb(string $table): Mongodb
    {
        if (isset($this->_mongodb)) return $this->_mongodb;

        $conf = $this->config['mongodb'] ?? null;
        if (is_null($conf)) throw new Error('创建Pool时指定的配置数据中没有(mongodb)项');

        return $this->_mongodb = new Mongodb($this, $conf, $table);
    }

    public function sqlite(): Sqlite
    {
        if (isset($this->_sqlite)) return $this->_sqlite;

        $conf = $this->config['sqlite'] ?? null;
        if (is_null($conf)) throw new Error('创建Pool时指定的配置数据中没有(sqlite)项');

        return $this->_sqlite = new Sqlite($this, $conf);
    }

    public function yac(string $table): Yac
    {
        if (isset($this->_yac)) return $this->_yac;
        return $this->_yac = new Yac($table);
    }

    public function apcu(string $table): Apcu
    {
        if (isset($this->_apcu)) return $this->_apcu;
        return $this->_apcu = new Apcu($table);
    }


}