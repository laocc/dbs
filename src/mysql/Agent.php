<?php

namespace esp\dbs\mysql;

use esp\dbs\Pool;

class Agent
{
    private Pool $pool;
    private array $conf;
    private bool $inTrans = false;
    private array $transSql = [];

    public function __construct(array $conf, Pool $pool)
    {
        $this->conf = $conf;
        $this->pool = &$pool;
    }


    public function trans()
    {
        $this->inTrans = true;
    }

    public function commit()
    {
        if (!$this->inTrans) return '当前未启动Trans事务';
        $agent = $this->requestGateway(['trans' => $this->transSql]);

        if (!_CLI) $this->pool->debug($this->transSql);

        if ($agent['success']) return true;
        return $agent['message'];
    }

    public function batch(array $sql)
    {
        $agent = $this->requestGateway(['trans' => $sql]);
        if ($agent['success']) return true;
        return $agent['message'];
    }

    public function query(string $action, string $sql, array $option)
    {
        $runResult = [
            'sql' => $sql,
            'param' => json_encode($option['param'], 256 | 64),
            'ready' => microtime(true),
        ];

        $params = [];
        $sqlAgent = $sql;
        preg_match_all('/(:\w+)/', $sql, $pma);
        if ($action === 'insert' or $action === 'replace') {
            foreach ($option['param'] as $val) {
                $line = [];
                foreach ($pma[0] as $key) {
                    $line[] = $val[$key];
                }
                $params[] = $line;
            }

            foreach ($pma[0] as $key) {
                $sqlAgent = str_replace($key, '?', $sqlAgent);
            }
        } else {

            foreach ($pma[0] as $key) {
                $sqlAgent = str_replace($key, '?', $sqlAgent);
                $params[] = $option['param'][$key];
            }

        }

        if ($this->inTrans) {
            $this->transSql[] = ['sql' => $sqlAgent, 'args' => $params];
            return true;
        }

        $payload = ['sql' => $sqlAgent, 'args' => $params];
        if ($option['count'] ?? '') {
            $payload['count'] = $option['count'];
        }

        if ($option['_count_sql'] ?? '') {
            $attach = [];
            $sqlAttach = $option['_count_sql'];
            preg_match_all('/(:\w+)/', $option['_count_sql'], $pmt);

            foreach ($pmt[0] as $key) {
                $sqlAttach = str_replace($key, '?', $sqlAttach);
                $attach[] = $option['param'][$key];
            }
            $payload['attach'] = ['sql' => $sqlAttach, 'args' => $attach];
        }

//        print_r($payload);

        $agent = $this->requestGateway($payload);

        $runResult += [
            'finish' => $time_b = microtime(true),
            'runTime' => ($time_b - $runResult['ready']) * 1000,
        ];

        //   ['select', 'insert', 'replace', 'update', 'delete', 'alter', 'analyze', 'call'])) {
        //   ['select', '', '', , 'alter', 'analyze', 'call'])) {

        (!_CLI) and $this->pool->debug(print_r($runResult, true));

        if ($agent['success']) {
            if ($action === 'insert' or $action === 'replace') {
                return $agent['result']['lastID'] ?? 0;
            }
            if ($action === 'update' or $action === 'delete') {
                return $agent['result']['affected'] ?? 0;
            }
        } else {
            if ($action === 'insert' or $action === 'replace') return 0;
            if ($action === 'update' or $action === 'delete') return 0;
        }

        return new AgentResult($agent, [], $sqlAgent);
    }


    private function requestGateway(array $payload): array
    {
        $baseUrl = $this->conf['go_http'] ?? '';//'http://127.0.0.1:8080/v1';
        $unixSocket = $this->conf['go_unix'] ?? '';
        $apiKey = $this->conf['go_secret'] ?? '';

        $headers = ['Content-Type: application/json'];
        if ($apiKey !== '') {
            $headers[] = 'X-API-Key: ' . $apiKey;
        }

        if (!isset($payload['sql'])) {
            if (isset($payload[1])) {
                $payload = ['trans' => $payload];
            }
        }

        $cOption = [
            CURLOPT_URL => $baseUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];

        if ($unixSocket !== '') {
            $cOption[CURLOPT_UNIX_SOCKET_PATH] = $unixSocket;
        }

        $cURL = curl_init();   //初始化一个cURL会话，若出错，则退出。
        curl_setopt_array($cURL, $cOption);

        $resp = curl_exec($cURL);
        $errno = curl_errno($cURL);
        $error = curl_error($cURL);
//        $infos = curl_getinfo($cURL);
        $status = (int)curl_getinfo($cURL, CURLINFO_HTTP_CODE);
        $cURL = null;


//        print_r([$resp, $errno, $error, $infos, $status]);

        if ($errno !== 0) {
            $error = "curl error($errno):{$error}";
            if ($errno === 7) $error = '_GatewayClosed_';

            return [
                'success' => false,
                'status' => $errno,
                'message' => $error,
                'result' => null,
            ];
        }

        $success = ($status >= 200 && $status < 300);
        $message = 'ok';
        if (!$success) $message = $resp['message'] ?? 'Error';

        return [
            'success' => $success,
            'status' => $status,
            'message' => $message,
            'result' => json_decode((string)$resp, true),
        ];
    }

}