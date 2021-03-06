<?php
namespace laocc\dbs\ext;

use laocc\dbs\Mysql;

/**
 *
 * https://sjolzy.cn/PDO-query-results-achieved-in-many-ways.html
 *
 * 负责查询或执行语句的创建
 * Class Builder
 */
final class Builder
{
    private $_table = '';//使用的表名称
    private $_table_pre;

    private $_select = [];//保存已经设置的选择字符串

    private $_where = '';//保存已经设置的Where子句
    private $_where_group_in = false;

    private $_limit = '';//LIMIT组合串
    private $_skip = 0;//跳过行数

    private $_join = [];//保存已经应用的join字符串，数组内保存解析好的完整字符串

    private $_order_by = '';//保存已经解析的排序字符串

    private $_group = '';
    private $_having = '';

    private $_MySQL;//Mysql
    private $_Trans_ID = 0;//多重事务ID，正常情况都=0，只有多重事务理才会大于0

    private $_count = false;//是否启用自动统计
    private $_distinct = false;//消除重复行
    private $_fetch_type = 1;//返回的数据，是用1键值对方式，还是0数字下标，或3都要，默认1

    private $_prepare = false;//是否启用预处理
    private $_param = false;//预处理中，是否启用参数占位符方式
    private $_param_data = [];//占位符的事后填充内容

    private $bindKV = [];

    public function __construct(Mysql &$callback, $table, $trans_id = 0)
    {
        $this->clean_builder();
        $this->_MySQL = $callback;
        $this->_Trans_ID = $trans_id;
        $this->_table_pre = $table;
    }


    /**
     * 记录日志的标题
     * @param null $action
     */
    public function log($action = null)
    {
        $this->_MySQL->log($action);
        return $this;
    }


    /**
     * 清除所有现有的Query_builder设置内容
     */
    private function clean_builder($clean_all = true)
    {
        $this->_table = $this->_where = $this->_limit = $this->_group = $this->_having = $this->_order_by = '';
        $this->_select = $this->_join = [];
        $this->_logTitle = null;
        $this->_where_group_in = false;

        //清除全部数据
        if ($clean_all === false) return;
        $this->bindKV = $this->_param_data = [];
        $this->_skip = $this->_Trans_ID = 0;
        $this->_fetch_type = 1;
        $this->_prepare = $this->_param = $this->_count = false;
    }


    /**
     * 设置，或获取表名
     * @param null $tableName
     * @return $this
     */
    public function table($tableName = null)
    {
        $this->clean_builder(false);
        if ($this->_table_pre and stripos($tableName, $this->_table_pre->fix) === 0) {
            $this->table_prefix($tableName);
        }
        $this->_table = $this->protect_identifier($tableName);
        return $this;
    }

    /**
     * 加表前缀
     * @param $table
     * @param $pre
     */
    private function table_prefix(&$table)
    {
        $set =& $this->_table_pre;
        $sub = (string)$set->str;
        if (preg_match("/^({$set->fix})(\w+)([\s\.]*.+)?/i", $table, $mat)) {
            unset($mat[0]);
            if ($set->pre) $mat[1] = $set->pre;
            if ($sub) $mat[2] = $sub($mat[2]);
            $table = implode($mat);
        }
    }


    /**
     * 事务结束，提交。
     * @return bool
     * @throws \Exception
     */
    public function commit()
    {
        return $this->_MySQL->trans_commit($this->_MySQL->master[$this->_Trans_ID], $this->_Trans_ID);
    }

    /**
     * 检查值是否合法，若=false，则回滚事务
     * @param $value ，为bool表达式结果，一般如：!!$ID，或：$val>0
     * @return bool，返回$value相反的值，即：返回true表示被回滚了，false表示正常
     */
    public function back($value)
    {
        if ($value) return false;
        return $this->_MySQL->trans_back($this->_MySQL->master[$this->_Trans_ID], $this->_Trans_ID);
    }

    /**
     * 相关错误
     * @return array
     */
    public function error()
    {
        return $this->_MySQL->_error[$this->_Trans_ID];
    }


    /**
     * 使用预处理方式
     * @param bool|true $bool
     * @return $this
     */
    public function prepare($bool = true)
    {
        $this->_prepare = $bool;
        return $this;
    }

