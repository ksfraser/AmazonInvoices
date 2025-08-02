<?php

declare(strict_types=1);

namespace AmazonInvoices\Models;

/**
 * Amazon Invoice Item Model
 * 
 * Represents a single line item within an Amazon invoice
 * 
 * @package AmazonInvoices\Models
 * @author  Your Name
 * @since   1.0.0
 */
class InvoiceItem
{
    /**
     * @var int|null Internal ID (populated after saving to database)
     */
    private ?int $id = null;

    /**
     * @var int Line number within the invoice
     */
    private int $lineNumber;

    /**
     * @var string Product name/description
     */
    private string $productName;

    /**
     * @var string|null Amazon Standard Identification Number
     */
    private ?string $asin = null;

    /**
     * @var string|null Stock Keeping Unit
     */
    private ?string $sku = null;

    /**
     * @var int Quantity ordered
     */
    private int $quantity;

    /**
     * @var float Price per unit
     */
    private float $unitPrice;

    /**
     * @var float Total price for this line item
     */
    private float $totalPrice;

    /**
     * @var float Tax amount for this item
     */
    private float $taxAmount = 0.0;

    /**
     * @var string|null Matched FrontAccounting stock ID
     */
    private ?string $faStockId = null;

    /**
     * @var bool Whether this item has been matched
     */
    private bool $isMatched = false;

    /**
     * @var string|null How the item was matched (auto, manual, new)
     */
    private ?string $matchType = null;

    /**
     * @var string|null Supplier item code
     */
    private ?string $supplierItemCode = null;

    /**
     * @var string|null Category suggestion for new items
     */
    private ?string $categorySuggestion = null;

    /**
     * @var string|null Additional notes
     */
    private ?string $notes = null;

    /**
     * InvoiceItem constructor
     * 
     * @param int    $lineNumber   Line number within invoice
     * @param string $productName  Product name/description
     * @param int    $quantity     Quantity ordered
     * @param float  $unitPrice    Price per unit
     * @param float  $totalPrice   Total price for this line
     */
    public function __construct(
        int $lineNumber,
        string $productName,
        int $quantity,
        float $unitPrice,
        float $totalPrice
    ) {
        $this->lineNumber = $lineNumber;
        $this->productName = $productName;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
        $this->totalPrice = $totalPrice;
    }

    /**
     * Get item ID
     * 
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set item ID
     * 
     * @param int $id
     * @return void
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * Get line number
     * 
     * @return int
     */
    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    /**
     * Get product name
     * 
     * @return string
     */
    public function getProductName(): string
    {
        return $this->productName;
    }

    /**
     * Set product name
     * 
     * @param string $productName
     * @return void
     */
    public function setProductName(string $productName): void
    {
        $this->productName = $productName;
    }

    /**
     * Get ASIN
     * 
     * @return string|null
     */
    public function getAsin(): ?string
    {
        return $this->asin;
    }

    /**
     * Set ASIN
     * 
     * @param string|null $asin
     * @return void
     */
    public function setAsin(?string $asin): void
    {
        $this->asin = $asin;
    }

    /**
     * Get SKU
     * 
     * @return string|null
     */
    public function getSku(): ?string
    {
        return $this->sku;
    }

    /**
     * Set SKU
     * 
     * @param string|null $sku
     * @return void
     */
    public function setSku(?string $sku): void
    {
        $this->sku = $sku;
    }

