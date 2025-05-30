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
                $question_author_data = $stmt_question_author->fetch(PDO::FETCH_ASSOC);
                $question_author_username = $question_author_data['username'] ?? 'Unknown';
                $question_author_id = $question_author_data['user_id'] ?? null;

                $answer_author_username = null;
                $answer_author_id = null;
                $answer_author_role_id = null;
                if (!empty($question['answered_by'])) {
                    $stmt_answer_author = $pdo->prepare("SELECT username, user_id, role_id FROM users WHERE user_id = :user_id");
                    $stmt_answer_author->bindParam(':user_id', $question['answered_by'], PDO::PARAM_INT);
                    $stmt_answer_author->execute();
                    $answer_author = $stmt_answer_author->fetch(PDO::FETCH_ASSOC);
                    if ($answer_author) {
                        $answer_author_username = $answer_author['username'];
                        $answer_author_id = $answer_author['user_id'];
                        $answer_author_role_id = $answer_author['role_id'];
                    }
                }

                $current_user_id = $_SESSION['user_id'] ?? null;
                $current_user_role_id = get_user_role_id();

                $can_edit_question = ($current_user_role_id >= ROLE_ADMIN || $current_user_id == $question_author_id);
                $can_edit_answer = ($current_user_role_id >= ROLE_ADMIN || ($current_user_role_id >= ROLE_ULAMA_SCHOLAR && $current_user_id == $answer_author_id));
                $can_delete_question = ($current_user_role_id >= ROLE_ADMIN || ($current_user_id == $question_author_id && empty($question['answer_text'])));
                $can_clear_answer = ($current_user_role_id >= ROLE_ADMIN || ($current_user_role_id >= ROLE_ULAMA_SCHOLAR && $current_user_id == $answer_author_id && !empty($question['answer_text'])));
        ?>
                <div class="card mb-3">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Q: <span id="question-text-<?php echo $question['qa_id']; ?>"><?php echo htmlspecialchars($question['question_text']); ?></span></strong>
                            <br>
                            <small class="text-muted">Asked by <?php echo htmlspecialchars($question_author_username); ?> on <?php echo date('M d, Y H:i', strtotime($question['created_at'])); ?></small>
                        </div>
                        <?php if ($can_edit_question || $can_delete_question): ?>
                            <div class="btn-group" role="group" aria-label="Question Actions">
                                <?php if ($can_edit_question): ?>
                                    <button class="btn btn-sm btn-info edit-question-btn" data-qa-id="<?php echo $question['qa_id']; ?>" data-question-text="<?php echo htmlspecialchars($question['question_text']); ?>" data-answer-text="<?php echo htmlspecialchars($question['answer_text']); ?>">Edit</button>
                                <?php endif; ?>
                                <?php if ($can_delete_question): ?>
                                    <button class="btn btn-sm btn-danger delete-question-btn" data-qa-id="<?php echo $question['qa_id']; ?>">Delete</button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($question['answer_text'])): ?>
                            <p><strong>A:</strong> <span id="answer-text-<?php echo $question['qa_id']; ?>"><?php echo nl2br(htmlspecialchars($question['answer_text'])); ?></span></p>
                            <small class="text-muted">Answered by <strong><?php echo htmlspecialchars($answer_author_username); ?><?php if ($answer_author_role_id == ROLE_ULAMA_SCHOLAR): ?> <span class="badge bg-success">Scholar</span><?php endif; ?></strong> on <?php echo date('M d, Y H:i', strtotime($question['answered_at'])); ?></small>
                            <?php if ($can_edit_answer || $can_clear_answer): ?>
                                <div class="btn-group ms-2" role="group" aria-label="Answer Actions">
                                    <?php if ($can_edit_answer): ?>
                                        <button class="btn btn-sm btn-info edit-answer-btn" data-qa-id="<?php echo $question['qa_id']; ?>" data-question-text="<?php echo htmlspecialchars($question['question_text']); ?>" data-answer-text="<?php echo htmlspecialchars($question['answer_text']); ?>">Edit Answer</button>
                                    <?php endif; ?>
                                    <?php if ($can_clear_answer): ?>
                                        <button class="btn btn-sm btn-danger clear-answer-btn" data-qa-id="<?php echo $question['qa_id']; ?>">Clear Answer</button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
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

<!-- Edit Q&A Modal -->
<div class="modal fade" id="editQaModal" tabindex="-1" aria-labelledby="editQaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editQaModalLabel">Edit Question & Answer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editQaForm" action="api.php?action=edit_q_and_a" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="qa_id" id="edit_qa_id">
                    <div class="mb-3">
                        <label for="edit_question_text" class="form-label">Question</label>
                        <textarea class="form-control" id="edit_question_text" name="question_text" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_answer_text" class="form-label">Answer (Scholar/Admin only)</label>
                        <textarea class="form-control" id="edit_answer_text" name="answer_text" rows="6"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const baseUrl = '<?php echo BASE_URL; ?>';
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    const editQaModal = new bootstrap.Modal(document.getElementById('editQaModal'));
    const editQaForm = document.getElementById('editQaForm');
    const editQaIdInput = document.getElementById('edit_qa_id');
    const editQuestionTextInput = document.getElementById('edit_question_text');
    const editAnswerTextInput = document.getElementById('edit_answer_text');

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

    // Edit Question/Answer button click handler
    document.querySelectorAll('.edit-question-btn, .edit-answer-btn').forEach(button => {
        button.addEventListener('click', function() {
            const qaId = this.dataset.qaId;
            const questionText = this.dataset.questionText;
            const answerText = this.dataset.answerText;

            editQaIdInput.value = qaId;
            editQuestionTextInput.value = questionText;
            editAnswerTextInput.value = answerText;

            editQaModal.show();
        });
    });

    // Edit Q&A Form Submission
    if (editQaForm) {
        editQaForm.addEventListener('submit', function(event) {
            event.preventDefault();

            const formData = new FormData(editQaForm);

            fetch(editQaForm.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    editQaModal.hide();
                    // Update the displayed text on the page
                    document.getElementById(`question-text-${data.qa_id}`).textContent = editQuestionTextInput.value;
                    const answerTextElement = document.getElementById(`answer-text-${data.qa_id}`);
                    if (answerTextElement) {
                        answerTextElement.innerHTML = editAnswerTextInput.value.replace(/\n/g, '<br>');
                    }
                    // Reload the page to update author/role info if needed, or dynamically update
                    window.location.reload();
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

    // Delete Question button click handler
    document.querySelectorAll('.delete-question-btn').forEach(button => {
        button.addEventListener('click', function() {
            const qaId = this.dataset.qaId;
            if (confirm('Are you sure you want to delete this question? If it has an answer, the answer will also be deleted. This action cannot be undone.')) {
                fetch(`api.php?action=delete_q_and_a`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `qa_id=${qaId}&csrf_token=${csrfToken}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        this.closest('.card').remove(); // Remove the question card from DOM
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
    });

    // Clear Answer button click handler
    document.querySelectorAll('.clear-answer-btn').forEach(button => {
        button.addEventListener('click', function() {
            const qaId = this.dataset.qaId;
            if (confirm('Are you sure you want to clear this answer? The question will remain.')) {
                fetch(`api.php?action=delete_q_and_a`, { // Re-using delete_q_and_a for clearing answer
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `qa_id=${qaId}&csrf_token=${csrfToken}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        window.location.reload(); // Reload to reflect cleared answer
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('An error occurred while clearing the answer. Please try again.');
                });
            }
        });
    });
});
</script>
