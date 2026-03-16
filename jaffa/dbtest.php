<?php
declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// ---- Load .env ----
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

// ---- Now you can safely use getenv() ----
try {
    $pdo = new PDO(
        getenv('DB_DSN'),
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "✅ Connected to database successfully.<br>";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database:<br>";
    echo "<pre>" . print_r($tables, true) . "</pre>";

    $stmt = $pdo->query('SELECT COUNT(*) AS count FROM notifications');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Notifications table has {$row['count']} row(s).";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}