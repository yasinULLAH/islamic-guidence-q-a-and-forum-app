<div class="container mt-5">
    <h1 class="mb-4">Discussion Forum</h1>
    <p class="lead">Engage in discussions with other users on various Islamic topics.</p>

    <?php
    $flash_forum_message = get_flash_message('forum_status');
    if ($flash_forum_message):
    ?>
        <div class="alert alert-<?php echo $flash_forum_message['type']; ?>" role="alert">
            <?php echo $flash_forum_message['message']; ?>
        </div>
    <?php endif; ?>

    <!-- Create New Topic Form -->
    <?php if (is_logged_in()): ?>
        <div class="card mb-4">
            <div class="card-header">Create New Topic</div>
            <div class="card-body">
                <form action="api.php?action=create_topic" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="mb-3">
                        <label for="topic_title" class="form-label">Topic Title</label>
                        <input type="text" class="form-control" id="topic_title" name="topic_title" required>
                    </div>
                    <div class="mb-3">
                        <label for="first_post_content" class="form-label">First Post Content</label>
                        <textarea class="form-control" id="first_post_content" name="first_post_content" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Topic</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            <a href="index.php?route=login">Log in</a> to create a new discussion topic.
        </div>
    <?php endif; ?>

    <h2 class="mt-5 mb-3">All Topics</h2>
    <div id="forum-topics-list">
        <?php
        $pdo = get_db_connection();
        $stmt_topics = $pdo->query("
            SELECT
                ft.topic_id,
                ft.title,
                ft.created_at,
                ft.last_post_at,
                u.username AS author_username,
                (SELECT COUNT(*) FROM forum_posts fp WHERE fp.topic_id = ft.topic_id) AS post_count
            FROM
                forum_topics ft
            JOIN
                users u ON ft.user_id = u.user_id
            ORDER BY
                ft.last_post_at DESC
        ");
        $topics = $stmt_topics->fetchAll();

        if ($topics):
            foreach ($topics as $topic):
        ?>
                <div class="card mb-2">
                    <div class="card-body">
                        <h5 class="card-title"><a href="index.php?route=forum_topic&id=<?php echo htmlspecialchars($topic['topic_id']); ?>"><?php echo htmlspecialchars($topic['title']); ?></a></h5>
                        <p class="card-text">
                            <small class="text-muted">
                                Started by <strong><?php echo htmlspecialchars($topic['author_username']); ?></strong> on <?php echo date('M d, Y H:i', strtotime($topic['created_at'])); ?>
                                | Posts: <?php echo htmlspecialchars($topic['post_count']); ?>
                                | Last activity: <?php echo date('M d, Y H:i', strtotime($topic['last_post_at'])); ?>
                            </small>
                        </p>
                    </div>
                </div>
        <?php
            endforeach;
        else:
        ?>
            <div class="alert alert-info" role="alert">
                No discussion topics yet. Be the first to <a href="index.php?route=community">create one</a>!
            </div>
        <?php endif; ?>
    </div>
</div>
