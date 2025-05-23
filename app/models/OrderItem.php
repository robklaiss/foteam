<?php

namespace FoTeam\Models;

use FoTeam\Model;
use PDO;
use PDOException;
use Exception;

class OrderItem extends Model
{
    protected static $table = 'order_items';
    protected static $primaryKey = 'id';
    protected static $fillable = [
        'order_id',
        'photo_id',
        'price',
        'license_type',
        'created_at'
    ];

    // License types and their display names
    public const LICENSE_TYPES = [
        'standard' => 'Standard License',
        'extended' => 'Extended License',
        'commercial' => 'Commercial Use',
        'exclusive' => 'Exclusive License'
    ];

    /**
     * Get the order this item belongs to
     */
    public function order(): ?Order
    {
        return Order::find($this->order_id);
    }

    /**
     * Get the photo associated with this item
     */
    public function photo(): ?Photo
    {
        return Photo::find($this->photo_id);
    }

    /**
     * Get the license type display name
     */
    public function getLicenseName(): string
    {
        return self::LICENSE_TYPES[$this->license_type] ?? ucfirst($this->license_type);
    }

    /**
     * Get the formatted price
     */
    public function getFormattedPrice(): string
    {
        return '$' . number_format($this->price, 2);
    }

    /**
     * Check if this item has a download available
     */
    public function hasDownload(): bool
    {
        $order = $this->order();
        if (!$order || !$order->isPaid()) {
            return false;
        }

        $db = self::getDb();
        $stmt = $db->prepare('SELECT COUNT(*) FROM downloads WHERE order_id = ? AND photo_id = ?');
        $stmt->execute([$this->order_id, $this->photo_id]);
        
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Get the download URL for this item if available
     */
    public function getDownloadUrl(): ?string
    {
        if (!$this->hasDownload()) {
            return null;
        }

        $db = self::getDb();
        $stmt = $db->prepare('SELECT download_token FROM downloads WHERE order_id = ? AND photo_id = ? LIMIT 1');
        $stmt->execute([$this->order_id, $this->photo_id]);
        $token = $stmt->fetchColumn();

        if (!$token) {
            return null;
        }

        $baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
        return $baseUrl . '/download/' . $token;
    }

    /**
     * Get the download token for this item if available
     */
    public function getDownloadToken(): ?string
    {
        $db = self::getDb();
        $stmt = $db->prepare('SELECT download_token FROM downloads WHERE order_id = ? AND photo_id = ? LIMIT 1');
        $stmt->execute([$this->order_id, $this->photo_id]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Get the download history for this item
     */
    public function getDownloadHistory(): array
    {
        $db = self::getDb();
        $stmt = $db->prepare('SELECT * FROM downloads WHERE order_id = ? AND photo_id = ? ORDER BY last_downloaded_at DESC');
        $stmt->execute([$this->order_id, $this->photo_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get the number of times this item has been downloaded
     */
    public function getDownloadCount(): int
    {
        $db = self::getDb();
        $stmt = $db->prepare('SELECT COALESCE(SUM(download_count), 0) FROM downloads WHERE order_id = ? AND photo_id = ?');
        $stmt->execute([$this->order_id, $this->photo_id]);
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Record a download of this item
     */
    public function recordDownload(string $ipAddress, string $userAgent): bool
    {
        $db = self::getDb();
        
        // Try to update existing download record
        $stmt = $db->prepare('UPDATE downloads SET 
            download_count = download_count + 1,
            last_downloaded_at = NOW(),
            last_ip_address = ?,
            last_user_agent = ?
            WHERE order_id = ? AND photo_id = ?');
            
        $stmt->execute([$ipAddress, $userAgent, $this->order_id, $this->photo_id]);
        
        if ($stmt->rowCount() > 0) {
            return true;
        }
        
        // If no rows were updated, try to insert a new record
        $token = $this->getDownloadToken();
        if (!$token) {
            return false; // No valid download token
        }
        
        $stmt = $db->prepare('INSERT INTO downloads (
            user_id, photo_id, order_id, download_token, download_count,
            first_downloaded_at, last_downloaded_at, first_ip_address,
            last_ip_address, first_user_agent, last_user_agent
        ) VALUES (?, ?, ?, ?, 1, NOW(), NOW(), ?, ?, ?, ?)');
        
        $order = $this->order();
        if (!$order) {
            return false;
        }
        
        return $stmt->execute([
            $order->user_id,
            $this->photo_id,
            $this->order_id,
            $token,
            $ipAddress,
            $ipAddress,
            $userAgent,
            $userAgent
        ]);
    }
}
