<?php

declare(strict_types=1);

namespace AmazonInvoices\Models;

/**
 * Amazon Payment Model
 * 
 * Represents a payment method used for an Amazon invoice
 * 
 * @package AmazonInvoices\Models
 * @author  Your Name
 * @since   1.0.0
 */
class Payment
{
    /**
     * @var int|null Internal ID (populated after saving to database)
     */
    private ?int $id = null;

    /**
     * @var string Payment method (credit_card, bank_transfer, paypal, gift_card, points, split)
     */
    private string $paymentMethod;

    /**
     * @var string|null Payment reference (last 4 digits, confirmation number, etc.)
     */
    private ?string $paymentReference = null;

    /**
     * @var float Payment amount
     */
    private float $amount;

    /**
     * @var int|null FrontAccounting bank account ID
     */
    private ?int $faBankAccount = null;

    /**
     * @var int|null FrontAccounting payment type ID
     */
    private ?int $faPaymentType = null;

    /**
     * @var bool Whether payment allocation is complete
     */
    private bool $allocationComplete = false;

    /**
     * @var string|null Additional notes
     */
    private ?string $notes = null;

    /**
     * Payment constructor
     * 
     * @param string $paymentMethod Payment method
     * @param float  $amount        Payment amount
     * @param string|null $paymentReference Payment reference
     */
    public function __construct(
        string $paymentMethod,
        float $amount,
        ?string $paymentReference = null
    ) {
        $this->setPaymentMethod($paymentMethod);
        $this->setAmount($amount);
        $this->paymentReference = $paymentReference;
    }

    /**
     * Get payment ID
     * 
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set payment ID
     * 
     * @param int $id
     * @return void
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * Get payment method
     * 
     * @return string
     */
    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    /**
     * Set payment method
     * 
     * @param string $paymentMethod
     * @return void
     */
    public function setPaymentMethod(string $paymentMethod): void
    {
        $validMethods = ['credit_card', 'bank_transfer', 'paypal', 'gift_card', 'points', 'split'];
        if (!in_array($paymentMethod, $validMethods, true)) {
            throw new \InvalidArgumentException("Invalid payment method: {$paymentMethod}");
        }
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Get payment reference
     * 
     * @return string|null
     */
    public function getPaymentReference(): ?string
    {
        return $this->paymentReference;
    }

    /**
     * Set payment reference
     * 
     * @param string|null $paymentReference
     * @return void
     */
    public function setPaymentReference(?string $paymentReference): void
    {
        $this->paymentReference = $paymentReference;
    }

    /**
     * Get amount
     * 
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * Set amount
     * 
     * @param float $amount
     * @return void
     */
    public function setAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero');
        }
        $this->amount = $amount;
    }

    /**
     * Get FA bank account
     * 
     * @return int|null
     */
    public function getFaBankAccount(): ?int
    {
        return $this->faBankAccount;
    }

    /**
     * Set FA bank account
     * 
     * @param int|null $faBankAccount
     * @return void
     */
    public function setFaBankAccount(?int $faBankAccount): void
    {
        $this->faBankAccount = $faBankAccount;
    }

    /**
     * Get FA payment type
     * 
     * @return int|null
     */
    public function getFaPaymentType(): ?int
    {
        return $this->faPaymentType;
    }

    /**
     * Set FA payment type
     * 
     * @param int|null $faPaymentType
     * @return void
     */
    public function setFaPaymentType(?int $faPaymentType): void
    {
        $this->faPaymentType = $faPaymentType;
    }

    /**
     * Check if allocation is complete
     * 
     * @return bool
     */
    public function isAllocated(): bool
    {
        return $this->allocationComplete;
    }

    /**
     * Set allocation status
     * 
     * @param bool $allocationComplete
     * @return void
     */
    public function setAllocationComplete(bool $allocationComplete): void
    {
        $this->allocationComplete = $allocationComplete;
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
     * Allocate payment to FA accounts
     * 
     * @param int $faBankAccount FA bank account ID
     * @param int|null $faPaymentType FA payment type ID
     * @param string|null $notes Additional notes
     * @return void
     */
    public function allocate(int $faBankAccount, ?int $faPaymentType = null, ?string $notes = null): void
    {
        $this->setFaBankAccount($faBankAccount);
        $this->setFaPaymentType($faPaymentType);
        $this->setNotes($notes);
        $this->setAllocationComplete(true);
    }

    /**
     * Get display name for payment method
     * 
     * @return string
     */
    public function getPaymentMethodDisplayName(): string
    {
        return match ($this->paymentMethod) {
            'credit_card' => 'Credit Card',
            'bank_transfer' => 'Bank Transfer',
            'paypal' => 'PayPal',
            'gift_card' => 'Gift Card',
            'points' => 'Points/Rewards',
            'split' => 'Split Payment',
            default => ucwords(str_replace('_', ' ', $this->paymentMethod))
        };
    }

    /**
     * Validate payment data
     * 
     * @return array Array of validation errors
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->paymentMethod)) {
            $errors[] = 'Payment method is required';
        }

        if ($this->amount <= 0) {
            $errors[] = 'Amount must be greater than zero';
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
            'payment_method' => $this->paymentMethod,
            'payment_reference' => $this->paymentReference,
            'amount' => $this->amount,
            'fa_bank_account' => $this->faBankAccount,
            'fa_payment_type' => $this->faPaymentType,
            'allocation_complete' => $this->allocationComplete,
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
        $payment = new self(
            $data['payment_method'],
            (float) $data['amount'],
            $data['payment_reference'] ?? null
        );

        if (isset($data['id'])) {
            $payment->setId((int) $data['id']);
        }

        $payment->setFaBankAccount($data['fa_bank_account'] ?? null);
        $payment->setFaPaymentType($data['fa_payment_type'] ?? null);
        $payment->setAllocationComplete((bool) ($data['allocation_complete'] ?? false));
        $payment->setNotes($data['notes'] ?? null);

        return $payment;
    }
}
