<?php
declare(strict_types=1);

namespace esp\dbs\mysql;

use esp\dbs\Pool;
use esp\error\Error;
use esp\dbs\library\Paging;
use function esp\helper\numbers;


/**
 * Class Mysql
 */
final class Mysql
{
    private Pool $pool;
    private Cache $Cache;

    private array $config;

    private int $_cache = 0;
    private array $_patch_cache = [];

    private array $_MysqlPool = array();

    public string $dbName;//库名
    public string $_table = '';//表名，创建对象时，或明确指定当前模型的对应表名

    private int $_tranIndex = 0;       //事务

    private int $_traceLevel = 1;

    private array $_order = [];
    private array $_decode = [];
    private string $_having = '';//having
    private bool $_protect = true;//是否加保护符，默认加
    private int $_count = 0;//执行统计总数，0为统计，0以上不统计
    private bool $_distinct = false;//消除重复行

    protected array $tableJoin = array();
    protected array $forceIndex = [];
    protected array $selectKey = [];
    protected string $groupKey;
    private string $sumKey;
    protected $columnKey;

    use Helper;

    public function __construct(Pool $pool, array $conf, string $table)
    {
        $this->pool = &$pool;
        $this->config = $conf;
        $this->dbName = $conf['db'];
        if ($table) $this->_table = $table;
    }


    /**
     * 创建一个Mysql实例
     * @param int $tranID
     * @param int $traceLevel
     * @return PdoContent
     */
    private function MysqlObj(int $tranID = 0, int $traceLevel = 0): PdoContent
    {
        if ($tranID === 1) $tranID = $this->_tranIndex++;

        if (isset($this->_MysqlPool[$tranID])) return $this->_MysqlPool[$tranID];

        return $this->_MysqlPool[$tranID] = new PdoContent($tranID, $this->config, $this->pool);
    }

    /**
     * 指定当前模型的表
     * @param string $table
     * @return $this
     */
    public function setTable(string $table): Mysql
    {
        $this->_table = $table;
        return $this;
    }

    /**
     * 需要 where或实际操作 之前执行，也就是在table之后
     * MysqlObj之前
     *
     * @param bool $debug
     * @return $this
     */
    public function debug(bool $debug): Mysql
    {
        $this->config['debug_sql'] = $debug;
        return $this;
    }

    /**
     * @param int $trans_id
     * @return Builder
     * @throws Error
     */
    public function trans(int $trans_id = 1): Builder
    {
        return $this->MysqlObj($trans_id)->trans($trans_id);
    }

    /**
     * @param int $trans_id
     * @return Builder
     * @throws Error
     */
    public function builder(int $trans_id = 1): Builder
    {
        return $this->MysqlObj($trans_id)->builder($trans_id);
    }

    /**
     * @param array $batch_SQLs
     * @return bool
     */
    public function batch(array $batch_SQLs): bool
    {
        return $this->MysqlObj(1)->trans_batch($batch_SQLs);
    }

    /**
     * 清除自身的一些对象变量
     */
    public function clear_initial(): void
    {
        //这两个值是程序临时指定的，与model自身的_table和_pri用处相同，优先级高
        $this->_table = '';
        $this->_count = 0;
        $this->_distinct = false;
        $this->_protect = true;
        $this->_having = '';
        $this->_order = [];
        $this->_decode = [];

        $this->columnKey = null;
        $this->groupKey = '';
        $this->forceIndex = [];
        $this->tableJoin = [];
        $this->selectKey = [];
    }

