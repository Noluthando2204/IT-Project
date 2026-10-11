
<?php

session_start();

require_once 'database-config/connection.php';

// Only authenticated users may react.
if (
    empty($_SESSION['loggedin']) ||
    empty($_SESSION['user_id'])
) {
    header('Location: login.php');
    exit;
}

// Reactions must be submitted using POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method.');
}

// Validate the post ID.
$post_id = filter_var(
    $_POST['post_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

// Read the reaction type.
$reaction_type = $_POST['reaction_type'] ?? '';

if ($post_id === false || $post_id === null) {
    http_response_code(400);
    exit('A valid post ID is required.');
}

// These values must match the REACTIONS table ENUM.
$allowed_reactions = [
    'thumbs_up',
    'clap',
    'lightbulb'
];

if (
    !is_string($reaction_type) ||
    !in_array($reaction_type, $allowed_reactions, true)
) {
    http_response_code(400);
    exit('Invalid reaction type.');
}

$user_id = $_SESSION['user_id'];

try {

    // Confirm that the post exists.
    $post_sql = "
        SELECT PostID
        FROM POSTS
        WHERE PostID = ?
    ";

    $post_stmt = $conn->prepare($post_sql);
    $post_stmt->execute([$post_id]);

    if (!$post_stmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        exit('The requested post could not be found.');
    }

    // Check whether this user has already reacted to this post.
    $check_sql = "
        SELECT ReactionID
        FROM REACTIONS
        WHERE PostID = ?
          AND UserID = ?
    ";

    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([$post_id, $user_id]);

    $existing_reaction = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_reaction) {

        // Update the existing reaction instead of creating a duplicate.
        $update_sql = "
            UPDATE REACTIONS
            SET ReactionType = ?,
                ReactionDate = CURRENT_TIMESTAMP
            WHERE ReactionID = ?
              AND UserID = ?
        ";

        $update_stmt = $conn->prepare($update_sql);

        $update_stmt->execute([
            $reaction_type,
            $existing_reaction['ReactionID'],
            $user_id
        ]);

    } else {

        // Insert a new reaction.
        $insert_sql = "
            INSERT INTO REACTIONS
                (PostID, UserID, ReactionType, ReactionDate)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ";

        $insert_stmt = $conn->prepare($insert_sql);

        $insert_stmt->execute([
            $post_id,
            $user_id,
            $reaction_type
        ]);
    }

    // Preserve the existing EventNest destination.
    header('Location: post.php');
    exit;

} catch (PDOException $e) {

    // Record technical details privately for debugging.
    error_log('EventNest reaction error: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to save your reaction. Please try again.');
}

?>

