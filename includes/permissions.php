<?php
function has_permission(mysqli $conn, string $resource, string $action): bool {
    if (!isset($_SESSION['userlevelid'])) {
        return false;
    }
    if($_SESSION['userlevelid']==-1)
    {
        return true;
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
        $stmt = $conn->prepare("SELECT id FROM resources WHERE name = ?");
        $stmt->bind_param("s", $resource);
        $stmt->execute();
        $stmt->bind_result($resource_id);
        $found = $stmt->fetch();
        $stmt->close();
        
        // Se non trovata, la inserisco
        if (!$found) {
            $stmt = $conn->prepare("INSERT INTO resources (name) VALUES (?)");
            $stmt->bind_param("s", $resource);
            if ($stmt->execute()) {
                $resource_id = $conn->insert_id;
            } else {
                die("Errore nell'inserimento della risorsa: " . $stmt->error);
            }
            $stmt->close();
        }
        $stmt = $conn->prepare(
            "INSERT INTO userlevel_permissions (userlevelid, resource_id, can_view, can_insert, can_update, can_delete)
             VALUES (?, ?, 0, 0, 0, 0)"
        );
        $stmt->bind_param(
            "ii",
            $_SESSION['userlevelid'],
            $resource_id
        );
        
        if ($stmt->execute()) {
            //echo "Permessi inseriti correttamente";
        } else {
            echo $_SESSION['userlevelid']." - ".$resource_id."<br>";
            echo "Errore nell'INSERT: " . $stmt->error;
        }
        
        $stmt->close();
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
