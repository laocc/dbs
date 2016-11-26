<?php
namespace laocc\dbs;

use laocc\dbs\ext\KeyValue;

class Yac implements KeyValue
{
    const _TTL = 0;
    private $conn;

    public function __construct($table = null)
    {
        if ($table)
            $this->conn = new \Yac($table . '_');
    }

    /**
     * 指定表，也就是指定键前缀
     * @param $table
     * @return $this
     */
    public function table($table)
    {
        $this->conn = new \Yac($table . '_');
        return $this;
    }

    /**
     * 读取【指定表】的所有行键
     * @param $table
     * @return array
     */
    public function keys()
    {
        $dump = $this->conn->dump(100000);
        $keys = array_column($dump, 'key');
        $ttls = array_column($dump, 'ttl');
        $time = time();
        foreach ($keys as $index => &$key) {
            $i = strpos($key, '_');
            if ($i > 0) $key = substr($key, $i + 1);
            if ($ttls[$index] > 0 and $ttls[$index] < $time) {
                unset($keys[$index]);
            }
            $key .= '-' . $ttls[$index];
        }
        return $keys;
    }


    /**
     * 存入【指定表】【行键】【行值】
     * @param $table
     * @param $key
     * @param $array
     * @param int $ttl 生存期
     * @return bool
     */
    public function set($key, $array, $ttl = self::_TTL)
    {
        return $this->conn->set($key, $array, $ttl);
    }


    /**
     * 读取【指定表】的【行键】数据
     * @param $key
     * @return array|bool
     */
    public function get($key)
    {
        return $this->conn->get($key);
    }


    /**
     * 删除key
     * @param $key
     * @return bool
     */
    public function del($key)
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
     * @param int $incrby 可以是正数、负数，或0，=0时为读取值
     * @return bool
     */
    public function counter($key = 'count', $incrby = 1)
    {
        $val = $this->conn->get($key);
        if ($incrby === 0) return intval($val);
        return $this->conn->set($key, intval($val) + $incrby);
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
    public function ping()
    {
        return is_object($this->conn);
    }

}