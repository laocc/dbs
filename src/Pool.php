<?php
declare(strict_types=1);

namespace esp\dbs;

use esp\core\Configure;

final class Pool
{
    /**
     * @var $config Configure
     */
    public $config;

    public function __construct(Configure $configure)
    {
        $this->config = &$configure;
    }

}