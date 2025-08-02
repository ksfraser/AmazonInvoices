<?php
/**
 * Amazon Invoices Module for FrontAccounting
 * Hooks configuration file
 */

// Include our modern architecture
require_once(__DIR__ . '/../src/Support/FrontAccountingMock.php');

// Autoloader for our classes
spl_autoload_register(function ($class) {
    if (strpos($class, 'AmazonInvoices\\') === 0) {
        $path = __DIR__ . '/../src/' . str_replace(['AmazonInvoices\\', '\\'], ['', '/'], $class) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
});

$hooks = array(
    array(
        'name' => _('Amazon Invoices'),
        'title' => _('Amazon Invoice Management'),
        'description' => _('Download and process Amazon invoices'),
        'active' => 1,
    )
);

class hooks_amazon_invoices extends hooks 
{
    var $module_name = 'amazon_invoices';

    function install_options() 
    {
        global $path_to_root;
        
        switch($this->get_post_value('setting')) {
            case 'install':
                return array(
                    array('type' => 'text', 'name' => 'amazon_email', 'text' => _('Amazon Account Email'), 'default' => ''),
                    array('type' => 'password', 'name' => 'amazon_password', 'text' => _('Amazon Account Password'), 'default' => ''),
                    array('type' => 'text', 'name' => 'download_path', 'text' => _('Invoice Download Path'), 'default' => $path_to_root.'/tmp/amazon_invoices/'),
                    array('type' => 'text', 'name' => 'default_supplier', 'text' => _('Default Amazon Supplier ID'), 'default' => ''),
                );
        }
        return array();
    }

    function install_access() 
    {
        $security_areas = array(
            'SA_AMAZON_INVOICES' => array(_('Amazon Invoices'), 1),
            'SA_AMAZON_DOWNLOAD' => array(_('Download Amazon Invoices'), 2),
            'SA_AMAZON_PROCESS' => array(_('Process Amazon Invoices'), 3),
            'SA_AMAZON_MATCH' => array(_('Match Amazon Items'), 4),
            'SA_AMAZON_PAYMENTS' => array(_('Amazon Payment Allocation'), 5),
        );
        return $security_areas;
    }

    /**
     * Install database tables using our new architecture
     * 
     * @return bool True on success
     */
    function install_tables()
    {
        try {
            // Use our new service-based architecture
            $database = new \AmazonInvoices\Repositories\FrontAccountingDatabaseRepository();
            $installer = new \AmazonInvoices\Services\DatabaseInstallationService($database);
            
            return $installer->install();
            
        } catch (\Exception $e) {
            display_error("Failed to install Amazon Invoice tables: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if module is properly installed
     * 
     * @return bool True if installed
     */
    function is_installed()
    {
        try {
            $database = new \AmazonInvoices\Repositories\FrontAccountingDatabaseRepository();
            $installer = new \AmazonInvoices\Services\DatabaseInstallationService($database);
            
            return $installer->isInstalled();
            
        } catch (\Exception $e) {
            return false;
        }
    }
}
