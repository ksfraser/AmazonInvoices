<?php

declare(strict_types=1);

namespace AmazonInvoices\Repositories;

use AmazonInvoices\Interfaces\InvoiceRepositoryInterface;
use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;
use AmazonInvoices\Models\Invoice;
use AmazonInvoices\Models\InvoiceItem;
use AmazonInvoices\Models\Payment;

/**
 * Invoice Repository Implementation
 * 
 * Handles persistence and retrieval of Amazon invoices
 * Framework-agnostic through DatabaseRepositoryInterface
 * 
 * @package AmazonInvoices\Repositories
 * @author  Your Name
 * @since   1.0.0
 */
class InvoiceRepository implements InvoiceRepositoryInterface
{
    /**
     * @var DatabaseRepositoryInterface Database repository
     */
    private DatabaseRepositoryInterface $database;

    /**
     * @var string Invoice staging table name
     */
    private string $invoiceTable;

    /**
     * @var string Invoice items staging table name
     */
    private string $itemsTable;

    /**
     * @var string Payment staging table name
     */
    private string $paymentsTable;

    /**
     * Constructor
     * 
     * @param DatabaseRepositoryInterface $database Database repository
     */
    public function __construct(DatabaseRepositoryInterface $database)
    {
        $this->database = $database;
        $prefix = $database->getTablePrefix();
        
        $this->invoiceTable = $prefix . 'amazon_invoices_staging';
        $this->itemsTable = $prefix . 'amazon_invoice_items_staging';
        $this->paymentsTable = $prefix . 'amazon_payment_staging';
    }