    /**
     * 缓存设置：
     * 建议应用环境：
     * 例如：tabConfig表，内容相对固定，前端经常读取，这时将此表相对应值缓存，前端不需要每次读取数据库；
     *
     * 调用 $rds->flush(); 清除所有缓存
     * 紧急情况，将databases.mysql.cache=false可关闭所有缓存读写
     *
     * get时，cache(true)，表示先从缓存读，若缓存无值，则从库读，读到后保存到缓存
     * 注意：get时若有select字段，缓存结果也是只包含这些字段的值
     *
     * update,delete时，cache(['key'=>VALUE])用于删除where自身之外的相关缓存
     * 当数据可以被缓存的键除了where中的键之外，还可以指定其他键，同时指定其值
     *
     * 例tabArticle中，除了artID可以被缓存外，artTitle 也可以被缓存
     *
     * 当删除['artID'=>10]的时候，该缓存会被删除，但是['artTitle'=>'test']这个缓存并没有删除
     * 所以这里需要在执行delete之前指定
     *
     * $this->cache(['artTitle'=>'test'])->delete(['artID'=>10]);
     * $this->cache(['artID'=>10])->update(['artTitle'=>'test']);
     *
     * 若只有artID可以被缓存，则需要调用：$this->cache(true)->delete(['artID'=>10]);
     *
     * 若需要执行$this->delete(['artID>'=>10]);这种指令，被删除的目标数据可能存在多行，此时若也要删除对应不同artTitle的缓存
     * 则需要采用：$this->cache([['artTitle'=>'abc'],['artTitle'=>'def']])->delete(['artID>'=>10]);
     * 也就是多个 ['artTitle'=>'abc'] 组成的三维数组
     *
     * 作用期间，连续执行的时候：
     * $this->cache(true)->delete(['artID'=>10]); 会删除缓存
     * $this->delete(['artID'=>11]);              不删除，因为没指定
     *
     *
     * @param void $run false=不执行，force=强制读取mysql并保存cache，array批量删除
     * @return $this
     */
    public function cache($run = null): Mysql
    {
        if ($run === false) {
            $this->_cache = 0;
        } else if (is_array($run)) {
            $this->_cache = 2;
            if (isset($run[0])) {
                $this->_patch_cache = $run;
            } else {
                $this->_patch_cache = [$run];
            }
        } else if ($run === 'force') {
            $this->_cache = 1;

        } else {
            $this->_cache = 2;
        }
        return $this;
    }

    /**
     * 直接删除相关表的缓存，一般用于批量事务完成之后
     *
     * @param string $table
     * @param array $where
     * @return $this
     */
    public function delete_cache(string $table, array $where): Mysql
    {
        if ($this->_cache) {
            $this->pool->debug([
                'cache' => 1,
                'sql' => 'update/delete' . " * from {$table} where ...",
                'params' => json_encode($where, 320),
                'patch' => json_encode($this->_patch_cache, 320),
            ], $this->_traceLevel + 1);
            $this->pool->cache()->table($table)->delete($where, ...$this->_patch_cache);
            $this->_cache = 0;
            $this->_patch_cache = [];
        }
        return $this;
    }


    /**
     * 检查执行结果，所有增删改查的结果都不会是字串，所以，如果是字串，则表示出错了
     * 非字串，即不是json格式的错误内容，退出
     * @param string $action
     * @param $data
     * @return null
     * @throws Error
     */
    private function checkRunData(string $action, $data)
    {
        $this->clear_initial();
        if (!is_string($data)) return null;
        $json = json_decode($data, true);
        if (isset($json[2]) or isset($json['2'])) {
            throw new Error($action . ':' . ($json[2] ?? $json['2']), $this->_traceLevel + 2);
        }
        throw new Error($data, $this->_traceLevel + 2);
    }

    /**
     * 增
     * @param array $data
     * @param bool $full 传入的数据是否已经是全部字段，如果不是，则要从表中拉取所有字段
     * @param bool $replace
     *  bool $returnID 返回新ID,false时返回刚刚添加的数据
     * @return int|null
     * @throws Error
     */
    public function insert(array $data, bool $full = false, bool $replace = false)
    {
        if (!$this->_table) throw new Error('Unable to get table name', $this->_traceLevel + 1);
        $mysql = $this->MysqlObj(0, 1);
        $data = $full ? $data : $this->_FillField($this->_table, $data);
        $obj = $mysql->table($this->_table, $this->_protect);
        $val = $obj->insert($data, $replace, $this->_traceLevel + 1);

        $ck = $this->checkRunData('insert', $val);
        if ($ck) return $ck;
        return $val;
    }

