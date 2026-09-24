<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when installing the command under a name the channel already uses for a different
 * command — see App\Chat\Providers\NightbotBotProvider::install().
 */
class CommandNameTakenException extends Exception {}
