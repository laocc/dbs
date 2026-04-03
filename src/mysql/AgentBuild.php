<?php

namespace esp\dbs\mysql;

use esp\dbs\Pool;
use esp\error\Error;
use esp\dbs\library\Paging;

class AgentBuild
{
    private Pool $pool;
    private AgentMysql $agentPay;

    private string $_table;
    private bool $_distinct = false;
    private array $_decode = [];
    private string $_having = '';
    private array $_order = [];
    private array $_forceIndex = [];
    private array $_joinTable = [];
    private array $_selectKey = [];
    private array $_join = [];
    private array $_join_select = [];
    private bool $_protect = true;
    private string $_limit = '';
    private int $_skip = 0;
    private array $sumKey = [];

    public function __construct(string $table, AgentMysql $agentPay, Pool $pool)
    {
        $this->_table = $table;
        $this->pool = &$pool;
        $this->agentPay = &$agentPay;
    }

    public function select(string $select, bool $add_identifier = true): AgentBuild
    {
        $this->_selectKey[] = [$select, $add_identifier];
        return $this;
    }

    public function order(string $key, string $sort = 'asc', bool $addProtect = true): AgentBuild
    {
        if ($sort === '') return $this;
        if (!in_array(strtolower($sort), ['asc', 'desc', 'rand'])) $sort = 'ASC';
        $this->_order[] = ['key' => $key, 'sort' => $sort, 'pro' => $addProtect];
        return $this;
    }

    public function index($index): AgentBuild
    {
        if (empty($index)) return $this;
        if (is_string($index)) $index = explode(',', $index);
        $new = array_merge($this->_forceIndex, $index);
        $this->_forceIndex = array_diff(array_unique($new), ['']);
        return $this;
    }

    public function paging(int $size, int $index = 0, int $recode = null): AgentBuild
    {
        if (!isset($this->pool->paging)) {
            $this->pool->paging = new Paging($size, $index, $recode);
        } else {
            $this->pool->paging->size($size)->index($index);
        }
        return $this;
    }

    public function distinct(bool $bool = true): AgentBuild
    {
        $this->_distinct = $bool;
        return $this;
    }

    public function decode(string $cols, string $type = 'json'): AgentBuild
    {
        if (!isset($this->_decode[$type])) $this->_decode[$type] = [];
        array_push($this->_decode[$type], ...array_map(function ($col) {
            if (strpos($col, '=') > 0) return explode('=', $col);
            return [$col, $col];
        }, explode(',', $cols)));
        return $this;
    }

    public function having(string $filter): AgentBuild
    {
        $this->_having = $filter;
        return $this;
    }

    public function join(string $table, $_filter, string $select = '*', string $method = 'left', bool $identifier = true): AgentBuild
    {
        $method = strtoupper($method);
        if (!in_array($method, [null, 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'FULL', 'USING'])) {
            throw new Error('DB_ERROR: JOIN模式不存在：' . $method, 1);
        }
        $this->_joinTable[] = $table;

        // 保护标识符
        if ($identifier) $table = $this->protect_identifier($table);

        //连接条件允许以数组方式
        if (is_string($_filter)) {
            if (stripos($_filter, ' and ')) {
                $_filter = explode(' and ', $_filter);
            } else if (stripos($_filter, ',')) {
                $_filter = explode(',', $_filter);
            } else {
                $_filter = [$_filter];
            }
        } else if (!is_array($_filter)) {
            throw new Error('DB_ERROR: JOIN 条件未指定，需为string或array形式', 1);
        }

        $_filter_arr = array_map(function ($re) use ($identifier) {

            return preg_replace_callback('/^(.*)([!><=&]{1,2})(.*)/', function ($mch) use ($identifier) {
                if ($mch[1] === $mch[3]) {
                    throw new Error('DB_ERROR: JOIN条件两边不能完全相同，如果是不同表相同字段名，请用[tabName.filed]的方式', 1);
                }

                if (($mch[2] === '&') or ($mch[2] === '&=')) {
                    return $this->protect_identifier($mch[1]) . " & {$mch[3]} >0";
                }
                if ($identifier) {
                    if (is_numeric($mch[3])) {
                        return $this->protect_identifier($mch[1]) . " {$mch[2]} {$mch[3]}";
                    } else {
                        return $this->protect_identifier($mch[1]) . " {$mch[2]} " . $this->protect_identifier($mch[3]);
                    }
                } else {
                    return "{$mch[1]} {$mch[2]} {$mch[3]}";
                }
            }, $re);

        }, $_filter);

        $_filter_str = implode(' and ', $_filter_arr);

        if ($method === 'USING') {
            $this->_join[] = " JOIN {$table} USING ({$_filter_str}) ";
        } else {
            $this->_join[] = " {$method} JOIN {$table} ON ({$_filter_str}) ";
        }

        if ($select === '') return $this;
        if (is_null($select)) $select = '*';
        if ($select === '*') $select = "{$table}.*";
        $this->_join_select[] = $select;

        return $this;
    }

