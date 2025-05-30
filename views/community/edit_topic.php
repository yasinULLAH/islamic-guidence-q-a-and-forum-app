<?php
if (!is_logged_in()) {
    redirect(BASE_URL . '/?route=login');
}

$topic_id = (int)($_GET['id'] ?? 0);
$pdo = get_db_connection();
$topic = null;
$first_post_content = '';

if ($topic_id > 0) {
    // Fetch topic details
    $stmt = $pdo->prepare("
        SELECT
            ft.topic_id,
            ft.title,
            ft.user_id AS author_user_id
        FROM
            forum_topics ft
        WHERE
            ft.topic_id = :topic_id
    ");
    $stmt->execute([':topic_id' => $topic_id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($topic) {
        // Check ownership/permissions
        $user_id = $_SESSION['user_id'];
        $user_role_id = get_user_role_id();

        if ($user_role_id < ROLE_ADMIN && $user_id != $topic['author_user_id']) {
            set_flash_message('forum_status', 'Permission denied. You can only edit your own topics.', 'danger');
            redirect(BASE_URL . '/?route=forum_topic&id=' . $topic_id);
        }

        // Fetch the content of the first post
        $stmt_first_post = $pdo->prepare("SELECT content FROM forum_posts WHERE topic_id = :topic_id ORDER BY created_at ASC LIMIT 1");
        $stmt_first_post->execute([':topic_id' => $topic_id]);
        $first_post_content = $stmt_first_post->fetchColumn();

    } else {
        set_flash_message('forum_status', 'Topic not found.', 'danger');
        redirect(BASE_URL . '/?route=community');
    }
} else {
    set_flash_message('forum_status', 'Invalid topic ID.', 'danger');
    redirect(BASE_URL . '/?route=community');
}

?>

<div class="container mt-5">
    <h1>Edit Topic: <?php echo htmlspecialchars($topic['title']); ?></h1>
    <?php $flash_message = get_flash_message('forum_status'); ?>
    <?php if ($flash_message): ?>
        <div class="alert alert-<?php echo $flash_message['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $flash_message['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form id="editTopicForm" action="api.php?action=edit_topic" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="topic_id" value="<?php echo htmlspecialchars($topic['topic_id']); ?>">

        <div class="mb-3">
            <label for="topic_title" class="form-label">Topic Title</label>
            <input type="text" class="form-control" id="topic_title" name="topic_title" value="<?php echo htmlspecialchars($topic['title']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="first_post_content" class="form-label">First Post Content</label>
            <textarea class="form-control" id="first_post_content" name="first_post_content" rows="6" required><?php echo htmlspecialchars($first_post_content); ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Update Topic</button>
        <a href="<?php echo BASE_URL; ?>/?route=forum_topic&id=<?php echo htmlspecialchars($topic['topic_id']); ?>" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editTopicForm = document.getElementById('editTopicForm');
    const baseUrl = '<?php echo BASE_URL; ?>';

    if (editTopicForm) {
        editTopicForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent default form submission

            const formData = new FormData(editTopicForm);

            fetch(editTopicForm.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    window.location.href = `${baseUrl}/?route=forum_topic&id=${data.topic_id}`;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('An error occurred. Please try again.');
            });
        });
    }
});
</script>