    /**
     * 对于where,update,insert时，相关值使用预定义参数方式
     * 此方式依赖于使用预处理方式，所以即便没有指定使用预处理方式，此方式下也会隐式的使用预处理方式。
     * @param bool|true $bool
     * @return $this
     */
    public function param($bool = true)
    {
        $this->_param = $bool;
        if ($bool and !$this->_prepare) {
            $this->_prepare = true;
        }
        return $this;
    }

    public function bind($key, &$param)
    {
        if ($key === 0) {
            throw new \Exception('PDO数据列绑定下标应该从1开始');
        }
        $this->bindKV[$key] = &$param;
        $this->_prepare = true;
        return $this;
    }


    /**
     * 启用统计
     * @param bool|true $bool
     * @return $this
     */
    public function count($bool = true)
    {
        $this->_count = $bool;
        return $this;
    }


    /**
     * 消除重复行
     */
    public function distinct($bool = true)
    {
        $this->_distinct = $bool;
        return $this;
    }

    /**
     * 返回的数据，是用1键值对方式，还是0数字下标，或3都要，默认1
     * @param null $type
     * @return $this
     */
    public function fetch($type = 1)
    {
        $this->_fetch_type = $type;
        return $this;
    }

    /**
     * 传回mysql的选项
     * @return array
     */
    private function option($action)
    {
        return [
            'param' => $this->_param_data,
            'prepare' => $this->_param ?: $this->_prepare,
            'count' => $this->_count,
            'fetch' => $this->_fetch_type,
            'bind' => $this->bindKV,
            'trans_id' => $this->_Trans_ID,
            'action' => $action,
        ];
    }

    /**
     * 执行一个选择子句，并进行简单的标识符转义
     * 标识符转义只能处理简单的选择子句，对于复杂的选择子句
     * 请设置第二个参数为 FALSE
     *
     * 自动保护标识符目前可以处理以下情况：
     *      1. 简单的选择内容
     *      2. 点号分隔的"表.字段"的格式
     *      3. 以上两种情况下的"AS"别名
     *
     * 暂不支持函数式
     *
     * @param string $fields 选择子句字符串，可以是多个子句用逗号分开的格式
     * @param bool|TRUE $add_identifier 是否自动添加标识符，默认是TRUE，
     *              对于复杂查询及带函数的查询请设置为FALSE
     * @return $this
     * @throws \Exception
     */
    public function select($fields, $add_identifier = TRUE)
    {
        if (is_array($fields) and !empty($fields)) {
            foreach ($fields as &$field) {
                $this->select($field, $add_identifier);
            }
            return $this;
        }
        if (!is_string($fields) or empty($fields)) {
            throw new \Exception('选择的字段不能为空，且必须只能是字符串类型。');
        }

        /**
         * 如果设置了不保护标识符（针对复杂的选择子句）
         * 或：选择*所有
         * 直接设置当前内容到选择数组，不再进行下面的操作
         */
        if (!$add_identifier or $fields === '*') {
            $this->_select[] = $fields;
            return $this;
        }

        //先去除已存在的`号
        $select = explode(',', $fields);
        $this->_select += $this->protect_identifier($select);

        return $this;
    }


    /**
     * 选择字段的最大值
     *
     * @param string $fields
     * @param string $rename
     * @return Builder
     */
    public function select_max($fields, $rename = null)
    {
        return $this->select_func('MAX', $fields, $rename);
    }

    /**
     * 选择字段的最小值
     *
     * @param string $fields
     * @param string $rename
     * @return Builder
     */
    public function select_min($fields, $rename = null)
    {
        return $this->select_func('MIN', $fields, $rename);
    }

    /**
     * 选择字段的平均值
     *
     * @param string $fields
     * @param string $rename
     * @return Builder
     */
    public function select_avg($fields, $rename = null)
    {
        return $this->select_func('AVG', $fields, $rename);
    }

    /**
     * 选择字段的和
     *
     * @param string $fields
     * @param string $rename
     * @return Builder
     */
    public function select_sum($fields, $rename = null)
    {
        return $this->select_func('SUM', $fields, $rename);
    }

    /**
     * 计算行数
     *
     * @param string $fields
     * @param null $rename
     * @return Builder
     */
    public function select_count($fields = '*', $rename = null)
    {
        return $this->select_func('COUNT', $fields, $rename);
    }

