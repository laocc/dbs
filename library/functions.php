<?php
declare(strict_types=1);

namespace esp\dbs\library;


function numbers(int $num): array
{
    $i = 1;
    $val = [];
    do {
        ($i & $num) && ($val[] = $i) && ($num -= $i);
    } while ($num > 0 && $i <<= 1);
    return $val;
}
