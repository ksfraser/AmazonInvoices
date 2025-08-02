<?php

declare(strict_types=1);

namespace AmazonInvoices\Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use AmazonInvoices\Models\Invoice;
use AmazonInvoices\Models\InvoiceItem;
use AmazonInvoices\Models\Payment;

/**
 * Unit tests for Invoice model
 * 
 * @package AmazonInvoices\Tests\Unit\Models
 * @author  Your Name
 * @since   1.0.0
 */
class InvoiceTest extends TestCase
{
    private Invoice $invoice;

    protected function setUp(): void
    {
        $this->invoice = new Invoice(
            'AMZ-001-123456',
            'ORD-001-789012',
            new \DateTime('2025-01-15'),
            99.99,
            'USD'
        );
    }

    public function testInvoiceCreation(): void
    {
        $this->assertEquals('AMZ-001-123456', $this->invoice->getInvoiceNumber());
        $this->assertEquals('ORD-001-789012', $this->invoice->getOrderNumber());
        $this->assertEquals('2025-01-15', $this->invoice->getInvoiceDate()->format('Y-m-d'));
        $this->assertEquals(99.99, $this->invoice->getTotalAmount());
        $this->assertEquals('USD', $this->invoice->getCurrency());
        $this->assertEquals('pending', $this->invoice->getStatus());
    }

    public function testInvoiceValidation(): void
    {
        $this->assertTrue($this->invoice->validate());
        
        // Test empty invoice number
        $invalidInvoice = new Invoice('', 'ORD-001', new \DateTime(), 100.0, 'USD');
        $this->assertFalse($invalidInvoice->validate());
        
        // Test negative amount
        $invalidInvoice2 = new Invoice('AMZ-001', 'ORD-001', new \DateTime(), -10.0, 'USD');
        $this->assertFalse($invalidInvoice2->validate());
    }

    public function testAddItem(): void
    {
        $item = new InvoiceItem(1, 'Test Product', 2, 24.99, 49.98);
        $this->invoice->addItem($item);
        
        $items = $this->invoice->getItems();
        $this->assertCount(1, $items);
        $this->assertEquals('Test Product', $items[0]->getProductName());
    }

    public function testAddPayment(): void
    {
        $payment = new Payment('credit_card', 50.0, 'CC-12345');
        $this->invoice->addPayment($payment);
        
        $payments = $this->invoice->getPayments();
        $this->assertCount(1, $payments);
        $this->assertEquals('credit_card', $payments[0]->getPaymentMethod());
        $this->assertEquals(50.0, $payments[0]->getAmount());
    }

    public function testStatusTransitions(): void
    {
        // Test valid status transitions
        $this->assertTrue($this->invoice->setStatus('processing'));
        $this->assertEquals('processing', $this->invoice->getStatus());
        
        $this->assertTrue($this->invoice->setStatus('completed'));
        $this->assertEquals('completed', $this->invoice->getStatus());
        
        // Test invalid status
        $this->assertFalse($this->invoice->setStatus('invalid_status'));
        $this->assertEquals('completed', $this->invoice->getStatus()); // Should remain unchanged
    }

    public function testArrayConversion(): void
    {
        $this->invoice->setTaxAmount(5.99);
        $this->invoice->setShippingAmount(3.99);
        
        $array = $this->invoice->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals('AMZ-001-123456', $array['invoice_number']);
        $this->assertEquals('ORD-001-789012', $array['order_number']);
        $this->assertEquals(99.99, $array['total_amount']);
        $this->assertEquals(5.99, $array['tax_amount']);
        $this->assertEquals(3.99, $array['shipping_amount']);
        $this->assertEquals('USD', $array['currency']);
    }

