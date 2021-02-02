<?php
namespace LightDBLayer\Utility;

use Doctrine\DBAL\Statement;
use LightDBLayer\Exception\NoDataForOperation;

trait SQLTransformation
{
    protected function arrayParam(array $array): string
    {
        if (!count($array)) {
            throw new NoDataForOperation();
        }
        return implode(',', array_fill(0, count($array), '?'));
    }
}
