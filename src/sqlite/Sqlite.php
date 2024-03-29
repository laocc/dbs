<?php

namespace esp\dbs\sqlite;

use PDO;
use esp\error\Error;
use esp\dbs\Pool;
use function esp\helper\mk_dir;

final class Sqlite
{
    private $conf;
    private $db;
    private $table;
    private $pool;

    public function __construct(Pool $pool, array $conf)
    {
        $this->pool = &$pool;
        $this->conf = $conf;
        if (!isset($this->conf['db'])) throw new Error('Sqlite库文件未指定');

        if (!file_exists($this->conf['db'])) {
            mk_dir($this->conf['db']);
            $fp = fopen($this->conf['db'], 'w');
            if (!$fp) throw new Error('Sqlite创建失败');
            fclose($fp);
        }
        $this->db = new PDO("sqlite:{$this->conf['db']}");
    }

    public function __destruct()
    {
        $this->db = null;
    }

    public function table(string $table): Sqlite
    {
        $this->table = $table;
        return $this;
    }

    public function create(array $data)
    {
        $filed = [];
        foreach ($data as $k => $type) {
            $filed[] = "{$k} {$type}";
        }
        return $this->db->exec('CREATE TABLE' . "{$this->table}(" . implode(',', $filed) . ')');
    }

    public function exec(string $sql)
    {
        return $this->db->exec($sql);
    }

    public function desc()
    {

    }

    public function where()
    {

    }

    public function get()
    {

    }

    public function list()
    {

    }

    public function all()
    {

    }

    public function update()
    {

    }

    public function delete()
    {

    }

    public function insert()
    {

    }

    function transaction(): bool
    {
//        sem_acquire($this->sem);
        return $this->db->beginTransaction();
    }

    function commit(): bool
    {
        $success = $this->db->commit();
//        sem_release($this->sem);
        return $success;
    }

    function rollback(): bool
    {
        $success = $this->db->rollBack();
//        sem_release($this->sem);
        return $success;
    }


}