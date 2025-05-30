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
        // Pagination settings
        $items_per_page = 10; // Number of community posts per page
        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($current_page - 1) * $items_per_page;

        // Get total number of community posts and paginated community posts
        $total_community_posts = get_total_community_posts_count();
        $topics = get_paginated_community_posts($items_per_page, $offset);
        $total_pages = ceil($total_community_posts / $items_per_page);

        if ($topics):
            foreach ($topics as $topic):
                // Fetch author details for topic
                $pdo = get_db_connection();
                $stmt_topic_author = $pdo->prepare("SELECT username FROM users WHERE user_id = :user_id");
                $stmt_topic_author->bindParam(':user_id', $topic['user_id'], PDO::PARAM_INT);
                $stmt_topic_author->execute();
                $author_username = $stmt_topic_author->fetchColumn();

                // Get post count for the topic
                $stmt_post_count = $pdo->prepare("SELECT COUNT(*) FROM forum_posts WHERE topic_id = :topic_id");
                $stmt_post_count->bindParam(':topic_id', $topic['topic_id'], PDO::PARAM_INT);
                $stmt_post_count->execute();
                $post_count = $stmt_post_count->fetchColumn();
        ?>
                <div class="card mb-2">
                    <div class="card-body">
                        <h5 class="card-title"><a href="index.php?route=forum_topic&id=<?php echo htmlspecialchars($topic['topic_id']); ?>"><?php echo htmlspecialchars($topic['title']); ?></a></h5>
                        <p class="card-text">
                            <small class="text-muted">
                                Started by <strong><?php echo htmlspecialchars($author_username); ?></strong> on <?php echo date('M d, Y H:i', strtotime($topic['created_at'])); ?>
                                | Posts: <?php echo htmlspecialchars($post_count); ?>
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

    <!-- Pagination Links -->
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?route=community&page=<?php echo $current_page - 1; ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?route=community&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?route=community&page=<?php echo $current_page + 1; ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
</div>
