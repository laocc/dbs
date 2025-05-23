<?php
declare(strict_types=1);

namespace esp\dbs\mysql;

use PDO;
use esp\error\Error;
use esp\dbs\Pool;
use PDOException;
use function esp\helper\_echo;

final class PdoContent
{
    public Pool $pool;
    private array $_pool = [];//进程级的连接池，$master，$slave

    private array $_CONF;//配置定义
    private array $_trans_run = array();//事务状态
    private string $_trans_error = '';//事务出错状态
    private array $connect_time = array();//连接时间
    private bool $_checkGoneAway = false;
    private bool $_cli_print_sql = false;
    private int $transID;
    public string $dbName;
    public array $_error = array();//每个连接的错误信息
    public bool $_debug_sql = false;

    /**
     * PdoContent constructor.
     * @param int $tranID
     * @param array $conf
     * @param Pool $pool
     */
    public function __construct(int $tranID, array $conf, Pool $pool)
    {
        $this->pool = &$pool;
        $this->_CONF = $conf + [
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
                'persistent' => false,//是否持久连
                'param' => true,
                'cache' => false,
                'timeout' => 2,
                'time_limit' => 5,//单条sql超时报警
            ];
        $this->transID = $tranID;
        $this->_checkGoneAway = _CLI;
        $this->dbName = $this->_CONF['db'];

        if (isset($this->_CONF['debug_sql'])) {
            $this->_debug_sql = boolval($this->_CONF['debug_sql']);
        }

    }

    /**
     * 统计执行sql并发
     *
     * @param string $action
     * @param string $sql
     * @param int $traceLevel
     * @throws Error
     */
    public function counter(string $action, string $sql, int $traceLevel)
    {
        if (!isset($this->pool->counter)) return;
        if ($traceLevel === -1) $this->pool->counter->recodeMysql($action, $sql, 1);
        $this->pool->counter->recodeMysql($action, $sql, $traceLevel + 1);
    }

    /**
     * @param string $tabName
     * @param bool|null $_protect
     * @return Builder
     */
    public function table(string $tabName, bool $_protect = null): Builder
    {
        $bud = new Builder($this, boolval($this->_CONF['param'] ?? 0), $this->transID);
        return $bud->table($tabName, $_protect);
    }

    /**
     * 存储过程
     *
     * @param string $proName
     * @param array $params
     * @param int $traceLevel
     * @return bool|int|Result|null
     * @throws Error
     */
    public function procedure(string $proName, array $params, int $traceLevel = 1)
    {
        $bud = new Builder($this, boolval($this->_CONF['param'] ?? 0), $this->transID);
        return $bud->procedure($proName, $params, $traceLevel);
    }


