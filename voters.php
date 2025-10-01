<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$msg = "";
$voters = [];

try {
    require 'db.php';
} catch (Exception $e) {
    $msg = "Database Error: " . $e->getMessage();
}

// Only process database operations if connection is working
if (isset($conn) && !$msg) {
    // Add voter
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_voter'])) {
        try {
            $fname = $_POST['voterFName'];
            $mname = $_POST['voterMName'] ?: null;
            $lname = $_POST['voterLName'];
            $pass = password_hash($_POST['voterPass'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO voters (voterPass,voterFName,voterMName,voterLName) VALUES (?,?,?,?)");
            $stmt->bind_param("ssss", $pass, $fname, $mname, $lname);
            $stmt->execute();
            $stmt->close();
            $msg = "Voter added.";
        } catch (Exception $e) {
            $msg = "Error adding voter: " . $e->getMessage();
        }
    }

    // Update voter
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_voter'])) {
        try {
            $id = (int)$_POST['voterID'];
            $fname = $_POST['voterFName'];
            $mname = $_POST['voterMName'] ?: null;
            $lname = $_POST['voterLName'];
            
            // Optional: update password if provided
            if (!empty($_POST['voterPass'])) {
                $pass = password_hash($_POST['voterPass'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE voters SET voterPass=?, voterFName=?, voterMName=?, voterLName=? WHERE voterID=?");
                $stmt->bind_param("ssssi", $pass, $fname, $mname, $lname, $id);
            } else {
                $stmt = $conn->prepare("UPDATE voters SET voterFName=?, voterMName=?, voterLName=? WHERE voterID=?");
                $stmt->bind_param("sssi", $fname, $mname, $lname, $id);
            }
            $stmt->execute();
            $stmt->close();
            $msg = "Voter updated.";
        } catch (Exception $e) {
            $msg = "Error updating voter: " . $e->getMessage();
        }
    }

    // Toggle status
    if (isset($_GET['toggle']) && isset($_GET['id'])) {
        try {
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("UPDATE voters SET voterStat = IF(voterStat='active','inactive','active') WHERE voterID=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            header("Location: voters.php");
            exit;
        } catch (Exception $e) {
            $msg = "Error toggling status: " . $e->getMessage();
        }
    }

    // Fetch voters
    try {
        $vRes = $conn->query("SELECT * FROM voters ORDER BY voterID DESC");
        if (!$vRes) {
            throw new Exception("Failed to fetch voters: " . $conn->error);
        }
        
        // Store voters in array for later use
        while($row = $vRes->fetch_assoc()) {
            $voters[] = $row;
        }
        
    } catch (Exception $e) {
        $msg = "Error fetching voters: " . $e->getMessage();
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Voters</title>
</head>
<body>
    <h2>Voters Management</h2>
    
    <?php if($msg): ?>
        <p style="color:green;"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>

    <h3>Add Voter</h3>
    <?php if (isset($conn) && !$msg): ?>
        <form method="post">
            <input name="voterFName" required placeholder="First name">
            <input name="voterMName" placeholder="Middle">
            <input name="voterLName" required placeholder="Last name">
            <input name="voterPass" required placeholder="Password">
            <button name="add_voter">Add</button>
        </form>
    <?php else: ?>
        <p>Database connection required to add voters.</p>
    <?php endif; ?>

    <h3>Existing Voters</h3>
<?php if (!empty($voters)): ?>
    <table border="1" cellpadding="5">
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Status</th>
            <th>Voted</th>
            <th>Actions</th>
        </tr>
        <?php foreach($voters as $row): ?>
            <tr>
                <td><?= $row['voterID'] ?></td>
                <td><?= htmlspecialchars($row['voterFName']." ".$row['voterMName']." ".$row['voterLName']) ?></td>
                <td><?= htmlspecialchars($row['voterStat']) ?></td>
                <td><?= $row['voted'] ? 'Yes' : 'No' ?></td>
                <td>
                    <form style="display:inline" method="post">
                        <input type="hidden" name="voterID" value="<?= $row['voterID'] ?>">
                        <input name="voterFName" value="<?= htmlspecialchars($row['voterFName']) ?>">
                        <input name="voterMName" value="<?= htmlspecialchars($row['voterMName']) ?>">
                        <input name="voterLName" value="<?= htmlspecialchars($row['voterLName']) ?>">
                        <input name="voterPass" placeholder="Leave blank to keep">
                        <button name="update_voter">Update</button>
                    </form>
                    <a href="?id=<?= $row['voterID'] ?>&toggle=1">
                        <?= $row['voterStat']=='active' ? 'Deactivate' : 'Activate' ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php else: ?>
    <p>No voters found.</p>
<?php endif; ?>


    <p><a href="index.php">Back</a></p>
</body>
</html>