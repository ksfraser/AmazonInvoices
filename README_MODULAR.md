# Amazon Invoice Processing Module for FrontAccounting

A comprehensive, modular system for importing and processing Amazon invoices from multiple sources (Gmail emails and PDF files) with advanced duplicate detection and FrontAccounting integration.

## 🚀 Features

### Core Functionality
- **Multi-Source Import**: Process invoices from Gmail emails and PDF files
- **Modular Architecture**: Standalone composer packages for maximum reusability
- **Duplicate Detection**: Advanced multi-algorithm duplicate detection with confidence scoring
- **SOLID Principles**: Clean, maintainable code following SOLID and DRY principles
- **Framework Agnostic**: Core libraries work with any PHP framework
- **Comprehensive Testing**: Full PHPUnit test coverage
- **Admin Interface**: Complete credential management system

### Import Methods
1. **Gmail Email Processing**
   - OAuth 2.0 authentication
   - Configurable search patterns
   - Automatic attachment processing
   - Batch processing with rate limiting

2. **PDF OCR Processing**
   - Drag-and-drop file upload
   - Server directory import
   - Tesseract OCR integration
   - Multi-format invoice parsing

3. **Duplicate Detection**
   - Order number matching
   - Invoice number matching
   - Date/total/address combination
   - Item similarity analysis
   - Fuzzy text matching with confidence scoring

## 📁 Project Structure

```
amazon-invoices/
├── packages/                           # Standalone composer packages
│   ├── gmail-invoice-processor/        # Gmail processing library
│   │   ├── src/
│   │   │   ├── Interfaces/            # Framework-agnostic interfaces
│   │   │   └── GmailInvoiceProcessor.php
│   │   └── composer.json
│   └── pdf-ocr-processor/              # PDF OCR processing library
│       ├── src/
│       │   ├── Interfaces/            # Framework-agnostic interfaces
│       │   └── PdfOcrProcessor.php
│       └── composer.json
├── src/                               # Main application code
│   ├── Controllers/                   # Web controllers
│   ├── Services/                      # Business logic services
│   │   ├── GmailProcessorWrapper.php  # FA-specific Gmail wrapper
│   │   ├── PdfOcrProcessorWrapper.php # FA-specific PDF wrapper
│   │   ├── UnifiedInvoiceImportService.php
│   │   └── DuplicateDetectionService.php
│   ├── Repositories/                  # Data access layer
│   ├── Models/                        # Data models
│   └── Interfaces/                    # Contracts
├── amazon_invoices/                   # FrontAccounting integration
│   ├── admin/                         # Admin screens
│   ├── includes/                      # Helper functions
│   └── config.php                     # Configuration
├── tests/                             # PHPUnit tests
└── sql/                              # Database schema
```

## 🛠 Installation

### 1. Prerequisites

```bash
# Required PHP extensions
php -m | grep -E "(curl|json|openssl|hash|mysqli)"

# Required system tools for PDF processing
sudo apt-get install tesseract-ocr poppler-utils imagemagick
```

### 2. Install Dependencies

```bash
# Install main project and packages
composer install

# Alternatively, install packages separately
cd packages/gmail-invoice-processor && composer install
cd ../pdf-ocr-processor && composer install
```

### 3. Database Setup

```sql
-- Run the schema
mysql -u username -p database_name < sql/email_pdf_import_schema.sql
```

### 4. Configuration

```php
// amazon_invoices/config.php
define('AMAZON_INVOICES_UPLOAD_PATH', '/path/to/uploads/');
define('AMAZON_INVOICES_TEMP_PATH', '/tmp/amazon_invoices/');
define('AMAZON_INVOICES_ENCRYPTION_KEY', 'your-secret-key-here');
```

## 📖 Usage

### Basic Integration

```php
<?php

require_once 'vendor/autoload.php';

use AmazonInvoices\Services\UnifiedInvoiceImportService;
use AmazonInvoices\Services\DuplicateDetectionService;
use AmazonInvoices\Repositories\FrontAccountingDatabaseRepository;

// Initialize services
$database = new FrontAccountingDatabaseRepository();
$duplicateDetector = new DuplicateDetectionService($database);
$importService = new UnifiedInvoiceImportService($database, $duplicateDetector);

// Process Gmail emails
$emailResults = $importService->processEmails([
    'max_emails' => 50,
    'days_back' => 7
]);

// Process PDF file
$pdfResult = $importService->processPdfFile('/path/to/invoice.pdf');

// Process uploaded files
$uploadResults = $importService->processUploadedFiles($_FILES['pdf_files']);

// Get processing statistics
$stats = $importService->getProcessingStatistics(30);
```

### Web Interface

```php
// Web controller usage
use AmazonInvoices\Controllers\ImportController;

$controller = new ImportController();
$controller->handleRequest(); // Routes based on ?action= parameter

// Available endpoints:
// ?action=dashboard     - Main dashboard
// ?action=emails        - Email import
// ?action=upload        - PDF upload
// ?action=directory     - Directory import
// ?action=review        - Invoice review
// ?action=api           - REST API
```

### API Usage

```bash
# Process emails via API
curl -X POST "https://yoursite.com/amazon_invoices/?action=api" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "process_emails",
    "options": {
      "max_emails": 20,
      "days_back": 3
    }
  }'

# Get pending invoices
curl -X POST "https://yoursite.com/amazon_invoices/?action=api" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get_pending",
    "limit": 50
  }'
```

## 🔧 Configuration

### Gmail Setup