    public function testFromArray(): void
    {
        $data = [
            'invoice_number' => 'AMZ-002-654321',
            'order_number' => 'ORD-002-210987',
            'invoice_date' => '2025-01-20',
            'total_amount' => 149.99,
            'tax_amount' => 12.99,
            'shipping_amount' => 7.99,
            'currency' => 'CAD',
            'status' => 'processing',
            'notes' => 'Test notes'
        ];

        $invoice = Invoice::fromArray($data);
        
        $this->assertEquals('AMZ-002-654321', $invoice->getInvoiceNumber());
        $this->assertEquals('ORD-002-210987', $invoice->getOrderNumber());
        $this->assertEquals('2025-01-20', $invoice->getInvoiceDate()->format('Y-m-d'));
        $this->assertEquals(149.99, $invoice->getTotalAmount());
        $this->assertEquals(12.99, $invoice->getTaxAmount());
        $this->assertEquals(7.99, $invoice->getShippingAmount());
        $this->assertEquals('CAD', $invoice->getCurrency());
        $this->assertEquals('processing', $invoice->getStatus());
        $this->assertEquals('Test notes', $invoice->getNotes());
    }

    public function testCalculateTotal(): void
    {
        $item1 = new InvoiceItem(1, 'Product 1', 2, 24.99, 49.98);
        $item2 = new InvoiceItem(2, 'Product 2', 1, 15.00, 15.00);
        
        $this->invoice->addItem($item1);
        $this->invoice->addItem($item2);
        $this->invoice->setShippingAmount(5.99);
        $this->invoice->setTaxAmount(4.50);
        
        $calculatedTotal = $this->invoice->calculateTotal();
        $this->assertEquals(75.47, $calculatedTotal); // 49.98 + 15.00 + 5.99 + 4.50
    }

    public function testGetItemCount(): void
    {
        $this->assertEquals(0, $this->invoice->getItemCount());
        
        $item1 = new InvoiceItem(1, 'Product 1', 2, 24.99, 49.98);
        $item2 = new InvoiceItem(2, 'Product 2', 1, 15.00, 15.00);
        
        $this->invoice->addItem($item1);
        $this->assertEquals(1, $this->invoice->getItemCount());
        
        $this->invoice->addItem($item2);
        $this->assertEquals(2, $this->invoice->getItemCount());
    }

    public function testGetPaymentTotal(): void
    {
        $payment1 = new Payment('credit_card', 50.0, 'CC-12345');
        $payment2 = new Payment('amazon_credit', 25.0, 'AC-67890');
        
        $this->invoice->addPayment($payment1);
        $this->invoice->addPayment($payment2);
        
        $this->assertEquals(75.0, $this->invoice->getPaymentTotal());
    }

    public function testIsFullyPaid(): void
    {
        $this->invoice = new Invoice('AMZ-001', 'ORD-001', new \DateTime(), 100.0, 'USD');
        
        $this->assertFalse($this->invoice->isFullyPaid());
        
        $payment1 = new Payment('credit_card', 60.0, 'CC-12345');
        $this->invoice->addPayment($payment1);
        $this->assertFalse($this->invoice->isFullyPaid());
        
        $payment2 = new Payment('amazon_credit', 40.0, 'AC-67890');
        $this->invoice->addPayment($payment2);
        $this->assertTrue($this->invoice->isFullyPaid());
    }

    public function testClone(): void
    {
        $item = new InvoiceItem(1, 'Test Product', 1, 25.0, 25.0);
        $payment = new Payment('credit_card', 25.0, 'CC-12345');
        
        $this->invoice->addItem($item);
        $this->invoice->addPayment($payment);
        
        $cloned = clone $this->invoice;
        
        // Should have same data but different object instances
        $this->assertEquals($this->invoice->getInvoiceNumber(), $cloned->getInvoiceNumber());
        $this->assertEquals($this->invoice->getItemCount(), $cloned->getItemCount());
        $this->assertNotSame($this->invoice, $cloned);
        $this->assertNotSame($this->invoice->getItems()[0], $cloned->getItems()[0]);
    }
}
