<?php
/**
 * Utility functions for MDVA system
 */

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
///-------------------------------------------------------------------------------------------
/**
 * Create verifiable donation session record
 * Used when starting a donation session with per-donation charity selection
 */
function create_verifiable_donation_session($donor_id, $user_id, $charity_id, $module_id, $db) {
    $timestamp = time();
    
    // Get previous transaction hash to maintain chain
    $previous_hash = get_last_transaction_hash($donor_id, $db);
    
    // Get charity name
    $charity_name = get_charity_name($charity_id, $db);
    
    // Create transaction data for cryptographic hashing
    $transaction_data = [
        'donor_id' => $user_id,
        'charity_id' => $charity_id,
        'charity_name' => $charity_name,
        'module_id' => $module_id,
        'action' => 'donation_session_start',
        'timestamp' => $timestamp,
        'previous_hash' => $previous_hash
    ];
    
    // Generate cryptographic hash
    $transaction_data_json = json_encode($transaction_data);
    $transaction_hash = hash('sha256', $transaction_data_json);
    
    // Store in verifiable transactions table
    $query = "INSERT INTO verifiable_transactions 
              (donor_id, transaction_type, transaction_data, transaction_hash, previous_hash, timestamp) 
              VALUES (?, 'donation_session', ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        $donor_id, 
        $transaction_data_json, 
        $transaction_hash,
        $previous_hash,
        date('Y-m-d H:i:s')
    ]);
    
    error_log("FairGive Donation Session: Donor $user_id starting session for $charity_name via module $module_id. Transaction Hash: $transaction_hash");
    
    return $transaction_hash;
}
///-------------------------------------------------------------------------------------------
/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

/**
 * Check if user is admin
 */
function is_admin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin';
}

/**
 * Check if user is charity
 */
function is_charity() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'charity';
}

/**
 * Redirect to specified page
 */
function redirect($page) {
    header("Location: " . $page);
    exit();
}

/**
 * Generate random string
 */
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * Format currency
 */
function format_currency($amount) {
    return '$' . number_format($amount, 2);
}

/**
 * Get charity name by ID
 */
function get_charity_name($charity_id, $db) {
    $query = "SELECT name FROM charities WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    $charity = $stmt->fetch(PDO::FETCH_ASSOC);
    return $charity ? $charity['name'] : 'Unknown Charity';
}

/**
 * Get donor user_id by ID
 */
function get_donor_user_id($donor_id, $db) {
    $query = "SELECT user_id FROM donors WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);
    return $donor ? $donor['user_id'] : 'Unknown Donor';
}

/**
 * Log activity
 */
/**
 * Log activity (updated to use description column)
 */
function log_activity($db, $user_type, $user_id, $action, $details = '') {
    try {
        $query = "INSERT INTO activity_logs (user_type, user_id, action, description) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_type, $user_id, $action, $details]);
        return true;
    } catch (Exception $e) {
        error_log("Activity log insert failed: " . $e->getMessage());
        return false;
    }
}
/**
 * Send notification to WebSocket server
 */
