<?php

declare(strict_types=1);

namespace App\Modules\Custom\Ecommerce\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Core\BaseEntity;
use DateTimeImmutable;

/**
 * Order Entity - E-commerce order/transaction record
 *
 * Demonstrates a more complex entity with:
 * - Multiple field types
 * - JSON storage for line items (normalized approach would use OrderItem entity)
 * - Status workflow
 * - Financial calculations
 */
#[ContentType(
    tableName: 'orders',
    label: 'Order',
    labelPlural: 'Orders',
    description: 'Customer orders and transactions',
    icon: 'receipt',
    revisionable: true,
    publishable: false,
    menuWeight: 20
)]
class Order extends BaseEntity
{
    /**
     * Unique order number (displayed to customers)
     */
    #[Field(
        type: 'string',
        label: 'Order Number',
        required: true,
        length: 50,
        unique: true,
        indexed: true,
        searchable: true,
        listable: true,
        description: 'Unique order identifier shown to customers'
    )]
    public string $order_number = '';

    /**
     * Order status
     */
    #[Field(
        type: 'string',
        label: 'Status',
        required: true,
        length: 30,
        default: 'pending',
        indexed: true,
        listable: true,
        filterable: true,
        widget: 'select',
        options: [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
        ],
        description: 'Current order status'
    )]
    public string $status = 'pending';

    /**
     * Payment status
     */
    #[Field(
        type: 'string',
        label: 'Payment Status',
        required: true,
        length: 30,
        default: 'pending',
        indexed: true,
        listable: true,
        filterable: true,
        widget: 'select',
        options: [
            'pending' => 'Pending',
            'paid' => 'Paid',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
        ]
    )]
    public string $payment_status = 'pending';

    /**
     * Customer email
     */
    #[Field(
        type: 'string',
        label: 'Customer Email',
        required: true,
        length: 255,
        indexed: true,
        searchable: true,
        listable: true,
        description: 'Customer email address'
    )]
    public string $customer_email = '';

    /**
     * Customer name
     */
    #[Field(
        type: 'string',
        label: 'Customer Name',
        required: true,
        length: 255,
        searchable: true,
        listable: true
    )]
    public string $customer_name = '';

    /**
     * Customer phone
     */
    #[Field(
        type: 'string',
        label: 'Phone',
        length: 50
    )]
    public string $customer_phone = '';

    /**
     * Billing address (stored as JSON)
     */
    #[Field(
        type: 'json',
        label: 'Billing Address',
        required: true,
        widget: 'address',
        description: 'Customer billing address'
    )]
    public array $billing_address = [];

    /**
     * Shipping address (stored as JSON)
     */
    #[Field(
        type: 'json',
        label: 'Shipping Address',
        widget: 'address',
        description: 'Shipping destination address'
    )]
    public array $shipping_address = [];

    /**
     * Order line items (stored as JSON array)
     * In a fully normalized schema, this would be a separate OrderItem entity
     */
    #[Field(
        type: 'json',
        label: 'Line Items',
        required: true,
        widget: 'line_items',
        description: 'Products in this order'
    )]
    public array $line_items = [];

    /**
     * Subtotal (before tax and shipping)
     */
    #[Field(
        type: 'decimal',
        label: 'Subtotal',
        required: true,
        precision: 10,
        scale: 2,
        listable: true
    )]
    public float $subtotal = 0.00;

    /**
     * Tax amount
     */
    #[Field(
        type: 'decimal',
        label: 'Tax',
        required: true,
        precision: 10,
        scale: 2,
        default: 0.00
    )]
    public float $tax_amount = 0.00;

    /**
     * Shipping cost
     */
    #[Field(
        type: 'decimal',
        label: 'Shipping',
        required: true,
        precision: 10,
        scale: 2,
        default: 0.00
    )]
    public float $shipping_amount = 0.00;

    /**
     * Discount amount
     */
    #[Field(
        type: 'decimal',
        label: 'Discount',
        precision: 10,
        scale: 2,
        default: 0.00
    )]
    public float $discount_amount = 0.00;

    /**
     * Order total
     */
    #[Field(
        type: 'decimal',
        label: 'Total',
        required: true,
        precision: 10,
        scale: 2,
        listable: true,
        filterable: true
    )]
    public float $total = 0.00;

    /**
     * Currency code
     */
    #[Field(
        type: 'string',
        label: 'Currency',
        required: true,
        length: 3,
        default: 'USD'
    )]
    public string $currency = 'USD';

    /**
     * Payment method used
     */
    #[Field(
        type: 'string',
        label: 'Payment Method',
        length: 50
    )]
    public string $payment_method = '';

    /**
     * Payment transaction ID
     */
    #[Field(
        type: 'string',
        label: 'Transaction ID',
        length: 255,
        indexed: true
    )]
    public string $transaction_id = '';

    /**
     * Shipping method
     */
    #[Field(
        type: 'string',
        label: 'Shipping Method',
        length: 100
    )]
    public string $shipping_method = '';

    /**
     * Tracking number
     */
    #[Field(
        type: 'string',
        label: 'Tracking Number',
        length: 255,
        searchable: true
    )]
    public string $tracking_number = '';

    /**
     * Customer notes/special instructions
     */
    #[Field(
        type: 'text',
        label: 'Customer Notes',
        widget: 'textarea'
    )]
    public string $customer_notes = '';

    /**
     * Internal admin notes
     */
    #[Field(
        type: 'text',
        label: 'Admin Notes',
        widget: 'textarea'
    )]
    public string $admin_notes = '';

    /**
     * IP address of order placement
     */
    #[Field(
        type: 'string',
        label: 'IP Address',
        length: 45
    )]
    public string $ip_address = '';

    /**
     * User agent string
     */
    #[Field(
        type: 'string',
        label: 'User Agent',
        length: 500
    )]
    public string $user_agent = '';

    /**
     * Shipped date
     */
    #[Field(
        type: 'datetime',
        label: 'Shipped At'
    )]
    public ?DateTimeImmutable $shipped_at = null;

    /**
     * Delivered date
     */
    #[Field(
        type: 'datetime',
        label: 'Delivered At'
    )]
    public ?DateTimeImmutable $delivered_at = null;

    /**
     * Generate order number before persist
     */
    public function prePersist(): void
    {
        parent::prePersist();

        if (empty($this->order_number)) {
            $this->order_number = $this->generateOrderNumber();
        }

        $this->calculateTotals();
    }

    /**
     * Generate unique order number
     */
    private function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $timestamp = date('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        return "{$prefix}-{$timestamp}-{$random}";
    }

    /**
     * Calculate order totals from line items
     */
    public function calculateTotals(): void
    {
        $subtotal = 0.00;

        foreach ($this->line_items as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $subtotal += $quantity * $price;
        }

        $this->subtotal = $subtotal;
        $this->total = $this->subtotal + $this->tax_amount + $this->shipping_amount - $this->discount_amount;
    }

    /**
     * Add a line item
     */
    public function addLineItem(array $item): void
    {
        $this->line_items[] = $item;
        $this->calculateTotals();
    }

    /**
     * Get total item count
     */
    public function getItemCount(): int
    {
        return array_sum(array_column($this->line_items, 'quantity'));
    }

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }

    /**
     * Check if order can be refunded
     */
    public function canBeRefunded(): bool
    {
        return $this->payment_status === 'paid' && !in_array($this->status, ['refunded'], true);
    }
}
