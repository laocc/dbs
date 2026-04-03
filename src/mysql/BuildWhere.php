<?php

namespace esp\dbs\mysql;

use esp\error\Error;

class BuildWhere
{

    public array $_param_data = [];
    private string $_where = '';
    private int $_where_group_in = 0;

    /**
     * 执行一个Where子句
     * 接受以下几种方式的参数：
     *      1. 直接的where字符串，这种方式不做任何转义和处理，不推荐使用，如 where('abc.def = "ade"')
     *      2. 两个参数分别是表达式和值的情况，自动添加标识符和值转义，如 where('abc.def', 'ade')
     *      3. 第2种情况的KV数组，如 where(['abc'=>'def', 'cde'=>'fgh'])
     *
     * 表达式支持以下格式的使用及自动转义
     *      where('aaa <=', 'ddd')
     *
     *
     * 如果当前查询中有join，且where中有所join表的字段条件，则在计算总数时不考虑join表
     * 而如果join表中有where条件，则需要在句子中明确指表名。
     * 如原语句：where userAge>10 and orderAmount>100,这其中userAge是join表tabUser的，
     * 则需改为：where tabUser.userAge>10 and orderAmount>100
     * 如果不加表名，则在不考虑userAge条件的情况下计算总数
     *
     * @param $field
     * @param null $value
     * @param null $is_OR
     * @return BuildWhere
     */
    public function where($field, $value = null, mixed $is_OR = null): BuildWhere
    {
        if (empty($field) and is_null($value)) return $this;

        //省略了第三个参数，第二个是布尔型
        if (is_bool($value) and $is_OR === null) {
            list($is_OR, $value) = [$value, null];
        }
        if (is_bool($value)) {
            throw new Error("DB_ERROR: where 不支持Bool类型的值", 1);
        } else if (is_object($value)) {
            throw new Error("DB_ERROR: where 不支持Object类型的值", 1);
        }
        if (is_string($is_OR)) {
            $is_OR = ('or' === strtolower($is_OR));
        }

        /**
         * 处理第一个参数是数组的情况（忽略第二个参数）
         * 每数组中的每个元素应用where子句
         */
        if (is_array($field) and !empty($field)) {
            foreach ($field as $key => $val) {
                $fType = is_string($key) ? strtolower($key[-1]) : '';
                if (is_int($key)) {
                    if (is_array($val)) {//多条件或开始

                        $this->where_group_start(false);

                        foreach ($val as $k => $v) {
                            if (is_int($k) and is_array($v)) {
                                if (empty($v)) {
                                    throw new Error("DB_ERROR: where 多条件联合时，不得有空条件", 1);
                                }
                                /**
                                 * $where[] = [['labID' => 1], ['labKey' => 2]]; //* 不同字段
                                 */
                                $simWhere = [];
                                foreach ($v as $vk => $vv) {
                                    $simWhere[] = $this->where($vk, $vv, 0);
                                }
                                $siw = implode(' and ', $simWhere);
                                $this->_where_insert("({$siw})", 'or');

                            } else {
                                //$where['labID'] = [1, 2];     //同一字段
                                $this->where($k, $v, true);
                            }
                        }
                        $this->where_group_end();
                    } else {
                        $this->where($val, null, $is_OR);
                    }
                } else if (is_array($val) and !in_array($fType, ['#', '$', '@', '%'])) {
                    $this->where_group_start(false);
                    foreach ($val as $v) $this->where($key, $v, true);
                    $this->where_group_end();
                } else {
                    $this->where($key, $val, $is_OR);
                }
            }
            return $this;
        }

        if (is_int($field)) {
            if (is_string($value)) {
                $field = $value;
                $value = null;
            } else {
//                print_r($this->_where);
                throw new Error("DB_ERROR: where 条件异常，第4级条件只能是字符串形式的原生SQL语句", 1);
            }
        }

        if (!is_string($field)) {
            throw new Error("DB_ERROR: where 条件异常:" . var_export($field, true), 1);
        }


        if ($value === null) {
            if (preg_match('/^[a-z\d]+$/i', $field)) {
                $_where = "isnull({$field})";
            } else {
                /**
                 * 未指定条件值，则其本身就是一个表达式，直接应用当前Where子句
                 * @NOTE 尽量不要使用这种方式（难以处理安全性）
                 */
                $_where = $field;
            }
        } else {
            $sqlVal = false;
            $findType = strtolower($field[-1]);
            if ($findType === '\\') {
                //where字段后加\号，如：$where['value<=\\'] = "(select num from table where expID={$expID})";
                if (!(is_numeric($value) or (is_string($value) and $value[0] === '(' and $value[-1] === ')'))) {
                    throw new Error("DB_ERROR: where 直接引用SQL时，被引用的SQL要用括号圈定完整语句", 1);
                }
                $field = substr($field, 0, -1);
                $findType = strtolower($field[-1]);
                $sqlVal = true;
            }

            $identifier = true;
            if ($field[0] === '\\') {
                $identifier = false;//字段名不加保护符
                $field = substr($field, 1);
            }

            switch ($findType) {
                case '~'://组合 like
                    $field = substr($field, 0, -1);
                    $pos = '';
                    if ($field[-1] === '!') {
                        $pos = 'not ';
                        $field = substr($field, 0, -1);
                    }
                    if (is_array($value)) $value = json_encode($value, 320);
                    else if (!is_string($value)) $value = strval($value);

                    if ($value !== '') {
                        if ($value[0] === '^') $value = substr($value, 1);
                        else if ($value[0] !== '%') $value = "%{$value}";
                        if (!empty($value)) {
                            if ($value[-1] === '$') $value = substr($value, 0, -1);
                            else if ($value[-1] !== '%') $value = "{$value}%";
                        }
                    }
                    $fieldPro = $this->protect_identifier($field, $identifier);
                    $key = $this->paramKey($field);
                    $this->_param_data[$key] = $value;
                    $_where = "{$fieldPro} {$pos} like {$key}";

                    break;
                case '^'://组合 "locate('{$key}',keyWord)";
                    $field = substr($field, 0, -1);
                    $pos = '>0';
                    if ($field[-1] === '!') {
                        $pos = '=0';
                        $field = substr($field, 0, -1);
                    }
                    $fieldPro = $this->protect_identifier($field, $identifier);
                    $key = $this->paramKey($field);
                    $this->_param_data[$key] = $value;
                    $_where = "locate(" . $key . ",{$fieldPro}){$pos}";
                    break;
                case '$'://全文搜索："MATCH (`godTitle`,`godPinYin`) AGAINST ('{$py}')"
                    $field = substr($field, 0, -1);
                    $pos = '>';
                    if ($field[-1] === '!') {
                        $pos = '<=';
                        $field = substr($field, 0, -1);
                    }
                    $fieldPro = $this->protect_identifier($field, $identifier);
                    if (!is_array($value)) $value = [$value, 0];
                    if (!is_float($value[1])) throw new Error("MATCH第2个值只能是浮点型值，表示匹配度", 1);

                    $key = $this->paramKey($field);
                    $this->_param_data[$key] = $value[0];
                    $_where = "MATCH({$fieldPro}) AGAINST (" . $key . "){$pos}{$value[1]}";
                    break;
                case '!'://等同于 !=
                    $field = substr($field, 0, -1);
                    $fieldPro = $this->protect_identifier($field, $identifier);

                    if ($sqlVal) {
                        $_where = "{$fieldPro} != {$value}";

                    } else {//采用占位符后置内容方式
                        $key = $this->paramKey($field);
                        $this->_param_data[$key] = $value;
                        $_where = "{$fieldPro} != {$key}";
                    }

                    break;
                case '&'://位运算
                    $field = substr($field, 0, -1);
                    $in = '>0';
                    if ($field[-1] === '!') {
                        $in = '=0';
                        $field = substr($field, 0, -1);
                    } else if ($field[-1] === '?') {
                        $in = '?0';
                        $field = substr($field, 0, -1);
                    }

                    if (is_array($value)) $value = array_sum($value);
                    else $value = intval($value);

                    $fieldPro = $this->protect_identifier($field, $identifier);

                    if ($value === 0) {
                        $_where = "({$fieldPro} = 0 )";
                    } else if ($in === '=0') {
                        $key = $this->paramKey($field);
                        $this->_param_data[$key] = $value;
                        $_where = "({$fieldPro} =0 or ({$fieldPro} & {$key})=0)";
                    } else if ($in === '?0') {
                        $key = $this->paramKey($field);
                        $this->_param_data[$key] = $value;
                        $_where = "({$fieldPro} =0 or ({$fieldPro} & {$key})>0)";
                    } else {
                        $key = $this->paramKey($field);
                        $this->_param_data[$key] = $value;
                        $_where = "({$fieldPro} >0 and ({$fieldPro} & {$key}){$in})";
                    }
                    break;
                case '*'://正则表达式
                    $field = substr($field, 0, -1);
                    $fieldPro = $this->protect_identifier($field, $identifier);
                    $key = $this->paramKey($field);
                    $this->_param_data[$key] = $value;
                    $_where = "{$fieldPro} REGEXP {$key}";
                    break;
                case '#'://组合 between;
                    $field = substr($field, 0, -1);
                    $in = 'between';
                    if ($field[-1] === '!') {
                        $in = 'not between';
                        $field = substr($field, 0, -1);
                    }

                    if (empty($value)) $value = [0, 0];
                    $fieldPro = $this->protect_identifier($field, $identifier);

                    if (is_array($value[0])) {
                        $_wbt = [];
                        foreach ($value as $vi => $val) {
                            $key1 = $this->paramKey($field . $vi);
                            $key2 = $this->paramKey($field . $vi);
                            $this->_param_data[$key1] = $val[0];
                            $this->_param_data[$key2] = $val[1];
                            $_wbt[] = "({$fieldPro} {$in} {$key1} and {$key2})";
                        }
                        $_where = '(' . implode(' or ', $_wbt) . ')';

                    } else {
                        $key1 = $this->paramKey($field);
                        $key2 = $this->paramKey($field);
                        $this->_param_data[$key1] = $value[0];
                        $this->_param_data[$key2] = $value[1];
                        $_where = "{$fieldPro} {$in} {$key1} and {$key2}";
                    }
                    break;
                case '@'://组合 in 和 not in
                    $field = substr($field, 0, -1);
                    $in = 'in';
                    if ($field[-1] === '!') {
                        $in = 'not in';
                        $field = substr($field, 0, -1);
                    }
                    $fieldPro = $this->protect_identifier($field, $identifier);

                    if ($sqlVal) {
                        //in的结果是一个SQL语句
                        $_where = "{$fieldPro} {$in} {$value}";
                        break;
                    } else if (!is_array($value)) {
                        throw new Error("where in 的值必须为数组形式", 1);
                    }
                    if (empty($value)) $value = [0, 0];

                    //用字段组合一个只有\w的字符，也就是剔除所有非\w的字符，用于预置占位符
                    $keys = [];
                    foreach ($value as $i => $val) {
                        $keys[$i] = $this->paramKey($field . $i);
                        $this->_param_data[$keys[$i]] = $val;
                    }
                    $key = implode(',', $keys);
                    $_where = "{$fieldPro} {$in} ({$key})";
                    break;
                case '%'://mod
                    $field = substr($field, 0, -1);
                    $in = '=';
                    if ($field[-1] === '!') {
                        $in = '!=';
                        $field = substr($field, 0, -1);
                    }

                    if (!is_array($value)) {
                        throw new Error("mod 的值必须为数组形式，如mod(Key,2)=1，则value=[2,1]", 1);
                    }
                    if (empty($value)) $value = [2, 1];
                    $fieldPro = $this->protect_identifier($field, $identifier);

                    $key = $this->paramKey($field);
                    $this->_param_data[$key] = $value[1];
                    $_where = "mod({$fieldPro},{$value[0]}) {$in} {$key} ";
                    break;
                case '=':
                    $field = substr($field, 0, -1);
                    $in = '<=>';
                    if ($field[-1] === '!') {
                        $in = '!=';
                        $field = substr($field, 0, -1);
                    } else if ($field[-1] === '>') {
                        $in = '>=';
                        $field = substr($field, 0, -1);
                    } else if ($field[-1] === '<') {
                        $in = '<=';
                        $field = substr($field, 0, -1);
                    }
                    $fieldPro = $this->protect_identifier($field, $identifier);
                    if ($sqlVal) {
                        $_where = "{$fieldPro} {$in} {$value}";

                    } else {
                        $key = $this->paramKey($field);
                        $this->_param_data[$key] = $value;
                        $_where = "{$fieldPro} {$in} {$key}";
                    }

                    break;
                case '>':
                case '<':
                    $field = substr($field, 0, -1);
                    $fieldPro = $this->protect_identifier($field, $identifier);

                    if ($sqlVal) {
                        $_where = "{$fieldPro} {$findType} {$value}";

                    } else {
                        $key = $this->paramKey($field);
                        $this->_param_data[$key] = $value;
                        $_where = "{$fieldPro} {$findType} {$key}";
                    }
                    break;
                case ':'://预留
                case ';'://预留
                case '?'://预留
                    break;
                default:
                    if (in_array($findType, ['-', '+', ',', '.', '?', '/'])) {
                        $field = substr($field, 0, -1);
                    }
                    $fieldPro = $this->protect_identifier($field, $identifier);

                    if ($sqlVal) {
                        $_where = "{$fieldPro} = {$value}";

                    } else {//采用占位符后置内容方式
                        $key = $this->paramKey($field);
                        $this->_param_data[$key] = $value;
                        $_where = "{$fieldPro} = {$key}";
                    }
            }
        }

        if (empty($_where)) {
            throw new Error("where条件为空", 1);
        }

        if ($is_OR === 0) return $this;

        $this->_where_insert($_where, ($is_OR ? ' OR ' : ' AND '));
        return $this;
    }

