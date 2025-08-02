# Amazon Invoices Module - Installation Guide

## Quick Start Installation

### Step 1: Copy Files
Copy the entire `amazon_invoices` folder to your FrontAccounting modules directory:

```
/path/to/frontaccounting/modules/amazon_invoices/
```

### Step 2: Install Module in FrontAccounting
1. Login to FrontAccounting as an administrator
2. Go to **Setup → Extensions**
3. Find "Amazon Invoices" in the Available Extensions list
4. Click **Install/Activate**
5. Configure initial settings during installation:
   - Amazon account email
   - Download path (ensure it's writable)
   - Default Amazon supplier ID

### Step 3: Set Up Permissions
1. Go to **Setup → Access Setup**
2. Configure user roles with appropriate Amazon module permissions:
   - `SA_AMAZON_INVOICES`: General module access
   - `SA_AMAZON_DOWNLOAD`: Download invoices
   - `SA_AMAZON_PROCESS`: Process staging invoices
   - `SA_AMAZON_MATCH`: Item matching
   - `SA_AMAZON_PAYMENTS`: Payment allocation

### Step 4: Create Amazon Supplier
1. Go to **Purchases → Suppliers**
2. Add new supplier with details:
   - Name: "Amazon" (or your preferred name)
   - Contact information
   - Payment terms
   - Currency settings
3. Note the Supplier ID for module configuration

### Step 5: Configure Module Settings
1. Go to **Amazon → Settings**
2. Configure:
   - **Amazon Email**: Your Amazon account email
   - **Amazon Password**: Your Amazon account password
   - **Download Path**: Writable directory for invoice files
   - **Default Supplier**: The Amazon supplier ID from Step 4
   - **Notification Email**: Email for processing notifications

### Step 6: Test Installation
1. Go to **Amazon → Settings**
2. Check the "System Check" section at the bottom
3. Verify all status indicators show "OK" or "Warning" (no "Error")

## Directory Structure

After installation, your module should have this structure:

```
modules/amazon_invoices/
├── config.php                 # Module configuration
├── hooks.php                  # FrontAccounting hooks
├── install.php               # Database installation
├── download.php              # Download interface
├── staging_review.php        # Review staging invoices
├── item_matching.php         # Item matching interface
├── payment_allocation.php    # Payment allocation interface
├── matching_rules.php        # Matching rules management
├── settings.php             # Module settings
├── example_usage.php        # Usage examples
└── includes/
    ├── db_functions.php      # Database functions
    └── amazon_downloader.php # Core download logic
```

## Database Tables

The module creates these tables in your FrontAccounting database:

- `amazon_invoices_staging` - Staging invoices
- `amazon_invoice_items_staging` - Staging invoice items
- `amazon_payment_staging` - Staging payment information
- `amazon_item_matching_rules` - Item matching rules
- `amazon_processing_log` - Activity log

## File Permissions

Ensure these directories are writable by your web server:

```bash
# Default download directory
chmod 755 /path/to/frontaccounting/tmp/amazon_invoices/
chmod 644 /path/to/frontaccounting/tmp/amazon_invoices/*

# Or your custom download directory
chmod 755 /your/custom/download/path/
```

## First Use

### 1. Set Up Matching Rules
1. Go to **Amazon → Matching Rules**
2. Add rules for your common products:
   - ASIN matches for specific products
   - SKU matches for your inventory codes
   - Keyword matches for product categories

### 2. Download Test Invoices
1. Go to **Amazon → Download Invoices**
2. Select a small date range (1-2 days)
3. Click **Download Invoices**
4. Review the downloaded invoices

### 3. Practice the Workflow
1. **Item Matching**: Go to **Amazon → Item Matching** and match items to your inventory
2. **Payment Allocation**: Go to **Amazon → Payment Allocation** and allocate payments to bank accounts
3. **Review & Process**: Go to **Amazon → Staging Review** and process the invoice

## Troubleshooting

### Common Installation Issues

#### "Database tables missing" error
- Check database user permissions for CREATE TABLE
- Manually run the install script from **Setup → Extensions**

#### "Download directory not writable" warning
```bash
# Fix permissions on the download directory
sudo chown -R www-data:www-data /path/to/download/directory/
sudo chmod -R 755 /path/to/download/directory/
```

#### Module not appearing in Extensions list
- Check file permissions on the module directory
- Verify the `config.php` file is present and readable
- Check web server error logs for PHP syntax errors

#### "Default supplier not configured" warning
- Create an Amazon supplier in **Purchases → Suppliers**
- Note the supplier ID and configure it in **Amazon → Settings**

### Getting Help

1. **Module Settings**: Check the system status at the bottom of **Amazon → Settings**
2. **Error Logs**: Check your web server error logs for detailed error messages
3. **Activity Log**: Review the activity log in **Amazon → Settings** for processing history
4. **FrontAccounting Logs**: Check the standard FA error logs

## Next Steps

After successful installation:

1. **Read the Documentation**: Review the full README.md for detailed usage instructions
2. **Configure Matching Rules**: Set up comprehensive item matching rules
3. **Train Users**: Train your accounting staff on the invoice processing workflow
4. **Test Thoroughly**: Process a few test invoices before going live
5. **Schedule Regular Downloads**: Set up a routine for downloading and processing invoices

## Uninstallation

To remove the module:

1. Go to **Setup → Extensions**
2. Find "Amazon Invoices" and click **Uninstall**
3. This will remove the database tables and module configuration
4. Manually delete the module files from the `modules/amazon_invoices/` directory

**Warning**: Uninstalling will permanently delete all staging data and processing history.
