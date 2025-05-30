<div class="container mt-5">
    <h1>Create New Guide</h1>
    <p>Use this form to create a new step-by-step Islamic guide.</p>

    <?php
    $flash_message = get_flash_message('guide_status');
    if ($flash_message):
    ?>
        <div class="alert alert-<?php echo $flash_message['type']; ?>" role="alert">
            <?php echo $flash_message['message']; ?>
        </div>
    <?php endif; ?>

    <form id="createGuideForm" action="api.php?action=create_guide" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="card mb-3">
            <div class="card-header">Guide Details</div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="guide_title" class="form-label">Guide Title</label>
                    <input type="text" class="form-control" id="guide_title" name="guide_title" required>
                </div>
                <div class="mb-3">
                    <label for="guide_description" class="form-label">Guide Description</label>
                    <textarea class="form-control" id="guide_description" name="guide_description" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label for="guide_category" class="form-label">Category</label>
                    <select class="form-select" id="guide_category" name="guide_category" required>
                        <option value="">Select a Category</option>
                        <?php
                        $pdo = get_db_connection();
                        $stmt = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
                        $categories = $stmt->fetchAll();
                        foreach ($categories as $category):
                        ?>
                            <option value="<?php echo htmlspecialchars($category['category_id']); ?>"><?php echo htmlspecialchars($category['category_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="guide_difficulty" class="form-label">Difficulty</label>
                    <select class="form-select" id="guide_difficulty" name="guide_difficulty">
                        <option value="Beginner">Beginner</option>
                        <option value="Intermediate">Intermediate</option>
                        <option value="Advanced">Advanced</option>
                    </select>
                </div>
            </div>
        </div>

        <h2 class="mt-4">Guide Steps</h2>
        <div id="guideStepsContainer">
            <!-- Step templates will be added here by JavaScript -->
        </div>
        <button type="button" class="btn btn-secondary mt-3" id="addStepBtn">Add Step</button>

        <button type="submit" class="btn btn-success mt-4">Create Guide</button>
    </form>
</div>

<!-- JavaScript for dynamic step builder -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const addStepBtn = document.getElementById('addStepBtn');
    const guideStepsContainer = document.getElementById('guideStepsContainer');
    let stepCount = 0;

    function addStep() {
        stepCount++;
        const stepHtml = `
            <div class="card mb-3 step-card" data-step-number="${stepCount}">
                <div class="card-header">
                    Step ${stepCount}
                    <button type="button" class="btn btn-danger btn-sm float-end remove-step-btn">Remove</button>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="step_title_${stepCount}" class="form-label">Step Title</label>
                        <input type="text" class="form-control" id="step_title_${stepCount}" name="steps[${stepCount}][title]">
                    </div>
                    <div class="mb-3">
                        <label for="step_content_${stepCount}" class="form-label">Step Content</label>
                        <textarea class="form-control" id="step_content_${stepCount}" name="steps[${stepCount}][content]" rows="4"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="step_image_${stepCount}" class="form-label">Image (Infographic Style)</label>
                        <input type="file" class="form-control" id="step_image_${stepCount}" name="steps[${stepCount}][image]" accept="image/*">
                    </div>
                    <div class="mb-3">
                        <label for="step_audio_${stepCount}" class="form-label">Audio Recitation</label>
                        <input type="file" class="form-control" id="step_audio_${stepCount}" name="steps[${stepCount}][audio]" accept="audio/*">
                    </div>
                    <div class="mb-3">
                        <label for="step_reference_${stepCount}" class="form-label">Quran/Hadith Reference</label>
                        <input type="text" class="form-control" id="step_reference_${stepCount}" name="steps[${stepCount}][reference]" placeholder="e.g., Quran 2:183, Sahih Bukhari 1:1:1">
                    </div>
                </div>
            </div>
        `;
        guideStepsContainer.insertAdjacentHTML('beforeend', stepHtml);
        updateStepNumbers();
    }

    function updateStepNumbers() {
        const stepCards = guideStepsContainer.querySelectorAll('.step-card');
        stepCards.forEach((card, index) => {
            const newStepNumber = index + 1;
            card.dataset.stepNumber = newStepNumber;
            card.querySelector('.card-header').childNodes[0].nodeValue = `Step ${newStepNumber} `;
            // Update input names and IDs
            card.querySelectorAll('[name^="steps["]').forEach(input => {
                const oldName = input.name;
                const newName = oldName.replace(/steps\[\d+\]/, `steps[${newStepNumber}]`);
                input.name = newName;
            });
            card.querySelectorAll('[id^="step_"]').forEach(input => {
                const oldId = input.id;
                const newId = oldId.replace(/step_\w+_\d+/, `step_${input.id.split('_')[1]}_${newStepNumber}`);
                input.id = newId;
                if (input.previousElementSibling && input.previousElementSibling.tagName === 'LABEL') {
                    input.previousElementSibling.setAttribute('for', newId);
                }
            });
        });
        stepCount = stepCards.length; // Ensure stepCount is accurate after removal
    }

    addStepBtn.addEventListener('click', addStep);

    guideStepsContainer.addEventListener('click', function(event) {
        if (event.target.classList.contains('remove-step-btn')) {
            event.target.closest('.step-card').remove();
            updateStepNumbers();
        }
    });

    // Add initial step when page loads
    addStep();
});
</script>
