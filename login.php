<?php
session_start();

include 'database-config/connection.php';

//Variables to hold messages
$message = "";
$message_type = "";

//Check if form was submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {

//Get and sanitize form data
$email = trim($_POST['email']);
$password = trim($_POST['password']);

//Basic Validation - check if fields are empty
if(empty($email) || empty($password)) {
    $message = "Please enter both email and password.";
    $message_type = "error";
} else {

//Prepare SQL to get user from USERS table by email
$sql = "SELECT user_id, name, email, password_hash FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);

if($stmt){
    //Bind the email parameter to ? in the query
    $stmt->bind_param("s", $email); // "s" means string

    //Execute the query 
    $stmt->execute();

    //Get the result
    $result = $stmt->get_result();

    //check if user exists
    if($result->num_rows == 1){
        
    //Fetch user data as an associative array
    $user = $result->fetch_assoc();

    //Verify the password entered vs the hash in database

    if(password_verify($password, $user['password_hash'])) {

    //Store user data in SESSION variables 
    //This "logs in" the user

    $_SESSION['user_id'] = $user['user_id'] ;
    $_SESSION['name'] = $user['name'] ;
    $_SESSION['email'] = $user['email'];
    $_SESSION['loggedin'] = true;

    $message = "Login successful! Redirecting...";
    $message_type = "success";

    //Redirect to dashboard/home page after 2 seconds
    header("refresh;2; url=index.php"); //change your home page 

    } else {
        //Password is wrong
        $message = "Invalid email or password.";
        $message_type = "error";

     }
    } else {
        //Email not found in database
        $message = "Invalid email or password.";
        $message_type = "error";
    }
    //Close the statement 
    $stmt->close();
} else {
    $message = "Database error. Please try again later.";
    $message_type = "error";
        }
    }  
}

//Close the database connection
$conn->close();
?>

<?php include 'shared-compoenets/ header.php'; ?>

<div class="conatiner">

<h2>User Login</h2>

<!-- Show success or error message -->
 <?php if (!empty($message)) { ?>
    <p style="color: <?php
     echo ($message_type == 'success' ? 'green' : 'red'); ?>">
     <?php echo $message; ?>

    </p>
   <?php } ?>

   <!-- Login Form --> 
    <form method="POST"
    action="login.php">
       <input type="email" name="email" placeholder="Email Address" required><br><br>
       <input type="password" name="password" placeholder="Password" required><br><br>
       <button type="submit">Login</button>

</form>

<p>Dont have an account? <a href="register.php">Register here</a></p>
</div>

<?php include 'shared-components/footer.php'; ?>
