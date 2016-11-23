<?php
use \laocc\dbs\Mongodb;

class MongodbModel
{
    private $conn;
    private $table;

    public function __construct($conf, $table = null)
    {
        $this->conn = new Mongodb($conf);
        $this->table = $table ?: 'test';
    }

    public function save(array $data)
    {
        return $this->conn
            ->table($this->table)
            ->insert($data);
    }


    public function read($limit)
    {
        return $this->conn
            ->table($this->table)
            ->where('rand', '>', mt_rand())
            ->limit($limit)
            ->get();
    }


}