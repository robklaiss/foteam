<?php
/**
 * Custom session handler to provide better control over session timeouts and regeneration
 */
class CustomSessionHandler implements \SessionHandlerInterface, \SessionIdInterface, \SessionUpdateTimestampHandlerInterface {
    private string $savePath;
    private int $timeout = 5; // 5 seconds for testing, should be 1800 (30 minutes) in production
    private bool $sessionExpired = false;
    
    public function __construct() {
        // Get the save path
        $this->savePath = $this->getSessionSavePath();
        
        // Create the directory if it doesn't exist
        if (!file_exists($this->savePath)) {
            @mkdir($this->savePath, 0777, true);
        }
        
        // Set a short timeout for testing (5 seconds)
        $this->timeout = 5;
    }
    
    private function getSessionSavePath(): string {
        // First try the configured save path
        $savePath = session_save_path();
        
        // If empty, use the default temporary directory
        if (empty($savePath) || $savePath === '') {
            $savePath = sys_get_temp_dir() . '/foteam_sessions';
        }
        
        // Make sure the directory exists
        if (!is_dir($savePath)) {
            mkdir($savePath, 0777, true);
        }
        
        return rtrim($savePath, '/\\');
    }
    
    public function open(string $savePath, string $sessionName): bool {
        $this->savePath = $savePath ?: $this->savePath;
        if (!is_dir($this->savePath)) {
            mkdir($this->savePath, 0777, true);
        }
        return true;
    }
    
    public function close(): bool {
        return true;
    }
    
    public function read(string $sessionId): string {
        $file = "{$this->savePath}/sess_{$sessionId}";
        $currentTime = time();
        
        // Default new session data
        $newSession = [
            'initiated' => 1,
            'session_start_time' => $currentTime,
            'last_activity' => $currentTime,
            'cart' => [],
            'renewed' => 1
        ];
        
        if (!file_exists($file)) {
            error_log("Session file not found: $file");
            return $this->serialize($newSession);
        }
        
        $data = file_get_contents($file);
        if ($data === false) {
            error_log("Failed to read session file: $file");
            return $this->serialize($newSession);
        }
        
        // Extract the session data without the serialization prefix
        $sessionData = preg_replace('/^[^|]*\|/', '', $data);
        $session = $this->unserialize($sessionData);
        
        // Debug log
        error_log(sprintf(
            'Session %s - Last activity: %s, Current time: %s, Timeout: %d',
            $sessionId,
            isset($session['last_activity']) ? date('Y-m-d H:i:s', $session['last_activity']) : 'not set',
            date('Y-m-d H:i:s', $currentTime),
            $this->timeout
        ));
        
        // Check if session has expired
        if (isset($session['last_activity']) && ($currentTime - $session['last_activity'] > $this->timeout)) {
            error_log("Session $sessionId has expired");
            $this->sessionExpired = true;
            
            // Update the session file with the new session data
            file_put_contents($file, $this->serialize($newSession), LOCK_EX);
            return $this->serialize($newSession);
        }
        
        // If we get here, the session is valid
        if (is_array($session)) {
            // Update last activity time
            $session['last_activity'] = $currentTime;
            
            // Ensure required keys exist
            if (!isset($session['initiated'])) {
                $session['initiated'] = 1;
            }
            if (!isset($session['session_start_time'])) {
                $session['session_start_time'] = $currentTime;
            }
            if (!isset($session['cart']) || !is_array($session['cart'])) {
                $session['cart'] = [];
            }
            
            // If this is a renewed session, keep the renewed flag
            if (isset($session['renewed'])) {
                $session['renewed'] = 1;
            }
            
            return $this->serialize($session);
        }
        
        // If we can't decode the session, return a new session
        return $this->serialize($newSession);
    }
    
    public function write(string $sessionId, string $data): bool {
        $file = "{$this->savePath}/sess_{$sessionId}";
        
        // Ensure the session directory exists
        if (!is_dir($this->savePath)) {
            mkdir($this->savePath, 0777, true);
        }
        
        // Decode the session data
        $session = $this->unserialize($data);
        
        // If this is an expired session, don't write it
        if ($this->sessionExpired) {
            error_log("Not writing expired session: $sessionId");
            // But ensure we have a valid session file
            if (!file_exists($file)) {
                $defaultSession = [
                    'initiated' => 1,
                    'session_start_time' => time(),
                    'last_activity' => time(),
                    'cart' => [],
                    'expired' => true
                ];
                $result = file_put_contents($file, $this->serialize($defaultSession), LOCK_EX);
                if ($result !== false) {
                    chmod($file, 0600);
                }
                return $result !== false;
            }
            return true;
        }
        
        // Ensure required session structure
        $currentTime = time();
        if (!is_array($session)) {
            $session = [];
        }
        
        // Update session metadata
        $session['last_activity'] = $currentTime;
        if (!isset($session['initiated'])) {
            $session['initiated'] = 1;
        }
        if (!isset($session['session_start_time'])) {
            $session['session_start_time'] = $currentTime;
        }
        if (!isset($session['cart']) || !is_array($session['cart'])) {
            $session['cart'] = [];
        }
        
        // Serialize the updated session
        $data = $this->serialize($session);
        
        // Write the session data
        $result = file_put_contents($file, $data, LOCK_EX);
        
        // Set file permissions
        if ($result !== false) {
            chmod($file, 0600);
        }
        
        return $result !== false;
    }
    
