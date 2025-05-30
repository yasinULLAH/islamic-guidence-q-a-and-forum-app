<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        handle_register();
        break;
    case 'login':
        handle_login();
        break;
    case 'add_category':
        handle_add_category();
        break;
    case 'create_guide':
        handle_create_guide();
        break;
    case 'search_guides':
        handle_search_guides();
        break;
    case 'add_comment':
        handle_add_comment();
        break;
    case 'ask_question':
        handle_ask_question();
        break;
    case 'answer_question':
        handle_answer_question();
        break;
    case 'add_favorite':
        handle_add_favorite();
        break;
    case 'remove_favorite':
        handle_remove_favorite();
        break;
    case 'submit_rating':
        handle_submit_rating();
        break;
    case 'create_topic':
        handle_create_topic();
        break;
    case 'post_reply':
        handle_post_reply();
        break;
    case 'logout':
        // Logout is handled directly in index.php for simplicity of redirection
        // but could be handled here if it was an AJAX logout
        break;
    // Add other API actions here (e.g., etc.)
    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid API action.']);
        break;
}

function handle_register() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        return;
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        set_flash_message('register_error', 'Invalid CSRF token.', 'danger');
        redirect(BASE_URL . '/?route=register');
        return;
    }

    $username = sanitize_input($_POST['username'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        set_flash_message('register_error', 'All fields are required.', 'danger');
        redirect(BASE_URL . '/?route=register');
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash_message('register_error', 'Invalid email format.', 'danger');
        redirect(BASE_URL . '/?route=register');
        return;
    }

    if ($password !== $confirm_password) {
        set_flash_message('register_error', 'Passwords do not match.', 'danger');
        redirect(BASE_URL . '/?route=register');
        return;
    }

    if (strlen($password) < 8) {
        set_flash_message('register_error', 'Password must be at least 8 characters long.', 'danger');
        redirect(BASE_URL . '/?route=register');
        return;
    }

    $pdo = get_db_connection();

    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
    $stmt->execute([':username' => $username, ':email' => $email]);
    if ($stmt->fetchColumn() > 0) {
        set_flash_message('register_error', 'Username or email already exists.', 'danger');
        redirect(BASE_URL . '/?route=register');
        return;
    }

    $password_hash = hash_password($password);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role_id) VALUES (:username, :email, :password_hash, :role_id)");
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $password_hash,
            ':role_id' => ROLE_REGISTERED_USER // Default role for new registrations
        ]);

        $pdo->commit();
        set_flash_message('login_error', 'Registration successful! Please log in.', 'success');
        redirect(BASE_URL . '/?route=login');
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Registration error: " . $e->getMessage());
        set_flash_message('register_error', 'Registration failed. Please try again later.', 'danger');
        redirect(BASE_URL . '/?route=register');
    }
}

