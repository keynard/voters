<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$msg = "";
$positions = [];
$results = [];

try {
    require 'db.php';
} catch (Exception $e) {
    $msg = "Database Error: " . $e->getMessage();
}

// Only process database operations if connection is working
if (isset($conn) && !$msg) {
    try {
        // Fetch positions
        $posRes = $conn->query("SELECT posID,posName FROM positions ORDER BY posID");
        if (!$posRes) {
            throw new Exception("Failed to fetch positions: " . $conn->error);
        }
        
        // Process each position
        while($p = $posRes->fetch_assoc()) {
            $pid = $p['posID'];
            
            // Get total votes cast for this position
            $tstmt = $conn->prepare("SELECT COUNT(*) as tot FROM votes WHERE posID=?");
            $tstmt->bind_param("i", $pid);
            $tstmt->execute();
            $tot = $tstmt->get_result()->fetch_assoc()['tot'] ?: 0;
            $tstmt->close();

            // Get candidates and their vote counts for this position
            $cstmt = $conn->prepare("
                SELECT c.candID, c.candFName, c.candMName, c.candLName, 
                       COUNT(v.voteID) as cnt
                FROM candidates c
                LEFT JOIN votes v ON c.candID = v.candID AND v.posID = ?
                WHERE c.posID = ?
                GROUP BY c.candID
                ORDER BY cnt DESC, c.candID ASC
            ");
            $cstmt->bind_param("ii", $pid, $pid);
            $cstmt->execute();
            $cres = $cstmt->get_result();
            
            $candidates = [];
            while($r = $cres->fetch_assoc()) {
                $name = trim($r['candFName']." ".$r['candMName']." ".$r['candLName']);
                $cnt = (int)$r['cnt'];
                $perc = $tot > 0 ? round(($cnt/$tot)*100, 2) : 0;
                
                $candidates[] = [
                    'name' => $name,
                    'votes' => $cnt,
                    'percentage' => $perc
                ];
            }
            $cstmt->close();
            
            // Store position data
            $positions[] = [
                'posID' => $pid,
                'posName' => $p['posName'],
                'totalVotes' => $tot,
                'candidates' => $candidates
            ];
        }
        
    } catch (Exception $e) {
        $msg = "Error fetching results: " . $e->getMessage();
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Results</title>
</head>
<body>
    <h2>Election Results</h2>
    
    <?php if($msg): ?>
        <p style="color:red;"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>
    <?php if (!empty($positions)): ?>
    <?php foreach($positions as $position): ?>
        <?php if (empty($position['candidates'])) continue; ?> <!-- Skip positions with no candidates -->
        
        <h3><?= htmlspecialchars($position['posName']) ?> — 
            Total votes cast for this position: <?= $position['totalVotes'] ?>
        </h3>
        
        <table border="1" cellpadding="5">
            <tr>
                <th>Candidate</th>
                <th>Total Votes</th>
                <th>Voting %</th>
            </tr>
            <?php foreach($position['candidates'] as $candidate): ?>
                <tr>
                    <td><?= htmlspecialchars($candidate['name']) ?></td>
                    <td><?= $candidate['votes'] ?></td>
                    <td><?= $candidate['percentage'] ?>%</td>
                </tr>
            <?php endforeach; ?>
        </table>
        <br>
    <?php endforeach; ?>
<?php else: ?>
    <p>No results found or database connection issue.</p>
<?php endif; ?>


    <p><a href="index.php">Back</a></p>
</body>
</html>