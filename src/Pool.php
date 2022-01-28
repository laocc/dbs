<?php
declare(strict_types=1);

namespace esp\dbs;

use Error;
use esp\dbs\mongodb\Mongodb;
use esp\dbs\mysql\Cache;
use esp\dbs\mysql\Mysql;
use esp\dbs\redis\Redis;

final class Pool
{
    public $config;

    private $_mysql;
    private $_cache;
    private $_redis;
    private $_mongodb;

    public function __construct(array $config)
    {
        $this->config = $config;
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

    public function mysql(string $table): Mysql
    {
        if (is_null($this->_mysql)) {
            $conf = $pool->config['mysql'] ?? null;
            if (is_null($conf)) throw new Error('创建Pool时指定的配置数据中没有(mysql)项');

            return $this->_mysql = new Mysql($this, $conf, $table);
        }
        return $this->_mysql;
    }

    public function mongodb(string $table): Mongodb
    {
        if (is_null($this->_mongodb)) {
            $conf = $pool->config['mongodb'] ?? null;
            if (is_null($conf)) throw new Error('创建Pool时指定的配置数据中没有(mongodb)项');

            return $this->_mongodb = new Mongodb($this, $conf, $table);
        }
        return $this->_mongodb;
    }

    public function cache(string $hashKey): Cache
    {
        if (is_null($this->_cache)) {
            $this->_cache = new Cache($this->redis(0)->redis, $hashKey);
        }
        return $this->_cache;
    }


}