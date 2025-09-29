<?php
session_start();
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'charity') {
    header("Location: ../auth/login.php");
    exit();
}
include '../includes/header.php';

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$charity_id = $_SESSION['user_id'];

// Get charity stats
$query = "SELECT SUM(amount) as total_donations, COUNT(*) as donation_count 
          FROM donations 
          WHERE charity_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$charity_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent donations
$query = "SELECT d.amount, d.created_at, do.user_id 
          FROM donations d 
          JOIN donors do ON d.donor_id = do.id 
          WHERE d.charity_id = ? 
          ORDER BY d.created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$charity_id]);
$recent_donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2>Charity Dashboard - <?php echo $_SESSION['charity_name']; ?></h2>
    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5 class="card-title">$<?php echo number_format($stats['total_donations'] ?? 0, 2); ?></h5>
                    <p class="card-text">Total Donations</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $stats['donation_count'] ?? 0; ?></h5>
                    <p class="card-text">Total Donations Count</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Donations</h5>
                </div>
                <div class="card-body">
                    <?php if(empty($recent_donations)): ?>
                        <p class="text-muted">No donations yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Donor ID</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recent_donations as $donation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($donation['user_id']); ?></td>
                                        <td>$<?php echo number_format($donation['amount'], 2); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($donation['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// WebSocket connection for real-time donation updates
const ws = new WebSocket('ws://localhost:8080');
ws.onopen = function() {
    ws.send(JSON.stringify({
        type: 'subscribe',
        user_type: 'charity',
        user_id: <?php echo $charity_id; ?>
    }));
};

ws.onmessage = function(event) {
    const data = JSON.parse(event.data);
    if(data.type === 'new_donation' && data.charity_id == <?php echo $charity_id; ?>) {
        // Show notification and reload donations
        showNotification('New donation received: $' + data.amount);
        setTimeout(() => {
            location.reload();
        }, 2000);
    }
};

function showNotification(message) {
    // Create and show notification
    const notification = document.createElement('div');
    notification.className = 'alert alert-success alert-dismissible fade show position-fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(notification);
}
</script>

<?php include '../includes/footer.php'; ?>