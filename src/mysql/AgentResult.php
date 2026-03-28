<?php

namespace esp\dbs\mysql;

use function esp\helper\numbers;

class AgentResult
{
    private bool $success;
    private string $message;
    private array $result = [];
    private int $count = 0;
    private string $sql = '';
    private array $sum = [];

    public function __construct(array $data, array $count, string $sql)
    {
        $this->message = $data['message'];
        $this->success = boolval($data['success']);
        if (!$this->success) return;
        if (empty($data['result']['rows'])) $data['result']['rows'] = [];

        $this->result = $data['result']['rows'];
        $this->sql = $sql;
        $this->count = count($data['result']['rows']);
        $this->sum = [];
        if (isset($data['result']['attach'])) {
            if (isset($data['result']['attach'][0])) {
                $data['result']['attach'] = $data['result']['attach'][0];
            }
            $this->sum = $data['result']['attach'];
        }
    }


    public function sql(): string
    {
        return $this->sql;
    }

    /**
     * 从结果中返回一行
     * @param null $col
     * @param array $decode
     * @return mixed|null
     */
    public function row($col = null, array $decode = [])
    {
        $data = $this->result[0];
        if (empty($data)) return null;
        if (empty($decode)) return $data;
        return $this->decode($data, $decode);
    }

    public function sum(): array
    {
        return $this->sum;
    }

    /**
     * 以数组形式返回结果集中的所有行
     *
     * @param int $row
     * @param int|null $col 返回第x列
     * @param array $decode
     * @return array|mixed
     */
    public function rows(int $row = 0, int $col = null, array $decode = [])
    {
        $data = $this->result[0];
        if (empty($data)) return [];
        if (empty($decode)) return $data;

        return array_map(function ($rs) use ($decode) {
            return $this->decode($rs, $decode);
        }, $data);
    }

    /**
     * 当前SQL去除limit的记录总数
     * 但是在构造查询时须加count()方法，否则获取到的只是当前批次的记录数。
     * @return int
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * 返回当前查询结果的字段列数
     * @return int
     */
    public function column(): int
    {
        return count($this->result[0]);
    }

    /**
     * 本次执行是否有错误
     * @return null|string
     */
    public function error(): ?string
    {
        if ($this->success) return null;
        return $this->message;
    }

    private function decode(array $data, array $decode)
    {
        if (isset($decode['array'])) $decode['json'] = $decode['array'];
        if (isset($decode['json'])) {
            foreach ($decode['json'] as $k) {
                if (is_int($data[$k[1]])) {
                    $data[$k[0]] = numbers($data[$k[1]]);
                } else {
                    $data[$k[0]] = json_decode(($data[$k[1]] ?? ''), true) ?: [];
                }
            }
        }

        if (isset($decode['time'])) {
            foreach ($decode['time'] as $k) {
                $tm = ($data[$k[1]] ?? 0);
                if ($tm) $data[$k[0]] = date('Y-m-d H:i:s', $tm);
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

        if (isset($decode['polygon'])) {
            foreach ($decode['point'] as $k) {
                preg_match('/polygon\((-?[\d\.]+)\s(-?[\d\.]+)\)/i', $data[$k[1]], $locMch);
                $data[$k[0]] = [
                    'longitude' => floatval($locMch[1] ?? 0),
                    'latitude' => floatval($locMch[2] ?? 0)
                ];
            }
        }

        return $data;
    }

}