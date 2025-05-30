<div class="container mt-5">
    <?php
    $guide_id = (int)($_GET['id'] ?? 0);
    $pdo = get_db_connection();

    if ($guide_id > 0) {
        // Fetch guide details
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
            WHERE
                g.guide_id = :guide_id
        ");
        $stmt->execute([':guide_id' => $guide_id]);
        $guide = $stmt->fetch();

        if ($guide) {
            // Fetch guide steps
            $stmt_steps = $pdo->prepare("SELECT * FROM guide_steps WHERE guide_id = :guide_id ORDER BY step_number ASC");
            $stmt_steps->execute([':guide_id' => $guide_id]);
            $steps = $stmt_steps->fetchAll();

            // Fetch references
            $stmt_refs = $pdo->prepare("SELECT step_id, reference_text FROM content_references WHERE guide_id = :guide_id");
            $stmt_refs->execute([':guide_id' => $guide_id]);
            $references = $stmt_refs->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_COLUMN); // Group by step_id
            ?>
            <h1 class="mb-3"><?php echo htmlspecialchars($guide['title']); ?></h1>
            <p class="lead"><?php echo htmlspecialchars($guide['description']); ?></p>
            <p><strong>Category:</strong> <?php echo htmlspecialchars($guide['category_name']); ?> | <strong>Difficulty:</strong> <?php echo htmlspecialchars($guide['difficulty']); ?></p>
            <p><small class="text-muted">Created by <?php echo htmlspecialchars($guide['author_username']); ?> on <?php echo date('M d, Y', strtotime($guide['created_at'])); ?></small></p>

            <?php if (is_logged_in()):
                $stmt_fav = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = :user_id AND guide_id = :guide_id");
                $stmt_fav->execute([':user_id' => $_SESSION['user_id'], ':guide_id' => $guide['guide_id']]);
                $is_favorited = $stmt_fav->fetchColumn() > 0;
            ?>
                <button type="button" class="btn btn-outline-warning mb-3" id="favoriteBtn" data-guide-id="<?php echo $guide['guide_id']; ?>" data-is-favorited="<?php echo $is_favorited ? 'true' : 'false'; ?>">
                    <i class="bi bi-star<?php echo $is_favorited ? '-fill' : ''; ?>"></i> <span id="favoriteText"><?php echo $is_favorited ? 'Favorited' : 'Add to Favorites'; ?></span>
                </button>
            <?php endif; ?>

            <hr>

            <h2 class="mt-4">Steps</h2>
            <div class="accordion" id="guideStepsAccordion">
                <?php if ($steps): ?>
                    <?php foreach ($steps as $index => $step): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading<?php echo $step['step_number']; ?>">
                                <button class="accordion-button <?php echo ($index === 0) ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $step['step_number']; ?>" aria-expanded="<?php echo ($index === 0) ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $step['step_number']; ?>">
                                    Step <?php echo htmlspecialchars($step['step_number']); ?>: <?php echo htmlspecialchars($step['title']); ?>
                                </button>
                            </h2>
                            <div id="collapse<?php echo $step['step_number']; ?>" class="accordion-collapse collapse <?php echo ($index === 0) ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $step['step_number']; ?>" data-bs-parent="#guideStepsAccordion">
                                <div class="accordion-body">
                                    <p><?php echo nl2br(htmlspecialchars($step['content'])); ?></p>
                                    <?php if (!empty($step['image_url'])): ?>
                                        <div class="text-center my-3">
                                            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($step['image_url']); ?>" class="img-fluid rounded" alt="Step Image" style="max-height: 400px;">
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($step['audio_url'])): ?>
                                        <div class="my-3">
                                            <audio controls class="w-100">
                                                <source src="<?php echo BASE_URL . '/' . htmlspecialchars($step['audio_url']); ?>" type="audio/mpeg">
                                                Your browser does not support the audio element.
                                            </audio>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($references[$step['step_id']])): ?>
                                        <h6 class="mt-3">References:</h6>
                                        <ul class="list-unstyled">
                                            <?php foreach ($references[$step['step_id']] as $ref_text): ?>
                                                <li><small><?php echo htmlspecialchars($ref_text); ?></small></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No steps found for this guide.</p>
                <?php endif; ?>
            </div>

            <!-- Rating Section -->
            <h2 class="mt-4">Rate this Guide</h2>
            <?php if (is_logged_in()):
                $stmt_user_rating = $pdo->prepare("SELECT rating FROM ratings WHERE user_id = :user_id AND guide_id = :guide_id");
                $stmt_user_rating->execute([':user_id' => $_SESSION['user_id'], ':guide_id' => $guide['guide_id']]);
                $user_rating = $stmt_user_rating->fetchColumn();
            ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <?php
                        $flash_rating_message = get_flash_message('rating_status');
                        if ($flash_rating_message):
                        ?>
                            <div class="alert alert-<?php echo $flash_rating_message['type']; ?>" role="alert">
                                <?php echo $flash_rating_message['message']; ?>
                            </div>
                        <?php endif; ?>
                        <form action="api.php?action=submit_rating" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="guide_id" value="<?php echo htmlspecialchars($guide['guide_id']); ?>">
                            <div class="mb-3">
                                <label for="rating" class="form-label">Your Rating:</label>
                                <select class="form-select" id="rating" name="rating" required>
                                    <option value="">Select a rating</option>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($user_rating == $i) ? 'selected' : ''; ?>><?php echo $i; ?> Star<?php echo ($i > 1) ? 's' : ''; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Rating</button>
                            <?php if ($user_rating): ?>
                                <small class="text-muted ms-2">You have rated this guide <?php echo $user_rating; ?> star<?php echo ($user_rating > 1) ? 's' : ''; ?>.</small>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    <a href="<?php echo BASE_URL; ?>/?route=login">Log in</a> to rate this guide.
                </div>
            <?php endif; ?>

            <hr class="my-5">

            <!-- Comments Section -->
            <h2 class="mb-3">Comments</h2>
            <?php if (is_logged_in()): ?>
                <div class="card mb-4">
                    <div class="card-header">Add a Comment</div>
                    <div class="card-body">
                        <?php
                        $flash_comment_message = get_flash_message('comment_status');
                        if ($flash_comment_message):
                        ?>
                            <div class="alert alert-<?php echo $flash_comment_message['type']; ?>" role="alert">
                                <?php echo $flash_comment_message['message']; ?>
                            </div>
                        <?php endif; ?>
                        <form action="api.php?action=add_comment" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="guide_id" value="<?php echo htmlspecialchars($guide['guide_id']); ?>">
                            <div class="mb-3">
                                <label for="comment_text" class="form-label">Your Comment</label>
                                <textarea class="form-control" id="comment_text" name="comment_text" rows="3" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Comment</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    <a href="<?php echo BASE_URL; ?>/?route=login">Log in</a> to post a comment.
                </div>
            <?php endif; ?>

            <h3 class="mt-4">All Comments</h3>
            <div id="comments-list">
                <?php
                // Function to display comments recursively
                function display_comments($comments, $parent_id = null, $level = 0) {
                    if (empty($comments)) return;

                    foreach ($comments as $comment) {
                        if ($comment['parent_comment_id'] == $parent_id) {
                            $margin_left = $level * 2; // Indent replies
                            ?>
                            <div class="card mb-2" style="margin-left: <?php echo $margin_left; ?>rem;">
                                <div class="card-body">
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">By <strong><?php echo htmlspecialchars($comment['username']); ?></strong> on <?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?></small>
                                        <?php if (is_logged_in()): ?>
                                            <button class="btn btn-sm btn-link reply-btn" data-comment-id="<?php echo $comment['comment_id']; ?>">Reply</button>
                                        <?php endif; ?>
                                    </div>
                                    <div id="reply-form-<?php echo $comment['comment_id']; ?>" style="display:none;" class="mt-2">
                                        <form action="api.php?action=add_comment" method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="guide_id" value="<?php echo htmlspecialchars($guide['guide_id']); ?>">
                                            <input type="hidden" name="parent_comment_id" value="<?php echo $comment['comment_id']; ?>">
                                            <div class="mb-2">
                                                <textarea class="form-control" name="comment_text" rows="2" placeholder="Reply to this comment..." required></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-success">Submit Reply</button>
                                            <button type="button" class="btn btn-sm btn-secondary cancel-reply-btn" data-comment-id="<?php echo $comment['comment_id']; ?>">Cancel</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php
                            display_comments($comments, $comment['comment_id'], $level + 1);
                        }
                    }
                }

                $stmt_comments = $pdo->prepare("
                    SELECT
                        c.comment_id,
                        c.comment_text,
                        c.parent_comment_id,
                        c.created_at,
                        u.username
                    FROM
                        comments c
                    JOIN
                        users u ON c.user_id = u.user_id
                    WHERE
                        c.guide_id = :guide_id
                    ORDER BY
                        c.created_at ASC
                ");
                $stmt_comments->execute([':guide_id' => $guide_id]);
                $all_comments = $stmt_comments->fetchAll();

                if ($all_comments) {
                    display_comments($all_comments);
                } else {
                    echo '<p>No comments yet. Be the first to comment!</p>';
                }
                ?>
            </div>

            <?php
        } else {
            // Guide not found
            http_response_code(404);
            include 'views/404.php';
        }
    } else {
        // No guide ID provided
        http_response_code(400);
        echo '<div class="alert alert-danger">Invalid guide ID provided.</div>';
    }
    ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Comment reply/cancel logic
    document.querySelectorAll('.reply-btn').forEach(button => {
        button.addEventListener('click', function() {
            const commentId = this.dataset.commentId;
            const replyForm = document.getElementById(`reply-form-${commentId}`);
            if (replyForm) {
                replyForm.style.display = 'block';
            }
        });
    });

    document.querySelectorAll('.cancel-reply-btn').forEach(button => {
        button.addEventListener('click', function() {
            const commentId = this.dataset.commentId;
            const replyForm = document.getElementById(`reply-form-${commentId}`);
            if (replyForm) {
                replyForm.style.display = 'none';
                replyForm.querySelector('textarea').value = ''; // Clear textarea
            }
        });
    });

    // Favorite button logic
    const favoriteBtn = document.getElementById('favoriteBtn');
    if (favoriteBtn) {
        favoriteBtn.addEventListener('click', function() {
            const guideId = this.dataset.guideId;
            let isFavorited = this.dataset.isFavorited === 'true';
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;
            const favoriteText = document.getElementById('favoriteText');
            const favoriteIcon = this.querySelector('i.bi');

            const action = isFavorited ? 'remove_favorite' : 'add_favorite';

            fetch(`api.php?action=${action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `guide_id=${guideId}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    isFavorited = !isFavorited; // Toggle state
                    this.dataset.isFavorited = isFavorited;
                    if (isFavorited) {
                        favoriteText.textContent = 'Favorited';
                        favoriteIcon.classList.remove('bi-star');
                        favoriteIcon.classList.add('bi-star-fill');
                    } else {
                        favoriteText.textContent = 'Add to Favorites';
                        favoriteIcon.classList.remove('bi-star-fill');
                        favoriteIcon.classList.add('bi-star');
                    }
                    // Optionally, show a small toast/alert for user feedback
                    console.log(data.message);
                } else {
                    console.error('Error:', data.message);
                    alert('Error: ' + data.message); // Simple alert for now
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
