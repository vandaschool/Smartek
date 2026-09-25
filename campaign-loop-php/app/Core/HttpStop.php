<?php
declare(strict_types=1);

namespace App\Core;

/** Thrown to stop request handling after a response was sent (redirect, json, download). */
final class HttpStop extends \RuntimeException
{
}
