<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

$msg = "";

try {
    require 'db.php';
} catch (Exception $e) {
    $msg = "Database Error: " . $e->getMessage();
}

// Only process login if database connection is working
if (isset($conn) && !$msg && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $lname = trim($_POST['voterLName']);
        $pass = $_POST['voterPass'];

        // Find voter by last name (assuming last name is unique!)
        $stmt = $conn->prepare("SELECT voterID,voterPass,voterStat,voted FROM voters WHERE voterLName = ?");
        $stmt->bind_param("s", $lname);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$res) {
            $msg = "No voter found with that last name.";
        } elseif ($res['voterStat'] !== 'active') {
            $msg = "Voter not active.";
        } elseif (!password_verify($pass, $res['voterPass'])) {
            $msg = "Invalid password.";
        } elseif ($res['voted']) {
            $msg = "You have already voted.";
        } else {
            // Store voter ID internally for tracking votes
            $_SESSION['voterID'] = $res['voterID'];
            header("Location: vote.php");
            exit;
        }
    } catch (Exception $e) {
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
    
    <?php if($msg): ?>
        <p style="color:red;"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>
    
    <?php if (isset($conn) && !$msg): ?>
        <form method="post">
            <label>Last Name: <input name="voterLName" required></label><br>
            <label>Password: <input name="voterPass" type="password" required></label><br>
            <button>Login</button>
        </form>
    <?php else: ?>
        <p>Database connection required to login.</p>
    <?php endif; ?>
    
    <p><a href="index.php">Back</a></p>
</body>
</html>
