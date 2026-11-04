<?php
require_once __DIR__ . '/db.php';
session_start();
$themeId = $_SESSION['theme_id'] ?? 1;
$stmt = $conn->prepare('SELECT background_color, text_color, primary_color, secondary_color FROM temi WHERE id = ?');
$stmt->bind_param('i', $themeId);
$stmt->execute();
$theme = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$theme) {
    $theme = [
        'background_color' => '#121212',
        'text_color' => '#ffffff',
        'primary_color' => '#1f1f1f',
        'secondary_color' => '#2b2b2b'
    ];
}
// Cards remain white for light backgrounds, while dark themes retain their surfaces.
$themeBg = (string)$theme['background_color'];
$themeIsLight = false;
if (preg_match('/^#[0-9a-f]{6}$/i', $themeBg)) {
    $red = hexdec(substr($themeBg, 1, 2));
    $green = hexdec(substr($themeBg, 3, 2));
    $blue = hexdec(substr($themeBg, 5, 2));
    $themeIsLight = (0.299 * $red + 0.587 * $green + 0.114 * $blue) >= 155;
}
$vacCardBg = $themeIsLight ? '#ffffff' : $theme['primary_color'];
$vacCardFg = $themeIsLight ? '#202b36' : $theme['text_color'];
$rgb = sscanf($theme['text_color'], "#%02x%02x%02x");
$textRgb = implode(',', $rgb);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: text/css');
?>
:root {
  --bg-color: <?= htmlspecialchars($theme['background_color']) ?>;
  --text-color: <?= htmlspecialchars($theme['text_color']) ?>;
  --primary-color: <?= htmlspecialchars($theme['primary_color']) ?>;
  --secondary-color: <?= htmlspecialchars($theme['secondary_color']) ?>;
  --text-color-rgb: <?= $textRgb ?>;
  --vac-card-bg: <?= htmlspecialchars($vacCardBg, ENT_QUOTES, 'UTF-8') ?>;
  --vac-card-fg: <?= htmlspecialchars($vacCardFg, ENT_QUOTES, 'UTF-8') ?>;
}

body {
  background-color: var(--bg-color);
  color: var(--text-color);
}

.bg-dark {
  background-color: var(--bg-color) !important;
}

.btn-dark {
  background-color: var(--bg-color) !important;
}

.dropdown-menu-dark{
  background-color: var(--primary-color) !important;
}

.text-white {
  color: var(--text-color) !important;
}

.btn-outline-light {
  color: var(--text-color);
  border-color: var(--text-color);
}

.btn-outline-light:hover {
  background-color: var(--primary-color);
  color: var(--bg-color);
}
