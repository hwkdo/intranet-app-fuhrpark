<?php

namespace Hwkdo\IntranetAppFuhrpark\Models;

use Hwkdo\IntranetAppFuhrpark\Data\AppSettings;
use Illuminate\Database\Eloquent\Model;

class IntranetAppFuhrparkSettings extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => AppSettings::class.':default',
        ];
    }

    public static function current(): ?IntranetAppFuhrparkSettings
    {
        return self::orderBy('version', 'desc')->first();
    }
}