    /**
     * 直接返回Builder
     *
     * @param string $table
     * @return Builder
     */
    public function table(string $table): Builder
    {
        return $this->MysqlObj(0, 1)->table($table, $this->_protect);
    }

    /**
     * 删
     * @param $where
     * @return bool|int|string
     * @throws Error
     */
    public function delete($where)
    {
        if (!$this->_table) throw new Error('Unable to get table name', $this->_traceLevel + 1);
        if (is_numeric($where)) {
            $where = [$this->PRI() => intval($where)];
        }

        $mysql = $this->MysqlObj(0, 1);
        $obj = $mysql->table($this->_table, $this->_protect);
        $val = $obj->where($where)->delete($this->_traceLevel + 1);

        $this->delete_cache($this->_table, $where);

        return $this->checkRunData('delete', $val) ?: $val;
    }


    /**
     * 改
     * @param $where
     * @param array $data
     * @return bool|null
     * @throws Error
     */
    public function update($where, array $data)
    {
        if (!$this->_table) throw new Error('Unable to get table name', $this->_traceLevel + 1);
        if (is_numeric($where)) {
            $where = [$this->PRI() => intval($where)];
        }
        if (empty($where)) throw new Error('Update Where 禁止为空', $this->_traceLevel + 1);
        $mysql = $this->MysqlObj(0, 1);

        $obj = $mysql->table($this->_table, $this->_protect);
        $val = $obj->where($where)->update($data, true, $this->_traceLevel + 1);

        $this->delete_cache($this->_table, $where);

        return $this->checkRunData('update', $val) ?: $val;
    }


    /**
     * 执行一个存储过程
     *
     * @param string $proName
     * @param array $params
     * @return array|mixed|null
     * @throws Error
     */
    public function call(string $proName, array $params)
    {
        $mysql = $this->MysqlObj(0, 1);
        $call = $mysql->procedure($proName, $params, $this->_traceLevel + 1);

        $val = $call->rows();

        if ($val === false) $val = null;

        return $val;
    }

    public function pdo(int $transID = 0): PdoContent
    {
        return $this->MysqlObj($transID, 1);
    }

    /**
     * 不经过安全检测
     *
     * @throws Error
     */
    public function execute(string $sql)
    {
        return $this->MysqlObj(0, 1)->execute($sql);
    }

    /**
     * 直接执行一条sql，经过一些基本的安全检测
     *
     * @param string $sql
     * @return bool|Result|int|string|null
     * @throws Error
     */
    public function query(string $sql)
    {
        return $this->MysqlObj(0, 1)->query($sql);
    }

    private function decode_data($data, $decode)
    {
        if (isset($decode['array'])) $decode['json'] = $decode['array'];
        if (isset($decode['json'])) {
            foreach ($decode['json'] as $k) {
                if (is_int($data[$k[1]])) {
                    $data[$k[0]] = numbers($data[$k[1]]);
                } else if (is_array($data[$k[1]])) {
                    $data[$k[0]] = $data[$k[1]];
                } else {
                    $data[$k[0]] = json_decode(($data[$k[1]] ?? ''), true) ?: [];
                }
            }
        }

        if (isset($decode['time'])) {
            foreach ($decode['time'] as $k) {
                $data[$k[0]] = date('Y-m-d H:i:s', ($data[$k[1]] ?? 0));
            }
        }

        if (isset($decode['point'])) {
            foreach ($decode['point'] as $k) {
                preg_match('/POINT\((-?[\d\.]+)\s(-?[\d\.]+)\)/i', $data[$k[1]], $locMch);
                $data[$k[0]] = [
                    'longitude' => floatval($locMch[1] ?? 0),
                    'latitude' => floatval($locMch[2] ?? 0)
                ];
            }
        }

        return $data;
    }

