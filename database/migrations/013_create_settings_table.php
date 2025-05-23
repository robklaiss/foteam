<?php

class Migration_013_create_settings_table {
    
    /**
     * Run the migrations
     */
    public function up(PDO $pdo): void
    {
        // Global settings table
        $pdo->exec('CREATE TABLE IF NOT EXISTS settings (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            `group` VARCHAR(100) NOT NULL DEFAULT "general",
            `key` VARCHAR(100) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            value TEXT DEFAULT NULL,
            type ENUM("text", "textarea", "number", "boolean", "select", "multiselect", "radio", "checkbox", "file", "color", "date", "datetime", "time") NOT NULL DEFAULT "text",
            options TEXT DEFAULT NULL COMMENT "JSON array of options for select, radio, etc.",
            is_public BOOLEAN DEFAULT 0 COMMENT "Whether this setting is publicly accessible",
            is_encrypted BOOLEAN DEFAULT 0 COMMENT "Whether the value should be encrypted",
            validation_rules TEXT DEFAULT NULL COMMENT "Validation rules in JSON format",
            `order` INT UNSIGNED DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_settings_group_key (`group`, `key`),
            INDEX idx_settings_group (`group`),
            INDEX idx_settings_public (is_public)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // User settings table
        $pdo->exec('CREATE TABLE IF NOT EXISTS user_settings (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            `key` VARCHAR(100) NOT NULL,
            value TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uk_user_settings (user_id, `key`),
            INDEX idx_user_settings_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Settings history/audit log
        $pdo->exec('CREATE TABLE IF NOT EXISTS setting_audits (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            setting_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            action ENUM("created", "updated", "deleted") NOT NULL,
            old_value TEXT DEFAULT NULL,
            new_value TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (setting_id) REFERENCES settings(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_setting_audits_setting (setting_id),
            INDEX idx_setting_audits_user (user_id),
            INDEX idx_setting_audits_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Insert default settings
        $this->insertDefaultSettings($pdo);
    }
    
    /**
     * Insert default application settings
     */
    private function insertDefaultSettings(PDO $pdo): void
    {
        $defaultSettings = [
            // General Settings
            [
                'group' => 'general',
                'key' => 'site_name',
                'display_name' => 'Site Name',
                'value' => 'FoTeam',
                'type' => 'text',
                'is_public' => 1,
                'order' => 1
            ],
            [
                'group' => 'general',
                'key' => 'site_description',
                'display_name' => 'Site Description',
                'value' => 'Professional marathon photo management platform',
                'type' => 'textarea',
                'is_public' => 1,
                'order' => 2
            ],
            [
                'group' => 'general',
                'key' => 'timezone',
                'display_name' => 'Timezone',
                'value' => 'UTC',
                'type' => 'select',
                'options' => json_encode([
                    ['value' => 'UTC', 'label' => 'UTC'],
                    ['value' => 'America/New_York', 'label' => 'Eastern Time (US & Canada)'],
                    // Add more timezones as needed
                ]),
                'order' => 3
            ],
            [
                'group' => 'general',
                'key' => 'date_format',
                'display_name' => 'Date Format',
                'value' => 'Y-m-d',
                'type' => 'select',
                'options' => json_encode([
                    ['value' => 'Y-m-d', 'label' => 'YYYY-MM-DD (2023-01-15)'],
                    ['value' => 'm/d/Y', 'label' => 'MM/DD/YYYY (01/15/2023)'],
                    ['value' => 'd/m/Y', 'label' => 'DD/MM/YYYY (15/01/2023)'],
                ]),
                'order' => 4
            ],
            
            // Email Settings
            [
                'group' => 'mail',
                'key' => 'mail_driver',
                'display_name' => 'Mail Driver',
                'value' => 'smtp',
                'type' => 'select',
                'options' => json_encode([
                    ['value' => 'smtp', 'label' => 'SMTP'],
                    ['value' => 'mailgun', 'label' => 'Mailgun'],
                    ['value' => 'ses', 'label' => 'Amazon SES'],
                    ['value' => 'sendmail', 'label' => 'Sendmail'],
                ]),
                'order' => 10
            ],
            [
                'group' => 'mail',
                'key' => 'mail_host',
                'display_name' => 'SMTP Host',
                'value' => 'smtp.mailtrap.io',
                'type' => 'text',
                'order' => 11
            ],
            [
                'group' => 'mail',
                'key' => 'mail_port',
                'display_name' => 'SMTP Port',
                'value' => '2525',
                'type' => 'number',
                'order' => 12
            ],
            [
                'group' => 'mail',
                'key' => 'mail_username',
                'display_name' => 'SMTP Username',
                'value' => '',
                'type' => 'text',
                'order' => 13
            ],
            [
                'group' => 'mail',
                'key' => 'mail_password',
                'display_name' => 'SMTP Password',
                'value' => '',
                'type' => 'password',
                'is_encrypted' => 1,
                'order' => 14
            ],
            [
                'group' => 'mail',
                'key' => 'mail_encryption',
                'display_name' => 'Encryption',
                'value' => 'tls',
                'type' => 'select',
                'options' => json_encode([
                    ['value' => 'tls', 'label' => 'TLS'],
                    ['value' => 'ssl', 'label' => 'SSL'],
                    ['value' => '', 'label' => 'None'],
                ]),
                'order' => 15
            ],
            [
                'group' => 'mail',
                'key' => 'mail_from_address',
                'display_name' => 'From Address',
                'value' => 'noreply@example.com',
                'type' => 'email',
                'order' => 16
            ],
            [
                'group' => 'mail',
                'key' => 'mail_from_name',
                'display_name' => 'From Name',
                'value' => 'FoTeam',
                'type' => 'text',
                'order' => 17
            ],
            
            // File Storage Settings
            [
                'group' => 'storage',
                'key' => 'default_disk',
                'display_name' => 'Default Storage Disk',
                'value' => 'local',
                'type' => 'select',
                'options' => json_encode([
                    ['value' => 'local', 'label' => 'Local'],
                    ['value' => 's3', 'label' => 'Amazon S3'],
                    ['value' => 'gcs', 'label' => 'Google Cloud Storage'],
                ]),
                'order' => 20
            ],
            [
                'group' => 'storage',
                'key' => 'max_upload_size',
                'display_name' => 'Maximum Upload Size (MB)',
                'value' => '10',
                'type' => 'number',
                'validation_rules' => json_encode(['min:1', 'max:100']),
                'order' => 21
            ],
            [
                'group' => 'storage',
                'key' => 'allowed_file_types',
                'display_name' => 'Allowed File Types',
                'value' => 'jpg,jpeg,png,gif,webp',
                'type' => 'text',
                'order' => 22
            ],
            
            // Image Processing Settings
            [
                'group' => 'images',
                'key' => 'image_quality',
                'display_name' => 'Image Quality (1-100)',
                'value' => '85',
                'type' => 'number',
                'validation_rules' => json_encode(['min:1', 'max:100']),
                'order' => 30
            ],
            [
                'group' => 'images',
                'key' => 'create_thumbnails',
                'display_name' => 'Create Thumbnails',
                'value' => '1',
                'type' => 'boolean',
                'order' => 31
            ],
            [
                'group' => 'images',
                'key' => 'thumbnail_width',
                'display_name' => 'Thumbnail Width (px)',
                'value' => '320',
                'type' => 'number',
                'order' => 32
            ],
            [
                'group' => 'images',
                'key' => 'thumbnail_height',
                'display_name' => 'Thumbnail Height (px)',
                'value' => '240',
                'type' => 'number',
                'order' => 33
            ],
            [
                'group' => 'images',
                'key' => 'add_watermark',
                'display_name' => 'Add Watermark to Images',
                'value' => '0',
                'type' => 'boolean',
                'order' => 34
            ],
            
            // Payment Settings
            [
                'group' => 'payments',
                'key' => 'currency',
                'display_name' => 'Default Currency',
                'value' => 'USD',
                'type' => 'select',
                'options' => json_encode([
                    ['value' => 'USD', 'label' => 'US Dollar (USD)'],
                    ['value' => 'EUR', 'label' => 'Euro (EUR)'],
                    ['value' => 'GBP', 'label' => 'British Pound (GBP)'],
                    // Add more currencies as needed
                ]),
                'order' => 40
            ],
            [
                'group' => 'payments',
                'key' => 'tax_rate',
                'display_name' => 'Tax Rate (%)',
                'value' => '0',
                'type' => 'number',
                'validation_rules' => json_encode(['min:0', 'max:100']),
                'order' => 41
            ],
            [
                'group' => 'payments',
                'key' => 'stripe_public_key',
                'display_name' => 'Stripe Public Key',
                'value' => '',
                'type' => 'password',
                'is_encrypted' => 1,
                'order' => 42
            ],
            [
                'group' => 'payments',
                'key' => 'stripe_secret_key',
                'display_name' => 'Stripe Secret Key',
                'value' => '',
                'type' => 'password',
                'is_encrypted' => 1,
                'order' => 43
            ],
            [
                'group' => 'payments',
                'key' => 'stripe_webhook_secret',
                'display_name' => 'Stripe Webhook Secret',
                'value' => '',
                'type' => 'password',
                'is_encrypted' => 1,
                'order' => 44
            ],
            
            // Google API Settings
            [
                'group' => 'google',
                'key' => 'google_maps_api_key',
                'display_name' => 'Google Maps API Key',
                'value' => '',
                'type' => 'password',
                'is_encrypted' => 1,
                'order' => 50
            ],
            [
                'group' => 'google',
                'key' => 'google_analytics_id',
                'display_name' => 'Google Analytics ID',
                'value' => '',
                'type' => 'text',
                'is_public' => 1,
                'order' => 51
            ],
            [
                'group' => 'google',
                'key' => 'google_vision_api_key',
                'display_name' => 'Google Vision API Key',
                'value' => '',
                'type' => 'password',
                'is_encrypted' => 1,
                'order' => 52
            ],
            
            // Social Media Settings
            [
                'group' => 'social',
                'key' => 'facebook_url',
                'display_name' => 'Facebook URL',
                'value' => '',
                'type' => 'url',
                'is_public' => 1,
                'order' => 60
            ],
            [
                'group' => 'social',
                'key' => 'twitter_url',
                'display_name' => 'Twitter URL',
                'value' => '',
                'type' => 'url',
                'is_public' => 1,
                'order' => 61
            ],
            [
                'group' => 'social',
                'key' => 'instagram_url',
                'display_name' => 'Instagram URL',
                'value' => '',
                'type' => 'url',
                'is_public' => 1,
                'order' => 62
            ],
            [
                'group' => 'social',
                'key' => 'linkedin_url',
                'display_name' => 'LinkedIn URL',
                'value' => '',
                'type' => 'url',
                'is_public' => 1,
                'order' => 63
            ],
            
            // Maintenance Settings
            [
                'group' => 'maintenance',
                'key' => 'maintenance_mode',
                'display_name' => 'Maintenance Mode',
                'value' => '0',
                'type' => 'boolean',
                'order' => 70
            ],
            [
                'group' => 'maintenance',
                'key' => 'maintenance_message',
                'display_name' => 'Maintenance Message',
                'value' => 'We are currently performing maintenance. Please check back soon.',
                'type' => 'textarea',
                'is_public' => 1,
                'order' => 71
            ],
            [
                'group' => 'maintenance',
                'key' => 'allowed_ips',
                'display_name' => 'Allowed IPs (comma-separated)',
                'value' => '127.0.0.1',
                'type' => 'text',
                'order' => 72
            ]
        ];
        
        $stmt = $pdo->prepare('INSERT INTO settings (`group`, `key`, display_name, value, type, options, is_public, is_encrypted, validation_rules, `order`)
                              VALUES (:group, :key, :display_name, :value, :type, :options, :is_public, :is_encrypted, :validation_rules, :order)');
        
        foreach ($defaultSettings as $setting) {
            $stmt->execute([
                'group' => $setting['group'],
                'key' => $setting['key'],
                'display_name' => $setting['display_name'],
                'value' => $setting['value'],
                'type' => $setting['type'],
                'options' => $setting['options'] ?? null,
                'is_public' => $setting['is_public'] ?? 0,
                'is_encrypted' => $setting['is_encrypted'] ?? 0,
                'validation_rules' => $setting['validation_rules'] ?? null,
                'order' => $setting['order']
            ]);
        }
    }
    
    /**
     * Rollback the migrations
     */
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS setting_audits');
        $pdo->exec('DROP TABLE IF EXISTS user_settings');
        $pdo->exec('DROP TABLE IF EXISTS settings');
    }
}