function notify_websocket($type, $data) {
    try {
        $context = new ZMQContext();
        $socket = $context->getSocket(ZMQ::SOCKET_PUSH, 'my pusher');
        $socket->connect("tcp://localhost:5555");
        
        $message = json_encode([
            'type' => $type,
            'data' => $data,
            'timestamp' => time()
        ]);
        
        $socket->send($message);
        return true;
    } catch (Exception $e) {
        error_log("WebSocket notification failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get donation statistics for charity
 */
function get_charity_stats($charity_id, $db) {
    $query = "SELECT 
                SUM(amount) as total_donations,
                COUNT(*) as donation_count,
                AVG(amount) as average_donation,
                MAX(amount) as largest_donation,
                MIN(amount) as smallest_donation
              FROM donations 
              WHERE charity_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get monthly donation data for charts
 */
function get_monthly_donation_data($charity_id, $db, $year = null) {
    if ($year === null) {
        $year = date('Y');
    }
    
    $query = "SELECT 
                MONTH(created_at) as month,
                SUM(amount) as total_amount,
                COUNT(*) as donation_count
              FROM donations 
              WHERE charity_id = ? AND YEAR(created_at) = ?
              GROUP BY MONTH(created_at)
              ORDER BY month";
    $stmt = $db->prepare($query);
    $stmt->execute([$charity_id, $year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Generate tax receipt data for donor
 */
function generate_tax_receipt_data($donor_id, $year, $db) {
    $query = "SELECT 
                d.amount,
                d.created_at,
                c.name as charity_name,
                c.id as charity_id
              FROM donations d
              JOIN charities c ON d.charity_id = c.id
              WHERE d.donor_id = ? AND YEAR(d.created_at) = ?
              ORDER BY d.created_at";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id, $year]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_amount = 0;
    foreach ($donations as $donation) {
        $total_amount += $donation['amount'];
    }
    
    return [
        'donations' => $donations,
        'total_amount' => $total_amount,
        'donation_count' => count($donations),
        'year' => $year
    ];
}

/**
 * Validate email format
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Check password strength
 */
function check_password_strength($password) {
    $strength = 0;
    
    // Length check
    if (strlen($password) >= 8) $strength++;
    
    // Contains lowercase
    if (preg_match('/[a-z]/', $password)) $strength++;
    
    // Contains uppercase
    if (preg_match('/[A-Z]/', $password)) $strength++;
    
    // Contains numbers
    if (preg_match('/[0-9]/', $password)) $strength++;
    
    // Contains special characters
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $strength++;
    
    return $strength >= 4; // At least 4 out of 5 criteria
}

/**
 * Generate QR code data for Module MDVA
 */
function generateModuleQRData($module_id, $module_name = '', $location = '') {
    $qr_data = [
        'module_id' => $module_id,
        'module_name' => $module_name,
        'location' => $location,
        'system' => 'MDVA',
        'type' => 'donation_module',
        'version' => '1.0',
        'timestamp' => time(),
        'url' => "https://yoursite.com/donate.php?module=" . urlencode($module_id)
    ];
    
    return json_encode($qr_data);
}

/**
 * Generate single QR code for a module
 */
function generateModuleQRCode($module_id, $module_name = '', $location = '', $save_path = null) {
    require_once 'Lib/phpqrcode/qrlib.php';
    
    $qr_data = generateModuleQRData($module_id, $module_name, $location);
    
    // If no save path provided, generate filename
    if ($save_path === null) {
        $qr_dir = "../qr_codes/";
        if (!file_exists($qr_dir)) {
            mkdir($qr_dir, 0755, true);
        }
        $save_path = $qr_dir . "mdva_module_" . $module_id . ".png";
    }
    
    // Generate and save QR code
    QRcode::png($qr_data, $save_path, QR_ECLEVEL_L, 10, 2);
    
    return $save_path;
}

/**
 * Generate multiple QR codes for all modules
 */
function generateAllModuleQRCodes($db) {
    require_once 'Lib/phpqrcode/qrlib.php';
    
    $qr_dir = "../qr_codes/";
    if (!file_exists($qr_dir)) {
        mkdir($qr_dir, 0755, true);
    }
    
    // Get all active modules
    $query = "SELECT m.*, l.name as location_name, l.address, l.city, l.state 
              FROM modules m 
              LEFT JOIN module_locations ml ON m.id = ml.module_id AND ml.status = 'active'
              LEFT JOIN locations l ON ml.location_id = l.id 
              WHERE m.status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $generated = [];
    
    foreach ($modules as $module) {
        $location = $module['location_name'] ? 
            $module['location_name'] . ', ' . $module['address'] . ', ' . $module['city'] . ', ' . $module['state'] : 
            $module['location'];
            
        $filename = "mdva_module_" . $module['module_id'] . ".png";
        $filepath = $qr_dir . $filename;
        
        // Generate QR code using the single QR code function
        generateModuleQRCode(
            $module['module_id'],
            $module['name'],
            $location,
            $filepath
        );
        
        $generated[] = [
            'module_id' => $module['module_id'],
            'module_name' => $module['name'],
            'qr_file' => $filename
        ];
    }
    
    return $generated;
}

/**
 * Get system statistics
 */
function get_system_stats($db) {
    $stats = [];
    
    // Total charities
    $query = "SELECT COUNT(*) as count FROM charities WHERE approved = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_charities'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total donors
    $query = "SELECT COUNT(*) as count FROM donors";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_donors'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total donations
    $query = "SELECT SUM(amount) as total FROM donations";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_donations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total donation count
    $query = "SELECT COUNT(*) as count FROM donations";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_donation_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Today's donations
    $query = "SELECT SUM(amount) as total FROM donations WHERE DATE(created_at) = CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['today_donations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    return $stats;
}

// =============================================================================
// FAIRGIVE VERIFIABLE TRANSACTION FUNCTIONS (Blockchain-esque System)
// =============================================================================

/**
 * Get the last transaction hash for a donor to maintain hash chain
 * Used for FairGive verifiable donation system
 */
function get_last_transaction_hash($donor_id, $db) {
    $query = "SELECT transaction_hash FROM verifiable_transactions 
              WHERE donor_id = ? 
              ORDER BY id DESC 
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['transaction_hash'] : '0'; // Genesis hash for first transaction
}

/**
 * Create initial verifiable transaction for new donors
 * Used for FairGive verifiable donation system
 */
function create_initial_verifiable_transaction($donor_id, $user_id, $db) {
    $timestamp = time();
    
    $transaction_data = [
        'donor_id' => $user_id,
        'action' => 'account_creation',
        'timestamp' => $timestamp,
        'previous_hash' => '0' // Genesis block
    ];
    
    $transaction_data_json = json_encode($transaction_data);
    $transaction_hash = hash('sha256', $transaction_data_json);
    
    $query = "INSERT INTO verifiable_transactions 
              (donor_id, transaction_type, transaction_data, transaction_hash, previous_hash, timestamp) 
              VALUES (?, 'account_creation', ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        $donor_id, 
        $transaction_data_json, 
        $transaction_hash,
        '0',
        date('Y-m-d H:i:s')
    ]);
    
    error_log("FairGive Initial Verifiable Record: Donor $user_id account created. Transaction Hash: $transaction_hash");
    
    return $transaction_hash;
}

/**
 * Create verifiable charity selection record
 * Used for FairGive verifiable donation system
 */
function create_verifiable_charity_selection($donor_id, $user_id, $old_charity_id, $new_charity_id, $charity_name, $db) {
    $timestamp = time();
    
    // Get previous transaction hash to maintain chain
    $previous_hash = get_last_transaction_hash($donor_id, $db);
    
    // Create transaction data for cryptographic hashing
    $transaction_data = [
        'donor_id' => $user_id,
        'old_charity_id' => $old_charity_id,
        'new_charity_id' => $new_charity_id,
        'charity_name' => $charity_name,
        'action' => 'charity_selection',
        'timestamp' => $timestamp,
        'previous_hash' => $previous_hash
    ];
    
    // Generate cryptographic hash (blockchain-esque verification)
    $transaction_data_json = json_encode($transaction_data);
    $transaction_hash = hash('sha256', $transaction_data_json);
    
    // Store in verifiable transactions table
    $query = "INSERT INTO verifiable_transactions 
              (donor_id, transaction_type, transaction_data, transaction_hash, previous_hash, timestamp) 
              VALUES (?, 'charity_selection', ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        $donor_id, 
        $transaction_data_json, 
        $transaction_hash,
        $previous_hash,
        date('Y-m-d H:i:s')
    ]);
    
    // Also log to audit table for immediate visibility and reporting
    $query = "INSERT INTO charity_selection_audit 
              (donor_id, old_charity_id, new_charity_id, transaction_hash, selected_at) 
              VALUES (?, ?, ?, ?, NOW())";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id, $old_charity_id, $new_charity_id, $transaction_hash]);
    
    error_log("FairGive Verifiable Record: Donor $user_id changed charity from $old_charity_id to $new_charity_id. Transaction Hash: $transaction_hash");
    
    return $transaction_hash;
}

/**
 * Create verifiable donation record
 * Used for FairGive verifiable donation system
 */
function create_verifiable_donation($donor_id, $user_id, $charity_id, $amount, $module_id, $db) {
    $timestamp = time();
    
    // Get previous transaction hash to maintain chain
    $previous_hash = get_last_transaction_hash($donor_id, $db);
    
    // Get charity name
    $charity_name = get_charity_name($charity_id, $db);
    
    // Create transaction data for cryptographic hashing
    $transaction_data = [
        'donor_id' => $user_id,
        'charity_id' => $charity_id,
        'charity_name' => $charity_name,
        'amount' => $amount,
        'module_id' => $module_id,
        'action' => 'donation',
        'timestamp' => $timestamp,
        'previous_hash' => $previous_hash
    ];
    
    // Generate cryptographic hash (blockchain-esque verification)
    $transaction_data_json = json_encode($transaction_data);
    $transaction_hash = hash('sha256', $transaction_data_json);
    
    // Store in verifiable transactions table
    $query = "INSERT INTO verifiable_transactions 
              (donor_id, transaction_type, transaction_data, transaction_hash, previous_hash, timestamp) 
              VALUES (?, 'donation', ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        $donor_id, 
        $transaction_data_json, 
        $transaction_hash,
        $previous_hash,
        date('Y-m-d H:i:s')
    ]);
    
    error_log("FairGive Verifiable Record: Donor $user_id donated $amount to $charity_name via module $module_id. Transaction Hash: $transaction_hash");
    
    return $transaction_hash;
}

/**
 * Verify transaction chain integrity for a donor
 * Used for FairGive verifiable donation system audit
 */
function verify_donor_transaction_chain($donor_id, $db) {
    $query = "SELECT transaction_hash, previous_hash, transaction_data, timestamp 
              FROM verifiable_transactions 
              WHERE donor_id = ? 
              ORDER BY id ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        return ['valid' => true, 'message' => 'No transactions to verify'];
    }
    
    $previous_hash = '0'; // Start with genesis hash
    $issues = [];
    
    foreach ($transactions as $index => $transaction) {
        // Check if previous hash matches
        if ($transaction['previous_hash'] !== $previous_hash) {
            $issues[] = "Transaction {$transaction['transaction_hash']} has incorrect previous hash. Expected: $previous_hash, Found: {$transaction['previous_hash']}";
        }
        
        // Verify current hash is correct
        $calculated_hash = hash('sha256', $transaction['transaction_data']);
        if ($calculated_hash !== $transaction['transaction_hash']) {
            $issues[] = "Transaction {$transaction['transaction_hash']} hash mismatch. Data may have been tampered with.";
        }
        
        $previous_hash = $transaction['transaction_hash'];
    }
    
    return [
        'valid' => empty($issues),
        'transaction_count' => count($transactions),
        'issues' => $issues,
        'last_transaction_hash' => end($transactions)['transaction_hash'] ?? null
    ];
}

/**
 * Get donor's verifiable transaction history
 * Used for FairGive verifiable donation system reporting
 */
function get_donor_verifiable_history($donor_id, $db, $limit = 50) {
    $query = "SELECT 
                transaction_type,
                transaction_data,
                transaction_hash,
                previous_hash,
                timestamp
              FROM verifiable_transactions 
              WHERE donor_id = ? 
              ORDER BY timestamp DESC 
              LIMIT ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$donor_id, $limit]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse transaction data
    foreach ($transactions as &$transaction) {
        $transaction['parsed_data'] = json_decode($transaction['transaction_data'], true);
    }
    
    return $transactions;
}

?>