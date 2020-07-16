<?php


namespace LightDBLayer\Exception;

use Throwable;

class NoDataForOperation extends \Exception
{
    public function __construct($code = 0, Throwable $previous = null)
    {
        parent::__construct('No data was passed', $code, $previous);
    }
}
