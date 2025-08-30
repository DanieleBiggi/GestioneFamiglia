<?php
$id = (int)($_GET['id'] ?? 0);
header('Location: vacanze_view.php?id=' . $id);
exit;
?>