function handle_submit_rating() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        return;
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token.']);
        return;
    }

    if (!is_logged_in()) {
        echo json_encode(['status' => 'error', 'message' => 'You must be logged in to submit a rating.']);
        return;
    }

    $guide_id = (int)($_POST['guide_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $user_id = $_SESSION['user_id'];

    if ($guide_id <= 0 || $rating < 1 || $rating > 5) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid guide ID or rating value. Rating must be between 1 and 5.']);
        return;
    }

    $pdo = get_db_connection();

    try {
        // Check if user has already rated this guide
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ratings WHERE user_id = :user_id AND guide_id = :guide_id");
        $stmt->execute([':user_id' => $user_id, ':guide_id' => $guide_id]);
        if ($stmt->fetchColumn() > 0) {
            // Update existing rating
            $stmt = $pdo->prepare("UPDATE ratings SET rating = :rating, created_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND guide_id = :guide_id");
            $stmt->execute([
                ':rating' => $rating,
                ':user_id' => $user_id,
                ':guide_id' => $guide_id
            ]);
            echo json_encode(['status' => 'success', 'message' => 'Rating updated successfully.']);
        } else {
            // Insert new rating
            $stmt = $pdo->prepare("INSERT INTO ratings (user_id, guide_id, rating) VALUES (:user_id, :guide_id, :rating)");
            $stmt->execute([
                ':user_id' => $user_id,
                ':guide_id' => $guide_id,
                ':rating' => $rating
            ]);
            echo json_encode(['status' => 'success', 'message' => 'Rating submitted successfully.']);
        }
    } catch (PDOException $e) {
        error_log("Submit rating error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to submit rating.']);
    }
}

function handle_post_reply() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        return;
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        set_flash_message('forum_status', 'Invalid CSRF token.', 'danger');
        redirect(BASE_URL . '/?route=forum_topic&id=' . ($_POST['topic_id'] ?? ''));
        return;
    }

    if (!is_logged_in()) {
        set_flash_message('forum_status', 'You must be logged in to post a reply.', 'danger');
        redirect(BASE_URL . '/?route=login');
        return;
    }

    $topic_id = (int)($_POST['topic_id'] ?? 0);
    $parent_post_id = (int)($_POST['parent_post_id'] ?? null); // Can be null for direct replies to topic
    $content = sanitize_input($_POST['content'] ?? '');
    $user_id = $_SESSION['user_id'];

    if ($topic_id <= 0 || empty($content)) {
        set_flash_message('forum_status', 'Topic ID and content are required.', 'danger');
        // Ensure topic_id is passed even if invalid, for debugging purposes on redirect
        $redirect_id = ($topic_id > 0) ? $topic_id : '';
        redirect(BASE_URL . '/?route=forum_topic&id=' . $redirect_id);
        return;
    }

    $pdo = get_db_connection();

    try {
        $pdo->beginTransaction();

        // Insert the new post
        $stmt = $pdo->prepare("INSERT INTO forum_posts (topic_id, user_id, content, parent_post_id) VALUES (:topic_id, :user_id, :content, :parent_post_id)");
        $stmt->execute([
            ':topic_id' => $topic_id,
            ':user_id' => $user_id,
            ':content' => $content,
            ':parent_post_id' => ($parent_post_id === 0) ? null : $parent_post_id
        ]);

        // Update last_post_at for the topic
        $stmt_update_topic = $pdo->prepare("UPDATE forum_topics SET last_post_at = CURRENT_TIMESTAMP WHERE topic_id = :topic_id");
        $stmt_update_topic->execute([':topic_id' => $topic_id]);

        $pdo->commit();
        set_flash_message('forum_status', 'Reply posted successfully!', 'success');
        redirect(BASE_URL . '/?route=forum_topic&id=' . $topic_id . '#post-' . $pdo->lastInsertId()); // Redirect to the new post
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Post reply error: " . $e->getMessage());
        set_flash_message('forum_status', 'Failed to post reply. Please try again later.', 'danger');
        redirect(BASE_URL . '/?route=forum_topic&id=' . $topic_id);
    }
}

function handle_create_topic() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        return;
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        set_flash_message('forum_status', 'Invalid CSRF token.', 'danger');
        redirect(BASE_URL . '/?route=community');
        return;
    }

    if (!is_logged_in()) {
        set_flash_message('forum_status', 'You must be logged in to create a topic.', 'danger');
        redirect(BASE_URL . '/?route=login');
        return;
    }

    $topic_title = sanitize_input($_POST['topic_title'] ?? '');
    $first_post_content = sanitize_input($_POST['first_post_content'] ?? '');
    $user_id = $_SESSION['user_id'];

    if (empty($topic_title) || empty($first_post_content)) {
        set_flash_message('forum_status', 'Topic title and first post content are required.', 'danger');
        redirect(BASE_URL . '/?route=community');
        return;
    }

    $pdo = get_db_connection();

    try {
        $pdo->beginTransaction();

        // Insert into forum_topics
        $stmt_topic = $pdo->prepare("INSERT INTO forum_topics (user_id, title) VALUES (:user_id, :title)");
        $stmt_topic->execute([
            ':user_id' => $user_id,
            ':title' => $topic_title
        ]);
        $topic_id = $pdo->lastInsertId();

        // Insert first post into forum_posts
        $stmt_post = $pdo->prepare("INSERT INTO forum_posts (topic_id, user_id, content) VALUES (:topic_id, :user_id, :content)");
        $stmt_post->execute([
            ':topic_id' => $topic_id,
            ':user_id' => $user_id,
            ':content' => $first_post_content
        ]);

        $pdo->commit();
        set_flash_message('forum_status', 'Topic created successfully!', 'success');
        redirect(BASE_URL . '/?route=forum_topic&id=' . $topic_id);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Create topic error: " . $e->getMessage());
        set_flash_message('forum_status', 'Failed to create topic. Please try again later.', 'danger');
        redirect(BASE_URL . '/?route=community');
    }
}

