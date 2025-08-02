# Amazon Invoices Module for FrontAccounting

A comprehensive FrontAccounting module for downloading, processing, and integrating Amazon invoices into your accounting system.

## Features

### Core Functionality
- **Automated Invoice Download**: Download invoices from Amazon for specified date ranges
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

### Data Management
- **Staging Tables**: Safe processing with dedicated staging tables
- **Data Validation**: Comprehensive validation before processing
- **Error Handling**: Robust error handling with detailed logging
- **Data Backup**: Optional backup of processed files

## Installation

### 1. Copy Module Files
Copy the `amazon_invoices` folder to your FrontAccounting `modules` directory:
```
/path/to/frontaccounting/modules/amazon_invoices/
```

### 2. Enable Module
1. Go to **Setup > Extensions**
2. Find "Amazon Invoices" in the list
3. Click **Install/Activate**
4. Configure the module settings during installation

### 3. Configure Settings
1. Go to **Amazon > Settings**
2. Configure:
   - Amazon account credentials
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
