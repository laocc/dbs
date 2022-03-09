<?php
declare(strict_types=1);

namespace esp\dbs\apcu;

use esp\dbs\library\KeyValue;
use esp\dbs\Pool;

class Apcu implements KeyValue
{
    const _TTL = 0;
    private $table;
    private $pool;

    public function __construct(Pool $pool, string $table)
    {
        $this->pool = &$pool;
        $this->table = $table;
    }

    public function table(string $table): Apcu
    {
        $this->table = $table;
        return $this;
    }

    /**
     * 根据旧值换新值
     * @param $key
     * @param $old
     * @param $new
     * @return bool
     */
    public function cas($key, $old, $new)
    {
        return apcu_cas("{$this->table}_{$key}", $old, $new);
    }

    /**
     * 添加一条
     *
     * @param string $key
     * @param $value
     * @param int $ttl
     * @param bool $update
     * @return array|bool|void
     * 当$update=true:若不存在则创建
     * 当$update=false:若存在则失败
     */
    public function set(string $key, $value, int $ttl = self::_TTL, bool $update = true)
    {
        if (is_bool($ttl)) list($ttl, $update) = [self::_TTL, $ttl];
        if ($update) {
            return apcu_store("{$this->table}_{$key}", $value, $ttl);
        } else {
            return apcu_add("{$this->table}_{$key}", $value, $ttl);
        }
    }


    /**
     * 读取
     * @param string $key
     * @param null $success
     * @return array|bool|mixed|void
     */
    public function get(string $key, &$success = null)
    {
        return apcu_fetch("{$this->table}_{$key}", $success);
    }

    /**
     * 删除一个或一批值
     * @param string ...$keys
     * @return bool|string[]|void
     */
    public function del(string ...$keys)
    {
        if (is_array($keys)) {
            foreach ($keys as &$key) $key = "{$this->table}_{$key}";
        } else {
            $keys = "{$this->table}_{$keys}";
        }
        return apcu_delete($keys);
    }

    /**
     * 查询Key是否存在
     * @param string|array $keys
     * @return bool|array
     */
    public function exists($keys)
    {
        if (is_array($keys)) {
            foreach ($keys as &$key) $key = "{$this->table}_{$key}";
        } else {
            $keys = "{$this->table}_{$keys}";
        }
        return apcu_exists($keys);
    }

    /**
     * 计数器
     * @param string $key
     * @param int $incurably
     * @param null $success 操作成功标识
     * @return bool|int|void
     */
    public function counter(string $key = 'count', int $incurably = 1, &$success = null)
    {
        if ($incurably >= 0) {
            return apcu_inc("{$this->table}_{$key}", $incurably, $success);
        } else {
            return apcu_dec("{$this->table}_{$key}", 0 - $incurably, $success);
        }
    }

    /**
     * 读取【指定表】的所有行键
     * @return array
     */
    public function keys(): array
    {
        $dump = apcu_cache_info();
        $keys = array_column($dump['cache_list'], 'info');
        $len = strlen($this->table) + 1;
        foreach ($keys as $i => &$key) {
            if (stripos($key, "{$this->table}_") === 0) {
                $key = substr($key, $len);
            } else {
                unset($keys[$i]);
            }
        }
        return $keys;
    }


    /**
     * 清空
     */
    public function flush()
    {
        return apcu_clear_cache();
    }

    /**
     * 查看当前存储信息
     * @param bool $limited
     * @return array|bool
     */
    public function info(bool $limited = false)
    {
        return apcu_sma_info($limited);
    }


    /**
     *  关闭
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function ping(): bool
    {
        return true;
    }
}