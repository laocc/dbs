<?php
declare(strict_types=1);

namespace esp\dbs\library;

interface KeyValue
{

    /**
     * 指定表，也就是指定键前缀
     * @param string $table
     */
    public function table(string $table);

    /**
     * 读取【指定表】的所有行键
     * @return array
     */
    public function keys();


    /**
     * 存入【指定表】【行键】【行值】
     * @param string $key
     * @param $value
     * @param int $ttl 生存期
     * @return bool
     */
    public function set(string $key, $value, int $ttl = 0);


    /**
     * 读取【指定表】的【行键】数据
     * @param string $key
     * @return array|bool
     */
    public function get(string $key);


    /**
     * 删除key
     * @param string ...$key
     * @return bool
     */
    public function del(string ...$key);

    /**
     * 清空
     * @return mixed
     */
    public function flush();


    /**
     * 计数器，只可是整型，
     * >0   加
     * <0   减
     * =0   获取值
     * @param string $key 表名.键名，但这儿的键名要是预先定好义的
     * @param int $incurably 可以是正数、负数，或0，=0时为读取值
     * @return bool
     */
    public function counter(string $key = 'count', int $incurably = 1);

    /**
     *  关闭
     */
    public function close();

    /**
     * @return bool
     */
    public function ping(): bool;

}