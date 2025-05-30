<?php
require_once 'config.php';
require_once 'functions.php';

echo "Starting sample data generation...\n";

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // --- 1. Add Sample Users ---
    echo "Adding sample users...\n";
    $user_ids = [];
    $roles = [ROLE_REGISTERED_USER, ROLE_ULAMA_SCHOLAR, ROLE_ADMIN];
    for ($i = 1; $i <= 20; $i++) {
        $username = "user" . $i;
        $email = "user" . $i . "@example.com";
        $password = "password123"; // Hashed below
        $role_id = $roles[($i - 1) % count($roles)]; // Cycle through roles

        $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (username, email, password_hash, role_id) VALUES (:username, :email, :password_hash, :role_id)");
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => hash_password($password),
            ':role_id' => $role_id
        ]);
        if ($stmt->rowCount() > 0) {
            $user_ids[] = $pdo->lastInsertId();
            echo "Added user: " . $username . "\n";
        } else {
            // User already exists, fetch their ID
            $stmt_fetch = $pdo->prepare("SELECT user_id FROM users WHERE username = :username");
            $stmt_fetch->execute([':username' => $username]);
            $user_ids[] = $stmt_fetch->fetchColumn();
            echo "User already exists: " . $username . "\n";
        }
    }

    // --- 2. Add Sample Categories ---
    echo "Adding sample categories...\n";
    $category_ids = [];
    for ($i = 1; $i <= 5; $i++) { // Create 5 categories
        $category_name = "Category " . $i;
        $description = "Description for Category " . $i . ".";
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO categories (category_name, description) VALUES (:category_name, :description)");
        $stmt->execute([
            ':category_name' => $category_name,
            ':description' => $description
        ]);
        if ($stmt->rowCount() > 0) {
            $category_ids[] = $pdo->lastInsertId();
            echo "Added category: " . $category_name . "\n";
        } else {
            $stmt_fetch = $pdo->prepare("SELECT category_id FROM categories WHERE category_name = :category_name");
            $stmt_fetch->execute([':category_name' => $category_name]);
            $category_ids[] = $stmt_fetch->fetchColumn();
            echo "Category already exists: " . $category_name . "\n";
        }
    }

    // --- 3. Add Sample Guides ---
    echo "Adding sample guides...\n";
    $guide_ids = [];
    $difficulties = ['Beginner', 'Intermediate', 'Advanced'];
    $scholar_users = array_filter($user_ids, function($id) use ($pdo) {
        $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $id]);
        return $stmt->fetchColumn() == ROLE_ULAMA_SCHOLAR;
    });
    if (empty($scholar_users)) {
        echo "No scholar users found. Cannot create guides.\n";
    } else {
        for ($i = 1; $i <= 20; $i++) {
            $title = "Guide Title " . $i;
            $description = "This is a detailed description for Guide " . $i . ", covering various aspects of Islamic knowledge.";
            $category_id = $category_ids[($i - 1) % count($category_ids)];
            $difficulty = $difficulties[($i - 1) % count($difficulties)];
            $created_by = $scholar_users[array_rand($scholar_users)]; // Random scholar

            $stmt = $pdo->prepare("INSERT INTO guides (title, description, category_id, difficulty, created_by) VALUES (:title, :description, :category_id, :difficulty, :created_by)");
            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':category_id' => $category_id,
                ':difficulty' => $difficulty,
                ':created_by' => $created_by
            ]);
            $guide_id = $pdo->lastInsertId();
            $guide_ids[] = $guide_id;
            echo "Added guide: " . $title . "\n";

            // Add 3-5 steps per guide
            $num_steps = rand(3, 5);
            for ($s = 1; $s <= $num_steps; $s++) {
                $step_title = "Step " . $s . " of Guide " . $i;
                $step_content = "Content for step " . $s . ". This explains the details of this particular stage of the guide.";
                $image_url = "uploads/placeholder_image_" . rand(1, 3) . ".jpg"; // Placeholder images
                $audio_url = "uploads/placeholder_audio_" . rand(1, 2) . ".mp3"; // Placeholder audio

                $stmt_step = $pdo->prepare("INSERT INTO guide_steps (guide_id, step_number, title, content, image_url, audio_url) VALUES (:guide_id, :step_number, :title, :content, :image_url, :audio_url)");
                $stmt_step->execute([
                    ':guide_id' => $guide_id,
                    ':step_number' => $s,
                    ':title' => $step_title,
                    ':content' => $step_content,
                    ':image_url' => $image_url,
                    ':audio_url' => $audio_url
                ]);
                $step_id = $pdo->lastInsertId();

                // Add a reference for some steps
                if (rand(0, 1)) { // 50% chance to add a reference
                    $reference_text = "Quran 2:" . rand(1, 286) . ", Sahih Bukhari " . rand(1, 9) . ":" . rand(1, 100);
                    $stmt_ref = $pdo->prepare("INSERT INTO content_references (guide_id, step_id, source, reference_text) VALUES (:guide_id, :step_id, :source, :reference_text)");
                    $stmt_ref->execute([
                        ':guide_id' => $guide_id,
                        ':step_id' => $step_id,
                        ':source' => 'Sample Data',
                        ':reference_text' => $reference_text
                    ]);
                }
            }
        }
    }

    // --- 4. Add Sample Comments ---
    echo "Adding sample comments...\n";
    foreach ($guide_ids as $g_id) {
        $num_comments = rand(0, 3);
        for ($c = 0; $c < $num_comments; $c++) {
            $user_id = $user_ids[array_rand($user_ids)];
            $comment_text = "This is a sample comment for guide " . $g_id . " by user " . $user_id . ".";
            $stmt = $pdo->prepare("INSERT INTO comments (guide_id, user_id, comment_text) VALUES (:guide_id, :user_id, :comment_text)");
            $stmt->execute([
                ':guide_id' => $g_id,
                ':user_id' => $user_id,
                ':comment_text' => $comment_text
            ]);
        }
    }

    // --- 5. Add Sample Favorites ---
    echo "Adding sample favorites...\n";
    foreach ($user_ids as $u_id) {
        $num_favorites = rand(0, 5);
        if ($num_favorites > 0 && !empty($guide_ids)) {
            // Ensure $num_favorites does not exceed the number of available guides
            $actual_num_favorites = min($num_favorites, count($guide_ids));
            $random_keys = (array) array_rand($guide_ids, $actual_num_favorites);
            // If array_rand returns a single key, it's not an array, so ensure it is
            if (!is_array($random_keys)) {
                $random_keys = [$random_keys];
            }
            foreach ($random_keys as $key) {
                $g_id = $guide_ids[$key];
                $stmt = $pdo->prepare("INSERT OR IGNORE INTO favorites (user_id, guide_id) VALUES (:user_id, :guide_id)");
                $stmt->execute([
                    ':user_id' => $u_id,
                    ':guide_id' => $g_id
                ]);
            }
        }
    }

    // --- 6. Add Sample Ratings ---
    echo "Adding sample ratings...\n";
    foreach ($user_ids as $u_id) {
        $num_ratings = rand(0, 5);
        if ($num_ratings > 0 && !empty($guide_ids)) {
            // Ensure $num_ratings does not exceed the number of available guides
            $actual_num_ratings = min($num_ratings, count($guide_ids));
            $random_keys = (array) array_rand($guide_ids, $actual_num_ratings);
            // If array_rand returns a single key, it's not an array, so ensure it is
            if (!is_array($random_keys)) {
                $random_keys = [$random_keys];
            }
            foreach ($random_keys as $key) {
                $g_id = $guide_ids[$key];
                $rating = rand(1, 5);
                $stmt = $pdo->prepare("INSERT OR IGNORE INTO ratings (user_id, guide_id, rating) VALUES (:user_id, :guide_id, :rating)");
                $stmt->execute([
                    ':user_id' => $u_id,
                    ':guide_id' => $g_id,
                    ':rating' => $rating
                ]);
            }
        }
    }

    // --- 7. Add Sample Q&A ---
    echo "Adding sample Q&A...\n";
    $scholar_users_qa = array_filter($user_ids, function($id) use ($pdo) {
        $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $id]);
        return $stmt->fetchColumn() == ROLE_ULAMA_SCHOLAR;
    });
    for ($i = 1; $i <= 10; $i++) { // 10 Q&A entries
        $question_author_id = $user_ids[array_rand($user_ids)];
        $question_text = "What is the ruling on X in Islam? (Question " . $i . ")";
        $answer_text = null;
        $answered_by = null;
        $answered_at = null;

        if (rand(0, 1) && !empty($scholar_users_qa)) { // 50% chance to be answered
            $answer_text = "The ruling on X is Y, based on evidence from the Quran and Sunnah. (Answer " . $i . ")";
            $answered_by = $scholar_users_qa[array_rand($scholar_users_qa)];
            $answered_at = date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days'));
        }

        $stmt = $pdo->prepare("INSERT INTO q_and_a (user_id, question_text, answer_text, answered_by, answered_at) VALUES (:user_id, :question_text, :answer_text, :answered_by, :answered_at)");
        $stmt->execute([
            ':user_id' => $question_author_id,
            ':question_text' => $question_text,
            ':answer_text' => $answer_text,
            ':answered_by' => $answered_by,
            ':answered_at' => $answered_at
        ]);
    }

    // --- 8. Add Sample Forum Topics and Posts ---
    echo "Adding sample forum topics and posts...\n";
    $topic_ids = [];
    for ($i = 1; $i <= 10; $i++) { // 10 forum topics
        $topic_author_id = $user_ids[array_rand($user_ids)];
        $title = "Discussion Topic " . $i . ": Importance of Zakat";
        $stmt = $pdo->prepare("INSERT INTO forum_topics (user_id, title) VALUES (:user_id, :title)");
        $stmt->execute([
            ':user_id' => $topic_author_id,
            ':title' => $title
        ]);
        $topic_id = $pdo->lastInsertId();
        $topic_ids[] = $topic_id;
        echo "Added topic: " . $title . "\n";

        // Add initial post
        $first_post_content = "Assalamu alaikum. I wanted to start a discussion on the importance of Zakat in our lives.";
        $stmt_post = $pdo->prepare("INSERT INTO forum_posts (topic_id, user_id, content) VALUES (:topic_id, :user_id, :content)");
        $stmt_post->execute([
            ':topic_id' => $topic_id,
            ':user_id' => $topic_author_id,
            ':content' => $first_post_content
        ]);
        $last_post_id = $pdo->lastInsertId();

        // Add 2-5 replies
        $num_replies = rand(2, 5);
        $current_parent_id = null; // For simple linear replies
        for ($r = 0; $r < $num_replies; $r++) {
            $reply_author_id = $user_ids[array_rand($user_ids)];
            $reply_content = "Reply " . ($r + 1) . " to topic " . $topic_id . ". This is a follow-up comment.";
            $stmt_reply = $pdo->prepare("INSERT INTO forum_posts (topic_id, user_id, content, parent_post_id) VALUES (:topic_id, :user_id, :content, :parent_post_id)");
            $stmt_reply->execute([
                ':topic_id' => $topic_id,
                ':user_id' => $reply_author_id,
                ':content' => $reply_content,
                ':parent_post_id' => $last_post_id // Reply to the previous post
            ]);
            $last_post_id = $pdo->lastInsertId();
        }
    }

    $pdo->commit();
    echo "Sample data generation complete!\n";

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Sample data generation failed: " . $e->getMessage());
    echo "Sample data generation failed: " . $e->getMessage() . "\n";
}
?>
