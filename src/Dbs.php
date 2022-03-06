<?php
declare(strict_types=1);

namespace esp\dbs;

use esp\core\Library;
use esp\dbs\kernel\DbsKernel;

/**
 * 非esp框架，可以自行实现此类，不需要扩展自esp\core\Library
 *
 * 1，在类中直接引用：use esp\dbs\kernel\DbsKernel
 * 2，在类中实现本类中的this->Pool()方法，返回\esp\Pool实例
 *      $this->_controller->_pool，建议是控制器中定义的一个变量
 *      控制器实例只会有一个，而Model不一定，所以，Pool要尽量保证在不同Model中是同一个实例对象
 *      new Pool($conf);中的$conf是包含mysql,redis等信息的数组
 */
abstract class Dbs extends Library
{
    use DbsKernel;

    public $_db_conf;

    public function Pool(array $conf = null): Pool
    {
        if (is_null($this->_controller->_pool)) {
            if (!empty($conf)) $this->_db_conf = $conf;
            if (is_null($this->_db_conf)) {
                $this->_db_conf = $this->_controller->_config->get('database');
            }

            $this->_controller->_pool = new Pool($this->_db_conf, $this->_controller);
        }
        return $this->_controller->_pool;
    }

}