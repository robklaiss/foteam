<?php

namespace FoTeam\Models;

use FoTeam\Model;
use PDO;
use PDOException;
use Exception;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class Photo extends Model
{
    protected static $table = 'photos';
    protected static $primaryKey = 'id';
    protected static $fillable = [
        'album_id',
        'photographer_id',
        'original_filename',
        'storage_path',
        'thumbnail_path',
        'watermark_path',
        'mime_type',
        'file_size',
        'width',
        'height',
        'is_approved',
        'is_featured',
        'taken_at',
        'created_at',
        'updated_at'
    ];

    // Image processing settings
    protected const THUMBNAIL_WIDTH = 300;
    protected const WATERMARK_OPACITY = 50; // 0-100

    /**
     * Get the album this photo belongs to
     */
    public function album(): ?Album
    {
        return Album::find($this->album_id);
    }

    /**
     * Get the photographer who uploaded this photo
     */
    public function photographer(): ?Photographer
    {
        return Photographer::find($this->photographer_id);
    }

    /**
     * Get all tags for this photo
     */
    public function tags(): array
    {
        $db = self::getDb();
        $stmt = $db->prepare('SELECT * FROM photo_tags WHERE photo_id = ?');
        $stmt->execute([$this->id]);
        
        return array_map(function($item) {
            return new PhotoTag($item);
        }, $stmt->fetchAll());
    }

    /**
     * Add a tag to this photo
     */
    public function addTag(string $tagText, float $confidence, ?array $boundingBox = null): bool
    {
        $tag = new PhotoTag([
            'photo_id' => $this->id,
            'tag_text' => $tagText,
            'confidence' => $confidence,
            'bounding_box' => $boundingBox ? json_encode($boundingBox) : null
        ]);

        return $tag->save();
    }

    /**
     * Remove a tag from this photo
     */
    public function removeTag(string $tagText): bool
    {
        $db = self::getDb();
        $stmt = $db->prepare('DELETE FROM photo_tags WHERE photo_id = ? AND tag_text = ?');
        return $stmt->execute([$this->id, $tagText]);
    }

    /**
     * Check if this photo has a specific tag
     */
    public function hasTag(string $tagText): bool
    {
        $db = self::getDb();
        $stmt = $db->prepare('SELECT COUNT(*) FROM photo_tags WHERE photo_id = ? AND tag_text = ?');
        $stmt->execute([$this->id, $tagText]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Get the URL to the original photo
     */
    public function getOriginalUrl(): string
    {
        return $this->getFileUrl($this->storage_path);
    }

    /**
     * Get the URL to the thumbnail
     */
    public function getThumbnailUrl(): string
    {
        return $this->getFileUrl($this->thumbnail_path);
    }

    /**
     * Get the URL to the watermarked version
     */
    public function getWatermarkUrl(): string
    {
        return $this->getFileUrl($this->watermark_path);
    }

    /**
     * Helper to get file URL
     */
    protected function getFileUrl(string $path): string
    {
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }
        
        // Remove leading slash if present
        $path = ltrim($path, '/');
        
        // Get base URL from config
        $baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
        
        return $baseUrl . '/' . $path;
    }

    /**
     * Process and save the uploaded photo
     */
    public static function processUpload(
        string $tempPath,
        string $originalFilename,
        int $photographerId,
        int $albumId,
        ?string $watermarkPath = null
    ): ?self {
        try {
            // Create image manager instance
            $manager = new ImageManager(new Driver());
            $image = $manager->read($tempPath);
            
            // Get image info
            $mimeType = mime_content_type($tempPath);
            $fileSize = filesize($tempPath);
            $width = $image->width();
            $height = $image->height();
            
            // Generate unique filename
            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            $filename = uniqid('photo_') . '.' . $extension;
            
            // Define paths
            $uploadDir = 'uploads/' . date('Y/m/d');
            $storageDir = $_SERVER['DOCUMENT_ROOT'] . '/storage/' . $uploadDir;
            
            // Create directory if it doesn't exist
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }
            
            $storagePath = $uploadDir . '/' . $filename;
            $fullStoragePath = $storageDir . '/' . $filename;
            
            // Save original
            $image->save($fullStoragePath);
            
            // Create thumbnail
            $thumbnailPath = $uploadDir . '/thumbs/' . $filename;
            $fullThumbnailPath = $storageDir . '/thumbs/' . $filename;
            
            if (!is_dir(dirname($fullThumbnailPath))) {
                mkdir(dirname($fullThumbnailPath), 0755, true);
            }
            
            $image->cover(self::THUMBNAIL_WIDTH, self::THUMBNAIL_WIDTH);
            $image->save($fullThumbnailPath);
            
            // Apply watermark if provided
            $watermarkFullPath = null;
            if ($watermarkPath && file_exists($watermarkPath)) {
                $watermarkFilename = 'watermark_' . $filename;
                $watermarkStoragePath = $uploadDir . '/watermarked/' . $watermarkFilename;
                $fullWatermarkPath = $storageDir . '/watermarked/' . $watermarkFilename;
                
                if (!is_dir(dirname($fullWatermarkPath))) {
                    mkdir(dirname($fullWatermarkPath), 0755, true);
                }
                
                // Reload original for watermarking
                $watermarkedImage = $manager->read($tempPath);
                $watermark = $manager->read($watermarkPath);
                
                // Calculate watermark position (center)
                $x = ($watermarkedImage->width() - $watermark->width()) / 2;
                $y = ($watermarkedImage->height() - $watermark->height()) / 2;
                
                // Place watermark with opacity
                $watermarkedImage->place(
                    $watermark->opacity(self::WATERMARK_OPACITY),
                    'top-left',
                    (int)$x,
                    (int)$y
                );
                
                $watermarkedImage->save($fullWatermarkPath);
                $watermarkFullPath = $watermarkStoragePath;
            }
            
            // Create photo record
            $photo = new self([
                'album_id' => $albumId,
                'photographer_id' => $photographerId,
                'original_filename' => $originalFilename,
                'storage_path' => $storagePath,
                'thumbnail_path' => $thumbnailPath,
                'watermark_path' => $watermarkFullPath,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'width' => $width,
                'height' => $height,
                'is_approved' => 0,
                'is_featured' => 0,
                'taken_at' => date('Y-m-d H:i:s'),
            ]);
            
            return $photo->save() ? $photo : null;
            
        } catch (Exception $e) {
            // Log the error
            error_log('Photo upload error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Approve this photo
     */
    public function approve(): bool
    {
        $this->is_approved = 1;
        return $this->save();
    }

    /**
     * Reject this photo
     */
    public function reject(): bool
    {
        $this->is_approved = 0;
        return $this->save();
    }

    /**
     * Toggle featured status
     */
    public function toggleFeatured(): bool
    {
        $this->is_featured = $this->is_featured ? 0 : 1;
        return $this->save();
    }

    /**
     * Get related photos (from the same album)
     */
    public function getRelatedPhotos(int $limit = 4): array
    {
        if (!$this->album_id) {
            return [];
        }
        
        $db = self::getDb();
        $sql = 'SELECT * FROM photos 
                WHERE album_id = ? AND id != ? AND is_approved = 1 
                ORDER BY RAND() LIMIT ?';
                
        $stmt = $db->prepare($sql);
        $stmt->execute([$this->album_id, $this->id, $limit]);
        
        return array_map(function($item) {
            return new self($item);
        }, $stmt->fetchAll());
    }
}
