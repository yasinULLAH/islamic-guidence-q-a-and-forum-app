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
                u.username AS author_username,
                g.created_by AS author_user_id
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

            <?php
            $can_edit_delete = false;
            if (is_logged_in()) {
                $user_id = $_SESSION['user_id'];
                $user_role_id = get_user_role_id();
                // Admin can edit/delete any guide
                // Ulama can edit/delete their own guides
                if ($user_role_id >= ROLE_ADMIN || ($user_role_id >= ROLE_ULAMA_SCHOLAR && $user_id == $guide['author_user_id'])) {
                    $can_edit_delete = true;
                }
            }
            ?>

            <?php if ($can_edit_delete): ?>
                <div class="mb-3">
                    <a href="<?php echo BASE_URL; ?>/?route=edit_guide&id=<?php echo htmlspecialchars($guide['guide_id']); ?>" class="btn btn-info btn-sm me-2">Edit Guide</a>
                    <button type="button" class="btn btn-danger btn-sm" id="deleteGuideBtn" data-guide-id="<?php echo htmlspecialchars($guide['guide_id']); ?>">Delete Guide</button>
                </div>
            <?php endif; ?>

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

            <div class="d-flex justify-content-end mb-3">
                <button class="btn btn-outline-primary me-2" id="linearViewBtn">Linear View</button>
                <button class="btn btn-outline-secondary" id="treeViewBtn">Tree View</button>
            </div>

            <div id="linearViewContainer">
                <h2 class="mt-4">Steps (Linear View)</h2>
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
            </div>

            <div id="treeViewContainer" style="display: none;">
                <h2 class="mt-4">Steps (Tree View)</h2>
                <div id="guideTree">
                    <!-- Tree will be rendered here by JavaScript -->
                </div>
            </div>

            <!-- Step Detail Modal -->
            <div class="modal fade" id="stepDetailModal" tabindex="-1" aria-labelledby="stepDetailModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="stepDetailModalLabel">Step Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <h3 id="modalStepTitle"></h3>
                            <p id="modalStepContent"></p>
                            <div id="modalStepImageContainer" class="text-center my-3"></div>
                            <div id="modalStepAudioContainer" class="my-3"></div>
                            <div id="modalStepReferencesContainer" class="mt-3"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
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
                        <div id="ratingStatusMessage" class="mb-3" style="display: none;"></div>
                        <form id="ratingForm" action="api.php?action=submit_rating" method="POST">
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
                                <small class="text-muted ms-2" id="currentRatingText">You have rated this guide <?php echo $user_rating; ?> star<?php echo ($user_rating > 1) ? 's' : ''; ?>.</small>
                            <?php else: ?>
                                <small class="text-muted ms-2" id="currentRatingText" style="display: none;"></small>
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
                        <div id="commentStatusMessage" class="mb-3" style="display: none;"></div>
                        <form id="mainCommentForm" action="api.php?action=add_comment" method="POST">
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
                function display_comments($comments, $guide_id_for_form, $parent_id = null, $level = 0) {
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
                                        <form class="replyCommentForm" action="api.php?action=add_comment" method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="guide_id" value="<?php echo htmlspecialchars($guide_id_for_form); ?>">
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
                            display_comments($comments, $guide_id_for_form, $comment['comment_id'], $level + 1);
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
                    display_comments($all_comments, $guide['guide_id']);
                } else {
                    echo '<p>No comments yet. Be the first to comment!</p>';
                }
                ?>
            </div>

            <?php
        } else {
            // Guide not found
            set_flash_message('guide_status', 'The requested guide was not found.', 'danger');
            redirect(BASE_URL . '/?route=guides');
        }
    } else {
        // No guide ID provided
        set_flash_message('guide_status', 'Invalid guide ID provided.', 'danger');
        redirect(BASE_URL . '/?route=guides');
    }
    ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // PHP data for JavaScript
    <?php
    // Temporarily suppress errors to prevent them from breaking JSON output
    $old_error_reporting = error_reporting(0);
    ?>
    const stepsData = <?php echo json_encode($steps); ?>;
    const referencesData = <?php echo json_encode($references); ?>;
    const guideDifficulty = '<?php echo htmlspecialchars($guide['difficulty']); ?>'; // Pass guide difficulty
    const baseUrl = '<?php echo BASE_URL; ?>';
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    <?php
    // Restore error reporting
    error_reporting($old_error_reporting);
    ?>

    // Delete Guide Logic
    const deleteGuideBtn = document.getElementById('deleteGuideBtn');
    if (deleteGuideBtn) {
        deleteGuideBtn.addEventListener('click', function() {
            const guideId = this.dataset.guideId;
            if (confirm('Are you sure you want to delete this guide? This action cannot be undone.')) {
                fetch(`api.php?action=delete_guide`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `guide_id=${guideId}&csrf_token=${csrfToken}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        window.location.href = `${baseUrl}/?route=guides`; // Redirect to guides list
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

    // View Mode Toggle Logic
    const linearViewBtn = document.getElementById('linearViewBtn');
    const treeViewBtn = document.getElementById('treeViewBtn');
    const linearViewContainer = document.getElementById('linearViewContainer');
    const treeViewContainer = document.getElementById('treeViewContainer');

    function showLinearView() {
        linearViewContainer.style.display = 'block';
        treeViewContainer.style.display = 'none';
        linearViewBtn.classList.add('btn-primary');
        linearViewBtn.classList.remove('btn-outline-primary');
        treeViewBtn.classList.add('btn-outline-secondary');
        treeViewBtn.classList.remove('btn-secondary');
    }

    function showTreeView() {
        linearViewContainer.style.display = 'none';
        treeViewContainer.style.display = 'block';
        treeViewBtn.classList.add('btn-secondary');
        treeViewBtn.classList.remove('btn-outline-secondary');
        linearViewBtn.classList.add('btn-outline-primary');
        linearViewBtn.classList.remove('btn-primary');
        renderTreeView(); // Render tree when switched to this view
    }

    linearViewBtn.addEventListener('click', showLinearView);
    treeViewBtn.addEventListener('click', showTreeView);

    // Initial view setup
    showLinearView(); // Default to linear view

    // Tree View Rendering
    const guideTree = document.getElementById('guideTree');
    const stepDetailModal = new bootstrap.Modal(document.getElementById('stepDetailModal'));
    const modalStepTitle = document.getElementById('modalStepTitle');
    const modalStepContent = document.getElementById('modalStepContent');
    const modalStepImageContainer = document.getElementById('modalStepImageContainer');
    const modalStepAudioContainer = document.getElementById('modalStepAudioContainer');
    const modalStepReferencesContainer = document.getElementById('modalStepReferencesContainer');

    function renderTreeView() {
        guideTree.innerHTML = ''; // Clear previous tree
        if (stepsData.length === 0) {
            guideTree.innerHTML = '<p>No steps found for this guide to display in tree view.</p>';
            return;
        }

        // Create the main tree level (all steps are on one level for now)
        const treeLevel = document.createElement('div');
        treeLevel.classList.add('tree-level');

        stepsData.forEach((step, index) => {
            const nodeContainer = document.createElement('div');
            nodeContainer.classList.add('tree-node-container');

            const node = document.createElement('a');
            node.href = "#"; // Prevent actual navigation
            node.classList.add('tree-node', 'text-decoration-none');
            // Add difficulty class for coloring from guideDifficulty
            node.classList.add(`difficulty-${guideDifficulty.toLowerCase()}`);
            node.dataset.stepId = step.step_id;
            node.innerHTML = `<strong>${step.step_number}</strong><br>${step.title}`;

            nodeContainer.appendChild(node);

            // Add horizontal connector if not the first node
            if (index > 0) {
                const connector = document.createElement('div');
                connector.classList.add('tree-level-connector');
                // Position the connector between nodes
                // This is a simplified approach; for complex trees, SVG or canvas might be better
                // For a linear horizontal flow, we can just add a line before each node (except first)
                nodeContainer.style.marginLeft = '50px'; // Adjust spacing
                nodeContainer.style.position = 'relative';
                connector.style.width = '50px'; // Length of the horizontal line
                connector.style.left = '-50px';
                connector.style.top = '50%';
                connector.style.transform = 'translateY(-50%)';
                nodeContainer.appendChild(connector);
            }

            treeLevel.appendChild(nodeContainer);
        });

        guideTree.appendChild(treeLevel);

        // Add click listeners to step nodes
        guideTree.querySelectorAll('.tree-node').forEach(node => {
            node.addEventListener('click', function(event) {
                event.preventDefault();
                const stepId = parseInt(this.dataset.stepId);
                const step = stepsData.find(s => s.step_id == stepId); // Use == for type coercion if step_id is string

                if (step) {
                    modalStepTitle.textContent = `Step ${step.step_number}: ${step.title}`;
                    modalStepContent.innerHTML = step.content.replace(/\n/g, '<br>'); // Preserve line breaks

                    // Image
                    modalStepImageContainer.innerHTML = '';
                    if (step.image_url) {
                        const img = document.createElement('img');
                        img.src = `${baseUrl}/${step.image_url}`;
                        img.classList.add('img-fluid', 'rounded');
                        img.style.maxHeight = '300px';
                        img.alt = 'Step Image';
                        modalStepImageContainer.appendChild(img);
                    }

                    // Audio
                    modalStepAudioContainer.innerHTML = '';
                    if (step.audio_url) {
                        const audio = document.createElement('audio');
                        audio.controls = true;
                        audio.classList.add('w-100');
                        const source = document.createElement('source');
                        source.src = `${baseUrl}/${step.audio_url}`;
                        source.type = 'audio/mpeg'; // Assuming MP3, adjust if needed
                        audio.appendChild(source);
                        modalStepAudioContainer.appendChild(audio);
                    }

                    // References
                    modalStepReferencesContainer.innerHTML = '';
                    if (referencesData[step.step_id] && referencesData[step.step_id].length > 0) {
                        const h6 = document.createElement('h6');
                        h6.textContent = 'References:';
                        const ulRef = document.createElement('ul');
                        ulRef.classList.add('list-unstyled');
                        referencesData[step.step_id].forEach(refText => {
                            const liRef = document.createElement('li');
                            const smallRef = document.createElement('small');
                            smallRef.textContent = refText;
                            liRef.appendChild(smallRef);
                            ulRef.appendChild(liRef);
                        });
                        modalStepReferencesContainer.appendChild(h6);
                        modalStepReferencesContainer.appendChild(ulRef);
                    }

                    stepDetailModal.show();
                }
            });
        });
    }

    // Comment reply/cancel logic (existing)
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

    // Main Comment Form Submission Logic
    const mainCommentForm = document.getElementById('mainCommentForm');
    if (mainCommentForm) {
        mainCommentForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent default form submission

            const formData = new FormData(mainCommentForm);
            const commentStatusMessage = document.getElementById('commentStatusMessage');
            const commentTextarea = document.getElementById('comment_text');

            fetch(`api.php?action=add_comment`, {
                method: 'POST',
                body: formData // FormData handles content-type and encoding
            })
            .then(response => response.json())
            .then(data => {
                commentStatusMessage.style.display = 'block';
                if (data.status === 'success') {
                    commentStatusMessage.className = 'alert alert-success';
                    commentStatusMessage.textContent = data.message;
                    commentTextarea.value = ''; // Clear textarea
                    // Optionally, dynamically add the new comment to the list without full reload
                    // For now, just show success message. User can refresh to see new comment.
                } else {
                    commentStatusMessage.className = 'alert alert-danger';
                    commentStatusMessage.textContent = 'Error: ' + data.message;
                }
                setTimeout(() => {
                    commentStatusMessage.style.display = 'none';
                }, 5000);
            })
            .catch(error => {
                console.error('Fetch error:', error);
                commentStatusMessage.style.display = 'block';
                commentStatusMessage.className = 'alert alert-danger';
                commentStatusMessage.textContent = 'An error occurred. Please try again.';
                setTimeout(() => {
                    commentStatusMessage.style.display = 'none';
                }, 5000);
            });
        });
    }

    // Reply Comment Forms Submission Logic (using event delegation for dynamically added forms)
    document.getElementById('comments-list').addEventListener('submit', function(event) {
        if (event.target.classList.contains('replyCommentForm')) {
            event.preventDefault(); // Prevent default form submission

            const form = event.target;
            const formData = new FormData(form);
            const commentId = form.querySelector('input[name="parent_comment_id"]').value;
            const replyFormContainer = document.getElementById(`reply-form-${commentId}`);
            const commentTextarea = form.querySelector('textarea[name="comment_text"]');

            // Use the main commentStatusMessage for replies too, or create a new one
            const commentStatusMessage = document.getElementById('commentStatusMessage'); // Re-using for simplicity

            fetch(`api.php?action=add_comment`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                commentStatusMessage.style.display = 'block';
                if (data.status === 'success') {
                    commentStatusMessage.className = 'alert alert-success';
                    commentStatusMessage.textContent = data.message;
                    commentTextarea.value = ''; // Clear textarea
                    replyFormContainer.style.display = 'none'; // Hide the reply form
                    // Optionally, dynamically add the new reply to the list
                } else {
                    commentStatusMessage.className = 'alert alert-danger';
                    commentStatusMessage.textContent = 'Error: ' + data.message;
                }
                setTimeout(() => {
                    commentStatusMessage.style.display = 'none';
                }, 5000);
            })
            .catch(error => {
                console.error('Fetch error:', error);
                commentStatusMessage.style.display = 'block';
                commentStatusMessage.className = 'alert alert-danger';
                commentStatusMessage.textContent = 'An error occurred. Please try again.';
                setTimeout(() => {
                    commentStatusMessage.style.display = 'none';
                }, 5000);
            });
        }
    });

    // Favorite button logic (existing)
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

    // Rating form submission logic
    const ratingForm = document.getElementById('ratingForm');
    if (ratingForm) {
        ratingForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent default form submission

            const formData = new FormData(ratingForm);
            const guideId = formData.get('guide_id');
            const rating = formData.get('rating');
            const csrfToken = formData.get('csrf_token');
            const ratingStatusMessage = document.getElementById('ratingStatusMessage');
            const currentRatingText = document.getElementById('currentRatingText');

            fetch(`api.php?action=submit_rating`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `guide_id=${guideId}&rating=${rating}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                ratingStatusMessage.style.display = 'block';
                if (data.status === 'success') {
                    ratingStatusMessage.className = 'alert alert-success';
                    ratingStatusMessage.textContent = data.message;
                    currentRatingText.textContent = `You have rated this guide ${rating} star${rating > 1 ? 's' : ''}.`;
                    currentRatingText.style.display = 'inline';
                } else {
                    ratingStatusMessage.className = 'alert alert-danger';
                    ratingStatusMessage.textContent = 'Error: ' + data.message;
                }
                // Hide message after a few seconds
                setTimeout(() => {
                    ratingStatusMessage.style.display = 'none';
                }, 5000);
            })
            .catch(error => {
                console.error('Fetch error:', error);
                ratingStatusMessage.style.display = 'block';
                ratingStatusMessage.className = 'alert alert-danger';
                ratingStatusMessage.textContent = 'An error occurred. Please try again.';
                setTimeout(() => {
                    ratingStatusMessage.style.display = 'none';
                }, 5000);
            });
        });
    }
});
</script>
