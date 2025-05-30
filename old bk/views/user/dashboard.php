<div class="container mt-5">
    <h1 class="mb-4">User Dashboard</h1>
    <p class="lead">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>! This is your personal dashboard.</p>

    <!-- Flash messages for dashboard actions -->
    <?php
    $flash_dashboard_message = get_flash_message('dashboard_status');
    if ($flash_dashboard_message):
    ?>
        <div class="alert alert-<?php echo $flash_dashboard_message['type']; ?>" role="alert">
            <?php echo $flash_dashboard_message['message']; ?>
        </div>
    <?php endif; ?>

    <h2 class="mt-4">Your Favorite Guides</h2>
    <div class="row">
        <?php
        $pdo = get_db_connection();
        $user_id = $_SESSION['user_id'] ?? 0;

        if ($user_id > 0) {
            $stmt_favorites = $pdo->prepare("
                SELECT
                    g.guide_id,
                    g.title,
                    g.description,
                    g.difficulty,
                    c.category_name
                FROM
                    favorites f
                JOIN
                    guides g ON f.guide_id = g.guide_id
                JOIN
                    categories c ON g.category_id = c.category_id
                WHERE
                    f.user_id = :user_id
                ORDER BY
                    f.created_at DESC
            ");
            $stmt_favorites->execute([':user_id' => $user_id]);
            $favorite_guides = $stmt_favorites->fetchAll();

            if ($favorite_guides):
                foreach ($favorite_guides as $guide):
            ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($guide['title']); ?></h5>
                                <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($guide['category_name']); ?> - <?php echo htmlspecialchars($guide['difficulty']); ?></h6>
                                <p class="card-text"><?php echo htmlspecialchars(substr($guide['description'], 0, 100)); ?>...</p>
                                <a href="<?php echo BASE_URL; ?>/?route=guide&id=<?php echo htmlspecialchars($guide['guide_id']); ?>" class="btn btn-primary btn-sm">View Guide</a>
                                <!-- Optionally add a remove from favorites button here -->
                            </div>
                        </div>
                    </div>
            <?php
                endforeach;
            else:
            ?>
                <div class="col-12">
                    <div class="alert alert-info" role="alert">
                        You haven't favorited any guides yet. Browse <a href="<?php echo BASE_URL; ?>/?route=guides">all guides</a> to find some!
                    </div>
                </div>
            <?php endif;
        } else {
            echo '<div class="col-12"><div class="alert alert-warning">Please log in to view your dashboard.</div></div>';
        }
        ?>
    </div>

    <!-- Other dashboard sections (e.g., Your Comments, Your Questions, etc.) can go here -->
</div>
