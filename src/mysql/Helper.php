<?php
declare(strict_types=1);

namespace esp\dbs\mysql;

use esp\error\Error;
use function esp\helper\root;

/**
 * Mysql() 中复用类方法
 */
trait Helper
{

    /**
     * @param string $table
     * @return Cache
     */
    private function hash(string $table): Cache
    {
        return $this->pool->cache($this->dbName)->table($table);
    }


    /**
     * select * from INFORMATION_SCHEMA.Columns where table_name='tabAdmin' and table_schema='dbPayCenter';
     * 当前模型表对应的主键字段名，即自增字段
     * @param string|null $table
     * @return string
     */
    public function PRI(string $table = null): string
    {
//        if (defined('_DISABLE_INFORMATION_SCHEMA') && _DISABLE_INFORMATION_SCHEMA) esp_error('_DISABLE_INFORMATION_SCHEMA', '禁用查询表字段');
        if (is_null($table)) $table = $this->_table;
        if (!$table) throw new Error('Unable to get table name');
        $mysql = $this->MysqlObj();
        $val = $mysql->table('INFORMATION_SCHEMA.Columns')
            ->select('COLUMN_NAME')
            ->where(['table_name' => $table, 'EXTRA' => 'auto_increment'])
            ->get(0, 3)->row();
        if (empty($val)) return '';
        return $val['COLUMN_NAME'];
    }

    /**
     * 设置自增ID起始值
     * @param string $table
     * @param int $id
     * @return bool|int|string|null
     * @throws Error
     */
    public function increment(string $table, int $id = 1)
    {
        //TRUNCATE TABLE dbAdmin;
        //alter table users AUTO_INCREMENT=10000;
        $mysql = $this->MysqlObj();
        return $mysql->query("alter table {$table} AUTO_INCREMENT={$id}", [], null, 1);
    }

    /**
     * 刷新INFORMATION_SCHEMA里的表信息
     * @param string $table
     * @return bool|mixed
     * @throws Error
     */
    public function analyze(string $table)
    {
        $this->hash($table)->set('_field', []);
        $this->hash($table)->set('_title', []);

        $val = $this->MysqlObj()->query("analyze table `{$table}`", [], null, 1)->rows();
        if (isset($val[1])) {
            return $val[0]['Msg_text'];
        } else {
            return true;
        }
    }

    /**
     * 读取表信息
     * @param null $table
     * @param bool $html
     * @return array|string
     */
    public function desc($table = null, bool $html = false)
    {
        if (is_bool($table)) list($table, $html) = [null, $table];
        $table = $table ?: $this->_table;
        if (!$table) throw new Error('Unable to get table name');

        $val = $this->MysqlObj()->table('INFORMATION_SCHEMA.Columns')
            ->select('column_name as name,COLUMN_DEFAULT as default,column_type as type,column_key as key,column_comment as comment')
            ->where(['table_schema' => $this->dbName, 'table_name' => $table])
            ->order('ORDINAL_POSITION', 'asc')
            ->get(0, 3)->rows();
        if (empty($val)) throw new Error("Table '{$table}' doesn't exist");
        if ($html) {
            $table = [];
            $table[] = '<table class="layui-table">';
            $table[] = "<thead><tr><td width='80'>Key</td><td width='150'>字段</td><td width='180'>类型</td><td width='80'>默认值</td><td>摘要</td></tr></thead>";
            foreach ($val as $rs) {
                $table[] = "<tr><td>{$rs['key']}</td><td>{$rs['name']}</td><td>{$rs['type']}</td><td>{$rs['default']}</td><td>{$rs['comment']}</td></tr>";
            }
            $table[] = '<table>';
            return implode("\n", $table);
        }
        return $val;
    }

    /**
     * @param false $html
     * @return array|mixed|string
     * @throws Error
     */
    public function tables(bool $html = false)
    {
        $val = $this->MysqlObj()->table('INFORMATION_SCHEMA.TABLES')
            ->select("TABLE_NAME as name,DATA_LENGTH as data,TABLE_ROWS as rows,AUTO_INCREMENT as increment,TABLE_COMMENT as comment,UPDATE_TIME as time")
            ->where(['TABLE_SCHEMA' => $this->dbName])->get(0, 3)->rows();

        if (empty($val)) return [];
        if ($html) {
            $table = [];
            $table[] = '<table class="layui-table">';
            $table[] = "<thead><tr><td>表名</td><td>行数</td><td>数据</td><td>自增ID</td><td>摘要</td><td>更新时间</td></tr></thead>";
            foreach ($val as $rs) {
                $rs['data'] = intval($rs['data'] / 1024 / 1024);
                $table[] = "<tr><td>{$rs['name']}</td><td>{$rs['rows']}</td><td>{$rs['data']}MB</td><td>{$rs['increment']}</td><td>{$rs['comment']}</td><td>{$rs['time']}</td></tr>";
            }
            $table[] = '<table>';
            return implode("\n", $table);
        }
        return $val;
    }

