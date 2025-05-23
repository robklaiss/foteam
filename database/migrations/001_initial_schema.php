<?php

/**
 * Database migration for initial schema setup
 * 
 * This migration creates all the necessary tables for the FoTeam photo platform.
 * It should be run once during the initial setup of the application.
 */

class Migration_001_initial_schema {
    
    /**
     * Run the migrations
     */
    public function up(PDO $pdo): void
    {
        // Enable foreign key constraints
        $pdo->exec('PRAGMA foreign_keys = ON;');
        
        // Users table
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            role ENUM("user", "photographer", "admin") NOT NULL DEFAULT "user",
            is_active BOOLEAN NOT NULL DEFAULT 1,
            reset_token VARCHAR(255) DEFAULT NULL,
            reset_token_expires_at DATETIME DEFAULT NULL,
            last_login_at DATETIME DEFAULT NULL,
            last_login_ip VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        
        // Photographers table
        $pdo->exec('CREATE TABLE IF NOT EXISTS photographers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            bio TEXT,
            website VARCHAR(255),
            instagram_handle VARCHAR(100),
            is_approved BOOLEAN NOT NULL DEFAULT 0,
            max_upload_size_mb INTEGER DEFAULT 1024,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(user_id)
        )');
        
        // Events table
        $pdo->exec('CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            event_date DATE NOT NULL,
            location VARCHAR(255),
            is_active BOOLEAN NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        
        // Albums table
        $pdo->exec('CREATE TABLE IF NOT EXISTS albums (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id INTEGER NOT NULL,
            photographer_id INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            is_public BOOLEAN NOT NULL DEFAULT 0,
            cover_photo_id INTEGER DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY (photographer_id) REFERENCES photographers(id) ON DELETE CASCADE,
            FOREIGN KEY (cover_photo_id) REFERENCES photos(id) ON DELETE SET NULL
        )');
        
        // Photos table
        $pdo->exec('CREATE TABLE IF NOT EXISTS photos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            album_id INTEGER NOT NULL,
            photographer_id INTEGER NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            storage_path VARCHAR(512) NOT NULL,
            thumbnail_path VARCHAR(512) NOT NULL,
            watermark_path VARCHAR(512) DEFAULT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size INTEGER NOT NULL,
            width INTEGER NOT NULL,
            height INTEGER NOT NULL,
            is_approved BOOLEAN NOT NULL DEFAULT 0,
            is_featured BOOLEAN NOT NULL DEFAULT 0,
            taken_at TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
            FOREIGN KEY (photographer_id) REFERENCES photographers(id) ON DELETE CASCADE
        )');
        
        // Photo tags table
        $pdo->exec('CREATE TABLE IF NOT EXISTS photo_tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            photo_id INTEGER NOT NULL,
            tag_text VARCHAR(50) NOT NULL,
            confidence FLOAT NOT NULL,
            bounding_box TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE,
            UNIQUE(photo_id, tag_text)
        )');
        
        // Orders table
        $pdo->exec('CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            order_number VARCHAR(50) NOT NULL UNIQUE,
            status ENUM("pending", "paid", "processing", "completed", "cancelled") NOT NULL DEFAULT "pending",
            subtotal DECIMAL(10, 2) NOT NULL,
            tax_amount DECIMAL(10, 2) NOT NULL,
            total_amount DECIMAL(10, 2) NOT NULL,
            payment_method VARCHAR(50),
            payment_id VARCHAR(255),
            customer_notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )');
        
        // Order items table
        $pdo->exec('CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            photo_id INTEGER NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            license_type VARCHAR(50) NOT NULL DEFAULT "standard",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE
        )');
        
        // Downloads table
        $pdo->exec('CREATE TABLE IF NOT EXISTS downloads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            photo_id INTEGER NOT NULL,
            order_id INTEGER,
            download_token VARCHAR(255) NOT NULL UNIQUE,
            download_count INTEGER NOT NULL DEFAULT 0,
            first_downloaded_at TIMESTAMP NULL DEFAULT NULL,
            last_downloaded_at TIMESTAMP NULL DEFAULT NULL,
            expires_at TIMESTAMP NULL DEFAULT NULL,
            first_ip_address VARCHAR(45) DEFAULT NULL,
            last_ip_address VARCHAR(45) DEFAULT NULL,
            first_user_agent TEXT,
            last_user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
        )');
        
        // Create indexes for better performance
        $this->createIndexes($pdo);
    }
    
    /**
     * Rollback the migrations
     */
    public function down(PDO $pdo): void
    {
        // Drop tables in reverse order to respect foreign key constraints
        $tables = [
            'downloads',
            'order_items',
            'orders',
            'photo_tags',
            'photos',
            'albums',
            'events',
            'photographers',
            'users'
        ];
        
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Create indexes for better query performance
     */
    protected function createIndexes(PDO $pdo): void
    {
        // Users table indexes
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)');
        
        // Photographers table indexes
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_photographers_user_id ON photographers(user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_photographers_is_approved ON photographers(is_approved)');
        
        // Events table indexes
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_events_event_date ON events(event_date)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_events_is_active ON events(is_active)');
        
        // Albums table indexes
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_albums_event_id ON albums(event_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_albums_photographer_id ON albums(photographer_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_albums_is_public ON albums(is_public)');
        
        // Photos table indexes
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_photos_album_id ON photos(album_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_photos_photographer_id ON photos(photographer_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_photos_is_approved ON photos(is_approved)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_photos_is_featured ON photos(is_featured)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_photos_taken_at ON photos(taken_at)');
        
        // Photo tags table indexes
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_photo_tags_photo_id ON photo_tags(photo_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_photo_tags_tag_text ON photo_tags(tag_text)');
        
        // Orders table indexes
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_user_id ON orders(user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_order_number ON orders(order_number)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_created_at ON orders(created_at)');
        
        // Order items table indexes
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_order_items_photo_id ON order_items(photo_id)');
        
        // Downloads table indexes
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_downloads_user_id ON downloads(user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_downloads_photo_id ON downloads(photo_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_downloads_order_id ON downloads(order_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_downloads_token ON downloads(download_token)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_downloads_expires_at ON downloads(expires_at)');
    }
}