    /**
     * 选择一条记录
     * @param $where
     * @param string|null $orderBy
     * @param string $sort
     * @return mixed|null
     * @throws Error
     */
    public function get($where, string $orderBy = null, string $sort = 'asc')
    {
        $mysql = $this->MysqlObj(0, 1);
        if (!$this->_table) throw new Error('Unable to get table name', $this->_traceLevel + 1);
        if (is_numeric($where)) {
            $where = [$this->PRI() => intval($where)];
        }

        $_select = $this->selectKey;
        if ($this->_cache > 1) {
            $data = $this->pool->cache()->table($this->_table)->read($where);
            if (!empty($data)) {

                $this->pool->debug([
                    'cache' => 1,
                    'sql' => 'select' . " * from {$this->_table} where ...",
                    'params' => json_encode($where, 320)
                ], $this->_traceLevel + 1);

                if (isset($this->pool->counter)) {
                    foreach ($where as $k => &$v) $v = (is_numeric($v) ? '%d' : '%s');
                    $sql = "HitCache({$this->_table}) " . json_encode($where, 320);
                    $this->pool->counter->recodeMysql('select', $sql, $this->_traceLevel + 1);
                }
                if ($this->_decode) $data = $this->decode_data($data, $this->_decode);
                $this->clear_initial();
                $this->_cache = 0;
                if ($_select) {
                    $_select = implode(',', array_column($_select, 0));
                    if (!str_contains($_select, '.')) {
                        $_select = explode(',', str_replace(',,', ',', $_select));
                        foreach ($data as $k => $vm) {
                            if (!in_array($k, $_select)) unset($data[$k]);
                        }
                    }
                }

                return $data;
            }
            $this->selectKey = [];//调了缓存，就删除select项
        }
        $table = $this->_table;

        $obj = $mysql->table($this->_table, $this->_protect);
        if (is_int($this->columnKey)) $obj->fetch(0);

        if (!empty($this->selectKey)) {
            foreach ($this->selectKey as $select) $obj->select(...$select);
        }
        if (!empty($this->tableJoin)) {
            foreach ($this->tableJoin as $join) $obj->join(...$join);
        }
        if ($this->forceIndex) $obj->force($this->forceIndex);
        if ($this->_having) $obj->having($this->_having);
        if ($where) $obj->where($where);
        if (isset($this->groupKey)) $obj->group($this->groupKey);
        if ($this->_distinct) $obj->distinct($this->_distinct);

        if (!empty($this->_order)) {
            foreach ($this->_order as $k => $a) {
                $obj->order($a['key'], $a['sort'], $a['pro']);
            }
        }
        if ($orderBy === 'PRI') $orderBy = $this->PRI($this->_table);
        if ($orderBy) {
            if (!in_array(strtolower($sort), ['asc', 'desc', 'rand'])) $sort = 'ASC';
            $obj->order($orderBy, $sort);
        }
        $data = $obj->get(1, $this->_traceLevel + 1);
        $_decode = $this->_decode;//必须在checkRunData之前缓存

        $ck = $this->checkRunData('get', $data);
        if ($ck) {
            $this->_cache = 0;
            return $ck;
        }

        $val = $data->row($this->columnKey);
        if ($val === false or $val === null) {
            $this->_cache = 0;
            return null;
        }

        if ($this->_cache) {
            $this->pool->debug([
                'cache' => 1,
                'sql' => "save cache from {$table} where ...",
                'params' => json_encode($where, 320)
            ], $this->_traceLevel + 1);

            $this->pool->cache()->table($table)->save($where, $val);
            $this->_cache = 0;
        }
        if ($_decode) $val = $this->decode_data($val, $_decode);
        if ($_select) {
            $_select = implode(',', array_column($_select, 0));
            if (!str_contains($_select, '.')) {
                $_select = explode(',', str_replace(',,', ',', $_select));
                foreach ($data as $k => $vm) {
                    if (!in_array($k, $_select)) unset($data[$k]);
                }
            }
        }

        return $val;
    }


