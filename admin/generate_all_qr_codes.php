<?php
session_start();
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

if (isset($_POST['generate_all'])) {
    $generated = generateAllModuleQRCodes($db);
    $success_message = "Generated " . count($generated) . " QR codes: " . implode(', ', $generated);
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Generate All Module QR Codes</h2>
    
    <?php if(isset($success_message)): ?>
    <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Bulk QR Code Generation</h5>
                </div>
                <div class="card-body">
                    <p>This will generate QR codes for all active modules in the system.</p>
                    <p><strong>Note:</strong> Existing QR codes with the same module ID will be overwritten.</p>
                    
                    <form method="POST">
                        <button type="submit" name="generate_all" class="btn btn-primary" onclick="return confirm('Generate QR codes for all active modules?')">
                            <i class="fas fa-qrcode"></i> Generate All QR Codes
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">QR Code Specifications</h5>
                </div>
                <div class="card-body">
                    <h6>QR Code Content:</h6>
                    <ul>
                        <li>Module ID</li>
                        <li>Module Name</li>
                        <li>Location Address</li>
                        <li>System Identifier</li>
                        <li>Timestamp</li>
                    </ul>
                    
                    <h6>Printing Recommendations:</h6>
                    <ul>
                        <li>Print on adhesive vinyl stickers</li>
                        <li>Minimum size: 2x2 inches (5x5 cm)</li>
                        <li>Use weather-resistant material for outdoor modules</li>
                        <li>Place in visible, easily scannable location</li>
                    </ul>
                    
                    <a href="generate_qr_codes.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Single QR Generator
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>