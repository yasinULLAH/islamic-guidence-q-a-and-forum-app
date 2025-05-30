<div class="container mt-5">
    <h1 class="mb-4">Questions & Answers</h1>

    <?php
    $flash_qa_message = get_flash_message('qa_status');
    if ($flash_qa_message):
    ?>
        <div class="alert alert-<?php echo $flash_qa_message['type']; ?>" role="alert">
            <?php echo $flash_qa_message['message']; ?>
        </div>
    <?php endif; ?>

    <!-- Ask a Question Form -->
    <?php if (is_logged_in()): ?>
        <div class="card mb-4">
            <div class="card-header">Ask a New Question</div>
            <div class="card-body">
                <form action="api.php?action=ask_question" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="mb-3">
                        <label for="question_text" class="form-label">Your Question</label>
                        <textarea class="form-control" id="question_text" name="question_text" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Question</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            <a href="<?php echo BASE_URL; ?>/?route=login">Log in</a> to ask a question.
        </div>
    <?php endif; ?>

    <h2 class="mt-5 mb-3">All Questions</h2>
    <div id="questions-list">
        <?php
        // Pagination settings
        $items_per_page = 10; // Number of Q&A entries per page
        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($current_page - 1) * $items_per_page;

        // Get total number of Q&A entries and paginated Q&A
        $total_q_and_a = get_total_q_and_a_count();
        $questions = get_paginated_q_and_a($items_per_page, $offset);
        $total_pages = ceil($total_q_and_a / $items_per_page);

        if ($questions):
            foreach ($questions as $question):
                // Fetch author details for question and answer
                $pdo = get_db_connection();
                $stmt_question_author = $pdo->prepare("SELECT username FROM users WHERE user_id = :user_id");
                $stmt_question_author->bindParam(':user_id', $question['user_id'], PDO::PARAM_INT);
                $stmt_question_author->execute();
                $question_author = $stmt_question_author->fetchColumn();

                $answer_author_username = null;
                $answer_author_role_id = null;
                if (!empty($question['answered_by'])) {
                    $stmt_answer_author = $pdo->prepare("SELECT username, role_id FROM users WHERE user_id = :user_id");
                    $stmt_answer_author->bindParam(':user_id', $question['answered_by'], PDO::PARAM_INT);
                    $stmt_answer_author->execute();
                    $answer_author = $stmt_answer_author->fetch();
                    if ($answer_author) {
                        $answer_author_username = $answer_author['username'];
                        $answer_author_role_id = $answer_author['role_id'];
                    }
                }
        ?>
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <strong>Q: <?php echo htmlspecialchars($question['question_text']); ?></strong>
                        <br>
                        <small class="text-muted">Asked by <?php echo htmlspecialchars($question_author); ?> on <?php echo date('M d, Y H:i', strtotime($question['created_at'])); ?></small>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($question['answer_text'])): ?>
                            <p><strong>A:</strong> <?php echo nl2br(htmlspecialchars($question['answer_text'])); ?></p>
                            <small class="text-muted">Answered by <strong><?php echo htmlspecialchars($answer_author_username); ?><?php if ($answer_author_role_id == ROLE_ULAMA_SCHOLAR): ?> <span class="badge bg-success">Scholar</span><?php endif; ?></strong> on <?php echo date('M d, Y H:i', strtotime($question['answered_at'])); ?></small>
                        <?php else: ?>
                            <p class="text-muted">No answer yet.</p>
                            <?php if (is_logged_in() && has_role(ROLE_ULAMA_SCHOLAR)): ?>
                                <button class="btn btn-sm btn-info answer-btn" data-qa-id="<?php echo $question['qa_id']; ?>">Answer Question</button>
                                <div id="answer-form-<?php echo $question['qa_id']; ?>" style="display:none;" class="mt-2">
                                    <form action="api.php?action=answer_question" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="qa_id" value="<?php echo htmlspecialchars($question['qa_id']); ?>">
                                        <div class="mb-2">
                                            <textarea class="form-control" name="answer_text" rows="3" placeholder="Provide your answer..." required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-success">Submit Answer</button>
                                        <button type="button" class="btn btn-sm btn-secondary cancel-answer-btn" data-qa-id="<?php echo $question['qa_id']; ?>">Cancel</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
        <?php
            endforeach;
        else:
        ?>
            <div class="alert alert-info" role="alert">
                No questions asked yet. Be the first to <a href="<?php echo BASE_URL; ?>/?route=q_and_a">ask one</a>!
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination Links -->
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?route=q_and_a&page=<?php echo $current_page - 1; ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?route=q_and_a&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?route=q_and_a&page=<?php echo $current_page + 1; ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.answer-btn').forEach(button => {
        button.addEventListener('click', function() {
            const qaId = this.dataset.qaId;
            const answerForm = document.getElementById(`answer-form-${qaId}`);
            if (answerForm) {
                answerForm.style.display = 'block';
                this.style.display = 'none'; // Hide the "Answer Question" button
            }
        });
    });

    document.querySelectorAll('.cancel-answer-btn').forEach(button => {
        button.addEventListener('click', function() {
            const qaId = this.dataset.qaId;
            const answerForm = document.getElementById(`answer-form-${qaId}`);
            const answerBtn = document.querySelector(`.answer-btn[data-qa-id="${qaId}"]`);
            if (answerForm) {
                answerForm.style.display = 'none';
                answerForm.querySelector('textarea').value = ''; // Clear textarea
            }
            if (answerBtn) {
                answerBtn.style.display = 'inline-block'; // Show the "Answer Question" button again
            }
        });
    });
});
</script>