    /**
     * 列出表字段
     * @param string|null $table
     * @return mixed
     * @throws Error
     */
    public function fields(string $table = null)
    {
        $table = $table ?: $this->_table;
        if (!$table) throw new Error('Unable to get table name');

        $val = $this->MysqlObj()->table('INFORMATION_SCHEMA.Columns')
            ->where(['table_schema' => $this->dbName, 'table_name' => $table])
            ->get(0, 3)->rows();
        if (empty($val)) throw new Error("Table '{$table}' doesn't exist");
        return $val;
    }

    /**
     * 根据数据库中的表，创建相应的模型
     * @param string $class
     * @return array|string
     *
     * $class = get_parent_class($modMain)
     * 也就是任何一个已存在的Model实例的命名空间路径
     * 这里所创建的model和该实例在同一目录
     * 调用： $model->createModel(get_parent_class($model))
     *
     * @throws Error
     */
    public function createModel(string $path, string $parent)
    {
//        $self = explode('\\', get_parent_class($this));
//        if (strpos($class, 'Object')) return '请使用->createModel(get_parent_class($modName))方式调用';

//        $self = explode('\\', $class);
//        $parent = array_pop($self);
//        if ($parent === 'Model') return 'Model实例应该有个中间类，比如_Base，不应该直接引自Model类，若确需这样，请手工创建。';
//        if (empty($self)) return 'Model实例应该引用自Model>_Base';
//        $path = '/' . implode('/', $self);
//        $parent = '_BaseModel';
        $root = root($path);
        if (!is_dir($root)) return "请先创建[{$root}]目录";

        $mysql = $this->MysqlObj();
        $val = $mysql->table('INFORMATION_SCHEMA.TABLES')
            ->select('TABLE_NAME')
            ->where(['TABLE_SCHEMA' => $this->dbName])->get(0, 3)->rows();
        $tables = [];
        foreach ($val as $table) {
            $tab = ucfirst(substr($table['TABLE_NAME'], 3)) . 'Model';
            if (!is_readable("{$root}/{$tab}.php")) {
                $keyID = $mysql->table('INFORMATION_SCHEMA.Columns')
                    ->select('COLUMN_NAME')
                    ->where([
                        'table_schema' => $this->dbName,
                        'table_name' => $table['TABLE_NAME'],
                        'EXTRA' => 'auto_increment'])
                    ->get(0, 3)->row();
                $namespace = str_replace('/', '\\', trim($path, '/'));
                $php = <<<PHP
<?php

namespace {$namespace};

class {$tab} extends {$parent} 
{
    public string \$_table = '{$table['TABLE_NAME']}';
    public string \$_id = '{$keyID['COLUMN_NAME']}';
    
}
PHP;
                $tables[] = $tab;
                file_put_contents("{$root}/{$tab}.php", $php);
            }
        }
        return $tables;
    }

    /**
     * 列出所有字段的名称
     * @return array
     * @throws Error
     */
    public function title(): array
    {
        $table = $this->_table;
        $data = $this->hash($table)->get('_title');
        if (!empty($data)) return [];
        if (!$table) throw new Error('Unable to get table name');
        $val = $this->MysqlObj()->table('INFORMATION_SCHEMA.Columns')
            ->select('COLUMN_NAME as field,COLUMN_COMMENT as title')
            ->where(['table_name' => $table])->get(0, 3)->rows();
        if (empty($val)) throw new Error("Table '{$table}' doesn't exist");
        $this->hash($table)->set('_title', $val);
        return $val;
    }

