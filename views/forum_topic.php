<div class="container mt-5">
    <?php
    $topic_id = (int)($_GET['id'] ?? 0);
    $pdo = get_db_connection();

    if ($topic_id > 0) {
        // Fetch topic details
        $stmt_topic = $pdo->prepare("
            SELECT
                ft.topic_id,
                ft.title,
                ft.created_at,
                ft.user_id AS author_user_id,
                u.username AS author_username
            FROM
                forum_topics ft
            JOIN
                users u ON ft.user_id = u.user_id
            WHERE
                ft.topic_id = :topic_id
        ");
        $stmt_topic->execute([':topic_id' => $topic_id]);
        $topic = $stmt_topic->fetch();

        if ($topic) {
            // Fetch forum posts for this topic
            $stmt_posts = $pdo->prepare("
                SELECT
                    fp.post_id,
                    fp.content,
                    fp.created_at,
                    fp.parent_post_id,
                    fp.user_id AS author_user_id,
                    u.username AS author_username
                FROM
                    forum_posts fp
                JOIN
                    users u ON fp.user_id = u.user_id
                WHERE
                    fp.topic_id = :topic_id
                ORDER BY
                    fp.created_at ASC
            ");
            $stmt_posts->execute([':topic_id' => $topic_id]);
            $all_posts = $stmt_posts->fetchAll();

            ?>
            <h1 class="mb-3"><?php echo htmlspecialchars($topic['title']); ?></h1>
            <p class="lead"><small class="text-muted">Started by <strong><?php echo htmlspecialchars($topic['author_username']); ?></strong> on <?php echo date('M d, Y H:i', strtotime($topic['created_at'])); ?></small></p>

            <?php
            $can_edit_delete_topic = false;
            if (is_logged_in()) {
                $user_id = $_SESSION['user_id'];
                $user_role_id = get_user_role_id();
                if ($user_role_id >= ROLE_ADMIN || $user_id == $topic['author_user_id']) {
                    $can_edit_delete_topic = true;
                }
            }
            ?>

            <?php if ($can_edit_delete_topic): ?>
                <div class="mb-3">
                    <a href="<?php echo BASE_URL; ?>/?route=edit_topic&id=<?php echo htmlspecialchars($topic['topic_id']); ?>" class="btn btn-info btn-sm me-2">Edit Topic</a>
                    <button type="button" class="btn btn-danger btn-sm" id="deleteTopicBtn" data-topic-id="<?php echo htmlspecialchars($topic['topic_id']); ?>">Delete Topic</button>
                </div>
            <?php endif; ?>

            <hr>

            <!-- Flash messages for forum posts -->
            <?php
            $flash_forum_message = get_flash_message('forum_status');
            if ($flash_forum_message):
            ?>
                <div class="alert alert-<?php echo $flash_forum_message['type']; ?>" role="alert">
                    <?php echo $flash_forum_message['message']; ?>
                </div>
            <?php endif; ?>

            <!-- Posts List -->
            <h2 class="mt-4">Posts</h2>
            <div id="forum-posts-list">
                <?php
                // Function to display posts recursively (for threaded replies)
                function display_forum_posts($posts, $topic_data, $current_user_id, $current_user_role_id, $parent_id = null, $level = 0) {
                    if (empty($posts)) return;

                    foreach ($posts as $post) {
                        if ($post['parent_post_id'] == $parent_id) {
                            $margin_left = $level * 2; // Indent replies
                            $can_edit_delete_post = false;
                            if (is_logged_in()) {
                                // Admin can edit/delete any post
                                // Post author can edit/delete their own post
                                if ($current_user_role_id >= ROLE_ADMIN || $current_user_id == $post['author_user_id']) {
                                    $can_edit_delete_post = true;
                                }
                            }
                            ?>
                            <div class="card mb-2" style="margin-left: <?php echo $margin_left; ?>rem;">
                                <div class="card-body">
                                    <p class="card-text" id="post-content-<?php echo $post['post_id']; ?>"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">By <strong><?php echo htmlspecialchars($post['author_username']); ?></strong> on <?php echo date('M d, Y H:i', strtotime($post['created_at'])); ?></small>
                                        <div>
                                            <?php if (is_logged_in()): ?>
                                                <button class="btn btn-sm btn-link reply-post-btn" data-post-id="<?php echo $post['post_id']; ?>">Reply</button>
                                            <?php endif; ?>
                                            <?php if ($can_edit_delete_post): ?>
                                                <button class="btn btn-sm btn-info edit-post-btn" data-post-id="<?php echo $post['post_id']; ?>" data-post-content="<?php echo htmlspecialchars($post['content']); ?>">Edit</button>
                                                <button class="btn btn-sm btn-danger delete-post-btn" data-post-id="<?php echo $post['post_id']; ?>">Delete</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div id="reply-post-form-<?php echo $post['post_id']; ?>" style="display:none;" class="mt-2">
                                        <form action="api.php?action=post_reply" method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="topic_id" value="<?php echo htmlspecialchars($topic_data['topic_id']); ?>">
                                            <input type="hidden" name="parent_post_id" value="<?php echo $post['post_id']; ?>">
                                            <div class="mb-2">
                                            <textarea class="form-control" name="content" rows="2" placeholder="Reply to this post..." required></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-success">Submit Reply</button>
                                            <button type="button" class="btn btn-sm btn-secondary cancel-post-reply-btn" data-post-id="<?php echo $post['post_id']; ?>">Cancel</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php
                            display_forum_posts($posts, $topic_data, $current_user_id, $current_user_role_id, $post['post_id'], $level + 1);
                        }
                    }
                }

                $current_user_id = is_logged_in() ? $_SESSION['user_id'] : null;
                $current_user_role_id = is_logged_in() ? get_user_role_id() : null;

                if ($all_posts) {
                    display_forum_posts($all_posts, $topic, $current_user_id, $current_user_role_id);
                } else {
                    echo '<p>No posts in this topic yet. Be the first to reply!</p>';
                }
                ?>
            </div>

            <!-- Reply to Topic Form -->
            <h2 class="mt-5">Reply to Topic</h2>
            <?php if (is_logged_in()): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="api.php?action=post_reply" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="topic_id" value="<?php echo htmlspecialchars($topic['topic_id']); ?>">
                            <div class="mb-3">
                                <label for="content" class="form-label">Your Reply</label>
                                <textarea class="form-control" id="content" name="content" rows="4" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Reply</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    <a href="index.php?route=login">Log in</a> to reply to this topic.
                </div>
            <?php endif; ?>

            <?php
        } else {
            // Topic not found
            set_flash_message('forum_status', 'The requested forum topic was not found.', 'danger');
            redirect(BASE_URL . '/?route=community');
        }
    } else {
        // No topic ID provided or invalid ID
        set_flash_message('forum_status', 'Invalid forum topic ID provided.', 'danger');
        redirect(BASE_URL . '/?route=community');
    }
    ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const baseUrl = '<?php echo BASE_URL; ?>';
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    const topicId = <?php echo json_encode($topic_id); ?>;

    // Delete Topic Logic
    const deleteTopicBtn = document.getElementById('deleteTopicBtn');
    if (deleteTopicBtn) {
        deleteTopicBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete this topic and all its posts? This action cannot be undone.')) {
                fetch(`api.php?action=delete_topic`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `topic_id=${topicId}&csrf_token=${csrfToken}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        window.location.href = `${baseUrl}/?route=community`; // Redirect to community list
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('An error occurred during deletion. Please try again.');
                });
            }
        });
    }

    // Reply Post Logic (existing)
    document.querySelectorAll('.reply-post-btn').forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.dataset.postId;
            const replyForm = document.getElementById(`reply-post-form-${postId}`);
            if (replyForm) {
                replyForm.style.display = 'block';
            }
        });
    });

    document.querySelectorAll('.cancel-post-reply-btn').forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.dataset.postId;
            const replyForm = document.getElementById(`reply-post-form-${postId}`);
            if (replyForm) {
                replyForm.style.display = 'none';
                replyForm.querySelector('textarea').value = ''; // Clear textarea
            }
        });
    });

    // Edit Post Logic (using a modal for now, will need a modal HTML structure)
    document.querySelectorAll('.edit-post-btn').forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.dataset.postId;
            const currentContent = this.dataset.postContent;
            // For simplicity, we'll use a prompt for now. A proper modal is better.
            const newContent = prompt('Edit your post:', currentContent);

            if (newContent !== null && newContent.trim() !== '') {
                console.log('Editing post with ID:', postId, 'New content:', newContent); // Added console.log
                fetch(`api.php?action=edit_post`, { // Assuming edit_post API action
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `post_id=${postId}&content=${encodeURIComponent(newContent)}&csrf_token=${csrfToken}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        // Update the post content on the page
                        document.getElementById(`post-content-${postId}`).innerHTML = newContent.replace(/\n/g, '<br>');
                        // Update the data-post-content attribute for future edits
                        button.dataset.postContent = newContent;
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('An error occurred during post update. Please try again.');
                });
            }
        });
    });

    // Delete Post Logic
    document.querySelectorAll('.delete-post-btn').forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.dataset.postId;
            if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
                console.log('Deleting post with ID:', postId); // Added console.log
                fetch(`api.php?action=delete_post`, { // Assuming delete_post API action
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `post_id=${postId}&csrf_token=${csrfToken}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        // Remove the post element from the DOM
                        this.closest('.card').remove();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('An error occurred during post deletion. Please try again.');
                });
            }
        });
    });
});
</script>
