<?php
/**
 * lib/config.example.php — copy to lib/config.php and fill in.
 * Shape per mvp-stack-standard v1 (binding). App constants BELOW the line only.
 */
declare(strict_types=1);

return [
    // --- database (required) ---
    'db' => [
        'host'    => 'localhost',   // Hostinger preset; rarely changes
        'port'    => 3306,
        'name'    => 'u123456789_plainslots',
        'user'    => 'u123456789_plainslots',
        'pass'    => 'CHANGE_ME',
    ],

    // --- app (optional, one line) ---
    'app_url' => 'https://plainslots.earth-turtle.com', // used for share URLs; omit = derive from request

    // --- debug (always present) ---
    'debug' => false, // true = display errors, no .htaccess-internal blocking in logs
];

// --- app-specific (below the line, only if truly needed) ---
// none