function handle_add_favorite() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        return;
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token.']);
        return;
    }

    if (!is_logged_in()) {
        echo json_encode(['status' => 'error', 'message' => 'You must be logged in to add favorites.']);
        return;
    }

    $guide_id = (int)($_POST['guide_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    if ($guide_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid guide ID.']);
        return;
    }

    $pdo = get_db_connection();

    try {
        // Check if already favorited
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = :user_id AND guide_id = :guide_id");
        $stmt->execute([':user_id' => $user_id, ':guide_id' => $guide_id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'info', 'message' => 'Already favorited.']);
            return;
        }

        $stmt = $pdo->prepare("INSERT INTO favorites (user_id, guide_id) VALUES (:user_id, :guide_id)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':guide_id' => $guide_id
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Guide added to favorites.']);
    } catch (PDOException $e) {
        error_log("Add favorite error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to add to favorites.']);
    }
}

function handle_remove_favorite() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        return;
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token.']);
        return;
    }

    if (!is_logged_in()) {
        echo json_encode(['status' => 'error', 'message' => 'You must be logged in to remove favorites.']);
        return;
    }

    $guide_id = (int)($_POST['guide_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    if ($guide_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid guide ID.']);
        return;
    }

    $pdo = get_db_connection();

    try {
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = :user_id AND guide_id = :guide_id");
        $stmt->execute([
            ':user_id' => $user_id,
            ':guide_id' => $guide_id
        ]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Guide removed from favorites.']);
        } else {
            echo json_encode(['status' => 'info', 'message' => 'Guide was not in favorites.']);
        }
    } catch (PDOException $e) {
        error_log("Remove favorite error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to remove from favorites.']);
    }
}

function handle_answer_question() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        return;
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        set_flash_message('qa_status', 'Invalid CSRF token.', 'danger');
        redirect(BASE_URL . '/?route=q_and_a');
        return;
    }

    if (!is_logged_in() || !has_role(ROLE_ULAMA_SCHOLAR)) {
        set_flash_message('qa_status', 'Permission denied. You must be a Scholar to answer questions.', 'danger');
        redirect(BASE_URL . '/');
        return;
    }

    $qa_id = (int)($_POST['qa_id'] ?? 0);
    $answer_text = sanitize_input($_POST['answer_text'] ?? '');

    if (empty($answer_text) || $qa_id <= 0) {
        set_flash_message('qa_status', 'Answer text and valid question ID are required.', 'danger');
        redirect(BASE_URL . '/?route=q_and_a');
        return;
    }

    $pdo = get_db_connection();

    try {
        $stmt = $pdo->prepare("UPDATE q_and_a SET answer_text = :answer_text, answered_by = :answered_by, answered_at = CURRENT_TIMESTAMP WHERE qa_id = :qa_id");
        $stmt->execute([
            ':answer_text' => $answer_text,
            ':answered_by' => $_SESSION['user_id'],
            ':qa_id' => $qa_id
        ]);

        set_flash_message('qa_status', 'Answer submitted successfully!', 'success');
        redirect(BASE_URL . '/?route=q_and_a');
    } catch (PDOException $e) {
        error_log("Answer question error: " . $e->getMessage());
        set_flash_message('qa_status', 'Failed to submit answer. Please try again later.', 'danger');
        redirect(BASE_URL . '/?route=q_and_a');
    }
}

