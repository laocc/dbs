<?php
use \laocc\dbs\Yac;

class YacModel
{
    private $conn;

    public function __construct($conf)
    {
        $this->conn = new Yac($conf);
    }

    public function save($key, $value, $ttl = null)
    {
        return $this->conn->set($key, $value, $ttl);
    }


    public function kill($key)
    {
        return $this->conn->del($key);
    }

    public function read($key)
    {
        return $this->conn->get($key);
    }


    public function keys()
    {
        return $this->conn->keys();
    }


}