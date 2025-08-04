<?php
/**
 * Amazon Invoices Module for FrontAccounting
 * Main module configuration
 */

$installed_modules[] = array(
    'module_id' => 'amazon_invoices',
    'name' => _('Amazon Invoices'),
    'description' => _('Download and process Amazon invoices into FrontAccounting'),
    'version' => '1.0.0',
    'author' => 'Custom Module',
    'active' => 1,
    'path' => 'amazon_invoices',
    'tab' => array(
        'name' => _('Amazon'),
        'lnk' => array(
            array(
                'url' => 'amazon_invoices/download.php',
                'text' => _('Download Invoices'),
                'access' => 'SA_AMAZON_DOWNLOAD'
            ),
            array(
                'url' => 'amazon_invoices/staging_list.php',
                'text' => _('Staging Review'),
                'access' => 'SA_AMAZON_PROCESS'
            ),
            array(
                'url' => 'amazon_invoices/item_matching.php',
                'text' => _('Item Matching'),
                'access' => 'SA_AMAZON_MATCH'
            ),
            array(
                'url' => 'amazon_invoices/payment_allocation.php',
                'text' => _('Payment Allocation'),
                'access' => 'SA_AMAZON_PAYMENTS'
            ),
            array(
                'url' => 'amazon_invoices/matching_rules.php',
                'text' => _('Matching Rules'),
                'access' => 'SA_AMAZON_INVOICES'
            ),
            array(
                'url' => 'amazon_invoices/amazon_credentials.php',
                'text' => _('API Credentials'),
                'access' => 'SA_AMAZON_INVOICES'
            ),
            array(
                'url' => 'amazon_invoices/settings.php',
                'text' => _('Settings'),
                'access' => 'SA_AMAZON_INVOICES'
            ),
        )
    )
);
