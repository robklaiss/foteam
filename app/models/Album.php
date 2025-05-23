<?php

namespace FoTeam\Models;

use FoTeam\Model;
use PDO;
use PDOException;
use Exception;

class Album extends Model
{
    protected static $table = 'albums';
    protected static $primaryKey = 'id';
    protected static $fillable = [
        'event_id',
        'photographer_id',
        'name',
        'description',
        'is_public',
        'cover_photo_id',
        'created_at',
        'updated_at'
    ];

    /**
     * Get the event this album belongs to
     */
    public function event(): ?Event
    {
        return Event::find($this->event_id);
    }

    /**
     * Get the photographer who created this album
     */
    public function photographer(): ?Photographer
    {
        return Photographer::find($this->photographer_id);
    }

    /**
     * Get the cover photo for this album
     */
    public function coverPhoto(): ?Photo
    {
        if (!$this->cover_photo_id) {
            return $this->getFirstPhoto();
        }
        return Photo::find($this->cover_photo_id);
    }

    /**
     * Get the first photo in this album
     */
    public function getFirstPhoto(): ?Photo
    {
        $db = self::getDb();
        $stmt = $db->prepare('SELECT * FROM photos WHERE album_id = ? AND is_approved = 1 ORDER BY id ASC LIMIT 1');
        $stmt->execute([$this->id]);
        $photoData = $stmt->fetch();
        
        return $photoData ? new Photo($photoData) : null;
    }

    /**
     * Get all photos in this album
     */
    public function photos(array $filters = []): array
    {
        $db = self::getDb();
        $sql = 'SELECT * FROM photos WHERE album_id = ?';
        $params = [$this->id];

        // Apply filters
        if (!empty($filters['is_approved'])) {
            $sql .= ' AND is_approved = ?';
            $params[] = $filters['is_approved'] ? 1 : 0;
        }

        if (!empty($filters['is_featured'])) {
            $sql .= ' AND is_featured = ?';
            $params[] = 1;
        }


        // Default ordering
        $sql .= ' ORDER BY ' . ($filters['order_by'] ?? 'created_at') . ' ' . 
                ($filters['order_direction'] ?? 'DESC');

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
     * Get the number of photos in this album
     */
    public function getPhotoCount(): int
    {
        $db = self::getDb();
        $stmt = $db->prepare('SELECT COUNT(*) FROM photos WHERE album_id = ?');
        $stmt->execute([$this->id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get the number of photos with a specific tag in this album
     */
    public function getPhotoCountByTag(string $tag): int
    {
        $db = self::getDb();
        $sql = 'SELECT COUNT(DISTINCT p.id) 
                FROM photos p 
                JOIN photo_tags pt ON p.id = pt.photo_id 
                WHERE p.album_id = ? AND pt.tag_text = ?';
                
        $stmt = $db->prepare($sql);
        $stmt->execute([$this->id, $tag]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get all unique tags in this album
     */
    public function getTags(): array
    {
        $db = self::getDb();
        $sql = 'SELECT DISTINCT pt.tag_text, COUNT(*) as count 
                FROM photo_tags pt 
                JOIN photos p ON pt.photo_id = p.id 
                WHERE p.album_id = ? 
                GROUP BY pt.tag_text 
                ORDER BY count DESC';
                
        $stmt = $db->prepare($sql);
        $stmt->execute([$this->id]);
        return $stmt->fetchAll();
    }

    /**
     * Check if the album is empty
     */
    public function isEmpty(): bool
    {
        return $this->getPhotoCount() === 0;
    }

    /**
     * Make the album public
     */
    public function makePublic(): bool
    {
        $this->is_public = 1;
        return $this->save();
    }

    /**
     * Make the album private
     */
    public function makePrivate(): bool
    {
        $this->is_public = 0;
        return $this->save();
    }

    /**
     * Set the cover photo for this album
     */
    public function setCoverPhoto(int $photoId): bool
    {
        // Verify the photo belongs to this album
        $db = self::getDb();
        $stmt = $db->prepare('SELECT id FROM photos WHERE id = ? AND album_id = ?');
        $stmt->execute([$photoId, $this->id]);
        
        if ($stmt->fetch()) {
            $this->cover_photo_id = $photoId;
            return $this->save();
        }
        
        return false;
    }

    /**
     * Get the URL to view this album
     */
    public function getUrl(): string
    {
        $baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
        return $baseUrl . '/album/' . $this->id . '-' . $this->slugify($this->name);
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

        return $text ?: 'album';
    }
}
