<?php

namespace FoTeam\Models;

use FoTeam\Model;
use PDO;
use PDOException;
use Exception;

class User extends Model
{
    protected static $table = 'users';
    protected static $primaryKey = 'id';
    protected static $fillable = [
        'email',
        'password_hash',
        'first_name',
        'last_name',
        'role',
        'is_active',
        'created_at',
        'updated_at'
    ];

    /**
     * Create a new user
     */
    public static function create(array $data): ?self
    {
        $required = ['email', 'password', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        // Check if email already exists
        if (self::emailExists($data['email'])) {
            throw new Exception('Email already in use');
        }

        $user = new self([
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => $data['role'] ?? 'user',
            'is_active' => $data['is_active'] ?? 1
        ]);

        return $user->save() ? $user : null;
    }

    /**
     * Authenticate a user
     */
    public static function authenticate(string $email, string $password): ?self
    {
        $db = self::getDb();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $userData = $stmt->fetch();

        if (!$userData) {
            return null;
        }

        if (!password_verify($password, $userData['password_hash'])) {
            return null;
        }

        return new self($userData);
    }

    /**
     * Check if email exists
     */
    public static function emailExists(string $email, ?int $excludeId = null): bool
    {
        $db = self::getDb();
        $sql = 'SELECT COUNT(*) FROM users WHERE email = ?';
        $params = [$email];

        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }


        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Get user's full name
     */
    public function getFullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is a photographer
     */
    public function isPhotographer(): bool
    {
        return in_array($this->role, ['photographer', 'admin']);
    }

    /**
     * Get the photographer profile if available
     */
    public function photographer()
    {
        if (!$this->isPhotographer()) {
            return null;
        }

        $db = self::getDb();
        $stmt = $db->prepare('SELECT * FROM photographers WHERE user_id = ? LIMIT 1');
        $stmt->execute([$this->id]);
        $photographerData = $stmt->fetch();

        return $photographerData ? new Photographer($photographerData) : null;
    }

    /**
     * Get user's orders
     */
    public function orders(): array
    {
        $db = self::getDb();
        $stmt = $db->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$this->id]);
        
        return array_map(function($item) {
            return new Order($item);
        }, $stmt->fetchAll());
    }

    /**
     * Get user's purchased photos
     */
    public function purchasedPhotos(): array
    {
        $db = self::getDb();
        $sql = 'SELECT p.* FROM photos p 
                JOIN order_items oi ON p.id = oi.photo_id 
                JOIN orders o ON oi.order_id = o.id 
                WHERE o.user_id = ? AND o.status = "completed"';
                
        $stmt = $db->prepare($sql);
        $stmt->execute([$this->id]);
        
        return array_map(function($item) {
            return new Photo($item);
        }, $stmt->fetchAll());
    }
}
