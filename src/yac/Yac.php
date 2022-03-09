<?php
declare(strict_types=1);

namespace esp\dbs\yac;

use esp\dbs\library\KeyValue;
use esp\dbs\Pool;

class Yac implements KeyValue
{
    const _TTL = 0;
    private $conn;
    private $table;
    private $pool;

    public function __construct(string $table = null)
    {
//        $this->pool = &$pool;
        if ($table) $this->conn = new \Yac($table . '_');
        $this->table = $table;
    }

    /**
     * 指定表，也就是指定键前缀
     * @param string $table
     * @return $this
     */
    public function table(string $table): Yac
    {
        $this->conn = new \Yac($table . '_');
        $this->table = $table;
        return $this;
    }

    /**
     * 读取【指定表】的所有行键
     * @param $table
     * @return array
     */
    public function keys(): array
    {
        $dump = $this->conn->dump(100000);
        $keys = array_column($dump, 'key');
        $ttls = array_column($dump, 'ttl');
        $value = array();
        foreach ($keys as $index => &$key) {
            $title = explode('_', $key);
            if ($title[0] !== $this->table) continue;
            if ($ttls[$index] > 0 and $ttls[$index] < time()) {
                unset($keys[$index]);
                continue;
            }
            $value[$title[0]][$title[1]] = $this->get($title[1]);
        }
        return $value;
    }


    /**
     * 存入【指定表】【行键】【行值】
     * @param $table
     * @param $key
     * @param $array
     * @param int $ttl 生存期
     * @return bool
     */
    public function set(string $key, $array, int $ttl = self::_TTL)
    {
        return $this->conn->set($key, $array, $ttl);
    }


    /**
     * 读取【指定表】的【行键】数据
     * @param $key
     * @return array|bool
     */
    public function get(string $key)
    {
        return $this->conn->get($key);
    }


    /**
     * 删除key
     * @param $key
     * @return bool
     */
    public function del(string ...$key)
    {
        return $this->conn->delete($key);
    }

    /**
     * 清空
     * @return mixed
     */
    public function flush()
    {
        return $this->conn->flush();
    }


    /**
     * 计数器，只可是整型，
     * >0   加
     * <0   减
     * =0   获取值
     * @param string $TabKey 表名.键名，但这儿的键名要是预先定好义的
     * @param int $incurably 可以是正数、负数，或0，=0时为读取值
     * @return bool
     */
    public function counter(string $key = 'count', int $incurably = 1)
    {
        $val = $this->conn->get($key);
        if ($incurably === 0) return intval($val);
        return $this->conn->set($key, intval($val) + $incurably);
    }

    public function info()
    {
        return $this->conn->info();
    }


    /**
     *  关闭
     */
    public function close()
    {
        $this->conn = null;
    }


    /**
     * @return bool
     */
    public function ping(): bool
    {
        return is_object($this->conn);
    }

}