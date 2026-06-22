<?php
// includes/settings.php - helper to read site settings from DB and cache them
require_once __DIR__ . '/../db.php';

function load_settings() {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    try {
        $pdo = getPDO();
        $stmt = $pdo->query('SELECT key_name, value FROM settings');
        foreach ($stmt->fetchAll() as $row) {
            $cache[$row['key_name']] = $row['value'];
        }
    } catch (Exception $e) {
        // ignore and continue with defaults
    }
    return $cache;
}

function get_setting($key, $default = null) {
    $all = load_settings();
    if (isset($all[$key]) && $all[$key] !== '') return $all[$key];
    return $default;
}

?>