    /**
     * 执行一个查询函数，如COUNT/MAX/MIN等等
     *
     * @param string $func 函数名
     * @param string $select 字段名
     * @param string $rename_value 如果需要重命名返回字段，这里是新的字段名
     * @return $this
     */
    private function select_func($func, $select = '*', $AS = null)
    {
        $select = $this->protect_identifier($select);
        $this->_select[] = strtoupper($func) . "({$select})" . (!!$AS ? " AS `{$AS}`" : '');
        return $this;
    }

    /**
     * 根据当前选择字符串生成完成的select 语句
     * @return string
     */
    private function _build_select()
    {
        return ($this->_count ? ' SQL_CALC_FOUND_ROWS ' : '') . (empty($this->_select) ? '*' : implode(',', $this->_select));
    }


    /**
     * 执行一个Where子句
     * 接受以下几种方式的参数：
     *      1. 直接的where字符串，这种方式不做任何转义和处理，不推荐使用，如 where('abc.def = "abcdefg"')
     *      2. 两个参数分别是表达式和值的情况，自动添加标识符和值转义，如 where('abc.def', 'abcdefg')
     *      3. 第2种情况的KV数组，如 where(['abc'=>'def', 'cde'=>'fgh'])
     *
     * 表达式支持以下格式的使用及自动转义
     *      where('abcde <=', 'ddddd')
     *
     * @param array|string $field
     * @param string $value
     * @param bool $is_OR
     * @return $this
     */
    public function where($field = '', $value = null, $is_OR = null)
    {
        if (empty($field)) return $this;

        //省略了第三个参数，第二个是布尔型
        if (is_bool($value) and $is_OR === null) {
            list($is_OR, $value) = [$value, null];
        }
        if (is_string($is_OR)) {
            $is_OR = strtolower($is_OR) === 'or' ? true : false;
        }

        /**
         * 处理第一个参数是数组的情况（忽略第二个参数）
         * 每数组中的每个元素应用where子句
         */
        if (is_array($field) and !empty($field)) {
            foreach ($field as $key => &$val) {
                if (is_int($key)) {
                    $this->where($val, null, $is_OR);
                } else {
                    $this->where($key, $val, $is_OR);
                }
            }
            return $this;
        }

        if (!is_string($field)) {
            throw new \Exception('DB_ERROR: where 条件异常');
        }


        /**
         * 未指定条件值，则其本身就是一个表达式，直接应用当前Where子句
         * @NOTE 尽量不要使用这种方式（难以处理安全性）
         */
        if ($value === null) {
            $_where = $field;

        } else {

            //所有以[-.`\w]开头的，加保护符
            $protectFiled = preg_replace_callback('/^([\w\-\.\`]+)$/i', function ($matches) {
                return $this->protect_identifier($matches[1]);
            }, $field);

            // 针对没有以数学运算符结尾的，在末端加上等号，否则只加空格
            $protectFiled .= preg_match('/\x20*?(<|>|<=|>=|=|<=>|\!=|<>)\x20*?$/', $protectFiled) ? ' ' : ' = ';

            if ($this->_param) {//采用占位符后置内容方式
                //用字段组合一个只有\w的字符，也就是剔除所有非\w的字符，用于预置占位符
                $key = ':' . preg_replace('/[^\w]/i', '', $field) . uniqid(true);
                $this->_param_data[$key] = $value;

                $_where = $protectFiled . $key;
            } else {
                $_where = $protectFiled . $this->quote($value);// 对 $value 进行安全转义
            }
        }
        $this->_where_insert($_where, ($is_OR ? ' OR ' : ' AND '));
        return $this;
    }

    /**
     * 执行一个where or 子句
     * @param $field
     * @param null $value
     * @return Builder
     */
    public function or_where($field, $value = null)
    {
        return $this->where($field, $value, TRUE);
    }

    public function where_or($field, $value = null)
    {
        return $this->where($field, $value, TRUE);
    }

    /**
     * 创建一个Where IN 查旬子句
     *
     * @param string $field 字段名
     * @param array $data IN的内容，必须是一个数组
     * @param bool|FALSE $is_OR
     * @return $this
     * @throws \Exception
     */
    public function where_in($field, $data, $is_OR = FALSE)
    {
        if (empty($field)) {
            throw new \Exception('DB_ERROR: where in 条件不可为空');
        }
        $protectField = $this->protect_identifier($field);

        $data = empty($data) ? null : $data;
        if (is_array($data)) $data = implode("','", $data);

        if ($this->_param) {
            $key = ':' . preg_replace('/[^\w]/i', '', $field) . uniqid(true);
            $this->_param_data[$key] = "'{$data}'";
            $_where = "{$protectField} IN ({$key})";
        } else {
            $data = quotemeta($data);
            $_where = "{$protectField} IN ('{$data}')";
        }
        $this->_where_insert($_where, ($is_OR ? ' OR ' : ' AND '));

        return $this;
    }

