<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

$msg = "";
$voterID = null;
$positions = [];
$voterInfo = null;

try {
    require 'db.php';
} catch (Exception $e) {
    $msg = "Database Error: " . $e->getMessage();
}

// Check if user is logged in
if (!isset($_SESSION['voterID'])) {
    header("Location: login.php");
    exit;
}

$voterID = (int)$_SESSION['voterID'];

// Only process if database connection is working
if (isset($conn) && !$msg) {
    try {
        // Check if voter still active and not voted
        $stmt = $conn->prepare("SELECT voterStat, voted FROM voters WHERE voterID=?");
        $stmt->bind_param("i", $voterID);
        $stmt->execute();
        $voterInfo = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$voterInfo || $voterInfo['voterStat'] !== 'active') {
            $msg = "Voter inactive.";
        } elseif ($voterInfo['voted']) {
            $msg = "You have already voted.";
        }
        
    } catch (Exception $e) {
        $msg = "Error checking voter status: " . $e->getMessage();
    }
}

// Handle vote submission
if (isset($conn) && !$msg && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_votes'])) {
    try {
        $conn->begin_transaction();
        
        // Fetch positions and allowed number
        $posRes = $conn->query("SELECT posID, numOfPositions FROM positions WHERE posStatus='active'");
        while($pos = $posRes->fetch_assoc()) {
            $pid = $pos['posID'];
            $allowed = (int)$pos['numOfPositions'];
            $selected = isset($_POST['pos_'.$pid]) ? (array)$_POST['pos_'.$pid] : [];
            
            if (count($selected) > $allowed) {
                throw new Exception("Too many selections for position ID $pid");
            }
            
            foreach($selected as $candID) {
                $cid = (int)$candID;
                $ins = $conn->prepare("INSERT INTO votes (posID,voterID,candID) VALUES (?,?,?)");
                $ins->bind_param("iii", $pid, $voterID, $cid);
                $ins->execute();
                $ins->close();
            }
        }
        
        // Mark voter as voted
        $upd = $conn->prepare("UPDATE voters SET voted=1 WHERE voterID=?");
        $upd->bind_param("i", $voterID);
        $upd->execute();
        $upd->close();
        
        $conn->commit();
        $msg = "Votes recorded. Thank you!";
        
        // Destroy session after successful vote
        session_destroy();
        
    } catch(Exception $e) {
        $conn->rollback();
        $msg = "Error: " . $e->getMessage();
    }
}

// Fetch positions and candidates for display (only if not voted and no errors)
if (isset($conn) && !$msg && (!$voterInfo || !$voterInfo['voted'])) {
    try {
        $posRes = $conn->query("SELECT posID,posName,numOfPositions FROM positions WHERE posStatus='active' ORDER BY posID");
        if (!$posRes) {
            throw new Exception("Failed to fetch positions: " . $conn->error);
        }
        
        while($p = $posRes->fetch_assoc()) {
            $pid = $p['posID'];
            $allowed = (int)$p['numOfPositions'];
            
            // Get candidates for this position
            $cstmt = $conn->prepare("SELECT candID,candFName,candMName,candLName FROM candidates WHERE posID=? AND candStat='active'");
            $cstmt->bind_param("i", $pid);
            $cstmt->execute();
            $cres = $cstmt->get_result();
            
            $candidates = [];
            while($c = $cres->fetch_assoc()) {
                $cname = trim($c['candFName']." ".$c['candMName']." ".$c['candLName']);
                $candidates[] = [
                    'candID' => $c['candID'],
                    'name' => $cname
                ];
            }
            $cstmt->close();
            
            $positions[] = [
                'posID' => $pid,
                'posName' => $p['posName'],
                'numOfPositions' => $allowed,
                'candidates' => $candidates
            ];
        }
        
    } catch (Exception $e) {
        $msg = "Error fetching voting data: " . $e->getMessage();
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Vote</title>
</head>
<body>
    <h2>Voting Page</h2>
    
    <?php if($voterID): ?>
        <p>Voter ID: <?= htmlspecialchars($voterID) ?></p>
    <?php endif; ?>
    
    <?php if($msg): ?>
        <p style="color:<?= strpos($msg, 'Error') !== false ? 'red' : 'green' ?>;"><?= htmlspecialchars($msg) ?></p>
        <?php if(strpos($msg, 'Votes recorded') !== false): ?>
            <p><a href="index.php">Home</a></p>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if (!empty($positions) && !$msg && (!$voterInfo || !$voterInfo['voted'])): ?>
        <form method="post">
            <?php foreach($positions as $position): ?>
                <fieldset>
                    <legend><?= htmlspecialchars($position['posName']) ?> (Choose up to <?= $position['numOfPositions'] ?>)</legend>
                    
                    <?php foreach($position['candidates'] as $candidate): ?>
                        <label>
                            <?php if ($position['numOfPositions'] > 1): ?>
                                <input type="checkbox" name="pos_<?= $position['posID'] ?>[]" value="<?= $candidate['candID'] ?>">
                            <?php else: ?>
                                <input type="radio" name="pos_<?= $position['posID'] ?>" value="<?= $candidate['candID'] ?>">
                            <?php endif; ?>
                            <?= htmlspecialchars($candidate['name']) ?>
                        </label><br>
                    <?php endforeach; ?>
                </fieldset><br>
            <?php endforeach; ?>
            
            <button name="submit_votes">Submit Votes</button>
        </form>
    <?php elseif($msg && strpos($msg, 'Votes recorded') === false): ?>
        <p>Unable to display voting form due to errors.</p>
    <?php endif; ?>
    
    <p><a href="index.php">Cancel / Home</a></p>
</body>
</html>