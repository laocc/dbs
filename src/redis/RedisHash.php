<?php
//declare(strict_types=1);

namespace esp\dbs\redis;

use \Redis;

class RedisHash
{
    private Redis $redis;
    private string $table;

    public function __construct(Redis $redis, string $key)
    {
        $this->redis = &$redis;
        $this->table = $key;
    }

    /**
     * @return Redis
     */
    public function redis(): Redis
    {
        return $this->redis;
    }


    /**
     * @param string $hashKey
     * @param $value
     * @return int
     */
    public function set(string $hashKey, $value): int
    {
        return $this->redis->hSet($this->table, $hashKey, $value);
    }

    /**
     * @param string $hashKey
     * @return Redis|string|null
     */
    public function get(string $hashKey)
    {
        $val = $this->redis->hGet($this->table, $hashKey);
        if (empty($val)) return null;
        return ($val);
    }


    /**
     * 当$hashKey不存在时，写入，若存在则忽略
     * @param string $hashKey
     * @param $value
     * @return bool
     */
    public function insert(string $hashKey, $value)
    {
        return $this->redis->hSetNx($this->table, $hashKey, $value);
    }


    /**
     * @return int
     */
    public function len()
    {
        return $this->redis->hLen($this->table);
    }

    /**
     * @param string ...$hashKey
     * @return bool|int
     */
    public function del(string ...$hashKey)
    {
        return $this->redis->hDel($this->table, ...$hashKey);
    }


    /**
     * @return array
     */
    public function keys()
    {
        return $this->redis->hKeys($this->table);
    }


    /**
     * @return array
     */
    public function all()
    {
        return $this->redis->hGetAll($this->table);
    }


    /**
     * @param string $hashKey
     * @return bool
     */
    public function exists(string $hashKey): bool
    {
        return $this->redis->hExists($this->table, $hashKey);
    }

    /**
     * @param string $hashKey
     * @param int $value
     * @return int
     */
    public function add(string $hashKey, int $value = 1): int
    {
        return $this->redis->hIncrBy($this->table, $hashKey, $value);
    }

    public function inc(string $hashKey, int $value = 1)
    {
        return $this->redis->hIncrBy($this->table, $hashKey, $value);
    }

    /**
     * @param array $hashKeys
     * @return bool
     */
    public function mSet(array $hashKeys)
    {
        return $this->redis->hMset($this->table, $hashKeys);
    }

    /**
     * @param array $hashKeys
     * @return array
     */
    public function mGet(array $hashKeys)
    {
        return $this->redis->hMGet($this->table, $hashKeys);
    }

    /**
     ***********************以下都是原生方法*******************
     */

    /**
     * @param string $hashKey
     * @param $value
     * @return int
     */
    public function hSet(string $hashKey, $value): int
    {
        return $this->redis->hSet($this->table, $hashKey, ($value));
    }

    public function hGet(string $hashKey)
    {
        $val = $this->redis->hGet($this->table, $hashKey);
        if (empty($val)) return null;
        return $val;
    }


    /**
     * Adds a value to the hash stored at key only if this field isn't already in the hash.
     *
     * @param string $hashKey
     * @param string $value
     * @return  bool    TRUE if the field was set, FALSE if it was already present.
     * @link    http://redis.io/commands/hsetnx
     * @example
     * <pre>
     * $redis->delete('h')
     * $redis->hSetNx('h', 'key1', 'hello'); // TRUE, 'key1' => 'hello' in the hash at "h"
     * $redis->hSetNx('h', 'key1', 'world'); // FALSE, 'key1' => 'hello' in the hash at "h". No change since the field
     * wasn't replaced.
     * </pre>
     */
    public function hSetNx(string $hashKey, $value)
    {
        return $this->redis->hSetNx($this->table, $hashKey, $value);
    }

    /**
     * Returns the length of a hash, in number of items
     *
     * @return  int     the number of items in a hash, FALSE if the key doesn't exist or isn't a hash.
     * @link    http://redis.io/commands/hlen
     * @example
     * <pre>
     * $redis->delete('h')
     * $redis->hSet('h', 'key1', 'hello');
     * $redis->hSet('h', 'key2', 'plop');
     * $redis->hLen('h'); // returns 2
     * </pre>
     */
    public function hLen()
    {
        return $this->redis->hLen($this->table);
    }

    /**
     * Removes a values from the hash stored at key.
     * If the hash table doesn't exist, or the key doesn't exist, FALSE is returned.
     *
     * @param string ...$hashKey
     * @return  int     Number of deleted fields
     * @link    http://redis.io/commands/hdel
     * @example
     * <pre>
     * $redis->hMSet('h',
     *               array(
     *                    'f1' => 'v1',
     *                    'f2' => 'v2',
     *                    'f3' => 'v3',
     *                    'f4' => 'v4',
     *               ));
     *
     * var_dump( $redis->hDel('h', 'f1') );        // int(1)
     * var_dump( $redis->hDel('h', 'f2', 'f3') );  // int(2)
     * s
     * var_dump( $redis->hGetAll('h') );
     * //// Output:
     * //  array(1) {
     * //    ["f4"]=> string(2) "v4"
     * //  }
     * </pre>
     */
    public function hDel(string ...$hashKey)
    {
        return $this->redis->hDel($this->table, ...$hashKey);
    }

