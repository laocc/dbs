<?php
use \laocc\dbs\Apcu;

class ApcuModel
{
    private $conn;

    public function __construct($table)
    {
        $this->conn = new Apcu($table);
    }

    public function save($key, $value, $ttl = null)
    {
        return $this->conn
            ->set($key, $value, $ttl);
    }


    public function counter($key, $num)
    {
        return $this->conn->counter($key, $num);
    }


    public function read($key)
    {
        $val = $this->conn->get($key, $success);
        return $val;
    }


    public function keys()
    {
        return $this->conn
            ->keys();
    }


}