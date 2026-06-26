<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Models;

use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogbookEntry extends Model
{
    protected $table = 'intranet_app_fuhrpark_logbook_entries';

    protected $fillable = [
        'booking_id',
        'user_id',
        'project_id',
        'route',
        'km_commute',
        'km_project',
        'fueled',
        'cleaned',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'fueled' => 'boolean',
            'cleaned' => 'boolean',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(FuhrparkModels::user(), 'user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
