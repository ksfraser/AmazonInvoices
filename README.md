# Amazon Invoice Import System

A comprehensive PHP system for importing Amazon purchase invoices into FrontAccounting (FA) with support for multiple import methods, manual item matching, payment allocation, and review workflows. The system follows SOLID principles and uses dependency injection for maximum flexibility.

## Features

### Multiple Import Methods
- **SP-API Integration**: Official Amazon Selling Partner API for business accounts
- **Gmail Email Processing**: Process Amazon order confirmation emails via Gmail API
- **PDF OCR Processing**: Extract data from Amazon PDF invoices using Tesseract OCR
- **Smart Data Extraction**: Intelligent parsing of invoice data from multiple sources

### Core Functionality
- **Automated Invoice Processing**: Download and process invoices from multiple sources
- **Staging System**: Review and validate invoices before processing them into FrontAccounting
- **Item Matching**: Smart matching of Amazon products to existing FrontAccounting stock items
- **Payment Allocation**: Flexible payment allocation including split payments across multiple accounts
- **Purchase Order Creation**: Generate supplier purchase orders from Amazon invoices
- **Audit Trail**: Complete logging of all processing activities

### Smart Item Matching
- **Automatic Matching**: Rule-based automatic matching using ASIN, SKU, product name, or keywords
- **Manual Matching**: Easy interface for manually matching items to existing stock
- **New Item Creation**: Direct integration with stock item creation for new products
- **Matching Rules Management**: Create and manage custom matching rules with priorities

### Payment Processing
- **Multiple Payment Methods**: Support for credit cards, bank transfers, PayPal, gift cards, and points
- **Split Payments**: Handle invoices paid with multiple payment methods
- **Bank Account Allocation**: Map payment methods to specific FrontAccounting bank accounts
- **Payment Type Integration**: Full integration with FrontAccounting payment terms

### Architecture & Security
- **SOLID Architecture**: Clean, maintainable code with dependency injection
- **Framework Agnostic**: Works with FrontAccounting, WordPress, or any PHP framework
- **Security**: Encrypted credential storage with AES-256-CBC encryption
- **Testing**: Comprehensive PHPUnit test coverage
- **Error Handling**: Robust error handling with detailed logging

## Import Method Details

### 1. SP-API Integration
The official Amazon Selling Partner API provides real-time access to order data:
- **Requirements**: Amazon Developer account, app approval, business/seller account
- **Data Access**: Real-time order information, invoice details, item data
- **Best For**: Businesses with Amazon seller accounts
- **Configuration**: Amazon SP-API credentials, refresh tokens, marketplace settings

### 2. Gmail Email Processing
Process Amazon order confirmation emails automatically:
- **Requirements**: Gmail account, Google Cloud Console project, OAuth2 setup
- **Data Access**: Email parsing for order details, invoice attachments
- **Best For**: Personal Amazon purchases, automated email processing
- **Features**: Configurable search patterns, duplicate detection, batch processing

### 3. PDF OCR Processing
Extract data from Amazon PDF invoices using optical character recognition:
- **Requirements**: Linux server, Tesseract OCR, Poppler utilities, ImageMagick
- **Data Access**: Text extraction from PDF invoices, image preprocessing
- **Best For**: Scanned invoices, PDF downloads, legacy data processing
- **Features**: Multiple language support, confidence thresholds, image enhancement

## System Requirements

### Base Requirements
- **PHP**: 8.0 or higher with strict types enabled
- **Extensions**: PDO, JSON, OpenSSL, cURL, GD/ImageMagick
- **Database**: MySQL 5.7+ or MariaDB 10.2+
- **FrontAccounting**: 2.4+ (if using FA integration)

### Gmail Processing Requirements
- **Google API Client**: `composer require google/apiclient`
- **OAuth2 Setup**: Google Cloud Console project with Gmail API enabled
- **Credentials**: OAuth2 client ID and secret

### PDF OCR Requirements (Linux)
- **Tesseract OCR**: `sudo apt-get install tesseract-ocr tesseract-ocr-eng`
- **Poppler Utils**: `sudo apt-get install poppler-utils`
- **ImageMagick**: `sudo apt-get install imagemagick`
- **Additional Languages**: `sudo apt-get install tesseract-ocr-[lang]`

## Installation

