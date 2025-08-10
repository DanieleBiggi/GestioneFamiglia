<?php
function render_lista_spesa(array $row): void {
    $id = (int)($row['id'] ?? 0);
    $nome = htmlspecialchars($row['nome'] ?? '', ENT_QUOTES);
    $checked = !empty($row['checked']);
    $line = $checked ? ' text-decoration-line-through' : '';
    echo '<div class="d-flex justify-content-between align-items-center py-2 border-bottom" data-id="' . $id . '">';
    echo '  <span class="flex-grow-1' . $line . '">' . $nome . '</span>';
    echo '  <input type="checkbox" class="form-check-input ms-2" data-id="' . $id . '"' . ($checked ? ' checked' : '') . '>';
    echo '</div>';
}
?>