1. **Google Cloud Console Setup**:
   - Create a project in Google Cloud Console
   - Enable Gmail API
   - Create OAuth 2.0 credentials
   - Add authorized redirect URIs

2. **Configure in Admin Panel**:
   - Go to Amazon Invoices → Gmail Credentials
   - Enter Client ID and Client Secret
   - Authorize access

### PDF Processing Setup

1. **System Dependencies**:
   ```bash
   # Ubuntu/Debian
   sudo apt-get install tesseract-ocr poppler-utils imagemagick
   
   # CentOS/RHEL
   sudo yum install tesseract poppler-utils ImageMagick
   ```

2. **File Permissions**:
   ```bash
   chmod 755 amazon_invoices/uploads/
   chmod 755 /tmp/amazon_invoices/
   ```

### Email Search Patterns

Configure email search patterns in the admin panel:

```sql
INSERT INTO amazon_email_patterns (pattern_name, subject_pattern, from_pattern, priority, is_active) VALUES
('Amazon Order Shipped', '%order%shipped%', '%amazon.com%', 1, 1),
('Amazon Receipt', '%receipt%amazon%', '%amazon.com%', 2, 1),
('Order Confirmation', '%order%confirmation%', '%amazon.com%', 3, 1);
```

## 🧪 Testing

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run static analysis
composer stan

# Run specific test suite
./vendor/bin/phpunit tests/Unit/Services/DuplicateDetectionServiceTest.php
```

### Test Structure

```
tests/
├── Unit/                          # Unit tests
│   ├── Models/                    # Model tests
│   └── Services/                  # Service tests
├── Integration/                   # Integration tests
│   └── InvoiceWorkflowTest.php   # End-to-end workflow
└── bootstrap.php                  # Test bootstrap
```

## 🔄 Duplicate Detection

The system uses multiple algorithms to detect duplicates:

### Detection Methods

1. **Exact Order Number Match** (100% confidence)
2. **Exact Invoice Number Match** (95% confidence)
3. **Date + Total + Address Combination** (85% confidence)
4. **Item Similarity Analysis** (75% confidence)
5. **Fuzzy Text Matching** (60-80% confidence)

### Configuration

```php
// Adjust duplicate detection sensitivity
$duplicateDetector = new DuplicateDetectionService($database, [
    'min_confidence_threshold' => 70,
    'fuzzy_match_threshold' => 0.8,
    'date_range_days' => 7
]);
```

## 📊 Monitoring & Maintenance

### Processing Statistics

```php
// Get comprehensive statistics
$stats = $importService->getProcessingStatistics(30);

echo "Email Import Success Rate: " . $stats['email_import']['success_rate'] . "%\n";
echo "Total Duplicates Found: " . $stats['duplicate_detection']['total_duplicates_found'] . "\n";
```

### Cleanup Old Records

```php
// Clean up records older than 90 days
$cleaned = $importService->cleanupOldRecords(90);
echo "Cleaned up " . array_sum($cleaned) . " old records\n";
```

### Recent Activity

```php
// Get recent processing activity
$activity = $importService->getRecentActivity(50);
foreach ($activity as $item) {
    echo "[{$item['created_at']}] {$item['source_type']}: {$item['description']} - {$item['status']}\n";
}
```

## 🔐 Security

### Credential Encryption

All sensitive credentials are encrypted using AES-256-CBC:

```php
// Credentials are automatically encrypted when stored
$credentialService = new AmazonCredentialService($database);
$credentialService->storeCredentials('gmail', [
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret'  // Automatically encrypted
]);
```

### Access Control

- Admin-only access to credential management
- Secure file upload validation
- SQL injection protection
- XSS prevention in web interface

## 🚨 Troubleshooting

### Common Issues

1. **Tesseract Not Found**:
   ```bash
   which tesseract
   # Install if missing: sudo apt-get install tesseract-ocr
   ```

2. **Permission Denied on Upload Directory**:
   ```bash
   chmod 755 amazon_invoices/uploads/
   chown www-data:www-data amazon_invoices/uploads/
   ```

3. **Gmail Authentication Failed**:
   - Check OAuth 2.0 credentials in Google Console
   - Verify redirect URI matches exactly
   - Ensure Gmail API is enabled

4. **PDF Processing Failed**:
   - Check if poppler-utils is installed: `pdftoppm -v`
   - Verify ImageMagick installation: `convert -version`
   - Check file permissions and disk space

### Debug Mode

```php
// Enable debug logging
define('AMAZON_INVOICES_DEBUG', true);

// Check logs
tail -f /tmp/amazon_invoices_debug.log
```

## 🤝 Contributing

1. **Development Setup**:
   ```bash
   git clone https://github.com/yourrepo/amazon-invoices.git
   cd amazon-invoices
   composer install
   composer install --working-dir=packages/gmail-invoice-processor
   composer install --working-dir=packages/pdf-ocr-processor
   ```

2. **Running Tests**:
   ```bash
   composer test
   composer stan
   ```

3. **Code Style**:
   ```bash
   composer cs-check
   composer cs-fix
   ```

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **Tesseract OCR** for text recognition
- **Poppler** for PDF processing
- **Google Gmail API** for email access
- **FrontAccounting** for the base ERP system
- **PHPUnit** for testing framework

## 📞 Support

For support and questions:

1. **Documentation**: Check this README and code comments
2. **Issues**: Create an issue on GitHub
3. **Testing**: Run the integration example: `php integration_example.php`

---

**Built with ❤️ for the FrontAccounting community**
