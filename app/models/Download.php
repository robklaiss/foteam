<?php

namespace FoTeam\Models;

use FoTeam\Model;
use PDO;
use PDOException;
use Exception;

class Download extends Model
{
    protected static $table = 'downloads';
    protected static $primaryKey = 'id';
    protected static $fillable = [
        'user_id',
        'photo_id',
        'order_id',
        'download_token',
        'download_count',
        'first_downloaded_at',
        'last_downloaded_at',
        'expires_at',
        'first_ip_address',
        'last_ip_address',
        'first_user_agent',
        'last_user_agent',
        'created_at'
    ];

    /**
     * Get the user who downloaded the photo
     */
    public function user(): ?User
    {
        return User::find($this->user_id);
    }

    /**
     * Get the photo that was downloaded
     */
    public function photo(): ?Photo
    {
        return Photo::find($this->photo_id);
    }

    /**
     * Get the order associated with this download
     */
    public function order(): ?Order
    {
        return Order::find($this->order_id);
    }

    /**
     * Check if the download link is expired
     */
    public function isExpired(): bool
    {
        if (empty($this->expires_at)) {
            return false; // No expiration
        }
        
        return strtotime($this->expires_at) < time();
    }

    /**
     * Check if the download link is valid
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Record a download
     */
    public function recordDownload(string $ipAddress, string $userAgent): bool
    {
        $this->download_count++;
        $this->last_downloaded_at = date('Y-m-d H:i:s');
        $this->last_ip_address = $ipAddress;
        $this->last_user_agent = $userAgent;
        
        if (empty($this->first_downloaded_at)) {
            $this->first_downloaded_at = $this->last_downloaded_at;
            $this->first_ip_address = $ipAddress;
            $this->first_user_agent = $userAgent;
        }
        
        return $this->save();
    }

    /**
     * Get the remaining downloads if there's a limit
     */
    public function getRemainingDownloads(): ?int
    {
        $maxDownloads = getenv('MAX_DOWNLOADS_PER_TOKEN') ? (int)getenv('MAX_DOWNLOADS_PER_TOKEN') : null;
        
        if ($maxDownloads === null) {
            return null; // No limit
        }
        
        return max(0, $maxDownloads - $this->download_count);
    }

    /**
     * Check if there are downloads remaining
     */
    public function hasDownloadsRemaining(): bool
    {
        $remaining = $this->getRemainingDownloads();
        return $remaining === null || $remaining > 0;
    }

    /**
     * Generate a new download token
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Find a download by token
     */
    public static function findByToken(string $token): ?self
    {
        $db = self::getDb();
        $stmt = $db->prepare('SELECT * FROM downloads WHERE download_token = ? LIMIT 1');
        $stmt->execute([$token]);
        
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Get all downloads for a user
     */
    public static function findByUser(int $userId, ?int $limit = null): array
    {
        $db = self::getDb();
        $sql = 'SELECT * FROM downloads WHERE user_id = ? ORDER BY last_downloaded_at DESC';
        
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId, $limit]);
        } else {
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
        }
        
        return array_map(function($item) {
            return new self($item);
        }, $stmt->fetchAll());
    }

    /**
     * Get all downloads for a photo
     */
    public static function findByPhoto(int $photoId, ?int $limit = null): array
    {
        $db = self::getDb();
        $sql = 'SELECT * FROM downloads WHERE photo_id = ? ORDER BY last_downloaded_at DESC';
        
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
            $stmt = $db->prepare($sql);
            $stmt->execute([$photoId, $limit]);
        } else {
            $stmt = $db->prepare($sql);
            $stmt->execute([$photoId]);
        }
        
        return array_map(function($item) {
            return new self($item);
        }, $stmt->fetchAll());
    }

    /**
     * Get all downloads for an order
     */
    public static function findByOrder(int $orderId, ?int $limit = null): array
    {
        $db = self::getDb();
        $sql = 'SELECT * FROM downloads WHERE order_id = ? ORDER BY last_downloaded_at DESC';
        
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
            $stmt = $db->prepare($sql);
            $stmt->execute([$orderId, $limit]);
        } else {
            $stmt = $db->prepare($sql);
            $stmt->execute([$orderId]);
        }
        
        return array_map(function($item) {
            return new self($item);
        }, $stmt->fetchAll());
    }

    /**
     * Clean up expired downloads
     */
    public static function cleanupExpired(): int
    {
        $db = self::getDb();
        
        // Delete expired downloads
        $stmt = $db->prepare('DELETE FROM downloads WHERE expires_at IS NOT NULL AND expires_at < NOW()');
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    /**
     * Get download statistics
     */
    public static function getStats(?int $photographerId = null, ?string $period = null): array
    {
        $db = self::getDb();
        
        $sql = 'SELECT 
                    COUNT(*) as total_downloads,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT photo_id) as unique_photos,
                    SUM(download_count) as total_files_downloaded
                FROM downloads d';
        
        $params = [];
        
        // Filter by photographer if specified
        if ($photographerId !== null) {
            $sql .= ' JOIN photos p ON d.photo_id = p.id WHERE p.photographer_id = ?';
            $params[] = $photographerId;
        }
        
        // Filter by period if specified
        if ($period === 'today') {
            $sql .= ' ' . ($photographerId === null ? 'WHERE' : 'AND') . ' DATE(d.last_downloaded_at) = CURDATE()';
        } elseif ($period === 'week') {
            $sql .= ' ' . ($photographerId === null ? 'WHERE' : 'AND') . ' YEARWEEK(d.last_downloaded_at, 1) = YEARWEEK(CURDATE(), 1)';
        } elseif ($period === 'month') {
            $sql .= ' ' . ($photographerId === null ? 'WHERE' : 'AND') . ' YEAR(d.last_downloaded_at) = YEAR(CURDATE()) AND MONTH(d.last_downloaded_at) = MONTH(CURDATE())';
        } elseif ($period === 'year') {
            $sql .= ' ' . ($photographerId === null ? 'WHERE' : 'AND') . ' YEAR(d.last_downloaded_at) = YEAR(CURDATE())';
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
