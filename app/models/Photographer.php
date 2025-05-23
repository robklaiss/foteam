<?php

namespace FoTeam\Models;

use FoTeam\Model;
use PDO;
use PDOException;
use Exception;

class Photographer extends Model
{
    protected static $table = 'photographers';
    protected static $primaryKey = 'id';
    protected static $fillable = [
        'user_id',
        'bio',
        'website',
        'instagram_handle',
        'is_approved',
        'max_upload_size_mb',
        'created_at',
        'updated_at'
    ];

    /**
     * Get the user associated with this photographer profile
     */
    public function user(): ?User
    {
        return User::find($this->user_id);
    }

    /**
     * Get all albums for this photographer
     */
    public function albums(): array
    {
        $db = self::getDb();
        $stmt = $db->prepare('SELECT * FROM albums WHERE photographer_id = ? ORDER BY created_at DESC');
        $stmt->execute([$this->id]);
        
        return array_map(function($item) {
            return new Album($item);
        }, $stmt->fetchAll());
    }

    /**
     * Get all photos uploaded by this photographer
     */
    public function photos(array $filters = []): array
    {
        $db = self::getDb();
        $sql = 'SELECT * FROM photos WHERE photographer_id = ?';
        $params = [$this->id];

        // Apply filters
        if (!empty($filters['album_id'])) {
            $sql .= ' AND album_id = ?';
            $params[] = $filters['album_id'];
        }

        if (!empty($filters['is_approved'])) {
            $sql .= ' AND is_approved = ?';
            $params[] = $filters['is_approved'] ? 1 : 0;
        }

        if (!empty($filters['is_featured'])) {
            $sql .= ' AND is_featured = ?';
            $params[] = 1;
        }

        $sql .= ' ORDER BY created_at DESC';

        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ?';
            $params[] = (int)$filters['limit'];
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return array_map(function($item) {
            return new Photo($item);
        }, $stmt->fetchAll());
    }

    /**
     * Get the total number of photos uploaded by this photographer
     */
    public function getTotalPhotos(): int
    {
        $db = self::getDb();
        $stmt = $db->prepare('SELECT COUNT(*) FROM photos WHERE photographer_id = ?');
        $stmt->execute([$this->id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get the total storage used by this photographer in MB
     */
    public function getStorageUsed(): float
    {
        $db = self::getDb();
        $stmt = $db->prepare('SELECT COALESCE(SUM(file_size), 0) / 1024 / 1024 FROM photos WHERE photographer_id = ?');
        $stmt->execute([$this->id]);
        return (float)round($stmt->fetchColumn(), 2);
    }

    /**
     * Get the storage limit in MB
     */
    public function getStorageLimit(): int
    {
        return $this->max_upload_size_mb;
    }

    /**
     * Check if the photographer has reached their storage limit
     */
    public function hasReachedStorageLimit(): bool
    {
        return $this->getStorageUsed() >= $this->getStorageLimit();
    }

    /**
     * Get the remaining storage in MB
     */
    public function getRemainingStorage(): float
    {
        return max(0, $this->getStorageLimit() - $this->getStorageUsed());
    }

    /**
     * Approve this photographer
     */
    public function approve(): bool
    {
        $this->is_approved = 1;
        return $this->save();
    }

    /**
     * Reject this photographer
     */
    public function reject(): bool
    {
        $this->is_approved = 0;
        return $this->save();
    }

    /**
     * Get the photographer's full name
     */
    public function getFullName(): string
    {
        $user = $this->user();
        return $user ? $user->getFullName() : 'Unknown Photographer';
    }

    /**
     * Get the photographer's email
     */
    public function getEmail(): ?string
    {
        $user = $this->user();
        return $user ? $user->email : null;
    }
}
