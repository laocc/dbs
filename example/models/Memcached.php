<?php
use laocc\dbs\Memcached;

class MemcachedModel
{
    private $conn;

    public function __construct($conf)
    {
        $this->conn = new Memcached($conf);
    }

    public function save($key, $value, $ttl = null)
    {
        return $this->conn
            ->table('med')
            ->set($key, $value, $ttl);
    }


    public function read($key)
    {
        return $this->conn
            ->table('med')
            ->get($key);
    }


}