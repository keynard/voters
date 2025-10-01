<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$msg = "";
$positions = [];

try {
    require 'db.php';
} catch (Exception $e) {
    $msg = "Database Error: " . $e->getMessage();
}

// Only process database operations if connection is working
if (isset($conn) && !$msg) {
    // Add position
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pos'])) {
        try {
            $posName = $_POST['posName'];
            $num = (int)$_POST['numOfPositions'];
            $stmt = $conn->prepare("INSERT INTO positions (posName, numOfPositions) VALUES (?, ?)");
            $stmt->bind_param("si", $posName, $num);
            $stmt->execute();
            $stmt->close();
            $msg = "Position added.";
            
            // Refresh positions array after adding
            $res = $conn->query("SELECT * FROM positions ORDER BY posID DESC");
            $positions = [];
            while($row = $res->fetch_assoc()) {
                $positions[] = $row;
            }
        } catch (Exception $e) {
            $msg = "Error adding position: " . $e->getMessage();
        }
    }

    // Update position
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pos'])) {
        try {
            $posID = (int)$_POST['posID'];
            $posName = $_POST['posName'];
            $num = (int)$_POST['numOfPositions'];
            $stmt = $conn->prepare("UPDATE positions SET posName=?, numOfPositions=? WHERE posID=?");
            $stmt->bind_param("sii", $posName, $num, $posID);
            $stmt->execute();
            $stmt->close();
            $msg = "Position updated.";
            
            // Refresh positions array after updating
            $res = $conn->query("SELECT * FROM positions ORDER BY posID DESC");
            $positions = [];
            while($row = $res->fetch_assoc()) {
                $positions[] = $row;
            }
        } catch (Exception $e) {
            $msg = "Error updating position: " . $e->getMessage();
        }
    }

    // Toggle status
    if (isset($_GET['toggle']) && isset($_GET['id'])) {
        try {
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("UPDATE positions SET posStatus = IF(posStatus='active','inactive','active') WHERE posID=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            header("Location: positions.php");
            exit;
        } catch (Exception $e) {
            $msg = "Error toggling status: " . $e->getMessage();
        }
    }

    // Fetch positions
    try {
        $res = $conn->query("SELECT * FROM positions ORDER BY posID DESC");
        if (!$res) {
            throw new Exception("Failed to fetch positions: " . $conn->error);
        }
        
        // Store positions in array for later use
        while($row = $res->fetch_assoc()) {
            $positions[] = $row;
        }
        
    } catch (Exception $e) {
        $msg = "Error fetching positions: " . $e->getMessage();
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Positions</title>
</head>
<body>
    <h2>Positions Management</h2>
    
    <?php if($msg): ?>
        <p style="color:green;"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>
    
    <h3>Add Position</h3>
    <?php if (isset($conn) && !$msg): ?>
        <form method="post">
            <input name="posName" required placeholder="Position name">
            <input name="numOfPositions" type="number" required min="1" value="1">
            <button name="add_pos">Add</button>
        </form>
    <?php else: ?>
        <p>Database connection required to add positions.</p>
    <?php endif; ?>

    <h3>Existing Positions</h3>
    <?php if (!empty($positions) && !$msg): ?>
        <table border="1" cellpadding="5">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Slots</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            <?php foreach($positions as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['posID']) ?></td>
                    <td><?= htmlspecialchars($row['posName']) ?></td>
                    <td><?= htmlspecialchars($row['numOfPositions']) ?></td>
                    <td><?= htmlspecialchars($row['posStatus']) ?></td>
                    <td>
                        <form style="display:inline" method="post">
                            <input type="hidden" name="posID" value="<?= $row['posID'] ?>">
                            <input name="posName" value="<?= htmlspecialchars($row['posName']) ?>">
                            <input name="numOfPositions" type="number" value="<?= $row['numOfPositions'] ?>" min="1">
                            <button name="update_pos">Update</button>
                        </form>
                        <a href="?id=<?= $row['posID'] ?>&toggle=1">
                            <?= $row['posStatus']=='active'? 'Deactivate' : 'Activate' ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No positions found or database connection issue.</p>
    <?php endif; ?>

    <p><a href="index.php">Back</a></p>
</body>
</html>