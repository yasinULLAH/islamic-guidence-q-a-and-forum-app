<?php
ob_start(); // Start output buffering to capture any redirects or errors
require_once 'config.php';
require_once 'functions.php';

// Check if this is an API request
if (isset($_GET['action']) && strpos($_SERVER['REQUEST_URI'], 'api.php') !== false) {
    require_once 'api.php';
    exit(); // Stop execution after API handles the request (and potentially redirects)
}

// Determine the current route for regular page loads
$route = $_GET['route'] ?? '';
$route = strtolower(trim($route, '/'));

// Generate CSRF token for forms (only for pages that will render HTML)
generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="<?php echo DEFAULT_LANG; ?>" dir="<?php echo (DEFAULT_LANG == 'ar') ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <!-- Google Fonts for Arabic typography (example: Noto Sans Arabic) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Arabic', sans-serif; /* Apply Arabic font */
        }
        /* RTL specific styles if needed */
        html[dir="rtl"] body {
            text-align: right;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><?php echo APP_NAME; ?></a>
            <!-- Removed toggler button and collapse for testing -->
            <!-- <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button> -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($route == '' || $route == 'home') ? 'active' : ''; ?>" aria-current="page" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($route == 'guides') ? 'active' : ''; ?>" href="index.php?route=guides">Guides</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($route == 'q_and_a') ? 'active' : ''; ?>" href="index.php?route=q_and_a">Q&A</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($route == 'prayer_times') ? 'active' : ''; ?>" href="index.php?route=prayer_times">Prayer Times</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($route == 'qibla_finder') ? 'active' : ''; ?>" href="index.php?route=qibla_finder">Qibla Finder</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($route == 'community') ? 'active' : ''; ?>" href="index.php?route=community">Community</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <?php if (is_logged_in()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="index.php?route=dashboard">Dashboard</a></li>
                                <?php if (has_role(ROLE_ULAMA_SCHOLAR)): ?>
                                    <li><a class="dropdown-item" href="index.php?route=create_guide">Create Guide</a></li>
                                <?php endif; ?>
                                <?php if (has_role(ROLE_ADMIN)): ?>
                                    <li><a class="dropdown-item" href="index.php?route=admin">Admin Panel</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="index.php?route=logout">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($route == 'login') ? 'active' : ''; ?>" href="index.php?route=login">Login</a>
                    </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($route == 'register') ? 'active' : ''; ?>" href="index.php?route=register">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main>
        <?php handle_route($route); ?>
    </main>

    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</span>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!-- Custom JS (re-add if needed for future features) -->
    <!-- <script src="js/app.js"></script> -->
</body>
</html>
