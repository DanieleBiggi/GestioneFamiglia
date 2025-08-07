<?php
function render_password(array $row) {
    $classes = 'password-card movement d-flex justify-content-between align-items-start text-white text-decoration-none';
    if (isset($row['attiva']) && !$row['attiva']) {
        $classes .= ' inactive';
    }
    $search = strtolower(($row['url_login'] ?? '') . ' ' . ($row['username'] ?? '') . ' ' . ($row['note'] ?? ''));
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $url = 'password_dettaglio.php?id=' . (int)$row['id_account'];
    echo '<div class="' . $classes . '" data-search="' . $searchAttr . '" onclick="window.location.href=\'' . $url . '\'">';
    echo '  <div class="flex-grow-1">';
    $icon = !empty($row['attiva']) ? '<i class="bi bi-check-circle-fill text-success me-2"></i>' : '<i class="bi bi-x-circle-fill text-danger me-2"></i>';
    $urlLogin = htmlspecialchars($row['url_login']);
    if (filter_var($row['url_login'], FILTER_VALIDATE_URL)) {
        $urlLogin = '<a href="' . htmlspecialchars($row['url_login']) . '" target="_blank" onclick="event.stopPropagation();">' . $urlLogin . '</a>';
    }
    echo '    <div class="fw-semibold">' . $icon . $urlLogin . '</div>';
    echo '    <div class="small">Username: ' . htmlspecialchars($row['username']) . '</div>';
    echo '    <div class="small">Password: ' . htmlspecialchars($row['password_account']) . '</div>';
    if (!empty($row['note'])) {
        echo '    <div class="small text-muted">' . htmlspecialchars($row['note']) . '</div>';
    }
    echo '  </div>';
    echo '</div>';
}
?>
