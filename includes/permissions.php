<?php
function has_permission(mysqli $conn, string $resource, string $action): bool {
    if (!isset($_SESSION['userlevelid'])) {
        return false;
    }
    $stmt = $conn->prepare(
        'SELECT up.can_view, up.can_insert, up.can_update, up.can_delete '
        . 'FROM userlevel_permissions up '
        . 'JOIN resources r ON up.resource_id = r.id '
        . 'WHERE up.userlevelid = ? AND r.name = ?'
    );
    $stmt->bind_param('is', $_SESSION['userlevelid'], $resource);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return false;
    }
    $map = [
        'view' => 'can_view',
        'insert' => 'can_insert',
        'update' => 'can_update',
        'delete' => 'can_delete'
    ];
    $field = $map[$action] ?? null;
    if (!$field) {
        return false;
    }
    return (bool)$row[$field];
}
?>
