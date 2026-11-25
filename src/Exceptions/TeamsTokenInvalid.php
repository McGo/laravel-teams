<?php

namespace MirkoHaaser\Teams\Exceptions;

use RuntimeException;

/** A token from Teams or Entra did not pass validation. The message is for logs only. */
class TeamsTokenInvalid extends RuntimeException {}
