<?php

namespace FoTeam\Models;

use FoTeam\Model;
use PDO;
use PDOException;
use Exception;

class PhotoTag extends Model
{
    protected static $table = 'photo_tags';
    protected static $primaryKey = 'id';
    protected static $fillable = [
        'photo_id',
        'tag_text',
        'confidence',
        'bounding_box',
        'created_at'
    ];

    /**
     * Get the photo this tag belongs to
     */
    public function photo(): ?Photo
    {
        return Photo::find($this->photo_id);
    }

    /**
     * Get the bounding box as an array
     */
    public function getBoundingBox(): ?array
    {
        if (empty($this->bounding_box)) {
            return null;
        }
        
        $box = json_decode($this->bounding_box, true);
        return is_array($box) ? $box : null;
    }

    /**
     * Set the bounding box from an array
     */
    public function setBoundingBox(?array $box): self
    {
        $this->bounding_box = $box ? json_encode($box) : null;
        return $this;
    }

    /**
     * Get the confidence as a percentage
     */
    public function getConfidencePercentage(): string
    {
        return round($this->confidence * 100, 1) . '%';
    }

    /**
     * Find photos by tag text
     */
    public static function findPhotosByTag(string $tag, ?int $photographerId = null, ?int $limit = null): array
    {
        $db = self::getDb();
        
        $sql = 'SELECT p.* FROM photos p 
                JOIN photo_tags pt ON p.id = pt.photo_id 
                WHERE pt.tag_text = ?';
                
        $params = [$tag];
        
        if ($photographerId !== null) {
            $sql .= ' AND p.photographer_id = ?';
            $params[] = $photographerId;
        }
        
        $sql .= ' AND p.is_approved = 1';
        $sql .= ' ORDER BY p.created_at DESC';
        
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return array_map(function($item) {
            return new Photo($item);
        }, $stmt->fetchAll());
    }

    /**
     * Get all unique tags with counts
     */
    public static function getAllTags(?int $photographerId = null, ?int $limit = null): array
    {
        $db = self::getDb();
        
        $sql = 'SELECT pt.tag_text, COUNT(*) as count 
                FROM photo_tags pt';
                
        if ($photographerId !== null) {
            $sql .= ' JOIN photos p ON pt.photo_id = p.id 
                     WHERE p.photographer_id = ?';
            $params = [$photographerId];
        } else {
            $params = [];
        }
        
        $sql .= ' GROUP BY pt.tag_text 
                 ORDER BY count DESC, pt.tag_text ASC';
                 
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get similar tags (fuzzy search)
     */
    public static function findSimilarTags(string $query, ?int $photographerId = null, int $limit = 10): array
    {
        $db = self::getDb();
        
        $sql = 'SELECT DISTINCT pt.tag_text 
                FROM photo_tags pt';
                
        if ($photographerId !== null) {
            $sql .= ' JOIN photos p ON pt.photo_id = p.id 
                     WHERE p.photographer_id = ? AND pt.tag_text LIKE ?';
            $params = [$photographerId, $query . '%'];
        } else {
            $sql .= ' WHERE pt.tag_text LIKE ?';
            $params = [$query . '%'];
        }
        
        $sql .= ' ORDER BY pt.tag_text ASC 
                 LIMIT ?';
                 
        $params[] = $limit;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get the most popular tags
     */
    public static function getPopularTags(?int $photographerId = null, int $limit = 10): array
    {
        $db = self::getDb();
        
        $sql = 'SELECT pt.tag_text, COUNT(*) as count 
                FROM photo_tags pt';
                
        if ($photographerId !== null) {
            $sql .= ' JOIN photos p ON pt.photo_id = p.id 
                     WHERE p.photographer_id = ?';
            $params = [$photographerId];
        } else {
            $params = [];
        }
        
        $sql .= ' GROUP BY pt.tag_text 
                 ORDER BY count DESC, pt.tag_text ASC 
                 LIMIT ?';
                 
        $params[] = $limit;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get the most recent tags
     */
    public static function getRecentTags(?int $photographerId = null, int $limit = 10): array
    {
        $db = self::getDb();
        
        $sql = 'SELECT DISTINCT pt.tag_text, MAX(pt.created_at) as last_used 
                FROM photo_tags pt';
                
        if ($photographerId !== null) {
            $sql .= ' JOIN photos p ON pt.photo_id = p.id 
                     WHERE p.photographer_id = ?';
            $params = [$photographerId];
        } else {
            $params = [];
        }
        
        $sql .= ' GROUP BY pt.tag_text 
                 ORDER BY last_used DESC 
                 LIMIT ?';
                 
        $params[] = $limit;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Merge tags (useful for normalizing similar tags)
     */
    public static function mergeTags(string $targetTag, array $tagsToMerge): bool
    {
        if (empty($tagsToMerge)) {
            return true;
        }
        
        $db = self::getDb();
        
        try {
            $db->beginTransaction();
            
            // First, update all photo_tags to use the target tag
            $placeholders = rtrim(str_repeat('?,', count($tagsToMerge)), ',');
            $params = array_merge([$targetTag], $tagsToMerge);
            
            $sql = "UPDATE photo_tags 
                    SET tag_text = ? 
                    WHERE tag_text IN ($placeholders)";
                    
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            // Now delete any duplicate tag entries for the same photo
            $sql = "DELETE t1 FROM photo_tags t1
                    INNER JOIN photo_tags t2 
                    WHERE t1.id < t2.id 
                    AND t1.photo_id = t2.photo_id 
                    AND t1.tag_text = t2.tag_text";
                    
            $db->exec($sql);
            
            $db->commit();
            return true;
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
