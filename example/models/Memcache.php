<?php
use \laocc\dbs\Memcache;

class MemcacheModel
{
    private $conn;

    public function __construct($conf)
    {
        $this->conn = new Memcache($conf);
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