    /**
     * 新增行时，填充字段
     * @param string $table
     * @param array $data
     * @return array|mixed
     * @throws Error
     */
    protected function _FillField(string $table, array $data)
    {
        $field = $this->hash($table)->get('_field');
        if (empty($field)) {
            $field = $this->fields($table);
            $s = $this->hash($table)->set('_field', $field);
        }
        $rowData = $data[0] ?? $data;

        foreach ($field as $i => $rs) {
            if (strtolower($rs['EXTRA']) === 'auto_increment') continue;//自增字段
            if (isset($rowData[$rs['COLUMN_NAME']])) continue;//传入数据中，字段有值
            if (isset($rowData[$rs['COLUMN_NAME'] . '\\'])) continue;//传入字段中以\\结尾的
            $string = array('CHAR', 'VARCHAR', 'TINYBLOB', 'TINYTEXT', 'BLOB', 'TEXT', 'MEDIUMBLOB', 'MEDIUMTEXT', 'LONGBLOB', 'LONGTEXT');
            $number = array('INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INTEGER', 'BIGINT');
            $float = array('FLOAT', 'DOUBLE', 'DECIMAL');
            if (in_array(strtoupper($rs['DATA_TYPE']), $number)) $rowData[$rs['COLUMN_NAME']] = intval($rs['COLUMN_DEFAULT']);//数值型
            elseif (in_array(strtoupper($rs['DATA_TYPE']), $float)) $rowData[$rs['COLUMN_NAME']] = floatval($rs['COLUMN_DEFAULT']);//浮点型
            elseif (in_array(strtoupper($rs['DATA_TYPE']), $string)) $rowData[$rs['COLUMN_NAME']] = strval($rs['COLUMN_DEFAULT']);//文本型
            else $data[$rs['COLUMN_NAME']] = null;//其他类型，均用null填充，主要是日期和时间类型
        }
        if (isset($data[0])) {
            foreach ($data as $i => $d) {
                $data[$i] = $d + $rowData;
            }
            return $data;
        } else {
            return $rowData;
        }
    }

    /**
     * @throws Error
     */
    protected function _AllField(string $table, array $data)
    {
        $field = $this->hash($table)->get('_field');
        if (empty($field)) {
            $field = $this->fields($table);
            $this->hash($table)->set('_field', $field);
        }
        $rowData = $data[0] ?? $data;

        foreach ($field as $i => $rs) {
            if (strtolower($rs['EXTRA']) === 'auto_increment') continue;//自增字段
            if (isset($rowData[$rs['COLUMN_NAME']])) continue;//传入数据中，字段有值
            $string = array('CHAR', 'VARCHAR', 'TINYBLOB', 'TINYTEXT', 'BLOB', 'TEXT', 'MEDIUMBLOB', 'MEDIUMTEXT', 'LONGBLOB', 'LONGTEXT');
            $number = array('INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INTEGER', 'BIGINT');
            $float = array('FLOAT', 'DOUBLE', 'DECIMAL');
            if (in_array(strtoupper($rs['DATA_TYPE']), $number)) $rowData[$rs['COLUMN_NAME']] = intval($rs['COLUMN_DEFAULT']);//数值型
            elseif (in_array(strtoupper($rs['DATA_TYPE']), $float)) $rowData[$rs['COLUMN_NAME']] = floatval($rs['COLUMN_DEFAULT']);//浮点型
            elseif (in_array(strtoupper($rs['DATA_TYPE']), $string)) $rowData[$rs['COLUMN_NAME']] = strval($rs['COLUMN_DEFAULT']);//文本型
            else $data[$rs['COLUMN_NAME']] = null;//其他类型，均用null填充，主要是日期和时间类型
        }
        if (isset($data[0])) {
            foreach ($data as $i => $d) {
                $data[$i] = $d + $rowData;
            }
            return $data;
        } else {
            return $rowData;
        }
    }

    public function printTableFields(string $table, string $key = 'data')
    {
        if (!$table) exit("未指定表名\n");
        if (!$key) $key = 'data';

        /**
         * select COLUMN_NAME,COLUMN_COMMENT from INFORMATION_SCHEMA.Columns
         * where TABLE_SCHEMA='' and TABLE_NAME=''
         */
        $fields = $this->MysqlObj()->table('INFORMATION_SCHEMA.Columns')
            ->where(['TABLE_SCHEMA' => $this->dbName, 'TABLE_NAME' => $table])->get()->rows();
        echo "\n";
        echo "\${$key} = [];\n";
        foreach ($fields as $fs) {
            if ($fs['COLUMN_KEY'] === 'PRI') continue;
            if ($fs['COLUMN_DEFAULT']) {
                echo "\${$key}['{$fs['COLUMN_NAME']}'] = '{$fs['COLUMN_DEFAULT']}';//{$fs['COLUMN_COMMENT']}\n";
            } else {
                echo "\${$key}['{$fs['COLUMN_NAME']}'] = 000000;//{$fs['COLUMN_COMMENT']}\n";
            }
        }
        echo "\n";
    }
}