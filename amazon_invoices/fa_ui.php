<?php
/**
 * FA UI Actions for Amazon Invoice Processing
 * Provides the FA user interface for importing, reviewing, matching, and managing Amazon invoices.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AmazonInvoices\Controllers\ImportController;

function amazon_invoices_ui_handle() {
    $controller = new ImportController();
    $action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
    switch ($action) {
        case 'dashboard':
            $controller->dashboard();
            break;
        case 'emails':
            $controller->processEmails();
            break;
        case 'upload':
            $controller->uploadPdfs();
            break;
        case 'directory':
            $controller->processDirectory();
            break;
        case 'review':
            $controller->reviewInvoices();
            break;
        case 'api':
            $controller->api();
            break;
        default:
            $controller->dashboard();
            break;
    }
}
