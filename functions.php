<?php
require_once 'config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Establishes a PDO database connection.
 * @return PDO
 */
function get_db_connection() {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        die("Database connection failed. Please try again later.");
    }
}

/**
 * Gets the total count of guides.
 * @return int
 */
function get_total_guides_count() {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT COUNT(*) FROM guides");
    return $stmt->fetchColumn();
}

/**
 * Gets a paginated list of guides.
 * @param int $limit The maximum number of guides to return.
 * @param int $offset The offset from the beginning of the result set.
 * @return array
 */
function get_paginated_guides($limit, $offset) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT
            g.guide_id,
            g.title,
            g.description,
            g.difficulty,
            g.created_at,
            c.category_name,
            u.username AS author_username
        FROM
            guides g
        JOIN
            categories c ON g.category_id = c.category_id
        JOIN
            users u ON g.created_by = u.user_id
        ORDER BY
            g.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Gets the total count of community posts.
 * @return int
 */
function get_total_community_posts_count() {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT COUNT(*) FROM forum_topics");
    return $stmt->fetchColumn();
}

/**
 * Gets a paginated list of community posts.
 * @param int $limit The maximum number of community posts to return.
 * @param int $offset The offset from the beginning of the result set.
 * @return array
 */
function get_paginated_community_posts($limit, $offset) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM forum_topics ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Gets the total count of Q&A entries.
 * @return int
 */
function get_total_q_and_a_count() {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT COUNT(*) FROM q_and_a");
    return $stmt->fetchColumn();
}

/**
 * Gets a paginated list of Q&A entries.
 * @param int $limit The maximum number of Q&A entries to return.
 * @param int $offset The offset from the beginning of the result set.
 * @return array
 */
function get_paginated_q_and_a($limit, $offset) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM q_and_a ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Generates a CSRF token and stores it in the session.
 * @return string
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a CSRF token.
 * @param string $token The token to validate.
 * @return bool
 */
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Hashes a password using bcrypt.
 * @param string $password The plain text password.
 * @return string The hashed password.
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verifies a password against a hash.
 * @param string $password The plain text password.
 * @param string $hash The hashed password.
 * @return bool
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Checks if a user is logged in.
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Gets the current user's role ID.
 * @return int|null
 */
function get_user_role_id() {
    return $_SESSION['role_id'] ?? null;
}

/**
 * Checks if the current user has a specific role.
 * @param int $role_id The role ID to check against.
 * @return bool
 */
function has_role($role_id) {
    return is_logged_in() && get_user_role_id() >= $role_id;
}

/**
 * Redirects to a specified URL.
 * @param string $url The URL to redirect to.
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Sanitizes input string to prevent XSS.
 * @param string $data The input string.
 * @return string The sanitized string.
 */
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Sets a flash message in the session.
 * @param string $name The name of the flash message.
 * @param string $message The message content.
 * @param string $type The type of message (e.g., 'success', 'error', 'info').
 */
function set_flash_message($name, $message, $type = 'info') {
    $_SESSION['flash_messages'][$name] = ['message' => $message, 'type' => $type];
}

/**
 * Gets and clears a flash message from the session.
 * @param string $name The name of the flash message.
 * @return array|null An array with 'message' and 'type', or null if not found.
 */
function get_flash_message($name) {
    if (isset($_SESSION['flash_messages'][$name])) {
        $message = $_SESSION['flash_messages'][$name];
        unset($_SESSION['flash_messages'][$name]);
        return $message;
    }
    return null;
}

// Function to handle routing (basic example)
function handle_route($route) {
    switch ($route) {
        case '':
        case 'home':
            include 'views/home.php';
            break;
        case 'guides':
            include 'views/guides.php';
            break;
        case 'guide':
            include 'views/guide_detail.php';
            break;
        case 'login':
            include 'views/auth/login.php';
            break;
        case 'register':
            include 'views/auth/register.php';
            break;
        case 'dashboard':
            if (is_logged_in()) {
                include 'views/user/dashboard.php';
            } else {
                redirect(BASE_URL . '/login');
            }
            break;
        case 'admin':
            if (has_role(ROLE_ADMIN)) {
                include 'views/admin/dashboard.php';
            } else {
                redirect(BASE_URL . '/');
            }
            break;
        case 'create_guide':
            if (has_role(ROLE_ULAMA_SCHOLAR)) {
                include 'views/guides/create_guide.php';
            } else {
                redirect(BASE_URL . '/');
            }
            break;
        case 'edit_guide':
            // Check if user is logged in and has permission (Admin or Ulama who owns the guide)
            // The view itself will perform a more detailed check based on guide ownership
            if (is_logged_in()) {
                include 'views/guides/edit_guide.php';
            } else {
                redirect(BASE_URL . '/?route=login');
            }
            break;
        case 'edit_topic':
            // Check if user is logged in and has permission (Admin or topic author)
            // The view itself will perform a more detailed check based on topic ownership
            if (is_logged_in()) {
                include 'views/community/edit_topic.php';
            } else {
                redirect(BASE_URL . '/?route=login');
            }
            break;
        case 'q_and_a':
            include 'views/q_and_a.php';
            break;
        case 'qibla_finder':
            include 'views/qibla_finder.php';
            break;
        case 'prayer_times':
            include 'views/prayer_times.php';
            break;
        case 'forum_topic':
            include 'views/forum_topic.php';
            break;
        case 'community':
            include 'views/community.php';
            break;
        case 'logout':
            session_destroy();
            redirect(BASE_URL . '/');
            break;
        default:
            // 404 Not Found
            http_response_code(404);
            include 'views/404.php';
            break;
    }
}
