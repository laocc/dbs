<?php
declare(strict_types=1);

namespace esp\dbs\mysql;

use \Redis;

final class Cache
{
    private string $hashKey;
    private string $table = '';
    private Redis $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = &$redis;
        $this->hashKey = _UNIQUE_KEY . '_MYSQL_CACHE_';
    }

    /**
     * @param string $table
     * @return $this
     */
    public function table(string $table): Cache
    {
        $this->table = $table;
        return $this;
    }

    public function get(string $key)
    {
        return $this->redis->hGet($this->hashKey, "{$this->table}_{$key}");
    }

    /**
     * 整型增加
     *
     * @param string $key
     * @param int $value
     * @return int
     */
    public function incr(string $key, int $value = 1): int
    {
        return (int)$this->redis->hIncrBy($this->hashKey, "{$this->table}_{$key}", $value);
    }

    /**
     * @param string $key
     * @param $value
     * @return int
     */
    public function set(string $key, $value): int
    {
        return (int)$this->redis->hSet($this->hashKey, "{$this->table}_{$key}", $value);
    }

    /**
     * @param string $key
     * @return int
     */
    public function del(string $key): int
    {
        return (int)$this->redis->hDel($this->hashKey, "{$this->table}_{$key}");
    }

    /**
     * 与get不同之处：
     * get为直接指定缓存key读取，与set()对应
     * read根据sql查询时where作为键，这是Mysql类中调用的方法，与save()对应
     *
     * @param array $where
     * @return false|string|null
     */
    public function read(array $where)
    {
        $mdKey = $this->table . '_' . sha1(var_export($where, true));
        return $this->redis->hGet($this->hashKey, $mdKey);
    }

    /**
     * @param array $where
     * @param $data
     * @return int
     */
    public function save(array $where, $data): int
    {
        $mdKey = $this->table . '_' . sha1(var_export($where, true));
        return (int)$this->redis->hSet($this->hashKey, $mdKey, $data);
    }


    /**
     * key存在于where，即删除符合该key的值
     *
     * @param array $where
     * @return int
     */
    public function delete(array ...$where): int
    {
        $mdKey = [];
        foreach ($where as $val) {
            if (empty($val)) continue;
            if (is_array($val)) {
                foreach ($val as $k => $v) {
                    $mdKey[] = $this->table . '_' . sha1(var_export([$k => $v], true));
                }
            } else {
                $mdKey[] = $this->table . '_' . sha1(var_export($val, true));
            }
        }
        if (!empty($mdKey)) {
            return (int)$this->redis->hDel($this->hashKey, ...$mdKey);
        }
        return 0;
    }

}