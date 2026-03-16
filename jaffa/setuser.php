<?php
session_start();

if(isset($_POST['username'])){
    $newUser = trim($_POST['username']);

    // Only show welcome back if the user changed
    if(!isset($_SESSION['user']) || $_SESSION['user'] !== $newUser){
        $_SESSION['welcomeBack'] = $newUser; // stored for index.php to display
    }

    $_SESSION['user'] = $newUser;

    header("Location: indexint1.php"); // redirect to home page
    exit();
}

$user = $_SESSION['user'] ?? "";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Set User</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Health Matters</h1>
<h3>Enter your name</h3>

<!-- User login form -->
<form method="post">
<input type="text"
       name="username"
       placeholder="Enter your name"
       value="<?php echo htmlspecialchars($user); ?>"
       required>
<br><br>
<button type="submit">Log In</button>
</form>

</body>
</html>