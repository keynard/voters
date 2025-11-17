
<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session to store voter login state
session_start();

$msg = ""; // Message for errors or status

try {
    // Include database connection
    require 'db.php';
} catch (Exception $e) {
    // Set error message if DB connection fails
    $msg = "Database Error: " . $e->getMessage();
}

// Only process login if database connection is working and form is submitted
if (isset($conn) && !$msg && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get voterID and password from form
        $voterID = trim($_POST['voterID']);
        $pass = $_POST['voterPass'];

        // Find voter by voterID
        $stmt = $conn->prepare("SELECT voterID, voterPass, voterStat, voted FROM voters WHERE voterID = ?");
        $stmt->bind_param("i", $voterID); // "i" for integer
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Handle login logic and errors
        if (!$res) {
            $msg = "No voter found with that ID.";
        } elseif ($res['voterStat'] !== 'active') {
            $msg = "Voter not active.";
        } elseif (!password_verify($pass, $res['voterPass'])) {
            $msg = "Invalid password.";
        } elseif ($res['voted']) {
            $msg = "You have already voted.";
        } else {
            // Store voter ID in session and redirect to voting page
            $_SESSION['voterID'] = $res['voterID'];
            header("Location: vote.php");
            exit;
        }
    } catch (Exception $e) {
        // Catch and display any login errors
        $msg = "Login Error: " . $e->getMessage();
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Voter Login</title>
</head>
<body>
    <h2>Voter Login</h2>
    
    <!-- Display error or status message if any -->
    <?php if($msg): ?>
        <p style="color:red;"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>
    
    <!-- Show login form if DB connection is OK and no error message -->
    <?php if (isset($conn) && !$msg): ?>
        <form method="post">
            <!-- Voter ID input field -->
            <label>Voter ID: <input name="voterID" type="number" required></label><br>
            <!-- Password input field -->
            <label>Password: <input name="voterPass" type="password" required></label><br>
            <button type="submit">Login</button>
        </form>
    <?php else: ?>
        <p>Database connection required to login.</p>
    <?php endif; ?>
    
    <p><a href="index.php">Back</a></p>
</body>
</html>