    public function destroy(string $sessionId): bool {
        $file = "{$this->savePath}/sess_{$sessionId}";
        if (file_exists($file)) {
            unlink($file);
        }
        return true;
    }
    
    public function gc(int $maxLifetime): int|false {
        $count = 0;
        $files = glob("{$this->savePath}/sess_*");
        
        if ($files === false) {
            return 0;
        }
        
        foreach ($files as $file) {
            if (filemtime($file) + $maxLifetime < time() && file_exists($file)) {
                unlink($file);
                $count++;
            }
        }
        return $count;
    }
    
    public function create_sid(): string {
        return bin2hex(random_bytes(32));
    }
    
    public function validateId(string $sessionId): bool {
        $file = "{$this->savePath}/sess_{$sessionId}";
        if (!file_exists($file)) {
            error_log("Session file not found: $file");
            return false;
        }
        
        $data = file_get_contents($file);
        if ($data === false) {
            error_log("Failed to read session file: $file");
            return false;
        }
        
        $sessionData = preg_replace('/^[^|]*\|/', '', $data);
        $session = $this->unserialize($sessionData);
        
        // Check if session has expired
        $currentTime = time();
        if (isset($session['last_activity']) && ($currentTime - $session['last_activity'] > $this->timeout)) {
            error_log("Session $sessionId has expired");
            $this->sessionExpired = true;
            
            // Create a new session with the same ID but reset data
            $newSession = [
                'initiated' => 1,
                'session_start_time' => $currentTime,
                'last_activity' => $currentTime,
                'cart' => [],
                'renewed' => 1
            ];
            
            // Write the new session data
            file_put_contents($file, $this->serialize($newSession), LOCK_EX);
            return true;
        }
        
        // Update last activity time
        if (is_array($session)) {
            $session['last_activity'] = $currentTime;
            file_put_contents($file, $this->serialize($session), LOCK_EX);
        }
        
        return true;
    }
    
    private function unserialize(string $session_data): array {
        $session = [];
        
        // Handle empty session data
        if (empty($session_data)) {
            return [];
        }
        
        $vars = preg_split(
            '/([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff^|]*)\|/',
            $session_data, 
            -1, 
            PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
        );
        
        if ($vars === false) {
            return [];
        }
        
        for ($i = 0; $i < count($vars); $i += 2) {
            if (!isset($vars[$i+1])) {
                continue;
            }
            $value = @unserialize($vars[$i+1]);
            if ($value !== false) {
                $session[$vars[$i]] = $value;
            } else if ($vars[$i+1] === 'b:0;') {
                // Handle boolean false
                $session[$vars[$i]] = false;
            } else if ($vars[$i+1] === 'N;') {
                // Handle null
                $session[$vars[$i]] = null;
            } else if (is_numeric($vars[$i+1])) {
                // Handle numeric values
                $session[$vars[$i]] = $vars[$i+1] + 0;
            } else {
                // Fallback to string
                $session[$vars[$i]] = $vars[$i+1];
            }
        }
        
        return $session;
    }
    
    private function serialize(array $session): string {
        $session_data = '';
        foreach ($session as $key => $value) {
            $session_data .= $key . '|' . serialize($value);
        }
        return $session_data;
    }
    
    private function updateLastActivity(string $file, array $session): bool {
        if (is_array($session)) {
            $session['last_activity'] = time();
            return file_put_contents($file, $this->serialize($session), LOCK_EX) !== false;
        }
        return false;
    }
    
    public function updateTimestamp(string $sessionId, string $data): bool {
        $file = "{$this->savePath}/sess_{$sessionId}";
        if (!file_exists($file)) {
            return false;
        }
            
        // Just update the last_activity time without rewriting the entire session
        $session = $this->unserialize(preg_replace('/^[^|]*\\|/', '', $data));
        return $this->updateLastActivity($file, $session);
    }
}

/**
 * Register the custom session handler
 * 
 * @return CustomSessionHandler The session handler instance
 */
function register_custom_session_handler() {
    $handler = new CustomSessionHandler();
    
    // Only configure session if it hasn't started yet
    if (session_status() === PHP_SESSION_NONE) {
        // Set session save handler
        session_set_save_handler($handler, true);
        
        // Set session configuration
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', '1800'); // 30 minutes
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        
        // Register shutdown function to ensure session is written
        register_shutdown_function('session_write_close');
    } else {
        // If session is already started, just set the save handler
        session_set_save_handler($handler, true);
    }
    
    return $handler;
}