    /**
     * {@inheritdoc}
     */
    public function save(Invoice $invoice): Invoice
    {
        $this->database->beginTransaction();

        try {
            if ($invoice->getId()) {
                $this->updateInvoice($invoice);
            } else {
                $this->insertInvoice($invoice);
            }

            // Save items
            $this->saveInvoiceItems($invoice);

            // Save payments
            $this->saveInvoicePayments($invoice);

            $this->database->commit();
            return $invoice;

        } catch (\Exception $e) {
            $this->database->rollback();
            throw new \Exception("Failed to save invoice: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Invoice
    {
        $query = "SELECT * FROM {$this->invoiceTable} WHERE id = ?";
        $result = $this->database->query($query, [$id]);
        $row = $this->database->fetch($result);

        if (!$row) {
            return null;
        }

        return $this->createInvoiceFromRow($row);
    }

    /**
     * {@inheritdoc}
     */
    public function findByInvoiceNumber(string $invoiceNumber): ?Invoice
    {
        $query = "SELECT * FROM {$this->invoiceTable} WHERE invoice_number = ?";
        $result = $this->database->query($query, [$invoiceNumber]);
        $row = $this->database->fetch($result);

        if (!$row) {
            return null;
        }

        return $this->createInvoiceFromRow($row);
    }

    /**
     * {@inheritdoc}
     */
    public function findByStatus(string $status, ?int $limit = null): array
    {
        $query = "SELECT * FROM {$this->invoiceTable} WHERE status = ? ORDER BY created_at DESC";
        if ($limit) {
            $query .= " LIMIT " . (int) $limit;
        }

        $result = $this->database->query($query, [$status]);
        return $this->createInvoicesFromResult($result);
    }

    /**
     * {@inheritdoc}
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate, ?int $limit = null): array
    {
        $query = "SELECT * FROM {$this->invoiceTable} 
                  WHERE invoice_date BETWEEN ? AND ? 
                  ORDER BY invoice_date DESC";
        
        if ($limit) {
            $query .= " LIMIT " . (int) $limit;
        }

        $result = $this->database->query($query, [
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        ]);

        return $this->createInvoicesFromResult($result);
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(array $filters = [], ?int $limit = null, int $offset = 0): array
    {
        $whereConditions = [];
        $params = [];

        // Build WHERE conditions from filters
        if (!empty($filters['status'])) {
            $whereConditions[] = 'status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'invoice_date >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'invoice_date <= ?';
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['order_number'])) {
            $whereConditions[] = 'order_number LIKE ?';
            $params[] = '%' . $filters['order_number'] . '%';
        }

        $query = "SELECT * FROM {$this->invoiceTable}";
        
        if (!empty($whereConditions)) {
            $query .= ' WHERE ' . implode(' AND ', $whereConditions);
        }

        $query .= ' ORDER BY created_at DESC';

        if ($limit) {
            $query .= " LIMIT " . (int) $limit;
            if ($offset > 0) {
                $query .= " OFFSET " . (int) $offset;
            }
        }

        $result = $this->database->query($query, $params);
        return $this->createInvoicesFromResult($result);
    }

    /**
     * {@inheritdoc}
     */
    public function count(array $filters = []): int
    {
        $whereConditions = [];
        $params = [];

        // Build WHERE conditions from filters (same as findAll)
        if (!empty($filters['status'])) {
            $whereConditions[] = 'status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'invoice_date >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'invoice_date <= ?';
            $params[] = $filters['date_to'];
        }

        $query = "SELECT COUNT(*) as count FROM {$this->invoiceTable}";
        
        if (!empty($whereConditions)) {
            $query .= ' WHERE ' . implode(' AND ', $whereConditions);
        }

        $result = $this->database->query($query, $params);
        $row = $this->database->fetch($result);

        return (int) ($row['count'] ?? 0);
    }

    /**
     * {@inheritdoc}
     */
    public function updateStatus(int $id, string $status, ?string $notes = null, ?int $faTransactionNumber = null): bool
    {
        $query = "UPDATE {$this->invoiceTable} 
                  SET status = ?, processed_at = NOW()";
        $params = [$status];

        if ($notes !== null) {
            $query .= ", notes = ?";
            $params[] = $notes;
        }

        if ($faTransactionNumber !== null) {
            $query .= ", fa_trans_no = ?";
            $params[] = $faTransactionNumber;
        }

        $query .= " WHERE id = ?";
        $params[] = $id;

        $this->database->query($query, $params);
        return $this->database->getAffectedRows() > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        $this->database->beginTransaction();

        try {
            // Delete in reverse order of foreign key dependencies
            $this->database->query("DELETE FROM {$this->paymentsTable} WHERE staging_invoice_id = ?", [$id]);
            $this->database->query("DELETE FROM {$this->itemsTable} WHERE staging_invoice_id = ?", [$id]);
            $this->database->query("DELETE FROM {$this->invoiceTable} WHERE id = ?", [$id]);

            $this->database->commit();
            return true;

        } catch (\Exception $e) {
            $this->database->rollback();
            throw new \Exception("Failed to delete invoice: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function existsByInvoiceNumber(string $invoiceNumber): bool
    {
        $query = "SELECT COUNT(*) as count FROM {$this->invoiceTable} WHERE invoice_number = ?";
        $result = $this->database->query($query, [$invoiceNumber]);
        $row = $this->database->fetch($result);

        return (int) ($row['count'] ?? 0) > 0;
    }

    /**
     * Insert new invoice
     * 
     * @param Invoice $invoice Invoice to insert
     * @return void
     */
    private function insertInvoice(Invoice $invoice): void
    {
        $query = "INSERT INTO {$this->invoiceTable} 
                  (invoice_number, order_number, invoice_date, invoice_total, 
                   tax_amount, shipping_amount, currency, pdf_path, raw_data, status, notes) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $this->database->query($query, [
            $invoice->getInvoiceNumber(),
            $invoice->getOrderNumber(),
            $invoice->getInvoiceDate()->format('Y-m-d'),
            $invoice->getTotalAmount(),
            $invoice->getTaxAmount(),
            $invoice->getShippingAmount(),
            $invoice->getCurrency(),
            $invoice->getPdfPath(),
            $invoice->getRawData(),
            $invoice->getStatus(),
            $invoice->getNotes()
        ]);

        $invoice->setId($this->database->getLastInsertId());
    }

    /**
     * Update existing invoice
     * 
     * @param Invoice $invoice Invoice to update
     * @return void
     */
    private function updateInvoice(Invoice $invoice): void
    {
        $query = "UPDATE {$this->invoiceTable} 
                  SET invoice_number = ?, order_number = ?, invoice_date = ?, 
                      invoice_total = ?, tax_amount = ?, shipping_amount = ?, 
                      currency = ?, pdf_path = ?, raw_data = ?, status = ?, notes = ?
                  WHERE id = ?";

        $this->database->query($query, [
            $invoice->getInvoiceNumber(),
            $invoice->getOrderNumber(),
            $invoice->getInvoiceDate()->format('Y-m-d'),
            $invoice->getTotalAmount(),
            $invoice->getTaxAmount(),
            $invoice->getShippingAmount(),
            $invoice->getCurrency(),
            $invoice->getPdfPath(),
            $invoice->getRawData(),
            $invoice->getStatus(),
            $invoice->getNotes(),
            $invoice->getId()
        ]);
    }

    /**
     * Save invoice items
     * 
     * @param Invoice $invoice Invoice with items to save
     * @return void
     */
    private function saveInvoiceItems(Invoice $invoice): void
    {
        // Delete existing items
        $this->database->query("DELETE FROM {$this->itemsTable} WHERE staging_invoice_id = ?", [$invoice->getId()]);

        // Insert new items
        foreach ($invoice->getItems() as $item) {
            $query = "INSERT INTO {$this->itemsTable} 
                      (staging_invoice_id, line_number, product_name, asin, sku, 
                       quantity, unit_price, total_price, tax_amount, fa_stock_id, 
                       fa_item_matched, item_match_type, supplier_item_code, notes) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $this->database->query($query, [
                $invoice->getId(),
                $item->getLineNumber(),
                $item->getProductName(),
                $item->getAsin(),
                $item->getSku(),
                $item->getQuantity(),
                $item->getUnitPrice(),
                $item->getTotalPrice(),
                $item->getTaxAmount(),
                $item->getFaStockId(),
                $item->isMatched() ? 1 : 0,
                $item->getMatchType(),
                $item->getSupplierItemCode(),
                $item->getNotes()
            ]);

            $item->setId($this->database->getLastInsertId());
        }
    }

    /**
     * Save invoice payments
     * 
     * @param Invoice $invoice Invoice with payments to save
     * @return void
     */
    private function saveInvoicePayments(Invoice $invoice): void
    {
        // Delete existing payments
        $this->database->query("DELETE FROM {$this->paymentsTable} WHERE staging_invoice_id = ?", [$invoice->getId()]);

        // Insert new payments
        foreach ($invoice->getPayments() as $payment) {
            $query = "INSERT INTO {$this->paymentsTable} 
                      (staging_invoice_id, payment_method, payment_reference, amount, 
                       fa_bank_account, fa_payment_type, allocation_complete, notes) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $this->database->query($query, [
                $invoice->getId(),
                $payment->getPaymentMethod(),
                $payment->getPaymentReference(),
                $payment->getAmount(),
                $payment->getFaBankAccount(),
                $payment->getFaPaymentType(),
                $payment->isAllocated() ? 1 : 0,
                $payment->getNotes()
            ]);

            $payment->setId($this->database->getLastInsertId());
        }
    }

    /**
     * Create invoice object from database row
     * 
     * @param array $row Database row
     * @return Invoice Created invoice object
     */
    private function createInvoiceFromRow(array $row): Invoice
    {
        $invoice = new Invoice(
            $row['invoice_number'],
            $row['order_number'],
            new \DateTime($row['invoice_date']),
            (float) $row['invoice_total'],
            $row['currency']
        );

        $invoice->setId((int) $row['id']);
        $invoice->setTaxAmount((float) ($row['tax_amount'] ?? 0));
        $invoice->setShippingAmount((float) ($row['shipping_amount'] ?? 0));
        $invoice->setPdfPath($row['pdf_path']);
        $invoice->setRawData($row['raw_data']);
        $invoice->setStatus($row['status']);
        $invoice->setNotes($row['notes']);

        if (!empty($row['created_at'])) {
            $invoice->setCreatedAt(new \DateTime($row['created_at']));
        }

        if (!empty($row['processed_at'])) {
            $invoice->setProcessedAt(new \DateTime($row['processed_at']));
        }

        if (!empty($row['fa_trans_no'])) {
            $invoice->setFaTransactionNumber((int) $row['fa_trans_no']);
        }

        // Load items and payments
        $this->loadInvoiceItems($invoice);
        $this->loadInvoicePayments($invoice);

        return $invoice;
    }

    /**
     * Load invoice items from database
     * 
     * @param Invoice $invoice Invoice to load items for
     * @return void
     */
    private function loadInvoiceItems(Invoice $invoice): void
    {
        $query = "SELECT * FROM {$this->itemsTable} 
                  WHERE staging_invoice_id = ? 
                  ORDER BY line_number";

        $result = $this->database->query($query, [$invoice->getId()]);
        $rows = $this->database->fetchAll($result);

        foreach ($rows as $row) {
            $item = new InvoiceItem(
                (int) $row['line_number'],
                $row['product_name'],
                (int) $row['quantity'],
                (float) $row['unit_price'],
                (float) $row['total_price']
            );

            $item->setId((int) $row['id']);
            $item->setAsin($row['asin']);
            $item->setSku($row['sku']);
            $item->setTaxAmount((float) ($row['tax_amount'] ?? 0));
            $item->setFaStockId($row['fa_stock_id']);
            $item->setMatched((bool) $row['fa_item_matched']);
            $item->setMatchType($row['item_match_type']);
            $item->setSupplierItemCode($row['supplier_item_code']);
            $item->setNotes($row['notes']);

            $invoice->addItem($item);
        }
    }

    /**
     * Load invoice payments from database
     * 
     * @param Invoice $invoice Invoice to load payments for
     * @return void
     */
    private function loadInvoicePayments(Invoice $invoice): void
    {
        $query = "SELECT * FROM {$this->paymentsTable} 
                  WHERE staging_invoice_id = ?";

        $result = $this->database->query($query, [$invoice->getId()]);
        $rows = $this->database->fetchAll($result);

        foreach ($rows as $row) {
            $payment = new Payment(
                $row['payment_method'],
                (float) $row['amount'],
                $row['payment_reference']
            );

            $payment->setId((int) $row['id']);
            $payment->setFaBankAccount($row['fa_bank_account'] ? (int) $row['fa_bank_account'] : null);
            $payment->setFaPaymentType($row['fa_payment_type'] ? (int) $row['fa_payment_type'] : null);
            $payment->setAllocationComplete((bool) $row['allocation_complete']);
            $payment->setNotes($row['notes']);

            $invoice->addPayment($payment);
        }
    }

    /**
     * Create invoice objects from query result
     * 
     * @param mixed $result Query result
     * @return Invoice[] Array of invoice objects
     */
    private function createInvoicesFromResult($result): array
    {
        $invoices = [];
        $rows = $this->database->fetchAll($result);

        foreach ($rows as $row) {
            $invoices[] = $this->createInvoiceFromRow($row);
        }

        return $invoices;
    }
}
