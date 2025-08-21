<?php
use PHPUnit\Framework\TestCase;

class FAWrapperTest extends TestCase
{
    public function testInstallOptions()
    {
        $hooks = new hooks_amazon_invoices();
        $options = $hooks->install_options();
        $this->assertIsArray($options);
        $this->assertNotEmpty($options);
    }

    public function testInstallAccess()
    {
        $hooks = new hooks_amazon_invoices();
        $access = $hooks->install_access();
        $this->assertIsArray($access);
        $this->assertArrayHasKey('SA_AMAZON_INVOICES', $access);
    }

    public function testInstallTables()
    {
        $hooks = new hooks_amazon_invoices();
        $result = $hooks->install_tables();
        $this->assertTrue($result);
    }

    public function testIsInstalled()
    {
        $hooks = new hooks_amazon_invoices();
        $result = $hooks->is_installed();
        $this->assertTrue($result);
    }
}