    /**
     * 创建一个 OR WHERE xxx IN xxxx 的查询
     *
     * @param string $field
     * @param array $data
     * @return Builder
     * @throws \Exception
     */
    public function or_where_in($field, array $data)
    {
        return $this->where_in($field, $data, TRUE);
    }

    /**
     * 创建一个where like 子句
     * 代码和 where_in差不多
     *
     * @param $field
     * @param $like_condition
     * @param bool|FALSE $is_OR
     * @return $this
     * @throws \Exception
     */
    public function where_like($field, $value, $is_OR = FALSE)
    {
        if (empty($field) || empty($value)) {
            throw new \Exception('DB_ERROR: where like 条件不能为空');
        }
        $protectField = $this->protect_identifier($field);

        if ($this->_param) {
            $key = ':' . preg_replace('/[^\w]/i', '', $field) . uniqid(true);
            $this->_param_data[$key] = $value;
            $_where = "{$protectField} LIKE {$key}";

        } else {
            $value = quotemeta($value);
            $_where = "{$protectField} LIKE '{$value}'";
        }
        $this->_where_insert($_where, ($is_OR ? 'OR' : 'AND'));
        return $this;
    }

    private function _where_insert($_where, $ao)
    {
        if (empty($this->_where)) {
            $this->_where = $_where;
        } else {
            $this->_where .= " {$ao} {$_where} ";
        }
    }

    /**
     * 开始一个where组，用于建立复杂的where查询，需要与
     * where_group_end()配合使用
     *
     * @param bool|FALSE $is_OR
     * @return $this
     */
    public function where_group_start($is_OR = FALSE)
    {
        if (!is_bool($is_OR)) $is_OR = (strtolower($is_OR) === 'or') ? true : false;

        if ($this->_where_group_in) {
            throw new \Exception('DB_ERROR: 当前还处于Where Group之中，请先执行where_group_end');
        }
        if (empty($this->_where)) {
            $this->_where = '(';
        } else {
            $this->_where .= ($is_OR ? ' or' : ' and') . ' (';
        }
        $this->_where_group_in = true;
        return $this;
    }

    /**
     * 结束一个where组，为语句加上后括号
     * @return $this
     * @throws \Exception
     */
    public function where_group_end()
    {
        if ($this->_where_group_in === false) {
            throw new \Exception('DB_ERROR: 当前未处于Where Group之中');
        }
        if (empty($this->_where)) {
            throw new \Exception('DB_ERROR: 当前where条件为空，无法创建where语句');
        } else {
            $this->_where .= ')';
            $this->_where_group_in = false;
        }
        return $this;
    }

    /**
     * @see $this->where_group_start
     * @return Builder
     */
    public function or_where_group_start()
    {
        return $this->where_group_start(TRUE);
    }

    /**
     * 创建Where查询字符串
     * @return string
     */
    private function _build_where()
    {
        if (empty($this->_where)) return '';
        if ($this->_where_group_in) {
            throw new \Exception('DB_ERROR: 当前还处于Where Group之中，请先执行where_group_end');
        }
        /**
         * 这只是个权宜之计，暂时先用正则替换掉括号后面的AND和OR
         * preg_replace('/\((\s*(?:AND|OR))/', '(', $this->_where)
         */
        return "WHERE {$this->_where}";
    }


    /**
     * limit的辅助跳过
     * @param $n
     * @return $this
     */
    public function skip($n)
    {
        $this->_skip = $n;
        return $this;
    }

    /**
     * 执行Limit查询
     *
     * @param int $count 要获取的记录数
     * @param int $offset 偏移
     * @return $this
     */
    public function limit($count, $offset = 0)
    {
        $offset = $offset ?: $this->_skip;
        if ($offset === 0) {
            $this->_limit = intval($count);
        } else {
            $this->_limit = intval($offset) . ',' . intval($count);
        }
        return $this;
    }