    /**
     * @param array $where
     * @param string|null $orderBy
     * @param string $sort
     * @param int $limit
     * @param int $skip
     * @return array
     * @throws Error
     */
    public function all(array $where = [], string $orderBy = null, string $sort = 'asc', int $limit = 0, int $skip = 0)
    {
        if (!$this->_table) throw new Error('Unable to get table name', $this->_traceLevel + 1);
        $obj = $this->MysqlObj(0, 1)->table($this->_table, $this->_protect)->prepare();
        if ($orderBy === 'PRI') {
            $orderBy = $this->PRI($this->_table);
            if (isset($where['PRI'])) {
                $where[$orderBy] = $where['PRI'];
                unset($where['PRI']);
            }
        }

        if (!empty($this->selectKey)) {
            foreach ($this->selectKey as $select) $obj->select(...$select);
        }
        if (!empty($this->tableJoin)) {
            foreach ($this->tableJoin as $join) $obj->join(...$join);
        }
        if ($where) $obj->where($where);
        if (isset($this->groupKey)) $obj->group($this->groupKey);
        if ($this->forceIndex) $obj->force($this->forceIndex);
        if ($this->_having) $obj->having($this->_having);
        if ($this->_distinct) $obj->distinct($this->_distinct);

        if (!empty($this->_order)) {
            foreach ($this->_order as $k => $a) {
                $obj->order($a['key'], $a['sort'], $a['pro']);
            }
        }
        if ($orderBy) {
            if (!in_array(strtolower($sort), ['asc', 'desc', 'rand'])) $sort = 'ASC';
            $obj->order($orderBy, $sort);
        }

        $data = $obj->skip($skip)->get($limit, $this->_traceLevel + 1);
        $_decode = $this->_decode;
        if ($v = $this->checkRunData('all', $data)) return $v;

        return $data->rows(0, $this->columnKey, $_decode);
    }

    /**
     * 统计总数
     *
     * @param array $where
     * @return int
     * @throws Error
     */
    public function count(array $where = []): int
    {
        $this->selectKey = [['count(1) as c', false]];
        $dbs = $this->get($where);
        if (empty($dbs)) return 0;
        return intval($dbs['c'] ?? 0);
    }

    /**
     * 取随机几条
     *
     * @param array $where
     * @param int $limit
     * @return array
     * @throws Error
     */
    public function rand(array $where = [], int $limit = 1): array
    {
        $dbs = $this->all($where, 'RAND', 'asc', $limit);
        if (empty($dbs)) return [];
        return $dbs;
    }

    /**
     * @param null $where
     * @param null $orderBy
     * @param string $sort
     * @return array|mixed|null
     * @throws Error
     */
    public function list($where = null, $orderBy = null, string $sort = 'desc')
    {
        if (!$this->_table) throw new Error('Unable to get table name', $this->_traceLevel + 1);
        $obj = $this->MysqlObj(0, 1)->table($this->_table, $this->_protect);
        if (!empty($this->selectKey)) {
            foreach ($this->selectKey as $select) $obj->select(...$select);
        }
        if (!empty($this->tableJoin)) {
            foreach ($this->tableJoin as $join) $obj->join(...$join);
        }
        $obj->protect($this->_protect);
        if ($this->forceIndex) $obj->force($this->forceIndex);
        if ($this->_distinct) $obj->distinct($this->_distinct);

        if ($where) $obj->where($where);
        if (isset($this->groupKey)) $obj->group($this->groupKey);
        if ($this->_having) $obj->having($this->_having);

        if (!empty($this->_order)) {
            foreach ($this->_order as $k => $a) {
                $obj->order($a['key'], $a['sort'], $a['pro']);
            }
        }

        if ($orderBy === 'PRI') $orderBy = $this->PRI($this->_table);
        if ($orderBy) {
            if (!in_array(strtolower($sort), ['asc', 'desc', 'rand'])) $sort = 'ASC';
            $obj->order($orderBy, $sort);
        }

        $obj->count($this->_count === 0);
        if (isset($this->sumKey)) $obj->sum($this->sumKey);

        if (!isset($this->pool->paging)) {
            $this->pool->paging = new Paging(0, 0, $this->_count);
        }

        $skip = ($this->pool->paging->index - 1) * $this->pool->paging->size;
        $data = $obj->limit($this->pool->paging->size, $skip)->get(0, $this->_traceLevel + 1);
        $_decode = $this->_decode;//中转一下，下面checkRunData里会清空此值

        if ($this->_count === 0) $this->pool->paging->recode($data->count());
        if (isset($this->sumKey)) $this->pool->paging->sum($data->sum());
        $this->pool->paging->calculate(0);

        if ($v = $this->checkRunData('list', $data)) return $v;
        return $data->rows(0, null, $_decode);
    }

