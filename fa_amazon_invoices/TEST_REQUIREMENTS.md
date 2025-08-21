# FA Amazon Invoices Module - Requirements & Test Coverage

## Requirements
- PHP 8.0+
- FrontAccounting ERP
- AmazonInvoices Composer package
- Database table: `amazon_invoices_prefs` for config variables
- All module config is managed via the admin UI (`admin/config.php`)
- Module install/activation handled by `hooks.php` class

## Test Coverage
- `tests/FAWrapperTest.php` covers:
  - install_options()
  - install_access()
  - install_tables()
  - is_installed()
- Admin config UI tested via manual form submission
- SQL table creation tested via install_tables()

## How to Test
1. Run `composer install` in `fa_amazon_invoices/`
2. Run PHPUnit: `./vendor/bin/phpunit tests/FAWrapperTest.php`
3. Access admin UI at `admin/config.php` and verify config persistence
4. Check that all module features are available in FA menu
