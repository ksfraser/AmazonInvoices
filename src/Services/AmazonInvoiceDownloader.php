<?php

declare(strict_types=1);

namespace AmazonInvoices\Services;

use AmazonInvoices\Interfaces\InvoiceDownloaderInterface;
use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;
use AmazonInvoices\Models\Invoice;
use AmazonInvoices\Models\InvoiceItem;
use AmazonInvoices\Models\Payment;

/**
 * Amazon Invoice Downloader Service
 * 
 * Handles downloading and parsing of Amazon invoices
 * This implementation uses sample data - extend for real Amazon API integration
 * 
 * @package AmazonInvoices\Services
 * @author  Your Name
 * @since   1.0.0
 */
class AmazonInvoiceDownloader implements InvoiceDownloaderInterface
{
    /**
     * @var DatabaseRepositoryInterface Database repository
     */
    private DatabaseRepositoryInterface $database;

    /**
     * @var array Authentication credentials
     */
    private array $credentials = [];

    /**
     * @var string Download path for invoice files
     */
    private string $downloadPath;

    /**
     * Constructor
     * 
     * @param DatabaseRepositoryInterface $database Database repository
     * @param string $downloadPath Path for downloading files
     */
    public function __construct(DatabaseRepositoryInterface $database, string $downloadPath)
    {
        $this->database = $database;
        $this->downloadPath = $downloadPath;
        
        // Ensure download directory exists
        if (!is_dir($this->downloadPath)) {
            if (!mkdir($this->downloadPath, 0755, true)) {
                throw new \RuntimeException("Failed to create download directory: {$this->downloadPath}");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function downloadInvoices(\DateTime $startDate, \DateTime $endDate): array
    {
        // Validate date range
        if ($startDate > $endDate) {
            throw new \InvalidArgumentException('Start date must be before end date');
        }

        $invoices = [];

        try {
            // This is a sample implementation
            // In production, this would connect to Amazon SP-API or use web scraping
            $sampleInvoices = $this->generateSampleInvoices($startDate, $endDate);
            
            foreach ($sampleInvoices as $invoiceData) {
                $invoice = $this->createInvoiceFromData($invoiceData);
                $invoices[] = $invoice;
            }

        } catch (\Exception $e) {
            throw new \Exception("Failed to download Amazon invoices: " . $e->getMessage(), 0, $e);
        }

        return $invoices;
    }

    /**
     * {@inheritdoc}
     */
    public function parseInvoiceData(string $rawData, string $format = 'pdf'): Invoice
    {
        switch ($format) {
            case 'pdf':
                return $this->parsePdfInvoice($rawData);
            case 'json':
                return $this->parseJsonInvoice($rawData);
            case 'html':
                return $this->parseHtmlInvoice($rawData);
            default:
                throw new \InvalidArgumentException("Unsupported format: {$format}");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateInvoice(Invoice $invoice): array
    {
        $errors = [];

        // Basic invoice validation
        $basicErrors = $invoice->validate();
        $errors = array_merge($errors, $basicErrors);

        // Validate all items
        foreach ($invoice->getItems() as $item) {
            $itemErrors = $item->validate();
            foreach ($itemErrors as $error) {
                $errors[] = "Item {$item->getLineNumber()}: {$error}";
            }
        }

        // Validate all payments
        foreach ($invoice->getPayments() as $payment) {
            $paymentErrors = $payment->validate();
            foreach ($paymentErrors as $error) {
                $errors[] = "Payment {$payment->getPaymentMethod()}: {$error}";
            }
        }

        // Additional business rules
        if (count($invoice->getItems()) === 0) {
            $errors[] = 'Invoice must have at least one item';
        }

        if (count($invoice->getPayments()) === 0) {
            $errors[] = 'Invoice must have at least one payment method';
        }

        return $errors;
    }

    /**
     * {@inheritdoc}
     */
    public function setCredentials(array $credentials): void
    {
        $requiredKeys = ['email', 'password'];
        foreach ($requiredKeys as $key) {
            if (!isset($credentials[$key])) {
                throw new \InvalidArgumentException("Missing required credential: {$key}");
            }
        }

        $this->credentials = $credentials;
    }

    /**
     * {@inheritdoc}
     */
    public function testConnection(): bool
    {
        // In production, this would test connection to Amazon services
        // For now, just check if credentials are set
        return !empty($this->credentials['email']) && !empty($this->credentials['password']);
    }

    /**
     * Generate sample invoice data for testing
     * 
     * @param \DateTime $startDate Start date
     * @param \DateTime $endDate End date
     * @return array Array of sample invoice data
     */
    private function generateSampleInvoices(\DateTime $startDate, \DateTime $endDate): array
    {
        $invoices = [];
        $daysDiff = $endDate->diff($startDate)->days;
        $numberOfInvoices = min(5, max(1, intval($daysDiff / 7))); // About 1 invoice per week

        for ($i = 1; $i <= $numberOfInvoices; $i++) {
            $invoiceDate = clone $startDate;
            $invoiceDate->add(new \DateInterval("P" . rand(0, $daysDiff) . "D"));

            $invoices[] = [
                'invoice_number' => 'AMZ-' . $invoiceDate->format('Ymd') . '-' . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
                'order_number' => '123-' . rand(1000000, 9999999) . '-' . rand(1000000, 9999999),
                'invoice_date' => $invoiceDate,
                'items' => $this->generateSampleItems(),
                'payments' => $this->generateSamplePayments(),
                'currency' => 'USD',
            ];
        }

        return $invoices;
    }

    /**
     * Generate sample items for testing
     * 
     * @return array Array of sample items
     */
    private function generateSampleItems(): array
    {
        $sampleProducts = [
            ['name' => 'Wireless Bluetooth Headphones', 'asin' => 'B08N5WRWNW', 'sku' => 'WBH-001'],
            ['name' => 'USB-C to USB-A Cable 6ft', 'asin' => 'B07232M876', 'sku' => 'CABLE-USB-001'],
            ['name' => 'Ergonomic Wireless Mouse', 'asin' => 'B085BTK9P7', 'sku' => 'MOUSE-ERG-001'],
            ['name' => 'Mechanical Gaming Keyboard', 'asin' => 'B07ZGDPT4M', 'sku' => 'KB-MECH-001'],
            ['name' => 'Portable Phone Charger 10000mAh', 'asin' => 'B07YSY9N19', 'sku' => 'CHRG-PORT-001'],
        ];

        $items = [];
        $numberOfItems = rand(1, 3);

        for ($i = 0; $i < $numberOfItems; $i++) {
            $product = $sampleProducts[array_rand($sampleProducts)];
            $quantity = rand(1, 2);
            $unitPrice = rand(1500, 8000) / 100; // $15.00 - $80.00
            $totalPrice = $quantity * $unitPrice;

            $items[] = [
                'line_number' => $i + 1,
                'product_name' => $product['name'],
                'asin' => $product['asin'],
                'sku' => $product['sku'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'tax_amount' => $totalPrice * 0.08, // 8% tax
            ];
        }

        return $items;
    }

    /**
     * Generate sample payments for testing
     * 
     * @return array Array of sample payments
     */
    private function generateSamplePayments(): array
    {
        $paymentMethods = ['credit_card', 'paypal', 'gift_card'];
        $selectedMethod = $paymentMethods[array_rand($paymentMethods)];

        $payments = [];

        if ($selectedMethod === 'gift_card' && rand(0, 1)) {
            // Sometimes split between gift card and credit card
            $giftCardAmount = rand(1000, 3000) / 100; // $10.00 - $30.00
            $payments[] = [
                'method' => 'gift_card',
                'reference' => 'Gift Card ****' . rand(1000, 9999),
                'amount' => $giftCardAmount,
            ];
            $payments[] = [
                'method' => 'credit_card',
                'reference' => '**** **** **** ' . rand(1000, 9999),
                'amount' => 0, // Will be calculated based on total
            ];
        } else {
            $reference = match ($selectedMethod) {
                'credit_card' => '**** **** **** ' . rand(1000, 9999),
                'paypal' => 'PayPal transaction ' . strtoupper(bin2hex(random_bytes(4))),
                'gift_card' => 'Gift Card ****' . rand(1000, 9999),
                default => 'Payment reference'
            };

            $payments[] = [
                'method' => $selectedMethod,
                'reference' => $reference,
                'amount' => 0, // Will be calculated based on total
            ];
        }

        return $payments;
    }

    /**
     * Create Invoice object from raw data
     * 
     * @param array $invoiceData Raw invoice data
     * @return Invoice Created invoice object
     */
    private function createInvoiceFromData(array $invoiceData): Invoice
    {
        // Calculate totals
        $itemsTotal = 0;
        $taxTotal = 0;
        foreach ($invoiceData['items'] as $itemData) {
            $itemsTotal += $itemData['total_price'];
            $taxTotal += $itemData['tax_amount'];
        }

        $shippingAmount = rand(0, 1000) / 100; // $0.00 - $10.00
        $totalAmount = $itemsTotal + $shippingAmount;

        // Create invoice
        $invoice = new Invoice(
            $invoiceData['invoice_number'],
            $invoiceData['order_number'],
            $invoiceData['invoice_date'],
            $totalAmount,
            $invoiceData['currency']
        );

        $invoice->setTaxAmount($taxTotal);
        $invoice->setShippingAmount($shippingAmount);
        $invoice->setRawData(json_encode($invoiceData));

        // Add items
        foreach ($invoiceData['items'] as $itemData) {
            $item = new InvoiceItem(
                $itemData['line_number'],
                $itemData['product_name'],
                $itemData['quantity'],
                $itemData['unit_price'],
                $itemData['total_price']
            );

            $item->setAsin($itemData['asin']);
            $item->setSku($itemData['sku']);
            $item->setTaxAmount($itemData['tax_amount']);

            $invoice->addItem($item);
        }

        // Add payments and calculate remaining amounts
        $remainingAmount = $totalAmount;
        foreach ($invoiceData['payments'] as $index => $paymentData) {
            $amount = $paymentData['amount'];
            if ($amount === 0) {
                $amount = $remainingAmount; // Use remaining amount for last/only payment
            }

            $payment = new Payment(
                $paymentData['method'],
                $amount,
                $paymentData['reference']
            );

            $invoice->addPayment($payment);
            $remainingAmount -= $amount;
        }

        return $invoice;
    }

    /**
     * Parse PDF invoice (placeholder implementation)
     * 
     * @param string $pdfData PDF data
     * @return Invoice Parsed invoice
     */
    private function parsePdfInvoice(string $pdfData): Invoice
    {
        // This would use a PDF parsing library like TCPDF, FPDF, or external services
        throw new \RuntimeException('PDF parsing not implemented - use external PDF parser');
    }

    /**
     * Parse JSON invoice
     * 
     * @param string $jsonData JSON data
     * @return Invoice Parsed invoice
     */
    private function parseJsonInvoice(string $jsonData): Invoice
    {
        $data = json_decode($jsonData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON data: ' . json_last_error_msg());
        }

        return $this->createInvoiceFromData($data);
    }

    /**
     * Parse HTML invoice (placeholder implementation)
     * 
     * @param string $htmlData HTML data
     * @return Invoice Parsed invoice
     */
    private function parseHtmlInvoice(string $htmlData): Invoice
    {
        // This would use HTML parsing libraries like DOMDocument, QueryPath, etc.
        throw new \RuntimeException('HTML parsing not implemented - use external HTML parser');
    }
}
