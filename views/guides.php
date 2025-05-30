<div class="container mt-5">
    <h1 class="mb-4">All Guides</h1>

    <!-- Search and Filter Section -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Search guides by title or description..." id="guideSearchInput">
                <button class="btn btn-outline-secondary" type="button" id="guideSearchBtn">Search</button>
            </div>
        </div>
        <div class="col-md-4">
            <select class="form-select" id="categoryFilter">
                <option value="">Filter by Category</option>
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
    </div>

    <div class="row" id="guidesListContainer">
        <?php
        // Pagination settings
        $items_per_page = 9; // Number of guides per page
        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($current_page - 1) * $items_per_page;

        // Get total number of guides and paginated guides
        $total_guides = get_total_guides_count();
        $guides = get_paginated_guides($items_per_page, $offset);
        $total_pages = ceil($total_guides / $items_per_page);

        if ($guides):
            foreach ($guides as $guide):
        ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($guide['title']); ?></h5>
                        <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($guide['category_name']); ?> - <?php echo htmlspecialchars($guide['difficulty']); ?></h6>
                        <p class="card-text"><?php echo htmlspecialchars(substr($guide['description'], 0, 100)); ?>...</p>
                        <p class="card-text"><small class="text-muted">By <?php echo htmlspecialchars($guide['author_username']); ?> on <?php echo date('M d, Y', strtotime($guide['created_at'])); ?></small></p>
                        <a href="<?php echo BASE_URL; ?>/?route=guide&id=<?php echo htmlspecialchars($guide['guide_id']); ?>" class="btn btn-primary">Read Guide</a>
                    </div>
                </div>
            </div>
        <?php
            endforeach;
        else:
        ?>
            <div class="col-12">
                <div class="alert alert-info" role="alert">
                    No guides available yet. Be the first to <a href="<?php echo BASE_URL; ?>/?route=create_guide">create one</a>!
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination Links -->
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?route=guides&page=<?php echo $current_page - 1; ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?route=guides&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?route=guides&page=<?php echo $current_page + 1; ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
</div>

<!-- JavaScript for search and filter -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const guideSearchInput = document.getElementById('guideSearchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const guidesListContainer = document.getElementById('guidesListContainer');

    function fetchGuides() {
        const searchTerm = guideSearchInput.value;
        const categoryId = categoryFilter.value;
        const currentPage = <?php echo $current_page; ?>; // Pass current page to JS

        const params = new URLSearchParams();
        if (searchTerm) {
            params.append('search', searchTerm);
        }
        if (categoryId) {
            params.append('category_id', categoryId);
        }
        params.append('page', currentPage); // Add current page to AJAX request

        fetch(`api.php?action=search_guides&${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                guidesListContainer.innerHTML = ''; // Clear current guides
                if (data.status === 'success' && data.guides.length > 0) {
                    data.guides.forEach(guide => {
                        const guideCard = `
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">${guide.title}</h5>
                                        <h6 class="card-subtitle mb-2 text-muted">${guide.category_name} - ${guide.difficulty}</h6>
                                        <p class="card-text">${guide.description.substring(0, 100)}...</p>
                                        <p class="card-text"><small class="text-muted">By ${guide.author_username} on ${new Date(guide.created_at).toLocaleDateString()}</small></p>
                                        <a href="${'<?php echo BASE_URL; ?>'}/?route=guide&id=${guide.guide_id}" class="btn btn-primary">Read Guide</a>
                                    </div>
                                </div>
                            </div>
                        `;
                        guidesListContainer.insertAdjacentHTML('beforeend', guideCard);
                    });
                } else {
                    guidesListContainer.innerHTML = `
                        <div class="col-12">
                            <div class="alert alert-info" role="alert">
                                No guides found matching your criteria.
                            </div>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error fetching guides:', error);
                guidesListContainer.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger" role="alert">
                            Error loading guides. Please try again.
                        </div>
                    </div>
                `;
            });
    }

    // Event listeners for search and filter
    guideSearchInput.addEventListener('input', fetchGuides); // Real-time search
    categoryFilter.addEventListener('change', fetchGuides);

    // Initial fetch (optional, as PHP already loads initially)
    // fetchGuides();
});
</script>