    /**
     * Get quantity
     * 
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * Set quantity
     * 
     * @param int $quantity
     * @return void
     */
    public function setQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero');
        }
        $this->quantity = $quantity;
    }

    /**
     * Get unit price
     * 
     * @return float
     */
    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    /**
     * Set unit price
     * 
     * @param float $unitPrice
     * @return void
     */
    public function setUnitPrice(float $unitPrice): void
    {
        if ($unitPrice < 0) {
            throw new \InvalidArgumentException('Unit price cannot be negative');
        }
        $this->unitPrice = $unitPrice;
    }

    /**
     * Get total price
     * 
     * @return float
     */
    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    /**
     * Set total price
     * 
     * @param float $totalPrice
     * @return void
     */
    public function setTotalPrice(float $totalPrice): void
    {
        if ($totalPrice < 0) {
            throw new \InvalidArgumentException('Total price cannot be negative');
        }
        $this->totalPrice = $totalPrice;
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
        if ($taxAmount < 0) {
            throw new \InvalidArgumentException('Tax amount cannot be negative');
        }
        $this->taxAmount = $taxAmount;
    }

    /**
     * Get FA stock ID
     * 
     * @return string|null
     */
    public function getFaStockId(): ?string
    {
        return $this->faStockId;
    }

    /**
     * Set FA stock ID
     * 
     * @param string|null $faStockId
     * @return void
     */
    public function setFaStockId(?string $faStockId): void
    {
        $this->faStockId = $faStockId;
        $this->isMatched = !empty($faStockId);
    }

    /**
     * Check if item is matched
     * 
     * @return bool
     */
    public function isMatched(): bool
    {
        return $this->isMatched;
    }

    /**
     * Set matched status
     * 
     * @param bool $isMatched
     * @return void
     */
    public function setMatched(bool $isMatched): void
    {
        $this->isMatched = $isMatched;
    }

    /**
     * Get match type
     * 
     * @return string|null
     */
    public function getMatchType(): ?string
    {
        return $this->matchType;
    }

    /**
     * Set match type
     * 
     * @param string|null $matchType
     * @return void
     */
    public function setMatchType(?string $matchType): void
    {
        $validTypes = ['auto', 'manual', 'new'];
        if ($matchType !== null && !in_array($matchType, $validTypes, true)) {
            throw new \InvalidArgumentException("Invalid match type: {$matchType}");
        }
        $this->matchType = $matchType;
    }

    /**
     * Get supplier item code
     * 
     * @return string|null
     */
    public function getSupplierItemCode(): ?string
    {
        return $this->supplierItemCode;
    }

    /**
     * Set supplier item code
     * 
     * @param string|null $supplierItemCode
     * @return void
     */
    public function setSupplierItemCode(?string $supplierItemCode): void
    {
        $this->supplierItemCode = $supplierItemCode;
    }

    /**
     * Get category suggestion
     * 
     * @return string|null
     */
    public function getCategorySuggestion(): ?string
    {
        return $this->categorySuggestion;
    }

    /**
     * Set category suggestion
     * 
     * @param string|null $categorySuggestion
     * @return void
     */
    public function setCategorySuggestion(?string $categorySuggestion): void
    {
        $this->categorySuggestion = $categorySuggestion;
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
     * Match item to FA stock item
     * 
     * @param string $faStockId
     * @param string $matchType
     * @param string|null $supplierItemCode
     * @return void
     */
    public function matchToStock(string $faStockId, string $matchType = 'manual', ?string $supplierItemCode = null): void
    {
        $this->setFaStockId($faStockId);
        $this->setMatchType($matchType);
        $this->setSupplierItemCode($supplierItemCode);
        $this->setMatched(true);
    }

    /**
     * Validate item data
     * 
     * @return array Array of validation errors
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->productName)) {
            $errors[] = 'Product name is required';
        }

        if ($this->quantity <= 0) {
            $errors[] = 'Quantity must be greater than zero';
        }

        if ($this->unitPrice < 0) {
            $errors[] = 'Unit price cannot be negative';
        }

        if ($this->totalPrice < 0) {
            $errors[] = 'Total price cannot be negative';
        }

        if ($this->taxAmount < 0) {
            $errors[] = 'Tax amount cannot be negative';
        }

        // Check if calculated total matches stored total
        $calculatedTotal = $this->quantity * $this->unitPrice;
        if (abs($calculatedTotal - $this->totalPrice) > 0.01) {
            $errors[] = 'Total price does not match quantity × unit price';
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
            'line_number' => $this->lineNumber,
            'product_name' => $this->productName,
            'asin' => $this->asin,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'total_price' => $this->totalPrice,
            'tax_amount' => $this->taxAmount,
            'fa_stock_id' => $this->faStockId,
            'is_matched' => $this->isMatched,
            'match_type' => $this->matchType,
            'supplier_item_code' => $this->supplierItemCode,
            'category_suggestion' => $this->categorySuggestion,
            'notes' => $this->notes,
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
        $item = new self(
            (int) $data['line_number'],
            $data['product_name'],
            (int) $data['quantity'],
            (float) $data['unit_price'],
            (float) $data['total_price']
        );

        if (isset($data['id'])) {
            $item->setId((int) $data['id']);
        }

        $item->setAsin($data['asin'] ?? null);
        $item->setSku($data['sku'] ?? null);
        $item->setTaxAmount((float) ($data['tax_amount'] ?? 0));
        $item->setFaStockId($data['fa_stock_id'] ?? null);
        $item->setMatched((bool) ($data['is_matched'] ?? false));
        $item->setMatchType($data['match_type'] ?? null);
        $item->setSupplierItemCode($data['supplier_item_code'] ?? null);
        $item->setCategorySuggestion($data['category_suggestion'] ?? null);
        $item->setNotes($data['notes'] ?? null);

        return $item;
    }
}
