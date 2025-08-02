<?php

declare(strict_types=1);

namespace AmazonInvoices\Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use AmazonInvoices\Models\InvoiceItem;

/**
 * Unit tests for InvoiceItem model
 * 
 * @package AmazonInvoices\Tests\Unit\Models
 * @author  Your Name
 * @since   1.0.0
 */
class InvoiceItemTest extends TestCase
{
    private InvoiceItem $item;

    protected function setUp(): void
    {
        $this->item = new InvoiceItem(
            1,
            'Wireless Bluetooth Headphones - Premium Quality',
            2,
            24.99,
            49.98
        );
    }

    public function testItemCreation(): void
    {
        $this->assertEquals(1, $this->item->getLineNumber());
        $this->assertEquals('Wireless Bluetooth Headphones - Premium Quality', $this->item->getProductName());
        $this->assertEquals(2, $this->item->getQuantity());
        $this->assertEquals(24.99, $this->item->getUnitPrice());
        $this->assertEquals(49.98, $this->item->getTotalPrice());
        $this->assertFalse($this->item->isMatched());
    }

    public function testItemValidation(): void
    {
        $this->assertTrue($this->item->validate());
        
        // Test invalid line number
        $invalidItem = new InvoiceItem(0, 'Product', 1, 10.0, 10.0);
        $this->assertFalse($invalidItem->validate());
        
        // Test empty product name
        $invalidItem2 = new InvoiceItem(1, '', 1, 10.0, 10.0);
        $this->assertFalse($invalidItem2->validate());
        
        // Test zero quantity
        $invalidItem3 = new InvoiceItem(1, 'Product', 0, 10.0, 0.0);
        $this->assertFalse($invalidItem3->validate());
        
        // Test negative prices
        $invalidItem4 = new InvoiceItem(1, 'Product', 1, -10.0, 10.0);
        $this->assertFalse($invalidItem4->validate());
    }

    public function testSettersAndGetters(): void
    {
        $this->item->setAsin('B08XYZ123');
        $this->assertEquals('B08XYZ123', $this->item->getAsin());
        
        $this->item->setSku('WBH-001');
        $this->assertEquals('WBH-001', $this->item->getSku());
        
        $this->item->setTaxAmount(3.99);
        $this->assertEquals(3.99, $this->item->getTaxAmount());
        
        $this->item->setFaStockId('HEADPHONES-001');
        $this->assertEquals('HEADPHONES-001', $this->item->getFaStockId());
        
        $this->item->setMatched(true);
        $this->assertTrue($this->item->isMatched());
        
        $this->item->setMatchType('asin');
        $this->assertEquals('asin', $this->item->getMatchType());
        
        $this->item->setSupplierItemCode('AMZ-WBH-001');
        $this->assertEquals('AMZ-WBH-001', $this->item->getSupplierItemCode());
        
        $this->item->setNotes('Test notes');
        $this->assertEquals('Test notes', $this->item->getNotes());
    }

    public function testMatchToStock(): void
    {
        $this->assertFalse($this->item->isMatched());
        
        $this->item->matchToStock('HEADPHONES-001', 'asin', 'AMZ-WBH-001');
        
        $this->assertTrue($this->item->isMatched());
        $this->assertEquals('HEADPHONES-001', $this->item->getFaStockId());
        $this->assertEquals('asin', $this->item->getMatchType());
        $this->assertEquals('AMZ-WBH-001', $this->item->getSupplierItemCode());
    }

    public function testArrayConversion(): void
    {
        $this->item->setAsin('B08XYZ123');
        $this->item->setSku('WBH-001');
        $this->item->setTaxAmount(3.99);
        
        $array = $this->item->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals(1, $array['line_number']);
        $this->assertEquals('Wireless Bluetooth Headphones - Premium Quality', $array['product_name']);
        $this->assertEquals('B08XYZ123', $array['asin']);
        $this->assertEquals('WBH-001', $array['sku']);
        $this->assertEquals(2, $array['quantity']);
        $this->assertEquals(24.99, $array['unit_price']);
        $this->assertEquals(49.98, $array['total_price']);
        $this->assertEquals(3.99, $array['tax_amount']);
        $this->assertEquals(0, $array['fa_item_matched']);
    }

