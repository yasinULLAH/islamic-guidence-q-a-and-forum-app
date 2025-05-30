<?php
if (!is_logged_in()) {
    redirect(BASE_URL . '/?route=login');
}

$guide_id = (int)($_GET['id'] ?? 0);
$pdo = get_db_connection();
$guide = null;
$steps = [];
$references = [];

if ($guide_id > 0) {
    // Fetch guide details
    $stmt = $pdo->prepare("
        SELECT
            g.guide_id,
            g.title,
            g.description,
            g.difficulty,
            g.created_at,
            g.category_id,
            g.created_by AS author_user_id,
            u.username AS author_username
        FROM
            guides g
        JOIN
            users u ON g.created_by = u.user_id
        WHERE
            g.guide_id = :guide_id
    ");
    $stmt->execute([':guide_id' => $guide_id]);
    $guide = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($guide) {
        // Check ownership/permissions
        $user_id = $_SESSION['user_id'];
        $user_role_id = get_user_role_id();

        if ($user_role_id < ROLE_ADMIN && ($user_role_id < ROLE_ULAMA_SCHOLAR || $user_id != $guide['author_user_id'])) {
            set_flash_message('guide_status', 'Permission denied. You can only edit your own guides.', 'danger');
            redirect(BASE_URL . '/?route=guide&id=' . $guide_id);
        }

        // Fetch guide steps
        $stmt_steps = $pdo->prepare("SELECT * FROM guide_steps WHERE guide_id = :guide_id ORDER BY step_number ASC");
        $stmt_steps->execute([':guide_id' => $guide_id]);
        $steps = $stmt_steps->fetchAll(PDO::FETCH_ASSOC);

        // Fetch references
        $stmt_refs = $pdo->prepare("SELECT step_id, reference_text FROM content_references WHERE guide_id = :guide_id");
        $stmt_refs->execute([':guide_id' => $guide_id]);
        $references_raw = $stmt_refs->fetchAll(PDO::FETCH_ASSOC);
        foreach ($references_raw as $ref) {
            $references[$ref['step_id']][] = $ref['reference_text'];
        }

    } else {
        set_flash_message('guide_status', 'Guide not found.', 'danger');
        redirect(BASE_URL . '/?route=guides');
    }
} else {
    set_flash_message('guide_status', 'Invalid guide ID.', 'danger');
    redirect(BASE_URL . '/?route=guides');
}

// Fetch categories for the dropdown
$stmt_categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC");
$categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container mt-5">
    <h1>Edit Guide: <?php echo htmlspecialchars($guide['title']); ?></h1>
    <?php $flash_message = get_flash_message('guide_status'); ?>
    <?php if ($flash_message): ?>
        <div class="alert alert-<?php echo $flash_message['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $flash_message['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form id="editGuideForm" action="api.php?action=edit_guide" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="guide_id" value="<?php echo htmlspecialchars($guide['guide_id']); ?>">

        <div class="mb-3">
            <label for="guide_title" class="form-label">Guide Title</label>
            <input type="text" class="form-control" id="guide_title" name="guide_title" value="<?php echo htmlspecialchars($guide['title']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="guide_description" class="form-label">Description</label>
            <textarea class="form-control" id="guide_description" name="guide_description" rows="3" required><?php echo htmlspecialchars($guide['description']); ?></textarea>
        </div>
        <div class="mb-3">
            <label for="guide_category" class="form-label">Category</label>
            <select class="form-select" id="guide_category" name="guide_category" required>
                <option value="">Select a Category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category['category_id']); ?>" <?php echo ($guide['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['category_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="guide_difficulty" class="form-label">Difficulty</label>
            <select class="form-select" id="guide_difficulty" name="guide_difficulty" required>
                <option value="Beginner" <?php echo ($guide['difficulty'] == 'Beginner') ? 'selected' : ''; ?>>Beginner</option>
                <option value="Intermediate" <?php echo ($guide['difficulty'] == 'Intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                <option value="Advanced" <?php echo ($guide['difficulty'] == 'Advanced') ? 'selected' : ''; ?>>Advanced</option>
            </select>
        </div>

        <h3 class="mt-4">Guide Steps</h3>
        <div id="guideStepsContainer">
            <?php if (!empty($steps)): ?>
                <?php foreach ($steps as $index => $step): ?>
                    <div class="card mb-3 step-item" data-step-number="<?php echo $index; ?>">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            Step <?php echo $index + 1; ?>
                            <button type="button" class="btn btn-danger btn-sm remove-step-btn">Remove Step</button>
                        </div>
                        <div class="card-body">
                            <input type="hidden" name="steps[<?php echo $index; ?>][step_number]" value="<?php echo $index + 1; ?>">
                            <div class="mb-3">
                                <label for="step_title_<?php echo $index; ?>" class="form-label">Step Title</label>
                                <input type="text" class="form-control" id="step_title_<?php echo $index; ?>" name="steps[<?php echo $index; ?>][title]" value="<?php echo htmlspecialchars($step['title']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="step_content_<?php echo $index; ?>" class="form-label">Step Content</label>
                                <textarea class="form-control" id="step_content_<?php echo $index; ?>" name="steps[<?php echo $index; ?>][content]" rows="4" required><?php echo htmlspecialchars($step['content']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="step_image_<?php echo $index; ?>" class="form-label">Image (Optional)</label>
                                <?php if (!empty($step['image_url'])): ?>
                                    <div class="existing-file-container mb-2">
                                        <img src="<?php echo BASE_URL . '/' . htmlspecialchars($step['image_url']); ?>" alt="Current Image" class="img-thumbnail" style="max-width: 150px;">
                                        <input type="hidden" name="steps[<?php echo $index; ?>][existing_image_url]" value="<?php echo htmlspecialchars($step['image_url']); ?>">
                                        <div class="form-check">
                                            <input class="form-check-input delete-image-checkbox" type="checkbox" id="delete_image_<?php echo $index; ?>" data-image-url="<?php echo htmlspecialchars($step['image_url']); ?>">
                                            <label class="form-check-label" for="delete_image_<?php echo $index; ?>">Delete current image</label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="step_image_<?php echo $index; ?>" name="steps[<?php echo $index; ?>][image]" accept="image/*">
                            </div>

                            <div class="mb-3">
                                <label for="step_audio_<?php echo $index; ?>" class="form-label">Audio (Optional)</label>
                                <?php if (!empty($step['audio_url'])): ?>
                                    <div class="existing-file-container mb-2">
                                        <audio controls>
                                            <source src="<?php echo BASE_URL . '/' . htmlspecialchars($step['audio_url']); ?>" type="audio/mpeg">
                                            Your browser does not support the audio element.
                                        </audio>
                                        <input type="hidden" name="steps[<?php echo $index; ?>][existing_audio_url]" value="<?php echo htmlspecialchars($step['audio_url']); ?>">
                                        <div class="form-check">
                                            <input class="form-check-input delete-audio-checkbox" type="checkbox" id="delete_audio_<?php echo $index; ?>" data-audio-url="<?php echo htmlspecialchars($step['audio_url']); ?>">
                                            <label class="form-check-label" for="delete_audio_<?php echo $index; ?>">Delete current audio</label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="step_audio_<?php echo $index; ?>" name="steps[<?php echo $index; ?>][audio]" accept="audio/*">
                            </div>

                            <div class="mb-3">
                                <label for="step_reference_<?php echo $index; ?>" class="form-label">Reference (Optional)</label>
                                <textarea class="form-control" id="step_reference_<?php echo $index; ?>" name="steps[<?php echo $index; ?>][reference]" rows="2"><?php echo htmlspecialchars(implode("\n", $references[$step['step_id']] ?? [])); ?></textarea>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No steps found for this guide. Please add at least one step.</p>
            <?php endif; ?>
        </div>

        <button type="button" class="btn btn-secondary mb-4" id="addStepBtn">Add Step</button>

        <button type="submit" class="btn btn-primary">Update Guide</button>
        <a href="<?php echo BASE_URL; ?>/?route=guide&id=<?php echo htmlspecialchars($guide['guide_id']); ?>" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div>

<script>
    const baseUrl = "<?php echo BASE_URL; ?>";
document.addEventListener('DOMContentLoaded', function() {
    const guideStepsContainer = document.getElementById('guideStepsContainer');
    const addStepBtn = document.getElementById('addStepBtn');
    let stepCounter = guideStepsContainer.children.length; // Initialize with existing steps count

    const deletedImages = [];
    const deletedAudio = [];

    // Function to update step numbers and input names
    function updateStepNumbers() {
        document.querySelectorAll('.step-item').forEach((stepItem, index) => {
            stepItem.querySelector('.card-header').textContent = `Step ${index + 1}`;
            stepItem.dataset.stepNumber = index; // Update data attribute

            // Update input names and IDs
            stepItem.querySelectorAll('[name^="steps["]').forEach(input => {
                const oldName = input.name;
                const newName = oldName.replace(/steps\[\d+\]/, `steps[${index}]`);
                input.name = newName;
                input.id = input.id.replace(/_\d+/, `_${index}`);
            });

            // Update labels' for attributes
            stepItem.querySelectorAll('label[for^="step_"]').forEach(label => {
                const oldFor = label.htmlFor;
                const newFor = oldFor.replace(/_\d+/, `_${index}`);
                label.htmlFor = newFor;
            });

            // Update checkbox IDs and data attributes
            stepItem.querySelectorAll('.delete-image-checkbox, .delete-audio-checkbox').forEach(checkbox => {
                const oldId = checkbox.id;
                const newId = oldId.replace(/_\d+/, `_${index}`);
                checkbox.id = newId;
            });
        });
    }

    // Add Step button click handler
    addStepBtn.addEventListener('click', function() {
        const newStepHtml = `
            <div class="card mb-3 step-item" data-step-number="${stepCounter}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    Step ${stepCounter + 1}
                    <button type="button" class="btn btn-danger btn-sm remove-step-btn">Remove Step</button>
                </div>
                <div class="card-body">
                    <input type="hidden" name="steps[${stepCounter}][step_number]" value="${stepCounter + 1}">
                    <div class="mb-3">
                        <label for="step_title_${stepCounter}" class="form-label">Step Title</label>
                        <input type="text" class="form-control" id="step_title_${stepCounter}" name="steps[${stepCounter}][title]" required>
                    </div>
                    <div class="mb-3">
                        <label for="step_content_${stepCounter}" class="form-label">Step Content</label>
                        <textarea class="form-control" id="step_content_${stepCounter}" name="steps[${stepCounter}][content]" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="step_image_${stepCounter}" class="form-label">Image (Optional)</label>
                        <input type="file" class="form-control" id="step_image_${stepCounter}" name="steps[${stepCounter}][image]" accept="image/*">
                    </div>
                    <div class="mb-3">
                        <label for="step_audio_${stepCounter}" class="form-label">Audio (Optional)</label>
                        <input type="file" class="form-control" id="step_audio_${stepCounter}" name="steps[${stepCounter}][audio]" accept="audio/*">
                    </div>
                    <div class="mb-3">
                        <label for="step_reference_${stepCounter}" class="form-label">Reference (Optional)</label>
                        <textarea class="form-control" id="step_reference_${stepCounter}" name="steps[${stepCounter}][reference]" rows="2"></textarea>
                    </div>
                </div>
            </div>
        `;
        guideStepsContainer.insertAdjacentHTML('beforeend', newStepHtml);
        stepCounter++;
        updateStepNumbers(); // Re-index all steps after adding
    });

    // Remove Step button click handler (using event delegation)
    guideStepsContainer.addEventListener('click', function(event) {
        if (event.target.classList.contains('remove-step-btn')) {
            if (guideStepsContainer.children.length > 1) { // Ensure at least one step remains
                event.target.closest('.step-item').remove();
                updateStepNumbers(); // Re-index all steps after removing
            } else {
                alert('A guide must have at least one step.');
            }
        }
    });

    // Handle delete image/audio checkboxes
    guideStepsContainer.addEventListener('change', function(event) {
        if (event.target.classList.contains('delete-image-checkbox')) {
            const imageUrl = event.target.dataset.imageUrl;
            if (event.target.checked) {
                deletedImages.push(imageUrl);
            } else {
                const index = deletedImages.indexOf(imageUrl);
                if (index > -1) {
                    deletedImages.splice(index, 1);
                }
            }
        } else if (event.target.classList.contains('delete-audio-checkbox')) {
            const audioUrl = event.target.dataset.audioUrl;
            if (event.target.checked) {
                deletedAudio.push(audioUrl);
            } else {
                const index = deletedAudio.indexOf(audioUrl);
                if (index > -1) {
                    deletedAudio.splice(index, 1);
                }
            }
        }
    });

    // Form submission handler
    const editGuideForm = document.getElementById('editGuideForm');
    if (editGuideForm) {
        editGuideForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent default form submission

            const formData = new FormData(editGuideForm);

            // Append deleted files arrays
            formData.append('deleted_images', JSON.stringify(deletedImages));
            formData.append('deleted_audio', JSON.stringify(deletedAudio));

            fetch(editGuideForm.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    window.location.href = `${baseUrl}/?route=guide&id=${data.guide_id}`;
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
});
</script>
