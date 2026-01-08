<?php
session_start();
require_once '../includes/config.php';

// Check login
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get pending bills
$stmt = $pdo->prepare("SELECT * FROM bills 
                      WHERE user_id = ? AND payment_status = 'pending'
                      ORDER BY due_date ASC");
$stmt->execute([$user_id]);
$pending_bills = $stmt->fetchAll();

// Get paid bills for history
$stmt = $pdo->prepare("SELECT b.*, p.payment_date, p.transaction_id 
                      FROM bills b 
                      JOIN payments p ON b.id = p.bill_id 
                      WHERE b.user_id = ? AND b.payment_status = 'paid'
                      ORDER BY p.payment_date DESC");
$stmt->execute([$user_id]);
$paid_bills = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bills - BillPay Pro</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <h1><i data-lucide="file-text"></i> My Bills</h1>
        
        <!-- Pending Bills -->
        <div class="card">
            <h2><i data-lucide="clock"></i> Pending Bills</h2>
            <?php if (count($pending_bills) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bill No.</th>
                            <th>Service</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_bills as $bill): 
                            $due_date = strtotime($bill['due_date']);
                            $today = time();
                            $is_overdue = $due_date < $today;
                        ?>
                        <tr>
                            <td><?php echo $bill['bill_number']; ?></td>
                            <td><?php echo ucfirst($bill['bill_type']); ?></td>
                            <td>₹<?php echo number_format($bill['amount'] ?? $bill['final_amount'], 2); ?></td>
                            <td>
                                <?php echo date('M d, Y', $due_date); ?>
                                <?php if ($is_overdue): ?>
                                    <span style="color: #e74c3c;">(Overdue)</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-warning">Pending</span></td>
                            <td>
                                <a href="payment.php?bill_id=<?php echo $bill['id']; ?>" class="btn btn-sm btn-primary">
                                    <i data-lucide="credit-card"></i> Pay Now
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem;">
                    <i data-lucide="check-circle" style="width: 3rem; height: 3rem; color: #27ae60; margin-bottom: 1rem;"></i>
                    <h3>No Pending Bills</h3>
                    <p>All your bills are paid up to date!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Paid Bills History -->
        <div class="card" style="margin-top: 2rem;">
            <h2><i data-lucide="history"></i> Payment History</h2>
            <?php if (count($paid_bills) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bill No.</th>
                            <th>Service</th>
                            <th>Amount</th>
                            <th>Paid Date</th>
                            <th>Transaction ID</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paid_bills as $bill): ?>
                        <tr>
                            <td><?php echo $bill['bill_number']; ?></td>
                            <td><?php echo ucfirst($bill['bill_type']); ?></td>
                            <td>₹<?php echo number_format($bill['amount'] ?? $bill['final_amount'], 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($bill['payment_date'])); ?></td>
                            <td><code><?php echo $bill['transaction_id']; ?></code></td>
                            <td><span class="badge badge-success">Paid</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem;">
                    <i data-lucide="credit-card" style="width: 3rem; height: 3rem; color: #95a5a6; margin-bottom: 1rem;"></i>
                    <h3>No Payment History</h3>
                    <p>You haven't made any payments yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        lucide.createIcons();
    </script>
</body>
</html>