<?php
// Enhanced Payment Gateway Configuration
class PaymentConfig {
    // eSewa Sandbox Configuration
    public static $esewa = [
        'merchant_id' => 'EPAYTEST',
        'secret_key' => '8gBm/:&EnhH.1/q',
        'api_url' => 'https://rc-epay.esewa.com.np/api/epay/main/v2/form',
        'verify_url' => 'https://rc-epay.esewa.com.np/api/epay/transaction/status/',
        'success_url' => 'http://localhost/online_billing_system/customer/payment_success.php',
        'failure_url' => 'http://localhost/online_billing_system/customer/payment_failed.php',
        'sandbox_url' => 'http://localhost/online_billing_system/customer/esewa_demo.php',
        'qr_merchant_id' => '9800000000' // Admin eSewa number for QR payments
    ];

    // Khalti Sandbox Configuration
    public static $khalti = [
        'public_key' => 'test_public_key_xxxxxxxxxxxx',
        'secret_key' => 'test_secret_key_xxxxxxxxxxxx',
        'api_url' => 'https://a.khalti.com/api/v2/epayment/initiate/',
        'verify_url' => 'https://a.khalti.com/api/v2/epayment/lookup/',
        'success_url' => 'http://localhost/online_billing_system/customer/payment_success.php',
        'failure_url' => 'http://localhost/online_billing_system/customer/payment_failed.php',
        'website_url' => 'http://localhost/online_billing_system'
    ];

    // Generate unique transaction ID
    public static function generateTransactionId($prefix = 'TXN') {
        return $prefix . date('YmdHis') . rand(1000, 9999);
    }

    // Validate payment amount
    public static function validateAmount($amount) {
        return is_numeric($amount) && $amount > 0;
    }

    // Generate eSewa signature
    public static function generateEsewaSignature($total_amount, $transaction_uuid, $product_code) {
        $secret_key = self::$esewa['secret_key'];
        $data = $total_amount . ',' . $transaction_uuid . ',' . $product_code;
        return base64_encode(hash_hmac('sha256', $data, $secret_key, true));
    }

    // Generate QR code data for eSewa
    public static function generateEsewaQRData($amount, $transaction_id, $product_code = 'EPAYTEST') {
        $success_url = self::$esewa['success_url'] . '?gateway=esewa&transaction_id=' . $transaction_id;
        return "esewa://pay?amt={$amount}&pid={$transaction_id}&scd={$product_code}&su=" . urlencode($success_url);
    }

    // Demo credentials for testing
    public static function getDemoCredentials() {
        return [
            'esewa' => [
                'mobile' => ['9800000001', '9800000002', '9800000003'],
                'password' => '1234',
                'admin_mobile' => 'admin',
                'admin_password' => 'admin123'
            ],
            'khalti' => [
                'mobile' => '9800000001',
                'mpin' => '1111',
                'otp' => '123456'
            ]
        ];
    }
}
?>