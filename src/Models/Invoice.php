<?php

declare(strict_types=1);

namespace AmazonInvoices\Models;

/**
 * Amazon Invoice Model
 * 
 * Represents an Amazon invoice with all its data and metadata
 * Implements validation and data transformation methods
 * 
 * @package AmazonInvoices\Models
 * @author  Your Name
 * @since   1.0.0
 */
class Invoice
{
    /**
     * @var int|null Internal ID (populated after saving to database)
     */
    private ?int $id = null;

    /**
     * @var string Amazon invoice number
     */
    private string $invoiceNumber;

    /**
     * @var string Amazon order number
     */
    private string $orderNumber;

    /**
     * @var \DateTime Invoice date
     */
    private \DateTime $invoiceDate;

    /**
     * @var float Total invoice amount
     */
    private float $totalAmount;

    /**
     * @var float Tax amount
     */
    private float $taxAmount;

    /**
     * @var float Shipping amount
     */
    private float $shippingAmount;

    /**
     * @var string Currency code (ISO 4217)
     */
    private string $currency;

    /**
     * @var string|null Path to downloaded PDF file
     */
    private ?string $pdfPath = null;

    /**
     * @var string|null Raw invoice data (JSON, XML, etc.)
     */
    private ?string $rawData = null;

    /**
     * @var string Processing status
     */
    private string $status = 'pending';

    /**
     * @var InvoiceItem[] Array of invoice items
     */
    private array $items = [];

    /**
     * @var Payment[] Array of payments
     */
    private array $payments = [];

    /**
     * @var \DateTime Creation timestamp
     */
    private \DateTime $createdAt;

    /**
     * @var \DateTime|null Processing timestamp
     */
    private ?\DateTime $processedAt = null;

    /**
     * @var int|null FrontAccounting transaction number
     */
    private ?int $faTransactionNumber = null;

    /**
     * @var string|null Processing notes
     */
    private ?string $notes = null;

    /**
     * Invoice constructor
     * 
     * @param string    $invoiceNumber Amazon invoice number
     * @param string    $orderNumber   Amazon order number
     * @param \DateTime $invoiceDate   Invoice date
     * @param float     $totalAmount   Total amount
     * @param string    $currency      Currency code
     */
    public function __construct(
        string $invoiceNumber,
        string $orderNumber,
        \DateTime $invoiceDate,
        float $totalAmount,
        string $currency = 'USD'
    ) {
        $this->invoiceNumber = $invoiceNumber;
        $this->orderNumber = $orderNumber;
        $this->invoiceDate = $invoiceDate;
        $this->totalAmount = $totalAmount;
        $this->currency = $currency;
        $this->createdAt = new \DateTime();
    }

    /**
     * Get invoice ID
     * 
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set invoice ID
     * 
     * @param int $id
     * @return void
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * Get invoice number
     * 
     * @return string
     */
    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    /**
     * Get order number
     * 
     * @return string
     */
    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    /**
     * Get invoice date
     * 
     * @return \DateTime
     */
    public function getInvoiceDate(): \DateTime
    {
        return $this->invoiceDate;
    }

    /**
     * Get total amount
     * 
     * @return float
     */
    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    /**
     * Set total amount
     * 
     * @param float $totalAmount
     * @return void
     */
    public function setTotalAmount(float $totalAmount): void
    {
        $this->totalAmount = $totalAmount;
    }

    /**
     * Get tax amount
     * 
     * @return float
     */
    public function getTaxAmount(): float
    {
        return $this->taxAmount;
    }

    /**
     * Set tax amount
     * 
     * @param float $taxAmount
     * @return void
     */
    public function setTaxAmount(float $taxAmount): void
    {
        $this->taxAmount = $taxAmount;
    }

    /**
     * Get shipping amount
     * 
     * @return float
     */
    public function getShippingAmount(): float
    {
        return $this->shippingAmount;
    }

    /**
     * Set shipping amount
     * 
     * @param float $shippingAmount
     * @return void
     */
    public function setShippingAmount(float $shippingAmount): void
    {
        $this->shippingAmount = $shippingAmount;
    }

    /**
     * Get currency
     * 
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Get PDF path
     * 
     * @return string|null
     */
    public function getPdfPath(): ?string
    {
        return $this->pdfPath;
    }

