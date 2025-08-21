# FrontAccounting Amazon Invoices Module

This directory contains the FrontAccounting wrapper for the AmazonInvoices Composer package.

## Installation

1. Run `composer install` in this directory to install the AmazonInvoices package and dependencies.
2. Copy the contents of `amazon_invoices/` (hooks.php, fa_ui.php, etc.) into this directory.
3. Activate the module in FrontAccounting.

## Files
- hooks.php: FA hooks integration
- fa_ui.php: FA UI actions
- install.php: DB table creation logic
- admin/: Admin screens
- includes/: Helper functions

## Requirements
- PHP 8.0+
- FrontAccounting ERP
- AmazonInvoices Composer package