    /**
     * Returns the keys in a hash, as an array of strings.
     *
     * @return  array   An array of elements, the keys of the hash. This works like PHP's array_keys().
     * @link    http://redis.io/commands/hkeys
     * @example
     * <pre>
     * $redis->delete('h');
     * $redis->hSet('h', 'a', 'x');
     * $redis->hSet('h', 'b', 'y');
     * $redis->hSet('h', 'c', 'z');
     * $redis->hSet('h', 'd', 't');
     * var_dump($redis->hKeys('h'));
     *
     * // Output:
     * // array(4) {
     * // [0]=>
     * // string(1) "a"
     * // [1]=>
     * // string(1) "b"
     * // [2]=>
     * // string(1) "c"
     * // [3]=>
     * // string(1) "d"
     * // }
     * // The order is random and corresponds to redis' own internal representation of the set structure.
     * </pre>
     */
    /**
     * @return array
     */
    public function hKeys()
    {
        return $this->redis->hKeys($this->table);
    }

    /**
     * Returns the values in a hash, as an array of strings.
     *
     * @return  array   An array of elements, the values of the hash. This works like PHP's array_values().
     * @link    http://redis.io/commands/hvals
     * @example
     * <pre>
     * $redis->delete('h');
     * $redis->hSet('h', 'a', 'x');
     * $redis->hSet('h', 'b', 'y');
     * $redis->hSet('h', 'c', 'z');
     * $redis->hSet('h', 'd', 't');
     * var_dump($redis->hVals('h'));
     *
     * // Output
     * // array(4) {
     * //   [0]=>
     * //   string(1) "x"
     * //   [1]=>
     * //   string(1) "y"
     * //   [2]=>
     * //   string(1) "z"
     * //   [3]=>
     * //   string(1) "t"
     * // }
     * // The order is random and corresponds to redis' own internal representation of the set structure.
     * </pre>
     */
    public function hVals()
    {
        return $this->redis->hVals($this->table);
    }

    /**
     * Returns the whole hash, as an array of strings indexed by strings.
     *
     * @return  array   An array of elements, the contents of the hash.
     * @link    http://redis.io/commands/hgetall
     * @example
     */
    public function hGetAll()
    {
        return $this->redis->hGetAll($this->table);
    }

    /**
     * Verify if the specified member exists in a key.
     *
     * @param string $hashKey
     * @return  bool    If the member exists in the hash table, return TRUE, otherwise return FALSE.
     * @link    http://redis.io/commands/hexists
     * @example
     */
    public function hExists(string $hashKey)
    {
        return $this->redis->hExists($this->table, $hashKey);
    }

    /**
     * Increments the value of a member from a hash by a given amount.
     *
     * @param string $hashKey
     * @param int $value (integer) value that will be added to the member's value
     * @return  int     the new value
     * @link    http://redis.io/commands/hincrby
     * @example
     */
    public function hIncrBy(string $hashKey, int $value)
    {
        return $this->redis->hIncrBy($this->table, $hashKey, $value);
    }

    /**
     * @param int $field
     * @param float $increment
     * @return float
     * @link    http://redis.io/commands/hincrbyfloat
     */
    public function hIncrByFloat(int $field, float $increment)
    {
        return $this->redis->hIncrByFloat($this->table, $field, $increment);
    }

    /**
     * Fills in a whole hash. Non-string values are converted to string, using the standard (string) cast.
     * NULL values are stored as empty strings
     *
     * @param array $hashKeys key → value array
     * @return  bool
     * @link    http://redis.io/commands/hmset
     * @example
     * <pre>
     * $redis->delete('user:1');
     * $redis->hMset('user:1', array('name' => 'Joe', 'salary' => 2000));
     * $redis->hIncrBy('user:1', 'salary', 100); // Joe earns 100 more now.
     * </pre>
     */
    public function hMset(array $hashKeys)
    {
        return $this->redis->hMset($this->table, $hashKeys);
    }

    /**
     * Retirieve the values associated to the specified fields in the hash.
     *
     * @param array $hashKeys
     * @return  array   Array An array of elements, the values of the specified fields in the hash,
     * with the hash keys as array keys.
     * @link    http://redis.io/commands/hmget
     * @example
     * <pre>
     * $redis->delete('h');
     * $redis->hSet('h', 'field1', 'value1');
     * $redis->hSet('h', 'field2', 'value2');
     * $redis->hmGet('h', array('field1', 'field2')); // returns array('field1' => 'value1', 'field2' => 'value2')
     * </pre>
     */
    public function hMGet(array $hashKeys)
    {
        return $this->redis->hMGet($this->table, $hashKeys);
    }

}