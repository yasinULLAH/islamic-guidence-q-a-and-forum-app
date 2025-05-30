<?php
// Database configuration
define('DB_PATH', __DIR__ . '/database.sqlite');

// Application settings
define('APP_NAME', 'Islamic Guidance & Education');
define('DEFAULT_LANG', 'en'); // Default language
define('BASE_URL', 'http://localhost/new22'); // Adjust if your server runs on a different port/domain

// Security settings
define('CSRF_TOKEN_LENGTH', 32);
define('SESSION_LIFETIME', 3600); // 1 hour in seconds

// User roles (matching database IDs)
define('ROLE_PUBLIC', 1);
define('ROLE_REGISTERED_USER', 2);
define('ROLE_ULAMA_SCHOLAR', 3);
define('ROLE_ADMIN', 4);

// Other configurations
define('MEDIA_UPLOAD_DIR', __DIR__ . '/uploads'); // Directory for media uploads
// ... (e.g., pagination limits)
