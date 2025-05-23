<?php
/**
 * Environment Variable Loader
 * 
 * This file loads environment variables from a .env file
 * for secure credential storage.
 */

/**
 * Load environment variables from .env file
 * 
 * @return bool True if .env file was loaded successfully, false otherwise
 */
function load_env_file() {
    $env_file = dirname(__DIR__) . '/.env';
    
    if (!file_exists($env_file)) {
        error_log('Warning: .env file not found at ' . $env_file);
        return false;
    }
    
    error_log('Loading environment variables from: ' . $env_file);
    
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $loaded_count = 0;
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Check if line contains an equals sign
        if (strpos($line, '=') === false) {
            continue;
        }
        
        // Parse the line
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Remove quotes if present
        if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
            $value = substr($value, 1, -1);
        } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
            $value = substr($value, 1, -1);
        }
        
        // Set environment variable
        if (!empty($name)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            $loaded_count++;
            
            // Log first and last 3 characters of sensitive values for debugging
            if (strpos($name, 'KEY') !== false || strpos($name, 'SECRET') !== false || strpos($name, 'PASSWORD') !== false) {
                $masked_value = strlen($value) > 6 ? 
                    substr($value, 0, 3) . '...' . substr($value, -3) : 
                    '***';
                error_log("Loaded environment variable: $name = $masked_value");
            } else {
                error_log("Loaded environment variable: $name");
            }
        }
    }
    
    error_log("Loaded $loaded_count environment variables");
    return $loaded_count > 0;
}

/**
 * Get an environment variable with an optional default value
 * 
 * @param string $key The environment variable name
 * @param mixed $default The default value if the variable is not set
 * @return mixed The environment variable value or the default value
 */
function env($key, $default = null) {
    // First check $_ENV array
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    
    // Then check $_SERVER array
    if (isset($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    
    // Finally check getenv()
    $value = getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    return $value;
}

/**
 * Directly set an environment variable
 * 
 * @param string $key The environment variable name
 * @param mixed $value The value to set
 * @return bool True if successful
 */
function set_env($key, $value) {
    putenv("$key=$value");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    return true;
}

// Load environment variables when this file is included
$env_loaded = load_env_file();

// If .env file wasn't found or had no variables, set some defaults for testing
if (!$env_loaded) {
    error_log('Setting default API key for testing since .env file was not loaded');
    set_env('GOOGLE_CLOUD_API_KEY', 'AIzaSyC3ukUv1YwP6eGEgw8JZTUjXk4rBeEYeOo');
}
?>
