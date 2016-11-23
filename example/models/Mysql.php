<?php
use \laocc\dbs\Mysql;

class MysqlModel
{
    private $conn;

    public function __construct($conf)
    {
        $this->conn = new Mysql($conf);
    }


    public function save($data)
    {
        return $this->conn->ping();

    }


}

