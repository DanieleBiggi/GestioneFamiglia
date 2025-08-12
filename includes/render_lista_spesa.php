<?php
function render_lista_spesa(array $row): void {
    $id = (int)($row['id'] ?? 0);
    $nome = htmlspecialchars($row['nome'] ?? '', ENT_QUOTES);
    $quantita = htmlspecialchars($row['quantita'] ?? '', ENT_QUOTES);
    $note = htmlspecialchars($row['note'] ?? '', ENT_QUOTES);
    $checked = !empty($row['checked']);
    $line = $checked ? ' text-decoration-line-through' : '';
    echo '<div class="d-flex justify-content-between align-items-center py-2 border-bottom" data-id="' . $id . '">';
    echo '  <div class="flex-grow-1' . $line . '">';
    echo        $nome;
    if ($quantita !== '') {
        echo ' <span class="badge bg-secondary ms-2">' . $quantita . '</span>';
    }
    if ($note !== '') {
        echo '<br><small class="text-muted">' . $note . '</small>';
    }
    echo '  </div>';
    echo '  <div class="d-flex align-items-center ms-2">';
    echo '    <button type="button" class="btn btn-sm btn-outline-light me-2 edit-btn" data-id="' . $id . '" data-nome="' . $nome . '" data-quantita="' . $quantita . '" data-note="' . $note . '"><i class="bi bi-pencil"></i></button>';
    echo '    <input type="checkbox" class="form-check-input" data-id="' . $id . '"' . ($checked ? ' checked' : '') . '>';
    echo '  </div>';
    echo '</div>';
}
?>
