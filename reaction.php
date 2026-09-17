<?php

session_start();

include 'database-config/connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Please login to react to a post.");
}

$user_id = $_SESSION['user_id'];

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request.");
}

// Get the post ID and reaction type
$post_id = $_POST['post_id'] ?? '';
$reaction_type = $_POST['reaction_type'] ?? '';

// Make sure a post ID was provided
if (empty($post_id)) {
    die("Post ID is required.");
}

// Only allow the three predefined reactions
$allowed_reactions = ['👍', '👏', '💡'];

if (!in_array($reaction_type, $allowed_reactions, true)) {
    die("Invalid reaction type.");
}

// Check if the user has already reacted to this post
$check_sql = "
    SELECT ReactionID
    FROM reactions
    WHERE PostID = ? AND UserID = ?
";

$check_stmt = $conn->prepare($check_sql);
$check_stmt->execute([$post_id, $user_id]);

$existing_reaction = $check_stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_reaction) {

    // Update the user's existing reaction
    $update_sql = "
        UPDATE reactions
        SET ReactionType = ?, ReactionDate = NOW()
        WHERE ReactionID = ?
    ";

    $update_stmt = $conn->prepare($update_sql);

    $update_stmt->execute([
        $reaction_type,
        $existing_reaction['ReactionID']
    ]);

} else {

    // Create a new reaction
    $insert_sql = "
        INSERT INTO reactions
        (ReactionType, ReactionDate, PostID, UserID)
        VALUES (?, NOW(), ?, ?)
    ";

    $insert_stmt = $conn->prepare($insert_sql);

    $insert_stmt->execute([
        $reaction_type,
        $post_id,
        $user_id
    ]);
}

// Return to the post
header("Location: post.php");
exit;

?>
