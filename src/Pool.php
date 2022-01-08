<?php
declare(strict_types=1);

namespace esp\dbs;

use esp\core\Configure;
use esp\dbs\mysql\Cache;
use esp\dbs\mysql\Mysql;
use esp\dbs\redis\Redis;

final class Pool
{
    /**
     * @var $config Configure
     */
    public $config;

    private $_mysql;
    private $_cache;
    private $_redis;

    public function __construct(Configure $configure)
    {
        $this->config = &$configure;
    }

    public function redis(int $dbIndex)
    {
        if (is_null($this->_redis)) {
            return $this->_redis = new Redis($this, $dbIndex);
        }
        return $this->_redis;
    }

    public function mysql(string $table): Mysql
    {
        if (is_null($this->_mysql)) {
            return $this->_mysql = new Mysql($this, $table);
        }
        return $this->_mysql;
    }

    public function cache(string $hashKey): Cache
    {
        if (is_null($this->_cache)) {
            $this->_cache = new Cache($this->config->_Redis->redis, $hashKey);
        }
        return $this->_cache;
    }


}