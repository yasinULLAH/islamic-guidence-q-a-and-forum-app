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
        $pdo = get_db_connection();
        $stmt_questions = $pdo->query("
            SELECT
                qa.qa_id,
                qa.question_text,
                qa.answer_text,
                qa.created_at,
                qa.answered_at,
                u.username AS question_author,
                au.username AS answer_author_username,
                au.role_id AS answer_author_role_id
            FROM
                q_and_a qa
            JOIN
                users u ON qa.user_id = u.user_id
            LEFT JOIN
                users au ON qa.answered_by = au.user_id
            ORDER BY
                qa.created_at DESC
        ");
        $questions = $stmt_questions->fetchAll();

        if ($questions):
            foreach ($questions as $question):
        ?>
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <strong>Q: <?php echo htmlspecialchars($question['question_text']); ?></strong>
                        <br>
                        <small class="text-muted">Asked by <?php echo htmlspecialchars($question['question_author']); ?> on <?php echo date('M d, Y H:i', strtotime($question['created_at'])); ?></small>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($question['answer_text'])): ?>
                            <p><strong>A:</strong> <?php echo nl2br(htmlspecialchars($question['answer_text'])); ?></p>
                            <small class="text-muted">Answered by <strong><?php echo htmlspecialchars($question['answer_author_username']); ?><?php if ($question['answer_author_role_id'] == ROLE_ULAMA_SCHOLAR): ?> <span class="badge bg-success">Scholar</span><?php endif; ?></strong> on <?php echo date('M d, Y H:i', strtotime($question['answered_at'])); ?></small>
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
