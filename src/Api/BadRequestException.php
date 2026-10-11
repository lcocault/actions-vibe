<?php

declare(strict_types=1);

namespace App\Api;

use RuntimeException;

/** Requête HTTP mal formée (corps illisible). */
final class BadRequestException extends RuntimeException
{
}
