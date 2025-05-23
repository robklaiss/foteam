<?php

class Migration_003_create_photo_metadata_table {
    
    /**
     * Run the migrations
     */
    public function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS photo_metadata (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            photo_id INTEGER NOT NULL,
            camera_make VARCHAR(100) DEFAULT NULL,
            camera_model VARCHAR(100) DEFAULT NULL,
            lens_model VARCHAR(100) DEFAULT NULL,
            focal_length VARCHAR(20) DEFAULT NULL,
            aperture VARCHAR(20) DEFAULT NULL,
            shutter_speed VARCHAR(20) DEFAULT NULL,
            iso INTEGER DEFAULT NULL,
            gps_latitude DECIMAL(10, 8) DEFAULT NULL,
            gps_longitude DECIMAL(11, 8) DEFAULT NULL,
            gps_altitude DECIMAL(10, 2) DEFAULT NULL,
            location_name VARCHAR(255) DEFAULT NULL,
            copyright VARCHAR(255) DEFAULT NULL,
            keywords TEXT DEFAULT NULL,
            custom_fields JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE,
            UNIQUE KEY uk_photo_metadata_photo_id (photo_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Add index for common search fields
        $pdo->exec('CREATE INDEX idx_photo_metadata_camera_make ON photo_metadata(camera_make)');
        $pdo->exec('CREATE INDEX idx_photo_metadata_camera_model ON photo_metadata(camera_model)');
        $pdo->exec('CREATE INDEX idx_photo_metadata_location ON photo_metadata(location_name)');
    }
    
    /**
     * Rollback the migrations
     */
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS photo_metadata');
    }
}
