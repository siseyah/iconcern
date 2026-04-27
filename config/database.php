<?php
/**
 * Database Configuration
 * iconcern - Database Connection Settings
 */

// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'iconcern_db');
define('DB_CHARSET', 'utf8mb4');

/**
 * Database Connection Class
 */
class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $this->connect();
    }

    private function connect() {
        $hosts = array_values(array_unique([DB_HOST, '127.0.0.1', 'localhost']));
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $lastError = null;
        foreach ($hosts as $host) {
            try {
                $dsn = "mysql:host=" . $host . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
                return;
            } catch (PDOException $e) {
                $lastError = $e;
            }
        }

        if ($lastError) {
            die(
                "Database Connection Failed: " . $lastError->getMessage() .
                " | Tried hosts: " . implode(', ', $hosts) . " on port " . DB_PORT .
                ". Ensure MySQL is running in XAMPP."
            );
        }
    }

    private function shouldReconnect(PDOException $e) {
        $msg = strtolower($e->getMessage());
        return (strpos($msg, 'server has gone away') !== false) ||
               (strpos($msg, 'lost connection') !== false) ||
               (strpos($msg, 'error: 2006') !== false) ||
               (strpos($msg, 'error: 2013') !== false);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        // Keep long-running sessions resilient to dropped MySQL connections.
        try {
            $this->conn->query('SELECT 1');
        } catch (PDOException $e) {
            if ($this->shouldReconnect($e)) {
                $this->connect();
            } else {
                throw $e;
            }
        }
        return $this->conn;
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Get database connection
function getDB() {
    return Database::getInstance()->getConnection();
}

