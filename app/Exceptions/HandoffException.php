<?php

namespace App\Exceptions;

use RuntimeException;

/** Handoff-токен от сайта-хозяина не принят (подпись, срок, повтор, неизвестная app). */
class HandoffException extends RuntimeException
{
}
