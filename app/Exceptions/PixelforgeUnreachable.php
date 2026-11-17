<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * No response: a timeout or connection failure. The request may or may not have
 * reached Pixelforge, which is why every create carries an idempotency key.
 */
final class PixelforgeUnreachable extends RuntimeException {}
