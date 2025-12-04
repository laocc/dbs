<?php

namespace esp\dbs\sqlite;

use PDO;
use PDOStatement;
use esp\error\Error;
use esp\dbs\Pool;
use function esp\helper\mk_dir;

final class Sqlite
{
    private $db;
    private Pool $pool;
    private string $table;
    private array $conf;

    // 链式查询参数
    private string $where = '';
    private array $whereParams = [];
    private string $fields = '*';
    private string $limit = '';
    private string $offset = '';
    public bool $isNew = false;

    public function __construct(Pool $pool, array $conf)
    {
        $this->pool = &$pool;
        $this->conf = $conf;

        if (!isset($this->conf['db'])) {
            throw new Error('Sqlite库文件未指定');
        }

        // 确保目录存在（mk_dir需处理路径dirname，避免创建文件名为目录）
        $dbDir = dirname($this->conf['db']);
        if (!is_dir($dbDir) && !mk_dir($dbDir)) {
            throw new Error('Sqlite目录创建失败: ' . $dbDir);
        }

        // 连接SQLite（文件不存在会自动创建，无需手动fopen）
        try {

            $this->db = new PDO("sqlite:{$this->conf['db']}");
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // 开启异常报错
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // 默认关联数组

        } catch (\PDOException $e) {
            throw new Error('Sqlite连接失败: ' . $e->getMessage());
        }
    }

    public function __destruct()
    {
        $this->db = null; // 释放PDO连接
        // 重置链式参数
        $this->resetQueryParams();
    }

    /**
     * 重置链式查询参数（避免多次调用污染）
     */
    private function resetQueryParams(): void
    {
        $this->where = '';
        $this->whereParams = [];
        $this->fields = '*';
        $this->limit = '';
        $this->offset = '';
    }

