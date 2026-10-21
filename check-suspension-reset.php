
<?php

// Start the session
session_start();

// Connect to the database
include 'database-config/connection.php';

// Find users whose 30-day suspension has expired
$sql = "
    SELECT UserID, Suspension_count
    FROM users
    WHERE UserStatus = 'suspended'
    AND Suspension_start_date IS NOT NULL
    AND Suspension_start_date <= DATE_SUB(NOW(), INTERVAL 30 DAY)
";

$stmt = $conn->prepare($sql);
$stmt->execute();

$suspended_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process each expired suspension
foreach ($suspended_users as $user) {

    // If the user has reached 2 suspensions,
    // permanently ban the account.
    if ($user['Suspension_count'] >= 2) {

        $update_sql = "
            UPDATE users
            SET UserStatus = 'banned'
            WHERE UserID = ?
        ";

        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([
            $user['UserID']
        ]);

    } else {

        // Otherwise, the 30-day suspension has expired,
        // so restore the user's account.
        $update_sql = "
            UPDATE users
            SET UserStatus = 'active',
                Suspension_start_date = NULL
            WHERE UserID = ?
        ";

        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([
            $user['UserID']
        ]);
    }
}

?>