    /**
     * 创建一个联合查询
     *
     * @param string $table 要联查的表的名称
     * @param string $_filter ON关系字符串
     * @param string $method 联查的类型，默认是NULL，可选值为'left','right','inner','outer','full'
     * @throws \Exception
     * @return $this
     */
    public function join($table, $_filter = null, $method = 'left', $identifier = true)
    {
        $method = strtoupper($method);
        if (!in_array($method, [null, 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'FULL', 'USING'])) {
            throw new \Exception('DB_ERROR: JOIN模式不存在：' . $method);
        }

        // 保护标识符
        $table = $this->protect_identifier($table);

        //连接条件允许以数组方式
        $_filters = is_array($_filter) ? $_filter : explode(',', $_filter);

        if ($method === 'USING') {
            $_filter_str = implode('`,`', $_filters);
            $this->_join[] = " JOIN {$table} USING(`{$_filter_str}`) ";
            return $this;
        }

        $_filter_arr = [];  // 临时保存拼接的 ON 字符串
        foreach ($_filters as &$re) {
            $_filter_arr[] = preg_replace_callback('/^(.*)(>|<|<>|=|<=>)(.*)/', function ($matches) use ($identifier) {
                if ($matches[1] === $matches[3]) {
                    throw new \Exception('DB_ERROR: JOIN条件两边不能完全相同，如果是不同表相同字段，请用tabName.filed的方式');
                }
                if ($identifier)
                    return $this->protect_identifier($matches[1]) . " {$matches[2]} " . $this->protect_identifier($matches[3]);
                else
                    return "{$matches[1]} {$matches[2]} $matches[3]";
            }, $re);
        }
        $_filter_str = implode(',', $_filter_arr);

        $this->_join[] = " {$method} JOIN {$table} ON {$_filter_str} ";

        return $this;
    }

    /**
     * 创建一个排序规则
     *
     * @param string $field 需要排序的字段名
     * @param string $method 排序的方法，可选值有 'ASC','DESC','RAND'
     *              其中，RAND随机排序和字段名无关（任意即可）
     * @return $this
     * @throws \Exception
     */
    public function order($field, $method = 'ASC', $addProtect = true)
    {
        /**
         * 检查传入的排序方式是否被支持
         */
        $method = strtoupper(trim($method));
        if (!in_array($method, ['ASC', 'DESC', 'RAND'])) {
            throw new \Exception('DB_ERROR: ORDER模式不存在：' . $method);
        }

        if ($method === 'RAND') {
            $str = 'RAND()';//随机排序,和字段无关
        } else {
            if ($addProtect) $field = $this->protect_identifier($field);
            $str = "{$field} {$method}";
        }

        if (empty($this->_order_by)) {
            $this->_order_by = $str;
        } else {
            $this->_order_by .= ', ' . $str;
        }

        return $this;
    }


    /**
     * 执行Group 和 having
     *
     * @param string $field 分组字段
     * @return $this
     *
     * 被分组，过滤条件的字段务必出现在select中
     *
     * $sql="select orgGoodsID,count(*) as orgCount from tabOrderGoods group by orgGoodsID having orgCount>1";
     */
    public function group($field)
    {
        $this->_group = $field;
        return $this;
    }

    /**
     * @param string $filter
     * @return $this
     */
    public function having($filter)
    {
        $this->_having = $filter;
        return $this;
    }


    private function _build_get()
    {
        $sql = [];
        $sql[] = "SELECT {$this->_build_select()} FROM {$this->_table}";

        if (!empty($this->_distinct)) $sql[] = "DISTINCT";

        if (!empty($this->_join)) $sql[] = implode(' ', $this->_join);

        if (!empty($where = $this->_build_where())) $sql[] = $where;

        if (!empty($this->_group)) $sql[] = "GROUP BY {$this->_group}";

        if (!empty($this->_having)) $sql[] = "HAVING {$this->_having}";

        if (!empty($this->_order_by)) $sql[] = "ORDER BY {$this->_order_by}";

        if (!empty($this->_limit)) $sql[] = "LIMIT {$this->_limit}";
        return implode(' ', $sql);
    }

    /**
     * 获取查询结果
     * @param bool|false $returnSQL
     * @return Result
     * @throws \Exception
     */
    public function get($row = 0)
    {
        if ($row > 0) $this->limit($row);
        $sql = $this->_build_get();
        $this->replace_tempTable($sql);
        return $this->_MySQL->query_exec($sql, $this->option('select'));
    }

