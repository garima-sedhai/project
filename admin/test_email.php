<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/email_helper.php';

// Redirect if not admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: ../customer/login.php");
    exit;
}

$result = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $test_email = $_POST['test_email'];
    $result = test_email_configuration();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Email Configuration</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input { width: 100%; padding: 8px; }
        button { background: #075B5E; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        .result { margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Email Configuration</h1>
        
        <form method="POST">
            <div class="form-group">
                <label>Test Email Address:</label>
                <input type="email" name="test_email" value="<?php echo ADMIN_EMAIL; ?>" required>
            </div>
            <button type="submit">Send Test Email</button>
        </form>
        
        <?php if ($result): ?>
            <div class="result">
                <h3>Result:</h3>
                <p><?php echo $result; ?></p>
            </div>
        <?php endif; ?>
        
        <h3>Current Email Configuration:</h3>
        <pre>
SMTP Enabled: <?php echo SMTP_ENABLED ? 'Yes' : 'No'; ?>
SMTP Host: <?php echo SMTP_HOST; ?>
SMTP Port: <?php echo SMTP_PORT; ?>
SMTP Username: <?php echo SMTP_USERNAME; ?>
From Email: <?php echo SMTP_FROM_EMAIL; ?>
From Name: <?php echo SMTP_FROM_NAME; ?>
        </pre>
    </div>
</body>
</html>