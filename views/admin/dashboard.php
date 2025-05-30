<div class="container mt-5">
    <h1>Admin Dashboard</h1>
    <p>Welcome, Admin! This is the administration panel.</p>

    <h2 class="mt-4">Manage Categories</h2>
    <?php
    $flash_message = get_flash_message('category_status');
    if ($flash_message):
    ?>
        <div class="alert alert-<?php echo $flash_message['type']; ?>" role="alert">
            <?php echo $flash_message['message']; ?>
        </div>
    <?php endif; ?>
    <div class="card mb-4">
        <div class="card-header">Add New Category</div>
        <div class="card-body">
            <form action="api.php?action=add_category" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="mb-3">
                    <label for="category_name" class="form-label">Category Name</label>
                    <input type="text" class="form-control" id="category_name" name="category_name" required>
                </div>
                <div class="mb-3">
                    <label for="category_description" class="form-label">Description</label>
                    <textarea class="form-control" id="category_description" name="category_description" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Add Category</button>
            </form>
        </div>
    </div>

    <h3 class="mt-4">Existing Categories</h3>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $pdo = get_db_connection();
            $stmt = $pdo->query("SELECT * FROM categories ORDER BY category_name");
            $categories = $stmt->fetchAll();
            if ($categories):
                foreach ($categories as $category):
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($category['category_id']); ?></td>
                    <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                    <td><?php echo htmlspecialchars($category['description']); ?></td>
                    <td>
                        <!-- Add edit/delete buttons later -->
                        <button class="btn btn-sm btn-warning" disabled>Edit</button>
                        <button class="btn btn-sm btn-danger" disabled>Delete</button>
                    </td>
                </tr>
            <?php
                endforeach;
            else:
            ?>
                <tr>
                    <td colspan="4">No categories found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
