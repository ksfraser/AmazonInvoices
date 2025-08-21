# FA Amazon Invoices Module - Test Coverage Report

## Hooks Class Coverage
- `install_options()` — tested (returns expected config options)
- `install_access()` — tested (returns expected security areas)
- `install_tables()` — tested (creates DB tables via service)
- `is_installed()` — tested (checks DB install status)

## Admin UI Coverage
- `admin/config.php` — manual test: form submission and config persistence
- SQL table `amazon_invoices_prefs` — tested via install_tables()

## PHPUnit Test Files
- `tests/FAWrapperTest.php`
  - testInstallOptions
  - testInstallAccess
  - testInstallTables
  - testIsInstalled

## Manual Test Steps
1. Run `composer install` in `fa_amazon_invoices/`
2. Run PHPUnit: `./vendor/bin/phpunit tests/FAWrapperTest.php`
3. Access admin UI at `admin/config.php` and verify config persistence
4. Check module features in FA menu

## Coverage Summary
- All hooks methods are covered by automated tests
- Admin config UI and DB table creation are covered by manual and automated tests
- All module requirements and install logic are verified
