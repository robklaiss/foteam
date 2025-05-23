<?php

namespace FoTeam\Models;

use FoTeam\Model;
use PDO;
use PDOException;
use Exception;

class Order extends Model
{
    protected static $table = 'orders';
    protected static $primaryKey = 'id';
    protected static $fillable = [
        'user_id',
        'order_number',
        'status',
        'subtotal',
        'tax_amount',
        'total_amount',
        'payment_method',
        'payment_id',
        'customer_notes',
        'created_at',
        'updated_at'
    ];

    // Order statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the user who placed this order
     */
    public function user(): ?User
    {
        return User::find($this->user_id);
    }

    /**
     * Get all items in this order
     */
    public function items(): array
    {
        $db = self::getDb();
        $stmt = $db->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $stmt->execute([$this->id]);
        
        return array_map(function($item) {
            return new OrderItem($item);
        }, $stmt->fetchAll());
    }

    /**
     * Add an item to the order
     */
    public function addItem(Photo $photo, float $price, string $licenseType = 'standard'): ?OrderItem
    {
        $item = new OrderItem([
            'order_id' => $this->id,
            'photo_id' => $photo->id,
            'price' => $price,
            'license_type' => $licenseType
        ]);

        return $item->save() ? $item : null;
    }

    /**
     * Calculate the order total based on items
     */
    public function calculateTotals(): array
    {
        $subtotal = 0;
        $items = $this->items();
        
        foreach ($items as $item) {
            $subtotal += $item->price;
        }
        
        // Calculate tax (simplified)
        $taxRate = getenv('TAX_RATE') ? (float)getenv('TAX_RATE') : 0.10; // 10% default
        $taxAmount = $subtotal * $taxRate;
        $total = $subtotal + $taxAmount;
        
        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'total' => round($total, 2)
        ];
    }

    /**
     * Update order totals based on current items
     */
    public function updateTotals(): bool
    {
        $totals = $this->calculateTotals();
        
        $this->subtotal = $totals['subtotal'];
        $this->tax_amount = $totals['tax_amount'];
        $this->total_amount = $totals['total'];
        
        return $this->save();
    }

    /**
     * Mark the order as paid
     */
    public function markAsPaid(string $paymentMethod, string $paymentId): bool
    {
        $this->status = self::STATUS_PAID;
        $this->payment_method = $paymentMethod;
        $this->payment_id = $paymentId;
        $this->updated_at = date('Y-m-d H:i:s');
        
        return $this->save();
    }

    /**
     * Mark the order as completed
     */
    public function markAsCompleted(): bool
    {
        $this->status = self::STATUS_COMPLETED;
        $this->updated_at = date('Y-m-d H:i:s');
        
        // Generate download tokens for each item
        if ($this->save()) {
            $this->generateDownloadTokens();
            return true;
        }
        
        return false;
    }

    /**
     * Generate download tokens for all items in the order
     */
    protected function generateDownloadTokens(): void
    {
        $items = $this->items();
        $db = self::getDb();
        
        // Default token validity: 30 days
        $validDays = getenv('DOWNLOAD_TOKEN_VALID_DAYS') ?: 30;
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$validDays} days"));
        
        foreach ($items as $item) {
            $token = bin2hex(random_bytes(32));
            
            $stmt = $db->prepare('INSERT INTO downloads 
                (user_id, photo_id, order_id, download_token, expires_at, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())');
                
            $stmt->execute([
                $this->user_id,
                $item->photo_id,
                $this->id,
                $token,
                $expiresAt
            ]);
        }
    }

    /**
     * Cancel the order
     */
    public function cancel(): bool
    {
        $this->status = self::STATUS_CANCELLED;
        $this->updated_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * Check if the order is paid
     */
    public function isPaid(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_COMPLETED]);
    }

    /**
     * Check if the order is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the order is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Get the order status as a human-readable string
     */
    public function getStatusLabel(): string
    {
        $statuses = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PAID => 'Paid',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled'
        ];
        
        return $statuses[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Get the order status with a badge for display
     */
    public function getStatusBadge(): string
    {
        $classes = [
            self::STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            self::STATUS_PAID => 'bg-blue-100 text-blue-800',
            self::STATUS_PROCESSING => 'bg-purple-100 text-purple-800',
            self::STATUS_COMPLETED => 'bg-green-100 text-green-800',
            self::STATUS_CANCELLED => 'bg-red-100 text-red-800'
        ];
        
        $class = $classes[$this->status] ?? 'bg-gray-100 text-gray-800';
        
        return sprintf(
            '<span class="px-2 py-1 text-xs font-medium rounded-full %s">%s</span>',
            $class,
            htmlspecialchars($this->getStatusLabel())
        );
    }

    /**
     * Generate a unique order number
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD' . date('Ymd');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        return $prefix . $random;
    }

    /**
     * Create a new order for a user
     */
    public static function createForUser(int $userId, array $items, ?string $customerNotes = null): ?self
    {
        $order = new self([
            'user_id' => $userId,
            'order_number' => self::generateOrderNumber(),
            'status' => self::STATUS_PENDING,
            'customer_notes' => $customerNotes
        ]);
        
        if (!$order->save()) {
            return null;
        }
        
        // Add items to the order
        foreach ($items as $item) {
            $order->addItem($item['photo'], $item['price'], $item['license_type'] ?? 'standard');
        }
        
        // Update totals
        $order->updateTotals();
        
        return $order;
    }
}