    /**
     * 组合SQL，并不执行，暂存起来，供后面调用
     * @return string
     */
    public function temp()
    {
        $tmpID = uniqid(true);
        $this->_temp_table[$tmpID] = $this->_build_get();
        return $tmpID;
    }

    private $_temp_table = [];


    /**
     * @param $sql
     */
    private function replace_tempTable(&$sql)
    {
        if (empty($this->_temp_table)) return;
        $sql = preg_replace_callback('/(?:\[|\<)([a-f0-9]{14})(?:\]|\>)/i', function ($matches) {
            if (!isset($this->_temp_table[$matches[1]])) return $matches[0];
            $table = $this->_temp_table[$matches[1]];
            unset($this->_temp_table[$matches[1]]);//用完即清除，所以不需要初始化时清空
            return "( {$table} ) as {$matches[1]} ";
        }, $sql);
    }


    /**
     * 删除记录，配合where类子句使用以删除指定记录
     * 没有where的情况下是删除表内所有数据
     * @return mixed
     * @throws \Exception
     */
    public function delete()
    {
        $where = $this->_build_where();
        if (empty($where)) {//禁止无where时删除数据
            throw new \Exception('DB_ERROR: 禁止无where时删除数据，如果要清空表，请采用：id>0的方式');
        }

        $sql = [];
        $sql[] = "DELETE FROM {$this->_table} {$where}";
        if (!empty($this->_order_by)) $sql[] = "ORDER BY {$this->_order_by}";
        if (!empty($this->_limit)) $sql[] = "LIMIT {$this->_limit}";
        $sql = implode(' ', $sql);
        return $this->_MySQL->query_exec($sql, $this->option('delete'), $this->_MySQL->master[$this->_Trans_ID]);
    }


    /**
     * 一次插入多个值
     * $v = [['name' => 'wang', 'sex' => 1], ['name' => 'zhang', 'sex' => 0]];
     * @param array $data
     * @param bool|FALSE $is_REPLACE
     * @return string
     * @throws \Exception
     * 注：在一次插入很多记录时，不用预处理或许速度更快，若一次插入数据只有几条或十几条，这种性能损失可以忽略不计。
     */
    public function insert(array $data, $is_REPLACE = FALSE)
    {
        if (empty($data)) {
            throw new \Exception('DB_ERROR: 无法 insert/replace 空数据');
        }
        $this->_param_data = [];

        //非多维数组，转换成多维
        if (!(isset($data[0]) and is_array($data[0]))) $data = [$data];

        $values = [];
        $keys = null;
        $param = null;
        $op = $is_REPLACE ? 'REPLACE' : 'INSERT';

        foreach ($data as $i => &$item) {

            if ($keys === null) {//获取keys
                $keys = $this->protect_identifier(array_keys($item));
                $keys = implode(', ', $keys);
            }

            if ($this->_param) {
                if ($param === null) {
                    $valKey = array_keys($item);
                    $param = '(:' . implode(',:', $valKey) . ')';
                }
                $nv = [];
                foreach ($item as $k => &$v) {
                    $nv[":$k"] = $v;
                }
                $this->_param_data[] = $nv;

            } else {
                $value = $this->quote(array_values($item));
                $values[] = '(' . implode(', ', $value) . ')';
            }
        }
        $value = $param ?: implode(', ', $values);
        $sql = "{$op} INTO {$this->_table} ({$keys}) VALUES {$value}";
        return $this->_MySQL->query_exec($sql, $this->option($op), $this->_MySQL->master[$this->_Trans_ID]);
    }

    /**
     * 执行一个replace查询
     * replace和insert的区别：当insert时，若唯一索引值冲突，则会报错，而replace则自动先delete再insert，这是原子性操作。
     * 在执行REPLACE后，系统返回了所影响的行数，
     * 如果返回1，说明在表中并没有重复的记录，
     * 如果返回2，说明有一条重复记录，系统自动先调用了 DELETE删除这条记录，然后再记录用INSERT来插入这条记录。
     * 如果返回的值大于2，那说明有多个唯一索引，有多条记录被删除和插入。
     *
     * @param array $data
     * @return string
     * @throws \Exception
     */
    public function replace(array $data)
    {
        return $this->insert($data, true);
    }


