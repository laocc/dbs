<?php
use \laocc\db\Apcu;

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


    public function add($key, $num)
    {
        return $this->conn->add($key, $num);
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