    /**
     * @param bool $upData
     * @param int $trans_id
     * @param int $isTry
     * @return mixed|PDO
     * @throws Error
     */
    private function connect(bool $upData, int $trans_id = 0, int $isTry = 0)
    {
        $real = $upData ? 'master' : 'slave';
        if (!$upData and !isset($this->_CONF['slave'])) $real = 'master';

        //当前缓存过该连接，直接返回
        if (!$isTry and isset($this->_pool[$real][$trans_id]) and !empty($this->_pool[$real][$trans_id])) {
            return $this->_pool[$real][$trans_id];
        }

        $cnf = $this->_CONF;
        if (!$upData) {
            $host = $cnf['slave'] ?? $cnf['master'];

            //不是更新操作时，选择从库，需选择一个点
            if (is_array($host)) {
                $host = $host[ip2long(_CIP) % count($host)];
            }
        } else {
            $host = $cnf['master'];
        }

        //自动提交事务=false，默认true,如果有事务ID，则为该事务的状态反值
        if (isset($this->_trans_run[$trans_id])) {
            $autoCommit = !$this->_trans_run[$trans_id];
        } else {
            $autoCommit = true;
        }

        try {
            $opts = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,//错误等级
                PDO::ATTR_AUTOCOMMIT => $autoCommit,//自动提交事务=false，默认true,如果有事务ID，则为false
                PDO::ATTR_EMULATE_PREPARES => false,//是否使用PHP本地模拟prepare,禁止
                PDO::ATTR_PERSISTENT => boolval($cnf['persistent'] ?? 0),//是否启用持久连接
                PDO::ATTR_TIMEOUT => intval($cnf['timeout']), //设置超时时间，秒，默认=2
            );
            if ($host[0] === '/') {//unix_socket
                $conStr = "mysql:dbname={$cnf['db']};unix_socket={$host};charset={$cnf['charset']};id={$trans_id};";
            } else {
                $port = 3306;
                if (strpos($host, ':') > 0) list($host, $port) = explode(':', "{$host}:3306", 2);
                $conStr = "mysql:dbname={$cnf['db']};host={$host};port={$port};charset={$cnf['charset']};id={$trans_id};";
            }

            try {
                $pdo = new PDO($conStr, $cnf['username'], $cnf['password'], $opts);
                $this->counter('connect', $conStr, -1);
                if (!_CLI) $this->pool->debug("{$real}({$trans_id}):{$conStr}");
                if (_CLI and $isTry) print_r([$opts, $cnf, $conStr]);

            } catch (PDOException $PdoError) {
                $err = [];
                $err['code'] = $PdoError->getCode();
                $err['msg'] = $PdoError->getMessage();
                $err['host'] = $host;
                throw new Error("MysqlPDO Connection failed:" . json_encode($err, 256 | 64));
            }

            $this->connect_time[$trans_id] = time();
            return $this->_pool[$real][$trans_id] = $pdo;

        } catch (PDOException $PdoError) {
            /*
             *信息详细程度取决于$opts里PDO::ATTR_ERRMODE =>
             * PDO::ERRMODE_SILENT，只简单地设置错误码，默认值
             * PDO::ERRMODE_WARNING： 还将发出一条传统的 E_WARNING 信息，
             * PDO::ERRMODE_EXCEPTION，还将抛出一个 PDOException 异常类并设置它的属性来反射错误码和错误信息，
            */
            throw new Error("MysqlPDO Connection failed:" . $PdoError->getCode() . ',' . $PdoError->getMessage(), 1, 1);
        }
    }


    /**
     * 从SQL语句中提取该语句的执行性质
     * @param string $sql
     * @return string
     * @throws Error
     */
    private function sqlAction(string $sql): string
    {
        if (preg_match('/^(select|insert|replace|update|delete|alter|analyze|call)\s+.+/is', trim($sql), $matches)) {
            return strtolower($matches[1]);
        } else {
            throw new Error("PDO_Error:SQL语句不合法:{$sql}");
        }
    }

    /**
     * @throws Error
     */
    public function quote($string)
    {
        $CONN = $this->connect(false, 0);
        return $CONN->quote($string);
    }

    /**
     * @param int $transID
     * @param string $real
     * @param PDO $CONN
     * @return bool true=已离线，false在线
     * @throws Error
     */
    private function connHasGoneAway(int $transID, string $real, PDO $CONN): bool
    {
        if (!$CONN->getAttribute(PDO::ATTR_PERSISTENT)) return false;

        $time = time();

        try {

            $info = $CONN->getAttribute(PDO::ATTR_SERVER_INFO);

        } catch (\Error|\Exception $error) {
            ////获取属性出错，PHP Warning:  PDO::getAttribute(): MySQL server has gone away in
            if (_CLI) {
                print_r([
                    'id' => $transID,
                    'connect_time' => $this->connect_time[$transID],
                    'now' => $time,
                    'wait' => $time - $this->connect_time[$transID],
                    'error' => $error->getMessage(),
                    'code' => $error->getCode(),
                ]);
                print_r($this->PdoAttribute($CONN));
            }

            unset($this->_pool[$real][$transID]);
            return true;
        }

        if (empty($info)) {//获取不到有关属性，说明连接可能已经断开
            if (_CLI) {
                print_r([
                    'id' => $transID,
                    'connect_time' => $this->connect_time[$transID],
                    'now' => $time,
                    'wait' => $time - $this->connect_time[$transID],
                ]);
                print_r($this->PdoAttribute($CONN));
            }
            unset($this->_pool[$real][$transID]);
            return true;
        }

        return false;
    }

    private function PdoAttribute(PDO $pdo): array
    {
        $attributes = array(
            'PARAM_BOOL', 'PARAM_NULL', 'PARAM_LOB', 'PARAM_STMT', 'FETCH_NAMED', 'FETCH_NUM', 'FETCH_BOTH', 'FETCH_OBJ', 'FETCH_BOUND', 'FETCH_COLUMN', 'FETCH_CLASS', 'FETCH_KEY_PAIR',
            'ATTR_AUTOCOMMIT', 'ATTR_ERRMODE', 'ATTR_SERVER_VERSION', 'ATTR_CLIENT_VERSION', 'ATTR_SERVER_INFO', 'ATTR_CONNECTION_STATUS', 'ATTR_CASE', 'ATTR_DRIVER_NAME', 'ATTR_ORACLE_NULLS', 'ATTR_PERSISTENT',
            'ATTR_STATEMENT_CLASS', 'ATTR_DEFAULT_FETCH_MODE', 'ATTR_EMULATE_PREPARES', 'ERRMODE_SILENT', 'CASE_NATURAL', 'NULL_NATURAL', 'FETCH_ORI_NEXT', 'FETCH_ORI_LAST',
            'FETCH_ORI_ABS', 'FETCH_ORI_REL', 'CURSOR_FWDONLY', 'ERR_NONE', 'PARAM_EVT_ALLOC', 'PARAM_EVT_EXEC_POST', 'PARAM_EVT_FETCH_PRE', 'PARAM_EVT_FETCH_POST', 'PARAM_EVT_NORMALIZE',
        );
        $attr = [];
        foreach ($attributes as $val) {
            $it = constant("\PDO::{$val}");
            if (is_int($it)) {
                $attr["PDO::{$val}"] = $pdo->getAttribute($it);
            } else {
                $attr["PDO::{$val}"] = $it;
            }
        }
        return $attr;
    }


    /**
     * 直接执行，不进行基本安全检测
     *
     * @throws Error
     */
    public function execute(string $sql, array $option = [], PDO $CONN = null, int $traceLevel = 0)
    {
        if (empty($sql)) {
            throw new Error("PDO_Error :  SQL语句不能为空", $traceLevel + 1);
        }
        if (_CLI and $this->_cli_print_sql) echo "{$sql}\n";

        if (empty($option) or !isset($option['trans_id']) or !isset($option['action']) or !isset($option['param'])) {
            $option = [
                'param' => $option,
                'prepare' => true,
                'count' => false,
                'fetch' => 1,
                'limit' => 0,
                'bind' => [],
                'trans_id' => 0,
                'action' => 'select',
            ];
        }

        $transID = ($option['trans_id']);
        $real = 'master';
        $try = 0;
        tryExe://重新执行起点

        //连接数据库，自动选择主从库
        if (!$CONN) {
            if (!$try and isset($this->_pool[$real][$transID]) and !empty($this->_pool[$real][$transID])) {
                $CONN = $this->_pool[$real][$transID];
            } else {
                $CONN = $this->connect(true, $transID, $try);
            }
        }

        if ($this->_checkGoneAway and $this->connHasGoneAway($transID, $real, $CONN)) {
            if (($try++) < 3) goto tryExe;
            if (_CLI) echo "Pool CreateTime:{$this->pool->createTime}\n";
            throw new Error("PDO_Error :  MysqlPDO has gone away", $traceLevel + 1);
        }

        $debug = true;
        $debug_sql = (($option['debug_sql'] ?? null) !== false);

        $error = array();//预置的错误信息

        $debugOption = [
            'trans' => $transID,
            'server' => $CONN->getAttribute(PDO::FETCH_COLUMN),//服务器IP
            'sql' => $sql,
            'prepare' => (!empty($option['param']) or $option['prepare']) ? 'YES' : 'NO',
            'param' => json_encode($option['param'], 256 | 64),
            'ready' => microtime(true),
        ];
        $result = $this->select($CONN, $sql, $option, $error, $traceLevel + 1);//执行

        $debugOption += [
            'finish' => $time_b = microtime(true),
            'runTime' => ($time_b - $debugOption['ready']) * 1000,
            'result' => is_object($result) ? 'Result' : var_export($result, true),
        ];
        if (($option['limit'] ?? 0) > 0 and !_CLI and $debugOption['runTime'] > $option['limit']) {
            $trueSQL = str_replace(array_keys($option['param']), array_map(function ($v) {
                return is_string($v) ? "'{$v}'" : $v;
            }, array_values($option['param'])), $sql);

            $this->pool->debug(print_r($debugOption, true), $traceLevel + 1);
            $this->pool->error(["SQL耗时超过限定的{$option['limit']}ms", $debugOption, $trueSQL], $traceLevel + 1);
        }

        if (!empty($error)) {
            $debugOption['error'] = $error;
            $this->_error[$transID] = $error;

            $errState = intval($error[1]);
            _CLI and print_r(['try' => $try, 'error' => $errState]);

            if ($debug and !_CLI) {
                $this->pool->debug(print_r($debugOption, true), $traceLevel + 1);
                $this->pool->error($error, $traceLevel + 1);
            }

            if ($try++ < 2 and in_array($errState, [2002, 2006, 2013])) {
                if (_CLI) {
                    print_r($debugOption);
                    print_r([
                        'id' => $transID,
                        'connect_time' => $this->connect_time[$transID],
                        'now' => time(),
                        'after' => time() - $this->connect_time[$transID],
                    ]);
                } else if ($debug) {
                    $this->pool->debug(print_r($debugOption, true), $traceLevel + 1);
                }
                unset($this->_pool[$real][$transID]);
                $CONN = null;
                goto tryExe; //重新执行
            }
            if ($debug) $error['sql'] = $sql;
            if (_CLI) print_r($debugOption);
            ($debug and !_CLI) and $this->pool->debug(print_r($debugOption, true), $traceLevel + 1);
            return json_encode($error, 256 | 64);
        }
        ($debug and $debug_sql and !_CLI) and $this->pool->debug(print_r($debugOption, true), $traceLevel + 1);
        return $result;
    }

    /**
     * 执行sql
     * 此方法内若发生错误，必须以string返回
     * @param string $sql
     * @param array $option
     * @param PDO|null $CONN
     * @param int $traceLevel
     * @return bool|string|int|Result
     * @throws Error
     */
    public function query(string $sql, array $option = [], PDO $CONN = null, int $traceLevel = 0)
    {
        if (empty($sql)) {
            throw new Error("PDO_Error :  SQL语句不能为空", $traceLevel + 1);
        }
        if (_CLI and $this->_cli_print_sql) echo "{$sql}\n";

        if (empty($option) or !isset($option['trans_id']) or !isset($option['action']) or !isset($option['param'])) {
            $option = [
                'param' => $option,
                'prepare' => true,
                'count' => false,
                'fetch' => 1,
                'limit' => 0,
                'bind' => [],
                'trans_id' => 0,
//                'action' => $this->sqlAction($sql),
                'action' => strtolower(substr($sql, 0, strpos($sql, ' '))),
            ];
        }

        $action = strtolower($option['action']);
        $transID = ($option['trans_id']);

        if (!in_array($action, ['select', 'insert', 'replace', 'update', 'delete', 'alter', 'analyze', 'call'])) {
            throw new Error("PDO_Error :  数据处理方式不明确：【{$action}】。", $traceLevel + 1);
        }

        //是否更新数据操作
        $upData = ($action !== 'select');
        $real = $upData ? 'master' : 'slave';
        //这4种操作要换成当前类中的对应操作方法
        switch ($action) {
            case 'delete':
                $action = 'update';
                break;
            case 'replace':
                $action = 'insert';
                break;
            case 'alter':
            case 'analyze':
            case 'call':
                $action = 'select';
                break;
        }

        $try = 0;
        tryExe://重新执行起点

        //连接数据库，自动选择主从库
        if (!$CONN) {
            if (!$try and isset($this->_pool[$real][$transID]) and !empty($this->_pool[$real][$transID])) {
                $CONN = $this->_pool[$real][$transID];
            } else {
                $CONN = $this->connect($upData, $transID, $try);
            }
        }

        if ($this->_checkGoneAway and $this->connHasGoneAway($transID, $real, $CONN)) {
            if (($try++) < 3) goto tryExe;
            if (_CLI) echo "Pool CreateTime:{$this->pool->createTime}\n";
            throw new Error("PDO_Error :  MysqlPDO has gone away", $traceLevel + 1);
        }

        $debug = true;
        $debug_sql = (($option['debug_sql'] ?? null) !== false);

        //数据操作时，若当前`trans_run`=false，则说明刚才被back过了或已经commit，后面的数据不再执行
        //更新操作，有事务ID，在运行中，且已被标识为false
        if ($upData and $transID and (!($this->_trans_run[$transID] ?? 0) or !$CONN->inTransaction())) {
            return null;
        }

        $error = array();//预置的错误信息

        $debugOption = [
            'trans' => $transID,
            'server' => $CONN->getAttribute(PDO::FETCH_COLUMN),//服务器IP
            'sql' => $sql,
            'prepare' => (!empty($option['param']) or $option['prepare']) ? 'YES' : 'NO',
            'param' => json_encode($option['param'], 256 | 64),
            'ready' => microtime(true),
        ];
        $result = $this->{$action}($CONN, $sql, $option, $error, $traceLevel + 1);//执行

//        print_r(['$result' => $result, 'act' => $action, '$option' => $option, '$sql' => $sql, '$error' => $error]);

        $debugOption += [
            'finish' => $time_b = microtime(true),
            'runTime' => ($time_b - $debugOption['ready']) * 1000,
            'result' => is_object($result) ? 'Result' : var_export($result, true),
        ];
        if (($option['limit'] ?? 0) > 0 and $debug and !_CLI and $debugOption['runTime'] > $option['limit']) {
            $trueSQL = str_replace(array_keys($option['param']), array_map(function ($v) {
                return is_string($v) ? "'{$v}'" : $v;
            }, array_values($option['param'])), $sql);

            $this->pool->debug(print_r($debugOption, true), $traceLevel + 1);
            $this->pool->error(["SQL耗时超过限定的{$option['limit']}ms", $debugOption, $trueSQL], $traceLevel + 1);
        }

        if (!empty($error)) {
            $debugOption['error'] = $error;
            $this->_error[$transID] = $error;

            $errState = intval($error[1]);
            _CLI and print_r(['try' => $try, 'error' => $errState]);

            if ($debug and !_CLI) {
                $this->pool->debug(print_r($debugOption, true), $traceLevel + 1);
                $this->pool->error($error, $traceLevel + 1);
            }

            if ($try++ < 2 and in_array($errState, [2002, 2006, 2013])) {
                if (_CLI) {
                    _echo("Pool CreateTime:{$this->pool->createTime}", 'red');
                    print_r($debugOption);
                    print_r([
                        'id' => $transID,
                        'connect_time' => $this->connect_time[$transID],
                        'now' => time(),
                        'after' => time() - $this->connect_time[$transID],
                    ]);
                } else {
                    ($debug) and $this->pool->debug(print_r($debugOption, true), $traceLevel + 1);
                }
                unset($this->_pool[$real][$transID]);
                $CONN = null;
                goto tryExe; //重新执行

            } else if ($transID > 0 and $upData) {
                $this->trans_back($transID, $error);//回滚事务
            }
            if ($debug) $error['sql'] = $sql;
            if (_CLI) print_r($debugOption);
            ($debug and !_CLI) and $this->pool->debug(print_r($debugOption, true), $traceLevel + 1);
            return json_encode($error, 256 | 64);
        }
        ($debug and $debug_sql and !_CLI) and $this->pool->debug(print_r($debugOption, true), $traceLevel + 1);
        return $result;
    }

    /**
     * @param PDO $CONN
     * @param string $sql
     * @param array $option
     * @param $error
     * @param int $traceLevel
     * @return int|null
     */
    private function update(PDO $CONN, string $sql, array &$option, &$error, int $traceLevel): ?int
    {
        if (!empty($option['param']) or $option['prepare']) {
            try {
                $stmt = $CONN->prepare($sql, [PDO::MYSQL_ATTR_FOUND_ROWS => true]);
                if ($stmt === false) {//预处理时就出错，一般是不应该的，有可能是字段名不对等等
                    $error = $CONN->errorInfo();
                    $stmt = null;
                    return null;
                }
            } catch (PDOException $PdoError) {//执行预处理，如果出错，很少见，还没遇到过
                $error = $PdoError->errorInfo;
                $stmt = null;
                return null;
            }
            try {
                $run = $stmt->execute($option['param']);
                $this->counter('update', $sql, $traceLevel + 1);
                if ($run === false) {//执行预处理过的内容，如果不成功，多出现传入的值不符合字段类型的情况
                    $error = $stmt->errorInfo();
                    $stmt = null;
                    return null;
                }
            } catch (PDOException $PdoError) {//执行预处理过的SQL，如果出错，很少见，还没遇到过
                $error = $PdoError->errorInfo;
                return null;
            }
            $rowCount = $stmt->rowCount();
            $stmt = null;
            return $rowCount;//受影响的行数
        } else {
            try {
                $run = $CONN->exec($sql);
                $this->counter('update', $sql, $traceLevel + 1);
                if ($run === false) {
                    $error = $CONN->errorInfo();
                    return null;
                } else {
                    return $run;//受影响的行数
                }
            } catch (PDOException $PdoError) {
                $error = $PdoError->errorInfo;
                return null;
            }
        }
    }

    /**
     * 最后插入的ID，若批量插入则返回值是数组
     * @param PDO $CONN
     * @param string $sql
     * @param array $option
     * @param $error
     * @param int $traceLevel
     * @return array|int|mixed|null
     */
    private function insert(PDO $CONN, string $sql, array &$option, &$error, int $traceLevel)
    {
        if (!empty($option['param']) or $option['prepare']) {
            $result = array();
            try {
                $stmt = $CONN->prepare($sql);
                if ($stmt === false) {
                    $error = $CONN->errorInfo();
                    $stmt = null;
                    return null;
                }
            } catch (PDOException $PdoError) {//执行预处理，如果出错，很少见，还没遇到过
                $error = $PdoError->errorInfo;
                $stmt = null;
                return null;
            }
            if (!empty($option['param'])) {//有后续参数
                foreach ($option['param'] as &$row) {
                    try {
                        $run = $stmt->execute($row);
                        $this->counter('insert', $sql, $traceLevel + 1);
                        if ($run === false) {
                            $error = $stmt->errorInfo();
                            $stmt = null;
                            return null;
                        } else {
                            $result[] = (int)$CONN->lastInsertId();//最后插入的ID
                        }
                    } catch (PDOException $PdoError) {
                        $error = $PdoError->errorInfo;
                        $stmt = null;
                        return null;
                    }
                }
            } else {//无后续参数
                try {
                    $run = $stmt->execute();
                    $this->counter('insert', $sql, $traceLevel + 1);
                    if ($run === false) {
                        $error = $stmt->errorInfo();
                        $stmt = null;
                        return null;
                    } else {
                        $result[] = $CONN->lastInsertId();
                    }
                } catch (PDOException $PdoError) {
                    $error = $PdoError->errorInfo;
                    return null;
                }
            }
            $stmt = null;
            //只有一条的情况下返回一个ID
            return (count($result) === 1) ? $result[0] : $result;

        } else {
            try {
                $run = $CONN->exec($sql);
                $this->counter('insert', $sql, $traceLevel + 1);
                if ($run === false) {
                    $error = $CONN->errorInfo();
                    return null;
                } else {
                    return (int)$CONN->lastInsertId();
                }
            } catch (PDOException $PdoError) {
                $error = $PdoError->errorInfo;
                return null;
            }
        }
    }


    /**
     * @param PDO $CONN
     * @param string $sql
     * @param array $option
     * @param $error
     * @param int $traceLevel
     * @return Result|null
     */
    private function select(PDO $CONN, string &$sql, array &$option, &$error, int $traceLevel): ?Result
    {
        $fetch = [PDO::FETCH_NUM, PDO::FETCH_ASSOC, PDO::FETCH_BOTH];
        if (!in_array($option['fetch'], [0, 1, 2])) $option['fetch'] = 2;
        $count = [];
        if (!empty($option['param']) or $option['prepare']) {
            try {
                //预处理，返回结果允许游标上下移动
                $stmt = $CONN->prepare($sql, [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL]);
                if ($stmt === false) {
                    $error = $CONN->errorInfo();
                    $stmt = null;
                    return null;
                }
            } catch (PDOException $PdoError) {//执行预处理，如果出错，很少见，还没遇到过
                $error = $PdoError->errorInfo;
                $stmt = null;
                return null;
            }

            try {
                //返回数据方式：数字索引值，键值对，两者都要
                //为语句设置默认的获取模式，也就是返回索引，还是键值对
                $stmt->setFetchMode($fetch[$option['fetch']]);

                //如果有字段绑定，输入
                if (!empty($option['bind'])) {
                    foreach ($option['bind'] as $k => &$av) {
                        $stmt->bindColumn($k, $av);
                    }
                }
                $run = $stmt->execute($option['param']);
                $this->counter('select', $sql, $traceLevel + 1);
                if ($run === false) {
                    $error = $stmt->errorInfo();
                    return null;
                }

                if ($option['count']) {
                    $stmtC = $CONN->prepare($option['_count_sql']);
                    if (!empty($option['bind'])) {
                        foreach ($option['bind'] as $k => &$av) {
                            $stmtC->bindColumn($k, $av);
                        }
                    }
                    if ($stmtC === false) {
                        $error = $CONN->errorInfo();
                        return null;
                    }
                    $a = microtime(true);
                    $stmtC->execute($option['param']);
                    $this->counter('select', $sql, -1);
                    if (!_CLI && (microtime(true) - $a) > $this->_CONF['time_limit']) {
                        $this->pool->debug($option['_count_sql']);
                        $this->pool->error("SQL count 超时{$this->_CONF['time_limit']}s", $traceLevel + 1);
                    }
                    /**
                     * 这块可能有问题
                     * 在有GROUP时，执行count(1) *** group by时返回的为多条group数据
                     * 还需要考虑HAVING
                     */
                    if (strpos($option['_count_sql'], 'GROUP')) {
                        $fetchAll = $stmtC->fetchAll(PDO::FETCH_ASSOC);
                        $count = ['count' => count($fetchAll)];
                    } else {
                        $count = $stmtC->fetch(PDO::FETCH_ASSOC);
                    }

                }


            } catch (PDOException $PdoError) {
                $error = $PdoError->errorInfo;
                $stmt = null;
                return null;
            }
        } else {
            try {
                $stmt = $CONN->query($sql, $fetch[$option['fetch']]);
                $this->counter('select', $sql, $traceLevel + 1);
                if ($stmt === false) {
                    $error = $CONN->errorInfo();
                    return null;
                }

                if ($option['count']) {
                    $a = microtime(true);
                    if (strpos($option['_count_sql'], 'GROUP')) {
                        $fetchAll = $CONN->query($option['_count_sql'], PDO::FETCH_ASSOC)->fetchAll();
                        $count = ['count' => count($fetchAll)];
                    } else {
                        $count = $CONN->query($option['_count_sql'], PDO::FETCH_ASSOC)->fetch();
                    }
//                    $count = $CONN->query($option['_count_sql'], PDO::FETCH_ASSOC)->fetch();
                    $this->counter('select', $sql, -1);
                    if (!_CLI && (microtime(true) - $a) > $this->_CONF['time_limit']) {
                        $this->pool->debug($option['_count_sql']);
                        $this->pool->error("SQL count 超时{$this->_CONF['time_limit']}s", $traceLevel + 1);
                    }
                }


            } catch (PDOException $PdoError) {
                $error = $PdoError->errorInfo;
                return null;
            }
        }

        if (is_bool($count)) {
            $error = $CONN->errorInfo();
            if ($error[0] === '00000') $error[0] = '合计计数出错，可能是sql语句执行错误，请检查sql语句';
            return null;
        }

        return new Result($stmt, $count, $sql);
    }


    /**
     * 暂未实现ping
     * @return bool
     */
    public function ping(): bool
    {
        return isset($this->_pool['master']);
    }

    /**
     * 断开所有链接
     */
    public function close(): void
    {
        foreach ($this->_pool as $r => &$pool) {
            foreach ($pool as $id => &$p) $p = null;
            $pool = null;
        }
        $this->_pool = [];
    }

    /**
     * @param int $trans_id
     * @param int $traceLevel
     * @return Builder
     * @throws Error
     */
    public function builder(int $trans_id = 1, int $traceLevel = 1): Builder
    {
        if ($trans_id === 0) {
            throw new Error("Trans Error: 事务ID须从1开始，不可以为0。", 1);
        }

        if (isset($this->_trans_run[$trans_id]) and $this->_trans_run[$trans_id]) {
            throw new Error("Trans Begin Error: 当前正处于未完成的事务{$trans_id}中，或该事务未正常结束", 1);
        }

        $try = 0;
        tryExe:
        $real = 'master';

        $CONN = $this->connect(true, $trans_id);//连接数据库，直接选择主库

        if ($this->_checkGoneAway and $this->connHasGoneAway($trans_id, $real, $CONN)) {
            if (($try++) < 3) goto tryExe;
            if (_CLI) echo "Pool CreateTime:{$this->pool->createTime}\n";
            throw new Error("PDO_Error :  MysqlPDO has gone away", $traceLevel + 1);
        }

        return new Builder($this, boolval($this->_CONF['param'] ?? 0), $trans_id);
    }

    /**
     * 创建事务开始，或直接执行批量事务
     * @param int $trans_id
     * @param int $prev
     * @return Builder
     * @throws Error
     */
    public function trans(int $trans_id = 1, int $prev = 1): Builder
    {
        return $this->builder($trans_id)->trans($trans_id, $prev + 1);
    }

    /**
     * @param array $batch_SQLs
     * @param int $prev
     * @return bool
     * @throws Error
     */
    public function trans_batch(array $batch_SQLs, int $prev = 1): bool
    {
        $CONN = $this->connect(true, 1);//连接数据库，直接选择主库

        foreach ($batch_SQLs as $sql) {
            $option = [
                'param' => false,
                'prepare' => true,
                'count' => false,
                'fetch' => 0,
                'bind' => [],
                'trans_id' => 1,
                'action' => strtolower(substr($sql, 0, strpos($sql, ' '))),
            ];
            $this->query($sql, $option, $CONN, $prev + 1);
        }

        return $CONN->commit();
    }

    /**
     * @param int $transID
     * @param int $prev
     * @return $this
     * @throws Error
     */
    public function trans_star(int $transID = 1, int $prev = 1)
    {
        $CONN = $this->_pool['master'][$transID];

        if ($CONN->inTransaction()) {
            if (_CLI) {
                _echo("当前正处于未完成的事务{$transID}中，请检查上一次事务是否已提交或退回", 'black', 'red');
                _echo("如果要执行多次事务，可以用\$mod->builder()获取连接实例后再执行mod->trans()", 'h', 'blue');
            }

            throw new Error("Trans Begin Error: 当前正处于未完成的事务{$transID}中", $prev + 1);
        }

        if (!$CONN->beginTransaction()) {
            throw new Error("PDO_Error :  启动事务失败。", $prev + 1);
        }
        $this->_trans_error = '';

        $this->_trans_run[$transID] = true;
        return $this;
    }


    /**
     * 提交事务
     * @param int $trans_id
     * @param bool $close
     * @return bool|string
     * @throws Error
     */
    public function trans_commit(int $trans_id, bool $close = false): bool|string
    {
        if (isset($this->_trans_run[$trans_id]) and $this->_trans_run[$trans_id] === false) {
            if (!empty($this->_trans_error)) return $this->_trans_error;
            return false;
        }

        /**
         * @var $CONN PDO
         */
        $CONN = $this->_pool['master'][$trans_id];
        if (!$CONN->inTransaction()) {
            if ($close) $this->close();
            throw new Error("Trans Commit Error: 当前没有处于事务{$trans_id}中", 1);
        }

        $this->_trans_run[$trans_id] = false;
        $commit = $CONN->commit();

        if ($close) $this->close();

        if (!_CLI) {
            $this->pool->debug(print_r([
                'transID' => $trans_id,
                'timestamp' => microtime(true),
                'value' => var_export($commit, true)
            ], true));
        }

        return $commit;
    }

    /**
     * 回滚事务
     * @param int $trans_id
     * @param $error
     * @param bool $close
     * @return bool
     */
    public function trans_back(int $trans_id, $error, bool $close = false): bool
    {
        $this->_trans_run[$trans_id] = false;
        /**
         * @var $CONN PDO
         */
        $CONN = $this->_pool['master'][$trans_id];
        if (!$CONN->inTransaction()) {
            if ($close) $this->close();
            return true;
        }
        if (is_array($error)) $error = json_encode($error, 320);
        else if (!is_string($error)) $error = strval($error);
        $this->_trans_error = $error;
        !_CLI and $this->pool->debug($this->_trans_error);

        $back = $CONN->rollBack();
        if ($close) $this->close();
        return $back;
    }

    /**
     * 检查当前连接是否还在事务之中
     * @param PDO $CONN
     * @param int $trans_id
     * @return bool
     */
    public function trans_in(PDO $CONN, int $trans_id = 1): bool
    {
        return $CONN->inTransaction();
    }

}