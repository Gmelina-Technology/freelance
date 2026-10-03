<?php

namespace App\Mcp\Exceptions;

use RuntimeException;

/**
 * Thrown inside an MCP tool to abort with a user-facing error message.
 */
class ToolFailedException extends RuntimeException {}