    public function testFromArray(): void
    {
        $data = [
            'line_number' => 2,
            'product_name' => 'USB-C Cable - 6 feet',
            'asin' => 'B09ABC456',
            'sku' => 'USB-C-6FT',
            'quantity' => 3,
            'unit_price' => 12.99,
            'total_price' => 38.97,
            'tax_amount' => 2.77,
            'fa_stock_id' => 'CABLE-USBC-6',
            'fa_item_matched' => 1,
            'item_match_type' => 'sku',
            'supplier_item_code' => 'AMZ-USB-C-6',
            'notes' => 'Matched automatically'
        ];

        $item = InvoiceItem::fromArray($data);
        
        $this->assertEquals(2, $item->getLineNumber());
        $this->assertEquals('USB-C Cable - 6 feet', $item->getProductName());
        $this->assertEquals('B09ABC456', $item->getAsin());
        $this->assertEquals('USB-C-6FT', $item->getSku());
        $this->assertEquals(3, $item->getQuantity());
        $this->assertEquals(12.99, $item->getUnitPrice());
        $this->assertEquals(38.97, $item->getTotalPrice());
        $this->assertEquals(2.77, $item->getTaxAmount());
        $this->assertEquals('CABLE-USBC-6', $item->getFaStockId());
        $this->assertTrue($item->isMatched());
        $this->assertEquals('sku', $item->getMatchType());
        $this->assertEquals('AMZ-USB-C-6', $item->getSupplierItemCode());
        $this->assertEquals('Matched automatically', $item->getNotes());
    }

    public function testPriceCalculation(): void
    {
        // Test if total price matches unit price * quantity
        $calculatedTotal = $this->item->getUnitPrice() * $this->item->getQuantity();
        $this->assertEquals($calculatedTotal, $this->item->getTotalPrice());
        
        // Test with different values
        $item2 = new InvoiceItem(2, 'Test Product', 5, 10.50, 52.50);
        $this->assertEquals(52.50, $item2->getTotalPrice());
        $this->assertEquals(10.50 * 5, $item2->getTotalPrice());
    }

    public function testCalculateTotalWithTax(): void
    {
        $this->item->setTaxAmount(4.99);
        
        $totalWithTax = $this->item->getTotalPrice() + $this->item->getTaxAmount();
        $this->assertEquals(54.97, $totalWithTax); // 49.98 + 4.99
    }

    public function testClone(): void
    {
        $this->item->setAsin('B08XYZ123');
        $this->item->setMatched(true);
        $this->item->setFaStockId('TEST-001');
        
        $cloned = clone $this->item;
        
        // Should have same data but different object instances
        $this->assertEquals($this->item->getAsin(), $cloned->getAsin());
        $this->assertEquals($this->item->isMatched(), $cloned->isMatched());
        $this->assertEquals($this->item->getFaStockId(), $cloned->getFaStockId());
        $this->assertNotSame($this->item, $cloned);
        
        // ID should be reset in clone
        $this->item->setId(123);
        $cloned2 = clone $this->item;
        $this->assertNull($cloned2->getId());
    }

    public function testGetDisplayName(): void
    {
        // Test with ASIN
        $this->item->setAsin('B08XYZ123');
        $displayName = $this->item->getProductName();
        $this->assertStringContainsString('Wireless Bluetooth Headphones', $displayName);
        
        // Test with SKU
        $this->item->setSku('WBH-001');
        $this->assertNotEmpty($this->item->getSku());
    }

    public function testMatchMethodBehavior(): void
    {
        // Test basic matching
        $this->item->matchToStock('TEST-001', 'manual');
        $this->assertTrue($this->item->isMatched());
        $this->assertEquals('TEST-001', $this->item->getFaStockId());
        
        // Test with supplier code
        $this->item->matchToStock('TEST-002', 'asin', 'SUPPLIER-CODE-123');
        $this->assertEquals('TEST-002', $this->item->getFaStockId());
        $this->assertEquals('SUPPLIER-CODE-123', $this->item->getSupplierItemCode());
    }
}
