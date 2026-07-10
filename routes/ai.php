<?php

declare(strict_types=1);

use Hwkdo\IntranetAppFuhrpark\Mcp\Servers\FuhrparkServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/apps/fuhrpark', FuhrparkServer::class)
    ->middleware(['auth:api']);
