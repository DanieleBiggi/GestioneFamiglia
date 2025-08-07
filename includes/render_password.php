<?php
function render_password(array $row) {
    $isActive = (int)($row['attiva'] ?? 0) === 1;
    $classes = 'password-card movement d-flex justify-content-between align-items-start text-white text-decoration-none';
    if (!$isActive) {
        $classes .= ' inactive';
    }
    $search = strtolower(($row['url_login'] ?? '') . ' ' . ($row['username'] ?? '') . ' ' . ($row['password_account'] ?? ''));
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $url = 'password_dettaglio.php?id=' . (int)$row['id_account_password'];
    $style = $isActive ? '' : ' style="display:none;"';
    echo '<div class="' . $classes . '" data-search="' . $searchAttr . '"' . $style . ' onclick="window.location.href=\'' . $url . '\'">';
    echo '  <div class="flex-grow-1">';
    $urlLogin = htmlspecialchars($row['url_login']);
    if (filter_var($row['url_login'], FILTER_VALIDATE_URL)) {
        $urlLogin = '<a href="' . htmlspecialchars($row['url_login']) . '" target="_blank" onclick="event.stopPropagation();">' . $urlLogin . '</a>';
    }
    echo '    <div class="fw-semibold">' . $urlLogin . '</div>';
    echo '    <div class="small">Username: ' . htmlspecialchars($row['username']) . '</div>';
    echo '    <div class="small">Password: ' . htmlspecialchars($row['password_account']) . '</div>';
    if (!empty($row['note'])) {
        echo '    <div class="small text-muted">' . htmlspecialchars($row['note']) . '</div>';
    }
    echo '  </div>';
    $icons = '';
    if (!empty($row['condivisa_con_famiglia'])) {
        $icons .= '<i class="bi bi-people-fill text-info me-2" title="Condivisa con famiglia"></i>';
    }
    $icons .= $isActive
        ? '<i class="bi bi-check-circle-fill text-success"></i>'
        : '<i class="bi bi-x-circle-fill text-danger"></i>';
    echo '  <div class="ms-2 text-nowrap">' . $icons . '</div>';
    echo '</div>';
}
?>
