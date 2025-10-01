<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$msg = "";
$posRes = null;
$cRes = null;
$positions = [];
$candidates = [];

try {
    require 'db.php';
    
    // Fetch positions for dropdown
    $posRes = $conn->query("SELECT posID, posName FROM positions WHERE posStatus='active'");
    
    if (!$posRes) {
        throw new Exception("Failed to fetch positions: " . $conn->error);
    }
    
    // Store positions in array for later use
    while($p = $posRes->fetch_assoc()) {
        $positions[] = $p;
    }
    
} catch (Exception $e) {
    $msg = "Database Error: " . $e->getMessage();
}

// Only process database operations if connection is working
if (isset($conn) && !$msg) {
    // Add candidate
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_cand'])) {
        try {
            $f = $_POST['candFName'];
            $m = $_POST['candMName'] ?: null;
            $l = $_POST['candLName'];
            $pos = (int)$_POST['posID'];
            $stmt = $conn->prepare("INSERT INTO candidates (candFName,candMName,candLName,posID) VALUES (?,?,?,?)");
            $stmt->bind_param("sssi", $f, $m, $l, $pos);
            $stmt->execute();
            $stmt->close();
            $msg = "Candidate added.";
            // Refresh positions after adding
            $posRes = $conn->query("SELECT posID, posName FROM positions WHERE posStatus='active'");
            $positions = [];
            while($p = $posRes->fetch_assoc()) {
                $positions[] = $p;
            }
        } catch (Exception $e) {
            $msg = "Error adding candidate: " . $e->getMessage();
        }
    }

    // Update candidate
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cand'])) {
        try {
            $id = (int)$_POST['candID'];
            $f = $_POST['candFName'];
            $m = $_POST['candMName'] ?: null;
            $l = $_POST['candLName'];
            $pos = (int)$_POST['posID'];
            $stmt = $conn->prepare("UPDATE candidates SET candFName=?,candMName=?,candLName=?,posID=? WHERE candID=?");
            $stmt->bind_param("sssii", $f, $m, $l, $pos, $id);
            $stmt->execute();
            $stmt->close();
            $msg = "Candidate updated.";
        } catch (Exception $e) {
            $msg = "Error updating candidate: " . $e->getMessage();
        }
    }

    // Toggle status
    if (isset($_GET['toggle']) && isset($_GET['id'])) {
        try {
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("UPDATE candidates SET candStat = IF(candStat='active','inactive','active') WHERE candID=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            header("Location: candidates.php");
            exit;
        } catch (Exception $e) {
            $msg = "Error toggling status: " . $e->getMessage();
        }
    }

    // Fetch candidates
    try {
        $cRes = $conn->query("SELECT c.*, p.posName FROM candidates c JOIN positions p ON c.posID = p.posID ORDER BY c.candID DESC");
        if (!$cRes) {
            throw new Exception("Failed to fetch candidates: " . $conn->error);
        }
        
        // Store candidates in array for later use
        while($row = $cRes->fetch_assoc()) {
            $candidates[] = $row;
        }
        
    } catch (Exception $e) {
        $msg = "Error fetching candidates: " . $e->getMessage();
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Candidates</title>
</head>
<body>
    <h2>Candidates Management</h2>
    
    <?php if($msg): ?>
        <p style="color:green;"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>

    <h3>Add Candidate</h3>
    <?php if (isset($conn) && !$msg): ?>
        <form method="post">
            <input name="candFName" required placeholder="First name">
            <input name="candMName" placeholder="Middle">
            <input name="candLName" required placeholder="Last name">
            <select name="posID" required>
                <option value="">Choose position</option>
                <?php foreach($positions as $p): ?>
                    <option value="<?= $p['posID'] ?>"><?= htmlspecialchars($p['posName']) ?></option>
                <?php endforeach; ?>
            </select>
            <button name="add_cand">Add</button>
        </form>
    <?php else: ?>
        <p>Database connection required to add candidates.</p>
    <?php endif; ?>

    <h3>Existing Candidates</h3>
    <?php if (!empty($candidates) && !$msg): ?>
        <table border="1" cellpadding="5">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Position</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            <?php foreach($candidates as $row): ?>
                <tr>
                    <td><?= $row['candID'] ?></td>
                    <td><?= htmlspecialchars($row['candFName']." ".$row['candMName']." ".$row['candLName']) ?></td>
                    <td><?= htmlspecialchars($row['posName']) ?></td>
                    <td><?= htmlspecialchars($row['candStat']) ?></td>
                    <td>
                        <form style="display:inline" method="post">
                            <input type="hidden" name="candID" value="<?= $row['candID'] ?>">
                            <input name="candFName" value="<?= htmlspecialchars($row['candFName']) ?>">
                            <input name="candMName" value="<?= htmlspecialchars($row['candMName']) ?>">
                            <input name="candLName" value="<?= htmlspecialchars($row['candLName']) ?>">
                            <select name="posID">
                                <?php foreach($positions as $pp): ?>
                                    <option value="<?= $pp['posID'] ?>" <?= $pp['posID']==$row['posID']?'selected':'' ?>>
                                        <?= htmlspecialchars($pp['posName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button name="update_cand">Update</button>
                        </form>
                        <a href="?id=<?= $row['candID'] ?>&toggle=1">
                            <?= $row['candStat']=='active'? 'Deactivate' : 'Activate' ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No candidates found or database connection issue.</p>
    <?php endif; ?>

    <p><a href="index.php">Back</a></p>
</body>
</html>