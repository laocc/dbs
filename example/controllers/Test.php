<?php
namespace demo;

class TestController
{
    private $config;
    private $temp;
    private $ttl;

    public function __construct($config)
    {
        $this->config = $config;
        $this->temp = date('Y-m-d H:i:s');
        $this->ttl = 10;
    }

    public function mysql()
    {
        $mysql = new \MysqlModel($this->config['mysql']);
        var_dump($mysql);
    }

    public function mongodb()
    {
        $mongo = new \MongodbModel($this->config['mongodb'], 'demo');
        $val = print_r([
            'star' => $mongo->read(2),
            'save' => $mongo->save(['rand' => mt_rand(), 'time' => "插入时间：{$this->temp}"]),
            'read' => $mongo->read(2),
        ], true);
        echo "<pre>MongoDB:{$val}</pre>";
    }

    public function redis()
    {
        $redis = new \RedisModel($this->config['redis']);
        $val = print_r([
            'star' => $redis->read('tmp'),
            'save' => $redis->save('tmp', $this->temp, $this->ttl),
            'read' => $redis->read('tmp'),
        ], true);
        echo "<pre>Redis:{$val}</pre>";
    }


    public function memcached()
    {
        $med = new \MemcachedModel($this->config['memcached']);
        $val = print_r([
            'star' => $med->read('tmp'),
            'save' => $med->save('tmp', $this->temp, $this->ttl),
            'read' => $med->read('tmp'),
        ], true);
        echo "<pre>Memcached:{$val}</pre>";
    }


    public function memcache()
    {
        $me = new \MemcacheModel($this->config['memcache']);
        $val = print_r([
            'star' => $me->read('tmp'),
            'save' => $me->save('tmp', $this->temp, $this->ttl),
            'read' => $me->read('tmp'),
        ], true);
        echo "<pre>Memcache:{$val}</pre>";
    }

    public function yac()
    {
        $yac = new \YacModel('Test');
        $yac->save('tmp1', mt_rand(), 10);
        $yac->save('tmp2', mt_rand(), 10);

        $val = print_r([
            'star' => $yac->read('tmp'),
            'save' => $yac->save('tmp', $this->temp, $this->ttl),
            'read' => $yac->read('tmp'),
            'keys' => $yac->keys(),
        ], true);
        echo "<pre>Yac:{$val}</pre>";
    }


    public function apcu()
    {
        $apc = new \ApcuModel('Test');
        $val = print_r([
            'star' => $apc->read('tmp'),
            'save' => $apc->save('tmp', $this->temp, $this->ttl),
            'read' => $apc->read('tmp'),
            'keys' => $apc->keys(),
        ], true);
        echo "<pre>Apcu:{$val}</pre>";
    }


}