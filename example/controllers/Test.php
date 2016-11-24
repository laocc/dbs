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
        $this->ttl = 5;
    }

    public function mysql()
    {
        if (!class_exists('pdo')) {
            echo '<h5>pdo扩展没关安装或没有加载，本程序操作mysql基于PDO</h5>';
            return;
        }

        $mysql = new \MysqlModel($this->config['mysql']);
        var_dump($mysql);
    }

    public function mongodb()
    {
        if (!class_exists('\MongoDB\Driver\Manager')) {
            echo '<h5>mongodb扩展没关安装或没有加载</h5>';
            return;
        }

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
        if (!class_exists('redis')) {
            echo '<h5>redis扩展没关安装或没有加载</h5>';
            return;
        }

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
        if (!class_exists('Memcached')) {
            echo '<h5>memcached扩展没关安装或没有加载</h5>';
            return;
        }

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
        if (!class_exists('memcache')) {
            echo '<h5>memcache扩展没关安装或没有加载</h5>';
            return;
        }

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
        if (!class_exists('yac')) {
            echo '<h5>Yac扩展没关安装或没有加载</h5>';
            return;
        }
        $yac = new \YacModel('Test');
        for ($i = 1; $i <= 3; $i++)
            $yac->save("tmp.{$i}", mt_rand(), $this->ttl);

        $yac->kill("tmp.2");
        $yac->save("tmp.4", mt_rand());
        $yac->save("tmp.5", mt_rand(), 0);
        $yac->save("tmp.6", mt_rand(), $this->ttl);

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
        if (!function_exists('apcu_fetch')) {
            echo '<h5>apcu扩展没关安装或没有加载</h5>';
            return;
        }

        $apc = new \ApcuModel('Test');
        $apc->save('tmp_1', $this->temp, $this->ttl);
        $apc->save('tmp_2', $this->temp, $this->ttl);
        $val = print_r([
            'star' => $apc->read('tmp'),
            'save' => $apc->save('tmp', $this->temp, $this->ttl),
            'read' => $apc->read('tmp'),
            'keys' => $apc->keys(),
        ], true);
        echo "<pre>Apcu:{$val}</pre>";
    }


}