function handle_ask_question() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        return;
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        set_flash_message('qa_status', 'Invalid CSRF token.', 'danger');
        redirect(BASE_URL . '/?route=q_and_a');
        return;
    }

    if (!is_logged_in()) {
        set_flash_message('qa_status', 'You must be logged in to ask a question.', 'danger');
        redirect(BASE_URL . '/?route=login');
        return;
    }

    $question_text = sanitize_input($_POST['question_text'] ?? '');

    if (empty($question_text)) {
        set_flash_message('qa_status', 'Question text cannot be empty.', 'danger');
        redirect(BASE_URL . '/?route=q_and_a');
        return;
    }

    $pdo = get_db_connection();

    try {
        $stmt = $pdo->prepare("INSERT INTO q_and_a (user_id, question_text) VALUES (:user_id, :question_text)");
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':question_text' => $question_text
        ]);

        set_flash_message('qa_status', 'Question submitted successfully! A scholar will review it soon.', 'success');
        redirect(BASE_URL . '/?route=q_and_a');
    } catch (PDOException $e) {
        error_log("Ask question error: " . $e->getMessage());
        set_flash_message('qa_status', 'Failed to submit question. Please try again later.', 'danger');
        redirect(BASE_URL . '/?route=q_and_a');
    }
}

function handle_add_comment() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        return;
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        set_flash_message('comment_status', 'Invalid CSRF token.', 'danger');
        redirect(BASE_URL . '/?route=guide&id=' . ($_POST['guide_id'] ?? ''));
        return;
    }

    if (!is_logged_in()) {
        set_flash_message('comment_status', 'You must be logged in to comment.', 'danger');
        redirect(BASE_URL . '/?route=login');
        return;
    }

    $guide_id = (int)($_POST['guide_id'] ?? 0);
    $comment_text = sanitize_input($_POST['comment_text'] ?? '');
    $parent_comment_id = (int)($_POST['parent_comment_id'] ?? null); // Can be null for top-level comments

    if (empty($comment_text) || $guide_id <= 0) {
        set_flash_message('comment_status', 'Comment text and valid guide ID are required.', 'danger');
        redirect(BASE_URL . '/?route=guide&id=' . $guide_id);
        return;
    }

    $pdo = get_db_connection();

    try {
        $stmt = $pdo->prepare("INSERT INTO comments (guide_id, user_id, parent_comment_id, comment_text) VALUES (:guide_id, :user_id, :parent_comment_id, :comment_text)");
        $stmt->execute([
            ':guide_id' => $guide_id,
            ':user_id' => $_SESSION['user_id'],
            ':parent_comment_id' => ($parent_comment_id === 0) ? null : $parent_comment_id,
            ':comment_text' => $comment_text
        ]);

        set_flash_message('comment_status', 'Comment added successfully!', 'success');
        redirect(BASE_URL . '/?route=guide&id=' . $guide_id . '#comments-list'); // Redirect to comments section
    } catch (PDOException $e) {
        error_log("Add comment error: " . $e->getMessage());
        set_flash_message('comment_status', 'Failed to add comment. Please try again later.', 'danger');
        redirect(BASE_URL . '/?route=guide&id=' . $guide_id);
    }
}

function handle_search_guides() {
    $pdo = get_db_connection();
    $searchTerm = $_GET['search'] ?? '';
    $categoryId = (int)($_GET['category_id'] ?? 0);

    $sql = "
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
        WHERE 1=1
    ";
    $params = [];

    if (!empty($searchTerm)) {
        $sql .= " AND (g.title LIKE :searchTerm OR g.description LIKE :searchTerm)";
        $params[':searchTerm'] = '%' . $searchTerm . '%';
    }

    if ($categoryId > 0) {
        $sql .= " AND g.category_id = :categoryId";
        $params[':categoryId'] = $categoryId;
    }

    $sql .= " ORDER BY g.created_at DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $guides = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'guides' => $guides]);
    } catch (PDOException $e) {
        error_log("Search guides error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to search guides.']);
    }
}

