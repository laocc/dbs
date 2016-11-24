<?php
namespace demo;

use laocc\dbs\Apcu;
use laocc\dbs\Memcache;
use laocc\dbs\Memcached;
use laocc\dbs\Redis;
use laocc\dbs\Yac;

/**
 * Mary 连续 set 10000次耗时：
 * ===========================
 * Memcache:     0.774010181427
 * Memcached:    0.67804718017578
 * Apcu:         0.011286020278931
 * Redis:        0.41659498214722
 * Yac:          0.0076091289520264
 *
 * Mary 连续 get 10000次耗时：
 * ===========================
 * Memcache:     0.62700915336609
 * Memcached:    0.65111303329468
 * Apcu:         0.0080101490020752
 * Redis:        0.40621709823608
 * Yac:          0.0068061351776123
 *
 *
 * Class SpeedController
 * @package demo
 */
class SpeedController
{
    private $number;
    private $config;
    private $ttl = 1;

    public function __construct($config, $action, $number)
    {
        $this->number = $number;
        $this->config = $config;
        $this->action = $action;

        echo "\n\nMary 连续 {$action} {$this->number}次耗时：\n===========================";
    }

    public function memcache()
    {
        $obj = new Memcache($this->config['memcache']);
        $a = microtime(true);
        $obj->table('speed');
        for ($i = 1; $i <= $this->number; $i++) {
            if ($this->action === 'set') {
                $obj->set("speed.{$i}", 1, $this->ttl);
            } else {
                $obj->get("speed.{$i}");
            }
        }
        $b = microtime(true) - $a;
        echo "\nMemcache:\t{$b}";
    }

    public function memcached()
    {
        $obj = new Memcached($this->config['memcached']);
        $a = microtime(true);
        $obj->table('speed');
        for ($i = 1; $i <= $this->number; $i++) {
            if ($this->action === 'set') {
                $obj->set("speed.{$i}", 1, $this->ttl);
//                $obj->set("speed.{$i}", 1);
            } else {
                $obj->get("speed.{$i}");
            }
        }
        $b = microtime(true) - $a;
        echo "\nMemcached:\t{$b}";
    }

    public function apcu()
    {
        $obj = new Apcu('speed');
        $a = microtime(true);
        $obj->table('speed');
        for ($i = 1; $i <= $this->number; $i++) {
            if ($this->action === 'set') {
                $obj->set("speed.{$i}", 1, $this->ttl);
            } else {
                $obj->get("speed.{$i}");
            }
        }
        $b = microtime(true) - $a;
        echo "\nApcu:\t\t{$b}";
    }

    public function redis()
    {
        $obj = new Redis($this->config['redis']);
        $a = microtime(true);
        $obj->table('speed');
        for ($i = 1; $i <= $this->number; $i++) {
            if ($this->action === 'set') {
                $obj->set("speed.{$i}", 1, $this->ttl);
            } else {
                $obj->get("speed.{$i}");
            }
        }
        $b = microtime(true) - $a;
        echo "\nRedis:\t\t{$b}";
    }

    public function yac()
    {
        $obj = new Yac('speed');
        $a = microtime(true);
        $obj->table('speed');
        for ($i = 1; $i <= $this->number; $i++) {
            if ($this->action === 'set') {
                $obj->set("speed.{$i}", 1, $this->ttl);
            } else {
                $obj->get("speed.{$i}");
            }
        }
        $b = microtime(true) - $a;
        echo "\nYac:\t\t{$b}";
    }
}