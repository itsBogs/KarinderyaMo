
<?php
// db.php - simple PDO connection helper for Karinderya Mo
// Adjust the DSN, user and password to match your local setup.
// Update these to your hosting MySQL credentials (not FTP). Example:
// define('DB_HOST', 'sql100.byetcluster.com');
// define('DB_NAME', 'ezyro_40605877_karinderya_mo');
// define('DB_USER', 'ezyro_40605877');
// define('DB_PASS', 'your_mysql_password');
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'karinderya_mo');
define('DB_USER', 'root');
define('DB_PASS', '');

function getPDO(){
    static $pdo = null;
    if($pdo) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try{
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }catch(PDOException $e){
        http_response_code(500);
        if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Database connection failed. Please try again later.']);
        } else {
            echo "Database connection failed: " . htmlspecialchars($e->getMessage());
        }
        exit;
    }
}
?>
