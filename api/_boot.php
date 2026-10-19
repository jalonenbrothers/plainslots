<?php
/**
 * api/_boot.php — shared bootstrap for API endpoints.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../src/Validator.php';
require_once __DIR__ . '/../src/PollService.php';

boot();