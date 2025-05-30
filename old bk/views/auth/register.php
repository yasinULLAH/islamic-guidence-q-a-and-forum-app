<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Register</div>
                <div class="card-body">
                    <?php
                    $flash_message = get_flash_message('register_error');
                    if ($flash_message):
                    ?>
                        <div class="alert alert-<?php echo $flash_message['type']; ?>" role="alert">
                            <?php echo $flash_message['message']; ?>
                        </div>
                    <?php endif; ?>
                    <form action="api.php?action=register" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Register</button>
                        <p class="mt-3">Already have an account? <a href="?route=login">Login here</a>.</p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