    /**
     * Set PDF path
     * 
     * @param string|null $pdfPath
     * @return void
     */
    public function setPdfPath(?string $pdfPath): void
    {
        $this->pdfPath = $pdfPath;
    }

    /**
     * Get raw data
     * 
     * @return string|null
     */
    public function getRawData(): ?string
    {
        return $this->rawData;
    }

    /**
     * Set raw data
     * 
     * @param string|null $rawData
     * @return void
     */
    public function setRawData(?string $rawData): void
    {
        $this->rawData = $rawData;
    }

    /**
     * Get status
     * 
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Set status
     * 
     * @param string $status
     * @return void
     */
    public function setStatus(string $status): void
    {
        $validStatuses = ['pending', 'processing', 'matched', 'completed', 'error'];
        if (!in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }
        $this->status = $status;
    }

    /**
     * Get invoice items
     * 
     * @return InvoiceItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Add invoice item
     * 
     * @param InvoiceItem $item
     * @return void
     */
    public function addItem(InvoiceItem $item): void
    {
        $this->items[] = $item;
    }

    /**
     * Set all invoice items
     * 
     * @param InvoiceItem[] $items
     * @return void
     */
    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    /**
     * Get payments
     * 
     * @return Payment[]
     */
    public function getPayments(): array
    {
        return $this->payments;
    }

    /**
     * Add payment
     * 
     * @param Payment $payment
     * @return void
     */
    public function addPayment(Payment $payment): void
    {
        $this->payments[] = $payment;
    }

    /**
     * Set all payments
     * 
     * @param Payment[] $payments
     * @return void
     */
    public function setPayments(array $payments): void
    {
        $this->payments = $payments;
    }

    /**
     * Get created at timestamp
     * 
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Set created at timestamp
     * 
     * @param \DateTime $createdAt
     * @return void
     */
    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * Get processed at timestamp
     * 
     * @return \DateTime|null
     */
    public function getProcessedAt(): ?\DateTime
    {
        return $this->processedAt;
    }

    /**
     * Set processed at timestamp
     * 
     * @param \DateTime|null $processedAt
     * @return void
     */
    public function setProcessedAt(?\DateTime $processedAt): void
    {
        $this->processedAt = $processedAt;
    }

    /**
     * Get FA transaction number
     * 
     * @return int|null
     */
    public function getFaTransactionNumber(): ?int
    {
        return $this->faTransactionNumber;
    }

    /**
     * Set FA transaction number
     * 
     * @param int|null $faTransactionNumber
     * @return void
     */
    public function setFaTransactionNumber(?int $faTransactionNumber): void
    {
        $this->faTransactionNumber = $faTransactionNumber;
    }

    /**
     * Get notes
     * 
     * @return string|null
     */
    public function getNotes(): ?string
    {
        return $this->notes;
    }

