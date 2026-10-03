<?php

use App\Mcp\Servers\BillingServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', BillingServer::class)->middleware(['auth:sanctum', 'bind.account']);