    /**
     * 开始一个where组，用于建立复杂的where查询，需要与
     * where_group_end()配合使用
     *
     * @param bool $is_OR
     * @return $this
     */
    public function where_group_start(bool $is_OR = false): BuildWhere
    {
        if ($this->_where_group_in) {
            throw new Error('DB_ERROR: 当前还处于Where Group之中，请先执行where_group_end', 1);
        }
        if (empty($this->_where)) {
            $this->_where = '(';
        } else {
            $this->_where .= ($is_OR ? ' or' : ' and') . ' (';
        }
        $this->_where_group_in++;
        return $this;
    }

    /**
     * 结束一个where组，为语句加上后括号
     * @return $this
     */
    public function where_group_end(): BuildWhere
    {
        if (!$this->_where_group_in) {
            throw new Error('DB_ERROR: 当前未处于Where Group之中', 1);
        }
        if (empty($this->_where)) {
            throw new Error('DB_ERROR: 当前where条件为空，无法创建where语句', 1);
        } else {
            $this->_where .= ')';
            $this->_where_group_in = 0;
        }
        return $this;
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

    /**
     * 给字符串加引号，同时转义元字符
     * @param $data
     * @return array|mixed|string
     */
    private function quote($data): mixed
    {
        if (empty($data)) return '';
        else if (is_string($data)) {
            return quotemeta($data);

        } else if (is_array($data)) {
            foreach ($data as $i => $v) {
                if (is_string($v)) $data[$i] = quotemeta($v);//转义元字符集
            }
            return $data;

        } else {
            return $data;
        }
    }

    private function paramKey(string $field): string
    {
        if (strlen($field) > 32) $field = md5($field);
        return ':' . preg_replace('/\W/', '', $field) . uniqid();
    }

    /**
     * 保存已经设置的Where子句
     *
     * @param string $_where
     * @param string $ao
     * @return void
     */
    private function _where_insert(string $_where, string $ao): void
    {
        if (empty($this->_where)) {
            $this->_where = $_where;
        } else {
            if ($this->_where_group_in === 1) {
                $this->_where .= " {$_where}";
            } else {
                $this->_where .= " {$ao} {$_where} ";
            }
            if ($this->_where_group_in) $this->_where_group_in++;
        }
    }


}