    public function limit(int $size, int $skip = 0): AgentBuild
    {
        $skip = $skip ?: $this->_skip;
        if ($skip < 0) $skip = 0;
        if ($skip === 0) {
            $this->_limit = strval($size);
        } else {
            $this->_limit = $skip . ',' . $size;
        }
        return $this;
    }

    /**
     * 选择一条记录
     * @param array $where
     * @param string|null $orderBy
     * @param string $sort
     * @return mixed|null
     * @throws Error
     */
    public function get(array $where, string $orderBy = null, string $sort = 'asc')
    {
        $buw = new BuildWhere();
        $whereSql = $buw->where($where);
        $this->limit(1);

        $option = [];

        $_build_sql = $this->_build_get($whereSql);

        if (!empty($this->sumKey)) {
            $option['_count_sql'] = $this->_build_sum_sql($whereSql);
        }

        return $this->agentPay->query('', $_build_sql, $option);
    }

    /**
     * 保护标识符
     * 目前处理类似于以下格式：
     *      abc
     *      abc.def
     *      def AS hij
     *      abc.def AS hij
     *
     * @param $clause
     * @param bool $identifier
     * @return array|mixed|string
     */
    private function protect_identifier($clause, bool $identifier = true): mixed
    {
        if (!$this->_protect || !$identifier || $clause === '*') return $clause;
        if (is_array($clause)) {//处理数组形式传入参数
            return array_map([$this, 'protect_identifier'], $clause, array_fill(0, count($clause), $identifier));
        }
        if (!is_string($clause)) return $clause;

        $clause = trim(str_replace('`', '', $clause));//先去除已存在的`号


        if (preg_match('/^[a-z]+\(.+\)$/i', $clause, $m)) {
            //userName => `userName`
            return $clause;

        } else if (preg_match('/^[\w\-]+$/i', $clause, $m)) {
            //userName => `userName`
            return "`{$clause}`";

        } else if (preg_match('/^([\w\-]+)\.([\w\-]+)$/i', $clause, $m)) {
            //tabUser.userName => `tabUser`.`userName`
            return "`{$m[1]}`.`{$m[2]}`";

        } else if (preg_match('/^([\w\-]+)\.\*$/i', $clause, $m)) {
            //tabUser.* => `tabUser`.*
            return "`{$m[1]}`.*";

        } else if (preg_match('/^[\w\-]+\.?[\w\-]+\,[\w\-]+.+$/i', $clause, $m)) {
            //tabUser.userName,userMobile like
            return "CONCAT({$clause})";

        } else if (preg_match('/^([\w\-]+)\s+AS\s+([\w\-]+)$/i', $clause, $m)) {
            //userName as name => `userName` as `name`
            return "`{$m[1]}` as `{$m[2]}`";

        } else if (preg_match('/^([\w\-]+)\.([\w\-]+)\s+AS\s+([\w\-]+)$/i', $clause, $m)) {
            //tabUser.userName as name => `tabUser`.`userName` as `name`
            return "`{$m[1]}`.`{$m[2]}` as `{$m[3]}`";
        }

        //其他情况都加
        return "`{$clause}`";
    }

    private function _build_sum_sql($where): string
    {
        $sql = array();
        $sum = ['count(1) as count'];
        if (isset($this->sumKey)) {
            foreach ($this->sumKey as $k) $sum[] = "sum(`{$k}`) as `{$k}`";
        }
        $sum = implode(',', $sum);
        $sql[] = "SELECT {$sum} FROM {$this->_table}";
        if (!empty($this->_forceIndex)) $sql[] = "force index({$this->_forceIndex})";

        $sql[] = "WHERE {$where}";

        if (!empty($this->_group)) $sql[] = "GROUP BY {$this->_group}";

        if (!empty($this->_having)) $sql[] = "HAVING {$this->_having}";

        return implode(' ', $sql);
    }

    private function _build_select(): string
    {
        //($this->_count ? ' SQL_CALC_FOUND_ROWS ' : '') .
        if (empty($this->_join)) {
            return (empty($this->_select) ? '*' : implode(',', $this->_select));
        } else {
            if (empty($this->_join_select)) {
                return (empty($this->_select) ? '*' : implode(',', $this->_select));
            } else if (empty($this->_select)) {
                return "{$this->_table}.*," . implode(',', $this->_join_select);
            } else {
                return implode(',', $this->_select) . ',' . implode(',', $this->_join_select);
            }
        }
    }

    private function _build_get($where): string
    {
        $sql = array();
        $sql[] = "SELECT " . ($this->_distinct ? 'DISTINCT ' : '') . $this->_build_select();

        $sql[] = "FROM {$this->_table}";

        if (!empty($this->_forceIndex)) $sql[] = "FORCE index({$this->_forceIndex})";

        if (!empty($this->_join)) $sql[] = implode(' ', $this->_join);

        $sql[] = "WHERE {$where}";

        if (!empty($this->_group)) $sql[] = "GROUP BY {$this->_group}";

        if (!empty($this->_having)) $sql[] = "HAVING {$this->_having}";

        if (!empty($this->_order_by)) $sql[] = "ORDER BY {$this->_order_by}";

        if (!empty($this->_limit)) $sql[] = "LIMIT {$this->_limit}";
        return implode(' ', $sql);
    }


}