    /**
     * 指定操作的表
     * @param string $table 表名（建议做简单校验，防止注入）
     * @return $this
     */
    public function table(string $table): Sqlite
    {
        // 简单表名校验（仅允许字母、数字、下划线）
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new Error('非法的表名格式: ' . $table);
        }
        $this->table = $table;
        $this->resetQueryParams(); // 切换表时重置查询参数
        return $this;
    }

    /**
     * 创建表
     * @param array $data 字段定义 [字段名 => 类型(如INTEGER, TEXT, REAL)]
     * @param string $primaryKey 主键（可选，如'id INTEGER PRIMARY KEY AUTOINCREMENT'）
     * @return bool
     */
    public function create(array $data, string $primaryKey = ''): bool
    {
        if (empty($this->table)) {
            throw new Error('未指定操作的表');
        }

        $fields = [];
        foreach ($data as $k => $type) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $k)) {
                throw new Error('非法的字段名: ' . $k);
            }
            $fields[] = "`{$k}` {$type}";
        }

        // 追加主键
        if ($primaryKey) {
            $fields[] = $primaryKey;
        }

        $sql = 'CREATE TABLE IF NOT EXISTS `' . $this->table . '` (' . implode(',', $fields) . ')';
        try {
            return (bool)$this->db->exec($sql);
        } catch (\PDOException $e) {
            throw new Error('创建表失败: ' . $e->getMessage());
        }
    }

    /**
     * 执行原生SQL
     * @param string $sql SQL语句
     * @param array $params 绑定参数（可选）
     * @return bool|PDOStatement
     */
    public function exec(string $sql, array $params = []): bool|PDOStatement
    {
        try {
            if (empty($params)) {
                return $this->db->exec($sql) !== false;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            throw new Error('SQL执行失败: ' . $e->getMessage() . ' | SQL: ' . $sql);
        }
    }

    /**
     * 查看表结构
     * @return array 表结构数组 [['cid','name','type','notnull','dflt_value','pk'], ...]
     */
    public function desc(): array
    {
        if (empty($this->table)) {
            throw new Error('未指定操作的表');
        }
        try {
            $stmt = $this->db->query("PRAGMA table_info(`{$this->table}`)");
            return $stmt->fetchAll() ?: [];
        } catch (\PDOException $e) {
            throw new Error('查询表结构失败: ' . $e->getMessage());
        }
    }

    /**
     * 设置WHERE条件（支持参数绑定，防止注入）
     * @param array $params 绑定参数（如: [1, 'test'] 或 ['name' => 'test']）
     * @return $this
     */
    public function where(array $params = []): Sqlite
    {
        $condition = [];
        foreach ($params as $k => $v) {
            $condition[] = "`{$k}` = :{$k}";
        }
        $this->where = 'WHERE ' . implode(' AND ', $condition);
        $this->whereParams = $params;
        return $this;
    }

    /**
     * 指定查询字段
     * @param string|array $fields 字段（如: 'id,name' 或 ['id','name']）
     * @return $this
     */
    public function select($fields): Sqlite
    {
        if (is_array($fields)) {
            // 字段名安全过滤
            $fields = array_filter($fields, function ($field) {
                return preg_match('/^[a-zA-Z0-9_]+$/', $field);
            });
            $this->fields = '`' . implode('`,`', $fields) . '`';
        } elseif (is_string($fields) && !empty($fields)) {
            $this->fields = $fields;
        }
        return $this;
    }

    /**
     * 设置LIMIT
     * @param int $limit 条数
     * @return $this
     */
    public function limit(int $limit): Sqlite
    {
        if ($limit > 0) {
            $this->limit = "LIMIT {$limit}";
        }
        return $this;
    }

    /**
     * 设置OFFSET
     * @param int $offset 偏移量
     * @return $this
     */
    public function offset(int $offset): Sqlite
    {
        if ($offset > 0) {
            $this->offset = "OFFSET {$offset}";
        }
        return $this;
    }

    /**
     * @param int $size
     * @param int $index
     * @return $this
     */
    public function paging(int $size, int $index = 1): Sqlite
    {
        if ($index < 1) $index = 1;
        $offset = $size * ($index - 1);
        $this->limit = "LIMIT {$size}";
        $this->offset = "OFFSET {$offset}";
        return $this;
    }

    /**
     * 获取单条数据
     * @param array $where
     * @return array|null
     * @throws Error
     */
    public function get(array $where = []): ?array
    {
        if (!empty($where)) $this->where($where);

        if (empty($this->table)) {
            throw new Error('未指定操作的表');
        }
        $sql = "SELECT {$this->fields} FROM `{$this->table}` {$this->where} LIMIT 1";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($this->whereParams);
            $data = $stmt->fetch();
            $this->resetQueryParams(); // 执行后重置参数
            return $data ?: null;
        } catch (\PDOException $e) {
            throw new Error('查询单条数据失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取所有数据
     * @param array $where
     * @param int $offset
     * @return array
     * @throws Error
     */
    public function all(array $where = [], int $offset = 0): array
    {
        if (!empty($where)) $this->where($where);
        if (empty($this->table)) {
            throw new Error('未指定操作的表');
        }
        $sql = "SELECT {$this->fields} FROM `{$this->table}` {$this->where}";
        if ($offset > 0) $sql .= " OFFSET {$offset}";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($this->whereParams);
            $data = $stmt->fetchAll();
            $this->resetQueryParams();
            return $data ?: [];
        } catch (\PDOException $e) {
            throw new Error('查询所有数据失败: ' . $e->getMessage());
        }
    }

    /**
     * 分页获取列表
     * @param int $page 页码（从1开始）
     * @param int $size 每页条数
     * @return array [列表数据, 总条数]
     */
    public function list(int $page = 1, int $size = 20): array
    {
        if (empty($this->table)) {
            throw new Error('未指定操作的表');
        }
        if ($page < 1) $page = 1;
        if ($size < 1) $size = 20;

        // 计算偏移量
        $offset = ($page - 1) * $size;

        // 查询列表
        $sql = "SELECT {$this->fields} FROM `{$this->table}` {$this->where} LIMIT {$size} OFFSET {$offset}";
        // 查询总数
        $countSql = "SELECT COUNT(*) AS total FROM `{$this->table}` {$this->where}";

        try {
            // 查总数
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($this->whereParams);
            $total = $countStmt->fetch()['total'] ?: 0;

            // 查列表
            $listStmt = $this->db->prepare($sql);
            $listStmt->execute($this->whereParams);
            $list = $listStmt->fetchAll() ?: [];

            $this->resetQueryParams();
            return [$list, $total];
        } catch (\PDOException $e) {
            throw new Error('分页查询失败: ' . $e->getMessage());
        }
    }

    /**
     * 插入数据
     * @param array $data 插入数据 [字段名 => 值]
     * @return int 最后插入的ID
     */
    public function insert(array $data): int
    {
        if (isset($data[0])) return $this->batchInsert($data);

        if (empty($this->table) || empty($data)) {
            throw new Error('未指定表或插入数据为空');
        }

        // 过滤非法字段名
        $fields = array_filter(array_keys($data), function ($field) {
            return preg_match('/^[a-zA-Z0-9_]+$/', $field);
        });
        if (empty($fields)) {
            throw new Error('无合法的插入字段');
        }

        $placeholders = rtrim(str_repeat('?,', count($fields)), ','); // 占位符
        $values = array_values(array_intersect_key($data, array_flip($fields))); // 对应值

        $sql = "INSERT INTO `{$this->table}` (`" . implode('`,`', $fields) . "`) VALUES ({$placeholders})";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            return (int)$this->db->lastInsertId(); // 返回自增ID
        } catch (\PDOException $e) {
            throw new Error('插入数据失败: ' . $e->getMessage());
        }
    }

    /**
     * 批量插入数据
     * @param array $list 数据列表 [[字段=>值], ...]
     * @return int
     */
    public function batchInsert(array $list): int
    {
        if (empty($this->table) || empty($list)) {
            throw new Error('未指定表或批量插入数据为空');
        }

        // 取第一条数据的字段作为基准
        $first = current($list);
        if (!is_array($first) || empty($first)) {
            throw new Error('批量插入数据格式错误');
        }

        $fields = array_filter(array_keys($first), function ($field) {
            return preg_match('/^[a-zA-Z0-9_]+$/', $field);
        });
        if (empty($fields)) {
            throw new Error('无合法的插入字段');
        }

        $placeholder = '(' . rtrim(str_repeat('?,', count($fields)), ',') . ')';
        $placeholders = rtrim(str_repeat($placeholder . ',', count($list)), ',');
        $values = [];

        foreach ($list as $item) {
            $values = array_merge($values, array_values(array_intersect_key($item, array_flip($fields))));
        }

        $sql = "INSERT INTO `{$this->table}` (`" . implode('`,`', $fields) . "`) VALUES {$placeholders}";

        try {
            $stmt = $this->db->prepare($sql);
            $save = $stmt->execute($values);
            if ($save) return count($list);
            else return 0;

        } catch (\PDOException $e) {
            throw new Error('批量插入失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新数据
     * @param array $data 更新数据 [字段名 => 值]
     * @return int 受影响的行数
     */
    public function update(array $data): int
    {
        if (empty($this->table) || empty($data)) {
            throw new Error('未指定表或更新数据为空');
        }
        if (empty($this->where)) {
            throw new Error('更新操作必须指定WHERE条件（防止全表更新）');
        }

        // 过滤非法字段名
        $fields = array_filter(array_keys($data), function ($field) {
            return preg_match('/^[a-zA-Z0-9_]+$/', $field);
        });
        if (empty($fields)) {
            throw new Error('无合法的更新字段');
        }

        $set = [];
        $values = [];
        foreach ($fields as $field) {
            $set[] = "`{$field}` = ?";
            $values[] = $data[$field];
        }

        // 合并where参数
        $values = array_merge($values, $this->whereParams);
        $sql = "UPDATE `{$this->table}` SET " . implode(',', $set) . " {$this->where}";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            $rowCount = $stmt->rowCount();
            $this->resetQueryParams();
            return $rowCount;
        } catch (\PDOException $e) {
            throw new Error('更新数据失败: ' . $e->getMessage());
        }
    }

    /**
     * 删除数据
     * @return int 受影响的行数
     */
    public function delete(array $where = []): int
    {
        if (!empty($where)) {
            $this->where($where);
        }

        if (empty($this->table)) {
            throw new Error('未指定操作的表');
        }
        if (empty($this->where)) {
            throw new Error('删除操作必须指定WHERE条件（防止全表删除）');
        }

        $sql = "DELETE FROM `{$this->table}` {$this->where}";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($this->whereParams);
            $rowCount = $stmt->rowCount();
            $this->resetQueryParams();
            return $rowCount;

        } catch (\PDOException $e) {
            throw new Error('删除数据失败: ' . $e->getMessage());
        }
    }

    /**
     * 开启事务
     * @return bool
     */
    function transaction(): bool
    {
        try {
            return $this->db->beginTransaction();
        } catch (\PDOException $e) {
            throw new Error('开启事务失败: ' . $e->getMessage());
        }
    }

    /**
     * 提交事务
     * @return bool
     */
    function commit(): bool
    {
        try {
            return $this->db->commit();
        } catch (\PDOException $e) {
            $this->rollback(); // 提交失败自动回滚
            throw new Error('提交事务失败: ' . $e->getMessage());
        }
    }

    /**
     * 回滚事务
     * @return bool
     */
    function rollback(): bool
    {
        try {
            return $this->db->rollBack();
        } catch (\PDOException $e) {
            throw new Error('回滚事务失败: ' . $e->getMessage());
        }
    }
}