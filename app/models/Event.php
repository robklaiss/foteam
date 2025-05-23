<?php

namespace FoTeam\Models;

use FoTeam\Model;
use PDO;
use PDOException;
use Exception;

class Event extends Model
{
    protected static $table = 'events';
    protected static $primaryKey = 'id';
    protected static $fillable = [
        'name',
        'description',
        'event_date',
        'location',
        'is_active',
        'created_at',
        'updated_at'
    ];

    /**
     * Get all albums for this event
     */
    public function albums(array $filters = []): array
    {
        $db = self::getDb();
        $sql = 'SELECT * FROM albums WHERE event_id = ?';
        $params = [$this->id];

        // Apply filters
        if (isset($filters['is_public'])) {
            $sql .= ' AND is_public = ?';
            $params[] = $filters['is_public'] ? 1 : 0;
        }

        // Default ordering
        $sql .= ' ORDER BY created_at DESC';

        // Apply limit if specified
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ?';
            $params[] = (int)$filters['limit'];
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return array_map(function($item) {
            return new Album($item);
        }, $stmt->fetchAll());
    }

    /**
     * Get all photos from this event
     */
    public function photos(array $filters = []): array
    {
        $db = self::getDb();
        $sql = 'SELECT p.* FROM photos p 
                JOIN albums a ON p.album_id = a.id 
                WHERE a.event_id = ?';
        $params = [$this->id];

        // Apply filters
        if (!empty($filters['is_approved'])) {
            $sql .= ' AND p.is_approved = ?';
            $params[] = $filters['is_approved'] ? 1 : 0;
        }

        if (!empty($filters['is_featured'])) {
            $sql .= ' AND p.is_featured = ?';
            $params[] = 1;
        }

        // Default ordering
        $sql .= ' ORDER BY p.created_at DESC';

        // Apply limit if specified
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
     * Get the number of photos in this event
     */
    public function getPhotoCount(): int
    {
        $db = self::getDb();
        $sql = 'SELECT COUNT(p.id) FROM photos p 
                JOIN albums a ON p.album_id = a.id 
                WHERE a.event_id = ?';
                
        $stmt = $db->prepare($sql);
        $stmt->execute([$this->id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get the number of albums in this event
     */
    public function getAlbumCount(): int
    {
        $db = self::getDb();
        $stmt = $db->prepare('SELECT COUNT(*) FROM albums WHERE event_id = ?');
        $stmt->execute([$this->id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get all unique tags in this event
     */
    public function getTags(): array
    {
        $db = self::getDb();
        $sql = 'SELECT DISTINCT pt.tag_text, COUNT(*) as count 
                FROM photo_tags pt 
                JOIN photos p ON pt.photo_id = p.id 
                JOIN albums a ON p.album_id = a.id 
                WHERE a.event_id = ? 
                GROUP BY pt.tag_text 
                ORDER BY count DESC';
                
        $stmt = $db->prepare($sql);
        $stmt->execute([$this->id]);
        return $stmt->fetchAll();
    }

    /**
     * Get the cover photo for this event
     */
    public function getCoverPhoto(): ?Photo
    {
        // Try to get from an album's cover photo first
        $db = self::getDb();
        $sql = 'SELECT p.* FROM photos p 
                JOIN albums a ON p.album_id = a.id 
                WHERE a.event_id = ? AND a.cover_photo_id IS NOT NULL 
                LIMIT 1';
                
        $stmt = $db->prepare($sql);
        $stmt->execute([$this->id]);
        $photoData = $stmt->fetch();
        
        if ($photoData) {
            return new Photo($photoData);
        }
        
        // If no album cover, get the first photo from the first album
        $sql = 'SELECT p.* FROM photos p 
                JOIN albums a ON p.album_id = a.id 
                WHERE a.event_id = ? 
                ORDER BY p.id ASC 
                LIMIT 1';
                
        $stmt = $db->prepare($sql);
        $stmt->execute([$this->id]);
        $photoData = $stmt->fetch();
        
        return $photoData ? new Photo($photoData) : null;
    }

    /**
     * Activate this event
     */
    public function activate(): bool
    {
        $this->is_active = 1;
        return $this->save();
    }

    /**
     * Deactivate this event
     */
    public function deactivate(): bool
    {
        $this->is_active = 0;
        return $this->save();
    }

    /**
     * Check if the event is upcoming
     */
    public function isUpcoming(): bool
    {
        return strtotime($this->event_date) > time();
    }

    /**
     * Check if the event is ongoing
     */
    public function isOngoing(): bool
    {
        $eventDate = strtotime($this->event_date);
        $endDate = strtotime('+1 day', $eventDate); // Assuming 1-day event by default
        $now = time();
        
        return $now >= $eventDate && $now <= $endDate;
    }

    /**
     * Check if the event is past
     */
    public function isPast(): bool
    {
        $eventDate = strtotime($this->event_date);
        $endDate = strtotime('+1 day', $eventDate); // Assuming 1-day event by default
        
        return time() > $endDate;
    }

    /**
     * Get the URL to view this event
     */
    public function getUrl(): string
    {
        $baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
        return $baseUrl . '/event/' . $this->id . '-' . $this->slugify($this->name);
    }

    /**
     * Helper to create URL-friendly slugs
     */
    protected function slugify(string $text): string
    {
        // Replace non-letter or non-digit characters with -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        // Transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // Remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);
        // Trim
        $text = trim($text, '-');
        // Remove duplicate -
        $text = preg_replace('~-+~', '-', $text);
        // Lowercase
        $text = strtolower($text);

        return $text ?: 'event';
    }
}