### 1. Copy Module Files
Copy the `amazon_invoices` folder to your FrontAccounting `modules` directory:
```
/path/to/frontaccounting/modules/amazon_invoices/
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Enable Module
1. Go to **Setup > Extensions**
2. Find "Amazon Invoices" in the list
3. Click **Install/Activate**
4. Configure the module settings during installation

### 4. Database Setup
The installation will automatically create the required database tables:
- Amazon invoice staging tables
- Item matching rules and history
- Credential storage (encrypted)
- Email processing logs
- PDF processing logs
- Import statistics

### 5. Configure Import Methods

#### SP-API Setup
1. Go to **Amazon > Amazon Credentials**
2. Select "SP-API" as authentication method
3. Enter your SP-API credentials:
   - Client ID
   - Client Secret
   - Refresh Token
   - AWS Region
   - Marketplace ID

#### Gmail Setup
1. Create Google Cloud Console project
2. Enable Gmail API
3. Create OAuth2 credentials
4. Go to **Amazon > Gmail Credentials**
5. Enter OAuth2 client ID and secret
6. Authorize access to Gmail account

#### PDF OCR Setup
1. Install required system packages (Linux)
2. Go to **Amazon > PDF OCR Config**
3. Configure paths and settings:
   - Tesseract executable path
   - Language data path
   - Processing parameters
   - Temporary directory

## Usage

### Email Import Workflow
1. **Configure Patterns**: Set up email search patterns to identify Amazon emails
2. **Run Email Import**: Process matching emails from Gmail account
3. **Review Results**: Check imported invoices in staging area
4. **Match Items**: Review and approve item matching suggestions
5. **Allocate Payments**: Configure payment method allocations
6. **Process to FA**: Move approved invoices to FrontAccounting

### PDF Import Workflow
1. **Upload PDFs**: Place PDF files in designated import directory
2. **Run OCR Processing**: Extract text and data from PDFs
3. **Review Extraction**: Verify OCR accuracy and data extraction
4. **Follow Standard Flow**: Item matching, payment allocation, FA processing

### SP-API Import Workflow
1. **Configure Date Range**: Set import date parameters
2. **Download Orders**: Retrieve order data from Amazon API
3. **Process Invoices**: Convert order data to invoice format
4. **Follow Standard Flow**: Review, match, allocate, process

## Configuration

### Email Search Patterns
Configure patterns to identify Amazon invoice emails:
- **Subject Patterns**: "Your order has been shipped", "Your Amazon order"
- **From Patterns**: "auto-confirm@amazon.com", "ship-confirm@amazon.com"
- **Regular Expressions**: Support for complex pattern matching
- **Priority System**: Order patterns by matching priority

### Item Matching Rules
Create rules for automatic item matching:
- **ASIN Matching**: Direct Amazon ASIN to stock code mapping
- **SKU Matching**: Amazon SKU to FrontAccounting stock code
- **Keyword Matching**: Pattern-based matching using product names
- **Category Rules**: Match by product category or department

### Payment Method Mapping
Map Amazon payment methods to FA accounts:
- **Credit Cards**: Map to specific credit card accounts
- **Bank Transfers**: Connect to bank accounts
- **Digital Payments**: PayPal, Amazon Pay, etc.
- **Gift Cards/Points**: Special handling for non-cash payments
   - Download path for invoice files
   - Default Amazon supplier
   - Notification settings

### 4. Set Up Supplier
Create a supplier record for Amazon:
1. Go to **Purchases > Suppliers**
2. Add a new supplier for Amazon
3. Note the supplier ID for configuration

## Usage

### 1. Download Invoices
1. Go to **Amazon > Download Invoices**
2. Select date range
3. Click **Download Invoices**
4. Review downloaded invoices in the list

### 2. Item Matching
1. Go to **Amazon > Item Matching**
2. Select an invoice from the dropdown
3. For each unmatched item:
   - Select existing stock item from dropdown, OR
   - Click **Create New Item** to add to inventory
4. Items with similar names will be suggested automatically

### 3. Payment Allocation
1. Go to **Amazon > Payment Allocation** 
2. Select an invoice
3. For each payment method:
   - Select appropriate bank account
   - Choose payment type
   - For complex payments, use **Split** option

### 4. Process to FrontAccounting
1. Go to **Amazon > Staging Review**
2. Select fully matched and allocated invoice
3. Click **Process to FrontAccounting**
4. Review the created supplier invoice and payments

### 5. Manage Matching Rules
1. Go to **Amazon > Matching Rules**
2. Add rules for automatic item matching:
   - **ASIN**: Match by Amazon Standard Identification Number
   - **SKU**: Match by Stock Keeping Unit
   - **Product Name**: Exact product name match
   - **Keyword**: Partial name matching
3. Set priorities for rule application order
4. Test rules with the built-in testing tool

## Database Schema

### Staging Tables

#### amazon_invoices_staging
- `id`: Primary key
- `invoice_number`: Amazon invoice number
- `order_number`: Amazon order number
- `invoice_date`: Invoice date
- `invoice_total`: Total amount
- `tax_amount`: Tax amount
- `shipping_amount`: Shipping cost
- `currency`: Currency code
- `pdf_path`: Path to downloaded PDF
- `raw_data`: JSON of original data
- `status`: Processing status
- `fa_trans_no`: FrontAccounting transaction number

#### amazon_invoice_items_staging
- `id`: Primary key
- `staging_invoice_id`: Link to invoice
- `line_number`: Item line number
- `product_name`: Product description
- `asin`: Amazon Standard Identification Number
- `sku`: Stock Keeping Unit
- `quantity`: Quantity ordered
- `unit_price`: Price per unit
- `total_price`: Total line amount
- `fa_stock_id`: Matched FA stock item
- `fa_item_matched`: Match status flag

#### amazon_payment_staging
- `id`: Primary key
- `staging_invoice_id`: Link to invoice
- `payment_method`: Payment method used
- `payment_reference`: Payment reference/last 4 digits
- `amount`: Payment amount
- `fa_bank_account`: Allocated FA bank account
- `allocation_complete`: Allocation status flag

#### amazon_item_matching_rules
- `id`: Primary key
- `match_type`: Type of matching (asin, sku, product_name, keyword)
- `match_value`: Value to match against
- `fa_stock_id`: Target FA stock item
- `priority`: Rule priority
- `active`: Rule status

#### amazon_processing_log
- `id`: Primary key
- `staging_invoice_id`: Link to invoice
- `action`: Action performed
- `details`: Action details
- `user_id`: User who performed action
- `created_at`: Timestamp

## Configuration

### Module Settings
- **Amazon Email**: Your Amazon account email
- **Amazon Password**: Your Amazon account password (encrypted)
- **Download Path**: Local path for storing downloaded files
- **Default Supplier**: FrontAccounting supplier ID for Amazon
- **Auto Processing**: Enable automatic processing of fully matched invoices
- **Notification Email**: Email for processing notifications
- **Backup Enabled**: Enable file backup after processing
- **Max File Age**: Days to keep downloaded files

### Security Permissions
- `SA_AMAZON_INVOICES`: General Amazon module access
- `SA_AMAZON_DOWNLOAD`: Download invoices permission
- `SA_AMAZON_PROCESS`: Process staging invoices
- `SA_AMAZON_MATCH`: Item matching operations
- `SA_AMAZON_PAYMENTS`: Payment allocation

## Workflow

### Typical Processing Flow
1. **Download** → Download invoices from Amazon
2. **Auto-Match** → System automatically matches known items
3. **Manual Match** → User matches remaining items
4. **Allocate Payments** → Configure payment method allocations
5. **Review** → Final review of staging invoice
6. **Process** → Create FA supplier invoice and payments
7. **Reconcile** → Standard FA reconciliation processes

### Status Progression
- `pending` → Initial download status
- `processing` → Being matched/allocated
- `completed` → Successfully processed to FA
- `error` → Processing error occurred

## API Integration

The module is designed to be extensible for Amazon API integration:

### Amazon Seller Partner API (SP-API)
- Replace sample data generation with real API calls
- Implement OAuth 2.0 authentication
- Add real-time invoice download capability
- Support for multiple Amazon marketplaces

### Extension Points
- `AmazonInvoiceDownloader::download_invoices()` - Replace with API calls
- `AmazonInvoiceDownloader::parse_pdf_invoice()` - Add PDF parsing
- Custom matching algorithms in `find_matching_fa_item()`

## Troubleshooting

### Common Issues

#### Download Directory Not Writable
- Ensure the download directory exists and is writable by the web server
- Check file permissions (755 for directories, 644 for files)

#### Missing Database Tables
- Run the module installation again
- Check database user permissions for CREATE TABLE

#### Items Not Auto-Matching
- Review matching rules in **Amazon > Matching Rules**
- Check rule priorities and active status
- Use the test matching tool to debug rules

#### Payment Allocation Issues
- Verify bank accounts are set up in FrontAccounting
- Check payment types configuration
- Ensure payment amounts match invoice totals

### Debug Mode
Enable debug logging by setting in your FA config:
```php
$debug = 1;
```

### Log Files
Check the following logs for issues:
- FrontAccounting error log
- Web server error log
- Module processing log in database

## Support

### Documentation
- FrontAccounting Wiki: [Link to FA documentation]
- Module GitHub Repository: [Link to repository]

### Reporting Issues
When reporting issues, please include:
1. FrontAccounting version
2. Module version
3. Error messages from logs
4. Steps to reproduce the issue
5. Sample data (anonymized)

## License

This module is released under the same license as FrontAccounting.

## Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## Version History

### 1.0.0
- Initial release
- Basic download and processing functionality
- Item matching system
- Payment allocation
- Staging review interface
- Matching rules management