function handle_create_guide() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        return;
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        set_flash_message('guide_status', 'Invalid CSRF token.', 'danger');
        redirect(BASE_URL . '/?route=create_guide');
        return;
    }

    if (!has_role(ROLE_ULAMA_SCHOLAR)) {
        set_flash_message('guide_status', 'Permission denied. You must be a Scholar to create guides.', 'danger');
        redirect(BASE_URL . '/');
        return;
    }

    $guide_title = sanitize_input($_POST['guide_title'] ?? '');
    $guide_description = sanitize_input($_POST['guide_description'] ?? '');
    $guide_category_id = (int)($_POST['guide_category'] ?? 0);
    $guide_difficulty = sanitize_input($_POST['guide_difficulty'] ?? 'Beginner');
    $steps = $_POST['steps'] ?? [];
    $files = $_FILES['steps'] ?? [];

    if (empty($guide_title) || empty($guide_category_id) || empty($steps)) {
        set_flash_message('guide_status', 'Guide title, category, and at least one step are required.', 'danger');
        redirect(BASE_URL . '/?route=create_guide');
        return;
    }

    $pdo = get_db_connection();

    try {
        $pdo->beginTransaction();

        // 1. Insert into guides table
        $stmt = $pdo->prepare("INSERT INTO guides (title, description, category_id, difficulty, created_by) VALUES (:title, :description, :category_id, :difficulty, :created_by)");
        $stmt->execute([
            ':title' => $guide_title,
            ':description' => $guide_description,
            ':category_id' => $guide_category_id,
            ':difficulty' => $guide_difficulty,
            ':created_by' => $_SESSION['user_id'] // Assuming user_id is in session
        ]);
        $guide_id = $pdo->lastInsertId();

        // 2. Process and insert guide steps
        foreach ($steps as $step_number => $step_data) {
            $step_title = sanitize_input($step_data['title'] ?? '');
            $step_content = sanitize_input($step_data['content'] ?? '');
            $step_reference = sanitize_input($step_data['reference'] ?? '');

            $image_url = null;
            $audio_url = null;

            // Handle image upload
            if (isset($files['name'][$step_number]['image']) && $files['error'][$step_number]['image'] === UPLOAD_ERR_OK) {
                $image_tmp_name = $files['tmp_name'][$step_number]['image'];
                $image_name = basename($files['name'][$step_number]['image']);
                $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
                $allowed_image_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (in_array($image_ext, $allowed_image_types) && $files['size'][$step_number]['image'] <= 5 * 1024 * 1024) { // Max 5MB
                    $unique_image_name = uniqid('img_', true) . '.' . $image_ext;
                    $image_destination = MEDIA_UPLOAD_DIR . '/' . $unique_image_name;
                    if (move_uploaded_file($image_tmp_name, $image_destination)) {
                        $image_url = 'uploads/' . $unique_image_name; // Relative path for web access
                    } else {
                        throw new Exception("Failed to move uploaded image for step $step_number.");
                    }
                } else {
                    throw new Exception("Invalid image file for step $step_number. Allowed types: jpg, jpeg, png, gif, webp. Max size: 5MB.");
                }
            }

            // Handle audio upload
            if (isset($files['name'][$step_number]['audio']) && $files['error'][$step_number]['audio'] === UPLOAD_ERR_OK) {
                $audio_tmp_name = $files['tmp_name'][$step_number]['audio'];
                $audio_name = basename($files['name'][$step_number]['audio']);
                $audio_ext = strtolower(pathinfo($audio_name, PATHINFO_EXTENSION));
                $allowed_audio_types = ['mp3', 'wav', 'ogg'];

                if (in_array($audio_ext, $allowed_audio_types) && $files['size'][$step_number]['audio'] <= 10 * 1024 * 1024) { // Max 10MB
                    $unique_audio_name = uniqid('audio_', true) . '.' . $audio_ext;
                    $audio_destination = MEDIA_UPLOAD_DIR . '/' . $unique_audio_name;
                    if (move_uploaded_file($audio_tmp_name, $audio_destination)) {
                        $audio_url = 'uploads/' . $unique_audio_name; // Relative path for web access
                    } else {
                        throw new Exception("Failed to move uploaded audio for step $step_number.");
                    }
                } else {
                    throw new Exception("Invalid audio file for step $step_number. Allowed types: mp3, wav, ogg. Max size: 10MB.");
                }
            }

            $stmt = $pdo->prepare("INSERT INTO guide_steps (guide_id, step_number, title, content, image_url, audio_url) VALUES (:guide_id, :step_number, :title, :content, :image_url, :audio_url)");
            $stmt->execute([
                ':guide_id' => $guide_id,
                ':step_number' => $step_number,
                ':title' => $step_title,
                ':content' => $step_content,
                ':image_url' => $image_url,
                ':audio_url' => $audio_url
            ]);
            $step_id = $pdo->lastInsertId();

            // Insert reference if provided
            if (!empty($step_reference)) {
                $stmt = $pdo->prepare("INSERT INTO content_references (guide_id, step_id, source, reference_text) VALUES (:guide_id, :step_id, :source, :reference_text)");
                $stmt->execute([
                    ':guide_id' => $guide_id,
                    ':step_id' => $step_id,
                    ':source' => 'User Input', // Or parse from reference_text if a specific format is enforced
                    ':reference_text' => $step_reference
                ]);
            }
        }

        $pdo->commit();
        set_flash_message('guide_status', 'Guide created successfully!', 'success');
        redirect(BASE_URL . '/?route=guide&id=' . $guide_id); // Redirect to the new guide's detail page
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Create guide error: " . $e->getMessage());
        set_flash_message('guide_status', 'Failed to create guide: ' . $e->getMessage(), 'danger');
        redirect(BASE_URL . '/?route=create_guide');
    }
}

