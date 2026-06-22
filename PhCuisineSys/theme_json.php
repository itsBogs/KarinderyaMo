<?php
// theme_json.php - returns theme colors as JSON
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$defaults = [
    'theme_primary_color'   => '#ffcb45',
    'theme_secondary_color' => '#f8d477',
    'theme_bg_color'        => '#fff9ea',
    'theme_muted_color'     => '#ffe8b4',
    'theme_text_color'      => '#1d1d1d',
];

$values = $defaults;
try {
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        "SELECT key_name, value FROM settings WHERE key_name IN ('theme_primary_color','theme_secondary_color','theme_bg_color','theme_muted_color','theme_text_color')"
    );
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
        if (!empty($v)) {
            $values[$k] = $v;
        }
    }
} catch (Exception $e) {
    // ignore and use defaults
}

echo json_encode([
    'primary'   => $values['theme_primary_color'],
    'secondary' => $values['theme_secondary_color'],
    'bg'        => $values['theme_bg_color'],
    'muted'     => $values['theme_muted_color'],
    'text'      => $values['theme_text_color'],
    // Include site metadata so clients on different origins/ports can fetch it
    'site_name' => (isset($values['site_name']) ? $values['site_name'] : null),
    'site_description' => (isset($values['site_description']) ? $values['site_description'] : null),
    'version'   => time()
]);
