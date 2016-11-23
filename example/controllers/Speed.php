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
 * Memcached:    0.046806812286377
 * Apcu:         0.014969825744629
 * Redis:        0.39833498001099
 * Yac:          0.01001501083374
 *
 * Mary 连续 get 10000次耗时：
 * ===========================
 * Memcached:    0.025343894958496
 * Apcu:         0.010867118835449
 * Redis:        0.38857293128967
 * Yac:          0.0080459117889404
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