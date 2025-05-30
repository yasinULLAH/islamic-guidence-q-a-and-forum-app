<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Login</div>
                <div class="card-body">
                    <?php
                    $flash_message = get_flash_message('login_error');
                    if ($flash_message):
                    ?>
                        <div class="alert alert-<?php echo $flash_message['type']; ?>" role="alert">
                            <?php echo $flash_message['message']; ?>
                        </div>
                    <?php endif; ?>
                    <form action="api.php?action=login" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username or Email</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Login</button>
                        <p class="mt-3">Don't have an account? <a href="?route=register">Register here</a>.</p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
