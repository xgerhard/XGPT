<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a channel has its own AI provider key set but the request's `token` query param
 * is missing or doesn't match — see App\Http\Controllers\CommandController.
 */
class InvalidTokenException extends Exception {}