    /**
     * Set notes
     * 
     * @param string|null $notes
     * @return void
     */
    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    /**
     * Calculate total from items
     * 
     * @return float
     */
    public function calculateItemsTotal(): float
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $total += $item->getTotalPrice();
        }
        return $total;
    }

    /**
     * Calculate total from payments
     * 
     * @return float
     */
    public function calculatePaymentsTotal(): float
    {
        $total = 0.0;
        foreach ($this->payments as $payment) {
            $total += $payment->getAmount();
        }
        return $total;
    }

    /**
     * Check if all items are matched
     * 
     * @return bool
     */
    public function areAllItemsMatched(): bool
    {
        foreach ($this->items as $item) {
            if (!$item->isMatched()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if all payments are allocated
     * 
     * @return bool
     */
    public function areAllPaymentsAllocated(): bool
    {
        foreach ($this->payments as $payment) {
            if (!$payment->isAllocated()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Validate invoice data
     * 
     * @return array Array of validation errors
     */
    public function validate(): array
    {
        $errors = [];

        // Check required fields
        if (empty($this->invoiceNumber)) {
            $errors[] = 'Invoice number is required';
        }

        if (empty($this->orderNumber)) {
            $errors[] = 'Order number is required';
        }

        if ($this->totalAmount <= 0) {
            $errors[] = 'Total amount must be greater than zero';
        }

        // Check currency format
        if (!preg_match('/^[A-Z]{3}$/', $this->currency)) {
            $errors[] = 'Currency must be a valid 3-letter ISO code';
        }

        // Check totals match
        $itemsTotal = $this->calculateItemsTotal();
        if (!empty($this->items) && abs($this->totalAmount - $itemsTotal) > 0.01) {
            $errors[] = 'Invoice total does not match sum of items';
        }

        $paymentsTotal = $this->calculatePaymentsTotal();
        if (!empty($this->payments) && abs($this->totalAmount - $paymentsTotal) > 0.01) {
            $errors[] = 'Invoice total does not match sum of payments';
        }

        return $errors;
    }

    /**
     * Convert to array representation
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoiceNumber,
            'order_number' => $this->orderNumber,
            'invoice_date' => $this->invoiceDate->format('Y-m-d'),
            'total_amount' => $this->totalAmount,
            'tax_amount' => $this->taxAmount,
            'shipping_amount' => $this->shippingAmount,
            'currency' => $this->currency,
            'pdf_path' => $this->pdfPath,
            'raw_data' => $this->rawData,
            'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'processed_at' => $this->processedAt?->format('Y-m-d H:i:s'),
            'fa_transaction_number' => $this->faTransactionNumber,
            'notes' => $this->notes,
            'items' => array_map(fn($item) => $item->toArray(), $this->items),
            'payments' => array_map(fn($payment) => $payment->toArray(), $this->payments),
        ];
    }

    /**
     * Create from array representation
     * 
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $invoice = new self(
            $data['invoice_number'],
            $data['order_number'],
            new \DateTime($data['invoice_date']),
            (float) $data['total_amount'],
            $data['currency'] ?? 'USD'
        );

        if (isset($data['id'])) {
            $invoice->setId((int) $data['id']);
        }

        $invoice->setTaxAmount((float) ($data['tax_amount'] ?? 0));
        $invoice->setShippingAmount((float) ($data['shipping_amount'] ?? 0));
        $invoice->setPdfPath($data['pdf_path'] ?? null);
        $invoice->setRawData($data['raw_data'] ?? null);
        $invoice->setStatus($data['status'] ?? 'pending');
        $invoice->setNotes($data['notes'] ?? null);

        if (isset($data['created_at'])) {
            $invoice->setCreatedAt(new \DateTime($data['created_at']));
        }

        if (isset($data['processed_at'])) {
            $invoice->setProcessedAt(new \DateTime($data['processed_at']));
        }

        if (isset($data['fa_transaction_number'])) {
            $invoice->setFaTransactionNumber((int) $data['fa_transaction_number']);
        }

        return $invoice;
    }

    /**
     * Calculate total amount including items, tax, and shipping
     * 
     * @return float Calculated total
     */
    public function calculateTotal(): float
    {
        $itemsTotal = 0.0;
        foreach ($this->items as $item) {
            $itemsTotal += $item->getTotalPrice();
        }
        
        return $itemsTotal + $this->taxAmount + $this->shippingAmount;
    }

    /**
     * Get number of items in invoice
     * 
     * @return int Item count
     */
    public function getItemCount(): int
    {
        return count($this->items);
    }

    /**
     * Get total payment amount
     * 
     * @return float Total payments
     */
    public function getPaymentTotal(): float
    {
        $total = 0.0;
        foreach ($this->payments as $payment) {
            $total += $payment->getAmount();
        }
        
        return $total;
    }

    /**
     * Check if invoice is fully paid
     * 
     * @return bool True if payment total equals or exceeds invoice total
     */
    public function isFullyPaid(): bool
    {
        return $this->getPaymentTotal() >= $this->totalAmount;
    }

    /**
     * Magic clone method to deep clone items and payments
     * 
     * @return void
     */
    public function __clone()
    {
        // Deep clone items array
        $clonedItems = [];
        foreach ($this->items as $item) {
            $clonedItems[] = clone $item;
        }
        $this->items = $clonedItems;

        // Deep clone payments array
        $clonedPayments = [];
        foreach ($this->payments as $payment) {
            $clonedPayments[] = clone $payment;
        }
        $this->payments = $clonedPayments;

        // Reset ID for cloned object
        $this->id = null;
    }
}
