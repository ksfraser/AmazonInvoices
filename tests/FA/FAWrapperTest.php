<?php
// tests/FA/FAWrapperTest.php
use PHPUnit\Framework\TestCase;

class FAWrapperTest extends TestCase
{
    public function testHooksInstallTables()
    {
        $hooks = new hooks_amazon_invoices();
        $result = $hooks->install_tables();
        $this->assertTrue($result, 'Database tables should be installed successfully');
    }

    public function testHooksIsInstalled()
    {
        $hooks = new hooks_amazon_invoices();
        $this->assertTrue($hooks->is_installed(), 'Module should report as installed');
    }

    public function testUIActionsDashboard()
    {
        // Simulate FA UI dashboard action
        $_GET['action'] = 'dashboard';
        ob_start();
        amazon_invoices_ui_handle();
        $output = ob_get_clean();
        $this->assertStringContainsString('Amazon', $output, 'Dashboard should render Amazon content');
    }

    public function testUIActionsUpload()
    {
        $_GET['action'] = 'upload';
        ob_start();
        amazon_invoices_ui_handle();
        $output = ob_get_clean();
        $this->assertStringContainsString('PDF', $output, 'Upload should render PDF upload content');
    }
}
