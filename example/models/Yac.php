<?php
use \laocc\db\Yac;

class YacModel
{
    private $conn;

    public function __construct($conf)
    {
        $this->conn = new Yac($conf);
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


    public function keys()
    {
        return $this->conn
            ->table('esp')
            ->keys();
    }


}