<?php
use laocc\db\Redis;

class RedisModel
{
    private $conn;

    public function __construct($conf)
    {
        $this->conn = new Redis($conf);
    }


    public function save($key, $value, $ttl = null)
    {
        return $this->conn
            ->table('esp')
            ->set($key, $value, $ttl);
    }


    public function read($key)
    {
        return $this->conn
            ->table('esp')
            ->get($key);
    }


}