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
                function display_forum_posts($posts, $topic_data, $parent_id = null, $level = 0) {
                    if (empty($posts)) return;

                    foreach ($posts as $post) {
                        if ($post['parent_post_id'] == $parent_id) {
                            $margin_left = $level * 2; // Indent replies
                            ?>
                            <div class="card mb-2" style="margin-left: <?php echo $margin_left; ?>rem;">
                                <div class="card-body">
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">By <strong><?php echo htmlspecialchars($post['author_username']); ?></strong> on <?php echo date('M d, Y H:i', strtotime($post['created_at'])); ?></small>
                                        <?php if (is_logged_in()): ?>
                                            <button class="btn btn-sm btn-link reply-post-btn" data-post-id="<?php echo $post['post_id']; ?>">Reply</button>
                                        <?php endif; ?>
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
                            display_forum_posts($posts, $topic_data, $post['post_id'], $level + 1);
                        }
                    }
                }

                if ($all_posts) {
                    display_forum_posts($all_posts, $topic);
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
});
</script>
