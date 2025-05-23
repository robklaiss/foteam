<?php

namespace FoTeam;

use PDO;
use PDOException;
use Exception;

/**
 * Base Model class
 */
abstract class Model
{
    /** @var PDO Database connection */
    protected static $db = null;

    /** @var string Table name */
    protected static $table = '';

    /** @var string Primary key */
    protected static $primaryKey = 'id';

    /** @var array Fillable fields for mass assignment */
    protected static $fillable = [];

    /** @var array Model attributes */
    protected $attributes = [];

    /**
     * Constructor
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Get the database connection
     */
    protected static function getDb(): PDO
    {
        if (self::$db === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: 'localhost',
                getenv('DB_NAME')
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$db = new PDO(
                    $dsn,
                    getenv('DB_USER'),
                    getenv('DB_PASS'),
                    $options
                );
            } catch (PDOException $e) {
                throw new Exception('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$db;
    }

    /**
     * Fill the model with an array of attributes
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, static::$fillable)) {
                $this->attributes[$key] = $value;
            }
        }
        return $this;
    }

    /**
     * Save the model to the database
     */
    public function save(): bool
    {
        if (empty($this->attributes)) {
            return false;
        }

        $db = self::getDb();
        $table = static::$table;
        $primaryKey = static::$primaryKey;
        $now = date('Y-m-d H:i:s');

        // Prepare data for insert/update
        $data = $this->attributes;
        unset($data[$primaryKey]);

        if (empty($this->$primaryKey)) {
            // Insert new record
            $columns = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
            $stmt = $db->prepare($sql);
            
            foreach ($data as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            
            $result = $stmt->execute();
            $this->$primaryKey = $db->lastInsertId();
            return $result;
        } else {
            // Update existing record
            $set = [];
            foreach (array_keys($data) as $key) {
                $set[] = "{$key} = :{$key}";
            }
            $set = implode(', ', $set);

            $sql = "UPDATE {$table} SET {$set} WHERE {$primaryKey} = :id";
            $stmt = $db->prepare($sql);
            
            foreach ($data as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindValue(':id', $this->$primaryKey);
            
            return $stmt->execute();
        }
    }

    /**
     * Find a model by primary key
     */
    public static function find($id): ?self
    {
        $db = self::getDb();
        $table = static::$table;
        $primaryKey = static::$primaryKey;
        
        $stmt = $db->prepare("SELECT * FROM {$table} WHERE {$primaryKey} = ? LIMIT 1");
        $stmt->execute([$id]);
        
        $result = $stmt->fetch();
        return $result ? new static($result) : null;
    }

    /**
     * Get all records
     */
    public static function all(): array
    {
        $db = self::getDb();
        $table = static::$table;
        
        $stmt = $db->query("SELECT * FROM {$table}");
        return array_map(function($item) {
            return new static($item);
        }, $stmt->fetchAll());
    }

    /**
     * Delete the model from the database
     */
    public function delete(): bool
    {
        if (empty($this->{static::$primaryKey})) {
            return false;
        }
        
        $db = self::getDb();
        $table = static::$table;
        $primaryKey = static::$primaryKey;
        
        $stmt = $db->prepare("DELETE FROM {$table} WHERE {$primaryKey} = ?");
        return $stmt->execute([$this->$primaryKey]);
    }

    /**
     * Magic getter for model attributes
     */
    public function __get($name)
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Magic setter for model attributes
     */
    public function __set($name, $value)
    {
        if (in_array($name, static::$fillable)) {
            $this->attributes[$name] = $value;
        }
    }

    /**
     * Magic isset for model attributes
     */
    public function __isset($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Convert the model to an array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