    public function having(string $filter): Mysql
    {
        $this->_having = $filter;
        return $this;
    }

    /**
     * 压缩字符串
     * @param string $string
     * @return false|string
     */
    public function gz(string $string)
    {
        try {
            return gzcompress($string, 5);
        } catch (\Error|\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 解压缩字符串
     * @param string $string
     * @return false|string
     */
    public function ugz(string $string)
    {
        try {
            return gzuncompress($string);
        } catch (\Error|\Exception $e) {
            return $e->getMessage();
        }
    }

    public function decode(string $cols, string $type = 'json'): Mysql
    {
        if (!isset($this->_decode[$type])) $this->_decode[$type] = [];
        array_push($this->_decode[$type], ...array_map(function ($col) {
            if (strpos($col, '=') > 0) return explode('=', $col);
            return [$col, $col];
        }, explode(',', $cols)));
        return $this;
    }

    /**
     * 组合空间-点
     * @param $lng
     * @param null $lat
     * @return string
     */
    public function point($lng, $lat = null): string
    {
        if (is_null($lat) and is_array($lng)) {
            $lng = $lng['longitude'] ?? ($lng['lng'] ?? ($lng[0] ?? 0));
            $lat = $lng['latitude'] ?? ($lng['lat'] ?? ($lng[1] ?? 0));
        }
        return "point({$lng} {$lat})";
    }

    /**
     * 组合空间-闭合的区域
     * @param array $location
     * @return string
     * @throws Error
     */
    public function polygon(array $location): string
    {
        if (count($location) < 3) throw new Error("空间区域至少需要3个点");
        $val = [];
        $fst = null;
        $lst = null;
        foreach ($location as $loc) {
            $lst = "{$loc['lng']} {$loc['lat']}";
            $val[] = $lst;
            if (is_null($fst)) $fst = $lst;
        }
        if ($fst !== $lst) $val[] = $fst;
        return "polygon(" . implode(',', $val) . ")";
    }


    /**
     * 地址类型转换可识别类型
     * 其他字段要另外 ->select('*') 且必须先select(*)
     * 如果不是*而是指定字段就无所谓先后了
     *
     * @param string $field
     * @param string $type point,polygon
     * @return $this
     */
    public function location(string $field, string $type = 'point'): Mysql
    {
        $this->selectKey[] = ["ST_AsText({$field}) as {$field}", false];
        return $this->decode($field, $type);
    }

    /**
     * 统计某几个字段的和值
     *
     * @param string $sumKey
     * @return $this
     */
    public function sum(string $sumKey): Mysql
    {
        $this->sumKey = $sumKey;
        $this->_count = 0;
        return $this;
    }

    /**
     * 当前请求结果的总行数
     * @param int|null $count
     * @return $this
     *
     * $count取值：
     * true     :执行count()统计总数
     * 0|false  :不统计总数
     * <0       :size的倍数，为了分页不至于显示0页
     * 0以上    :为指定总数
     */
    public function do_count(int $count = null): Mysql
    {
        if (is_null($count)) {//无参，表示执行统计
            $this->_count = 0;

        } else if ($count === 0) {//送入0表示不执行统计
            $this->_count = PHP_INT_MIN;

        } else if ($count < 0) {//负数，:size的倍数，为了分页不至于显示0页
            $this->_count = $count;

        } else {//大于0表示已知道总数，不再计算，以此值为准
            $this->_count = $count;
        }

        return $this;
    }

    /**
     * 是否加保护符，默认加
     * @param bool $protect
     * @return $this
     */
    public function protect(bool $protect): Mysql
    {
        $this->_protect = $protect;
        return $this;
    }

    /**
     * 消除重复行
     * @param bool $bool
     * @return $this
     */
    public function distinct(bool $bool = true): Mysql
    {
        $this->_distinct = $bool;
        return $this;
    }

    public function paging(int $size, int $index = 0, int $recode = null): Mysql
    {
        if (!isset($this->pool->paging)) {
            $this->pool->paging = new Paging($size, $index, $recode);
        } else {
            $this->pool->paging->size($size)->index($index);
        }
        return $this;
    }

    public function pagingIndex(int $index): Mysql
    {
        $this->pool->paging->index($index);
        return $this;
    }

    public function pagingSize(int $size): Mysql
    {
        $this->pool->paging->size($size);
        return $this;
    }

    /**
     * @param string $string
     * @return false|string
     * @throws Error
     */
    public function quote(string $string)
    {
        return $this->MysqlObj(0, 1)->quote($string);
    }

    /**
     * 断开所有链接，
     */
    public function close(): void
    {
        $branchName = $this->_branch ?? 'auto';
        $mysql = $this->_MysqlPool[$branchName];
        $mysql->close();
    }


    public function join(...$data): Mysql
    {
        if (empty($data)) {
            $this->tableJoin = array();
            return $this;
        }
        $this->tableJoin[] = $data;
        return $this;
    }

    public function group(string $groupKey, bool $only = false): Mysql
    {
        if ($only) $this->columnKey = 0;
        $this->groupKey = $groupKey;
        return $this;
    }


    /**
     * 返回指定键列
     * @param string $field
     * @return $this
     */
    public function field(string $field): Mysql
    {
        $this->columnKey = 0;
        $this->selectKey = [[$field, true]];
        return $this;
    }

    /**
     * 返回第x列数据
     * @param int $field
     * @return $this
     */
    public function column(int $field = 0): Mysql
    {
        $this->columnKey = $field;
        return $this;
    }

    /**
     * 强制从索引中读取，多索引用逗号连接
     * @param $index
     * @return $this
     */
    public function force($index): Mysql
    {
        return $this->index($index);
    }

    /**
     * 强制从索引中读取
     * @param  $index
     * @return $this
     */
    public function index($index): Mysql
    {
        if (empty($index)) return $this;
        if (is_string($index)) $index = explode(',', $index);
        $new = array_merge($this->forceIndex, $index);
        $this->forceIndex = array_diff(array_unique($new), ['']);
        return $this;
    }


    /**
     * 设置排序字段，优先级高于函数中指定的方式
     * @param $key
     * @param string $sort
     * @param bool $addProtect
     * @return $this
     */
    public function order($key, string $sort = 'asc', bool $addProtect = null): Mysql
    {
        if ($sort === '') return $this;

        if (is_array($key)) {
            foreach ($key as $ks) {
                if (!isset($ks[1])) $ks[1] = 'asc';
                if (!isset($ks[2])) $ks[2] = true;
                if (!in_array(strtolower($ks[1]), ['asc', 'desc', 'rand'])) $ks[1] = 'ASC';
                $this->_order[] = ['key' => $ks[0], 'sort' => $ks[1], 'pro' => $ks[2]];
            }
            return $this;
        }
        if (!in_array(strtolower($sort), ['asc', 'desc', 'rand'])) $sort = 'ASC';
        if (is_null($addProtect)) $addProtect = $this->_protect;
        $this->_order[] = ['key' => $key, 'sort' => $sort, 'pro' => $addProtect];
        return $this;
    }

    /**
     * @param $select
     * @param $add_identifier
     * @return $this
     * @throws Error
     */
    public function select($select, $add_identifier = null): Mysql
    {
        if (is_int($add_identifier)) {
            //当$add_identifier是整数时，表示返回第x列数据
            $this->columnKey = $add_identifier;
            $this->selectKey[] = [$select, true];

        } else {
            if (is_null($add_identifier)) $add_identifier = $this->_protect;
            if ($select and ($select[0] === '~' or $select[0] === '!')) {
                //不含选择，只适合从单表取数据
                $field = $this->fields();
                $seKey = array_column($field, 'COLUMN_NAME');
                $kill = explode(',', substr($select, 1));
                $this->selectKey[] = [implode(',', array_diff($seKey, $kill)), $add_identifier];

            } else {
                $this->selectKey[] = [$select, $add_identifier];
            }
        }
        return $this;
    }


}