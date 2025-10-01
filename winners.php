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
    try {
        // Fetch positions
        $posRes = $conn->query("SELECT posID,posName,numOfPositions FROM positions ORDER BY posID");
        if (!$posRes) {
            throw new Exception("Failed to fetch positions: " . $conn->error);
        }
        
        // Process each position to get winners
        while($p = $posRes->fetch_assoc()) {
            $pid = $p['posID'];
            $slots = (int)$p['numOfPositions'];
            
            // Get candidates sorted by votes desc (winners)
            $stmt = $conn->prepare("
                SELECT c.candID, c.candFName, c.candMName, c.candLName, COUNT(v.voteID) as cnt
                FROM candidates c
                LEFT JOIN votes v ON c.candID = v.candID AND v.posID = ?
                WHERE c.posID = ?
                GROUP BY c.candID
                ORDER BY cnt DESC, c.candID ASC
                LIMIT ?
            ");
            $stmt->bind_param("iii", $pid, $pid, $slots);
            $stmt->execute();
            $res = $stmt->get_result();
            
            $winners = [];
            while($r = $res->fetch_assoc()) {
                $name = trim($r['candFName']." ".$r['candMName']." ".$r['candLName']);
                $winners[] = [
                    'name' => $name,
                    'votes' => (int)$r['cnt']
                ];
            }
            $stmt->close();
            
            // Store position data with winners
            $positions[] = [
                'posID' => $pid,
                'posName' => $p['posName'],
                'numOfPositions' => $slots,
                'winners' => $winners
            ];
        }
        
    } catch (Exception $e) {
        $msg = "Error fetching winners: " . $e->getMessage();
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Winners</title>
</head>
<body>
    <h2>Election Winners</h2>
    
    <?php if($msg): ?>
        <p style="color:red;"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>
    
    <?php if (!empty($positions) && !$msg): ?>
       <?php foreach($positions as $position): ?>
    <?php if (empty($position['winners'])) continue; ?> <!-- Skip if no winners -->

    <h3><?= htmlspecialchars($position['posName']) ?></h3>
    <table border="1" cellpadding="5">
        <tr>
            <th>Winner</th>
            <th>Total Votes</th>
        </tr>
        <?php foreach($position['winners'] as $winner): ?>
            <tr>
                <td><?= htmlspecialchars($winner['name']) ?></td>
                <td><?= $winner['votes'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <br>
<?php endforeach; ?>

    <?php else: ?>
        <p>No winners found or database connection issue.</p>
    <?php endif; ?>

    <p><a href="index.php">Back</a></p>
</body>
</html>