    /**
     * 更新记录到数据库，
     * 往往需要配合 where 子句执行
     * ['name' => 'wang', 'sex' => 1]
     *
     * @param array $data
     * @return mixed
     * @throws \Exception
     */
    public function update(array $data)
    {
        if (empty($data)) {
            throw new \Exception('DB_ERROR: 不能 update 空数据');
        }
        $sets = [];
        foreach ($data as $key => &$value) {
            if ($this->_param) {
                $pKey = ":{$key}" . uniqid(true);
                $this->_param_data[$pKey] = $value;
                $value = $pKey;
            } else {
                $value = $this->quote($value);
            }
            $sets [] = $this->protect_identifier($key) . " = {$value}";
        }
        $sets = implode(', ', $sets);
        $sql = "UPDATE {$this->_table} SET {$sets} {$this->_build_where()}";
        return $this->_MySQL->query_exec($sql, $this->option('update'), $this->_MySQL->master[$this->_Trans_ID]);
    }

    /**
     * 用于复杂的条件更新
     * @param array $data
     * @param $key
     * @return mixed
     * @throws \Exception
     *
     * 此时外部where作为大条件，内部条件仅实现了根据name查询，更复杂的条件有待进一步设计
     *
     * update_batch(['name' => ['张小三' => '张三', '李大牛' => '李四'],'add' => ['上海' => '江苏']]);
     * 将：name=张小三改为张三，李大牛改为李四，同时将add=上海改为江苏，两者无关系。
     *
     */
    public function update_batch(array $upData)
    {
        if (empty($upData)) {
            throw new \Exception('DB_ERROR: 不能 update 空数据');
        }
        $sql = "UPDATE {$this->_table} SET ";
        foreach ($upData as $key => &$data) {
            $protected_key = $this->protect_identifier($key);
            $sql .= $protected_key . ' = CASE ';
            foreach ($data as $oldVal => &$newVal) {
                if (is_array($newVal)) {

                } else {
                    $oldVal = quotemeta($oldVal);
                    $newVal = quotemeta($newVal);
                    $sql .= "WHEN {$protected_key} = {$oldVal} THEN {$newVal} ";
                }
            }
            $sql .= "ELSE {$protected_key} END, ";
        }
        $sql = rtrim($sql, ', ') . $this->_build_where();
        return $this->_MySQL->query_exec($sql, $this->option('update'), $this->_MySQL->master[$this->_Trans_ID]);
    }


    /**
     * 保护标识符
     * 目前处理类似于以下格式：
     *      abc
     *      abc.def
     *      def AS hij
     *      abc.def AS hij
     *
     * @param string|array $clause
     * @return mixed|string
     */
    private function protect_identifier($clause)
    {
        /**
         * 处理数组形式传入参数
         */
        if (is_array($clause)) {
            $r = [];
            foreach ($clause as &$cls) {
                $r[] = $this->protect_identifier($cls);
            }
            return $r;
        }
        if (!is_string($clause)) return $clause;
        if ($clause === '*') return '*';

        $clause = trim(str_replace('`', '', $clause));

        //tabUser.userName => `tabUser`.`userName`
        if (preg_match('/^([\w\-]+)\.([\w\-]+)$/i', $clause, $m)) {
            return "`{$m[1]}`.`{$m[2]}`";
        }

        //userName => `userName`
        if (preg_match('/^([\w\-]+)$/i', $clause, $m)) {
            return "`{$m[1]}`";
        }

        //tabUser.* => `tabUser`.*
        if (preg_match('/^([\w\-]+)\.\*$/i', $clause, $m)) {
            return "`{$m[1]}`.*";
        }

        //userName as name => `userName` as `name`
        if (preg_match('/^([\w\-]+)\s+AS\s+([\w\-]+)$/i', $clause, $m)) {
            return "`{$m[1]}` as `{$m[2]}`";
        }

        //tabUser.userName as name => `tabUser`.`userName` as `name`
        if (preg_match('/^([\w\-]+)\.([\w\-]+)\s+AS\s+([\w\-]+)$/i', $clause, $m)) {
            return "`{$m[1]}`.`{$m[2]}` as `{$m[3]}`";
        }

        return "`{$clause}`";
    }

    /**
     * 给字符串加引号，同时转义元字符
     * @param $data
     * @return array|string
     */
    private function quote($data)
    {
        if (is_array($data)) {
            foreach ($data as $i => &$v) {
                if (is_string($v)) {
                    $data[$i] = "'" . quotemeta($v) . "'";
                }
            }
            return $data;
        } elseif (is_string($data)) {
            return "'" . quotemeta($data) . "'";
        } else {
            return $data;
        }
    }


}