function handle_add_category() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        return;
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        set_flash_message('category_status', 'Invalid CSRF token.', 'danger');
        redirect(BASE_URL . '/?route=admin');
        return;
    }

    if (!has_role(ROLE_ADMIN)) {
        set_flash_message('category_status', 'Permission denied. You must be an Admin to add categories.', 'danger');
        redirect(BASE_URL . '/?route=admin');
        return;
    }

    $category_name = sanitize_input($_POST['category_name'] ?? '');
    $category_description = sanitize_input($_POST['category_description'] ?? '');

    if (empty($category_name)) {
        set_flash_message('category_status', 'Category name cannot be empty.', 'danger');
        redirect(BASE_URL . '/?route=admin');
        return;
    }

    $pdo = get_db_connection();

    // Check if category name already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE category_name = :category_name");
    $stmt->execute([':category_name' => $category_name]);
    if ($stmt->fetchColumn() > 0) {
        set_flash_message('category_status', 'Category with this name already exists.', 'danger');
        redirect(BASE_URL . '/?route=admin');
        return;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO categories (category_name, description) VALUES (:category_name, :description)");
        $stmt->execute([
            ':category_name' => $category_name,
            ':description' => $category_description
        ]);

        $pdo->commit();
        set_flash_message('category_status', 'Category added successfully!', 'success');
        redirect(BASE_URL . '/?route=admin');
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Add category error: " . $e->getMessage());
        set_flash_message('category_status', 'Failed to add category. Please try again later.', 'danger');
        redirect(BASE_URL . '/?route=admin');
    }
}

function handle_login() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        return;
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        set_flash_message('login_error', 'Invalid CSRF token.', 'danger');
        redirect(BASE_URL . '/?route=login');
        return;
    }

    $username_or_email = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username_or_email) || empty($password)) {
        set_flash_message('login_error', 'Username/Email and password are required.', 'danger');
        redirect(BASE_URL . '/?route=login');
        return;
    }

    $pdo = get_db_connection();

    $stmt = $pdo->prepare("SELECT user_id, username, password_hash, role_id FROM users WHERE username = :username OR email = :email");
    $stmt->execute([':username' => $username_or_email, ':email' => $username_or_email]);
    $user = $stmt->fetch();

    if ($user && verify_password($password, $user['password_hash'])) {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['last_activity'] = time();

        // Update session table (optional, for more robust session management)
        $session_id = session_id();
        $expires_at = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        $csrf_token = generate_csrf_token(); // Regenerate CSRF token for new session

        $stmt = $pdo->prepare("INSERT OR REPLACE INTO sessions (session_id, user_id, csrf_token, expires_at) VALUES (:session_id, :user_id, :csrf_token, :expires_at)");
        $stmt->execute([
            ':session_id' => $session_id,
            ':user_id' => $user['user_id'],
            ':csrf_token' => $csrf_token,
            ':expires_at' => $expires_at
        ]);

        set_flash_message('login_success', 'Login successful!', 'success');
        redirect(BASE_URL . '/?route=dashboard');
    } else {
        set_flash_message('login_error', 'Invalid username/email or password.', 'danger');
        redirect(BASE_URL . '/?route=login');
    }
}
