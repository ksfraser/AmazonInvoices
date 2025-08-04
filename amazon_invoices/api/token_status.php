<?php
/**
 * Amazon API Token Status Endpoint
 * 
 * Provides real-time token status for the credentials admin screen
 * 
 * @package AmazonInvoices
 * @author  Your Name
 * @since   1.0.0
 */

header('Content-Type: application/json');

// Basic security check
$path_to_root = "../../..";
include($path_to_root . "/includes/session.inc");

// Check if user has access
if (!check_user_access('SA_AMAZON_INVOICES')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    // Include our credential service
    require_once(dirname(__FILE__) . '/../../src/Services/AmazonCredentialService.php');
    
    $credentialService = new \AmazonInvoices\Services\AmazonCredentialService();
    $status = $credentialService->getCredentialStatus();
    
    // Check if token needs refresh (for SP-API)
    $needsRefresh = false;
    
    if ($status['configured'] && $status['method'] === 'sp_api') {
        // Check token expiration
        if (isset($status['token_expires'])) {
            $expiresAt = strtotime($status['token_expires']);
            $now = time();
            
            // Needs refresh if expiring within 10 minutes
            $needsRefresh = ($expiresAt - $now) < 600;
        }
    }
    
    echo json_encode([
        'success' => true,
        'needs_refresh' => $needsRefresh,
        'status' => $status,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
