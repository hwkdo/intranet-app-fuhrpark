<?php

namespace Hwkdo\IntranetAppFuhrpark\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hwkdo\IntranetAppFuhrpark\IntranetAppFuhrpark
 */
class IntranetAppFuhrpark extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hwkdo\IntranetAppFuhrpark\IntranetAppFuhrpark::class;
    }
}
