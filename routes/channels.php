<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('fuhrpark-channel', function ($user): bool {
    return $user->can('see-app-fuhrpark');
});
