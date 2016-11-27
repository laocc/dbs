<?php
namespace laocc\dbs\ext;

interface KeyValue
{

    /**
     * 指定表，也就是指定键前缀
     * @param $table
     * @return $this
     */
    public function table($table);

    /**
     * 读取【指定表】的所有行键
     * @param $table
     * @return array
     */
    public function keys();


    /**
     * 存入【指定表】【行键】【行值】
     * @param $table
     * @param $key
     * @param $array
     * @param int $ttl 生存期
     * @return bool
     */
    public function set($key, $array, $ttl = 0);


    /**
     * 读取【指定表】的【行键】数据
     * @param $key
     * @return array|bool
     */
    public function get($key);


    /**
     * 删除key
     * @param $key
     * @return bool
     */
    public function del($key);

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
     * @param string $TabKey 表名.键名，但这儿的键名要是预先定好义的
     * @param int $incrby 可以是正数、负数，或0，=0时为读取值
     * @return bool
     */
    public function counter($key = 'count', $incrby = 1);

    /**
     *  关闭
     */
    public function close();

    /**
     * @return bool
     */
    public function ping();

}