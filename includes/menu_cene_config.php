<?php
/**
 * Configurazione alimentare per famiglia. I file privati risiedono in config/
 * (directory esclusa dal versionamento). Nessun JSON è esposto direttamente dal web.
 */
function food_config_path(int $familyId, string $kind): string {
    if ($familyId <= 0 || !in_array($kind, ['master', 'ricette'], true)) {
        throw new InvalidArgumentException('Configurazione non valida');
    }
    return dirname(__DIR__) . '/config/menu_cene/' . $familyId . '/' . $kind . '.json';
}
function food_config_read(int $familyId, string $kind): ?array {
    $path = food_config_path($familyId, $kind);
    if (!is_file($path)) return null;
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Impossibile leggere la configurazione');
    $value = json_decode($raw, true);
    if (!is_array($value) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Configurazione salvata non valida');
    }
    food_config_validate($kind, $value);
    return $value;
}
function food_config_validate(string $kind, array $data): void {
    if (($data['version'] ?? null) !== 1) throw new InvalidArgumentException('Versione JSON non supportata');
    if ($kind === 'master') {
        $cycle = $data['ciclo'] ?? null;
        if (!is_array($cycle) || ($cycle['settimane'] ?? null) !== 4
            || !is_int($cycle['settimana_riferimento'] ?? null)
            || $cycle['settimana_riferimento'] < 1 || $cycle['settimana_riferimento'] > 4) {
            throw new InvalidArgumentException('Ciclo non valido: sono richieste quattro settimane');
        }
        $anchor = $cycle['data_riferimento'] ?? '';
        $date = is_string($anchor) ? DateTimeImmutable::createFromFormat('!Y-m-d', $anchor) : false;
        if (!$date || $date->format('Y-m-d') !== $anchor || $date->format('N') !== '1') {
            throw new InvalidArgumentException('Data di riferimento non valida: usare un lunedì YYYY-MM-DD');
        }
        foreach (['Estivo', 'Invernale'] as $season) {
            foreach (range(1, 4) as $week) {
                foreach (['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì'] as $day) {
                    $item = $data['stagioni'][$season][(string)$week][$day] ?? null;
                    if (!is_array($item) || !is_string($item['cena'] ?? null)
                        || trim($item['cena']) === '' || !is_string($item['contorno'] ?? null)) {
                        throw new InvalidArgumentException("Menù incompleto: $season, settimana $week, $day");
                    }
                    if (mb_strlen($item['cena']) > 500 || mb_strlen($item['contorno']) > 500) {
                        throw new InvalidArgumentException('Descrizione piatto troppo lunga');
                    }
                }
            }
        }
        if (isset($data['anno_scolastico']) && !is_string($data['anno_scolastico'])) {
            throw new InvalidArgumentException('Anno scolastico non valido');
        }
    } elseif ($kind === 'ricette') {
        if (!isset($data['ricette']) || !is_array($data['ricette']) || array_keys($data['ricette']) !== range(0, count($data['ricette']) - 1) && count($data['ricette']) > 0) {
            throw new InvalidArgumentException('Ricette deve essere un elenco');
        }
        $ids = [];
        foreach ($data['ricette'] as $r) {
            if (!is_array($r) || !is_string($r['id'] ?? null) || !preg_match('/^[a-z0-9_-]{1,80}$/', $r['id'])
                || !is_string($r['nome'] ?? null) || trim($r['nome']) === ''
                || !is_string($r['istruzioni'] ?? null)
                || !isset($r['alias']) || !is_array($r['alias']) || (count($r['alias']) > 0 && array_keys($r['alias']) !== range(0, count($r['alias']) - 1))) {
                throw new InvalidArgumentException('Ricetta non valida');
            }
            if (isset($ids[$r['id']])) throw new InvalidArgumentException('ID ricetta duplicato');
            $ids[$r['id']] = true;
            if (mb_strlen($r['nome']) > 150 || mb_strlen($r['istruzioni']) > 3000) {
                throw new InvalidArgumentException('Ricetta troppo lunga');
            }
            foreach ($r['alias'] as $alias) {
                if (!is_string($alias) || trim($alias) === '' || mb_strlen($alias) > 150) {
                    throw new InvalidArgumentException('Alias non valido');
                }
            }
        }
    } else {
        throw new InvalidArgumentException('Tipo di configurazione non valido');
    }
}
function food_config_save(int $familyId, string $kind, array $data): void {
    food_config_validate($kind, $data);
    $path = food_config_path($familyId, $kind);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Directory non scrivibile');
    }
    $lockPath = $dir . '/' . $kind . '.lock';
    $lock = fopen($lockPath, 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Impossibile bloccare la configurazione');
    try {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $tmp = tempnam($dir, '.new_');
        if (!$tmp) throw new RuntimeException('Impossibile creare file temporaneo');
        try {
            if (file_put_contents($tmp, $json . "\n") === false) throw new RuntimeException('Errore di scrittura');
            chmod($tmp, 0600);
            if (is_file($path) && !copy($path, $path . '.bak')) throw new RuntimeException('Backup non riuscito');
            if (!rename($tmp, $path)) throw new RuntimeException('Salvataggio non riuscito');
        } finally {
            if (is_file($tmp)) unlink($tmp);
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
function food_config_default_recipes(): array {
    return ['version' => 1, 'ricette' => []];
}
function food_config_recipes(int $familyId): array {
    return food_config_read($familyId, 'ricette') ?? food_config_default_recipes();
}
function food_config_norm(string $name): string {
    $name = mb_strtolower(trim($name), 'UTF-8');
    return preg_replace('/\s+/u', ' ', $name);
}
function food_config_match_recipes(array $menuItems, array $recipes): array {
    $map = [];
    foreach ($recipes['ricette'] as $recipe) {
        foreach (array_merge([$recipe['nome']], $recipe['alias']) as $alias) {
            $key = food_config_norm($alias);
            if ($key !== '') $map[$key][$recipe['id']] = $recipe;
        }
    }
    $matched = [];
    $unmatched = [];
    foreach ($menuItems as $item) {
        foreach (preg_split('/\r?\n|\s*\+\s*|\s*;\s*/u', $item['piatto'] ?? '') as $dish) {
            $dish = trim($dish);
            if ($dish === '') continue;
            $choices = array_values($map[food_config_norm($dish)] ?? []);
            if (count($choices) === 1) {
                $matched[$choices[0]['id']] = $choices[0];
            } else {
                $unmatched[] = ['giorno' => $item['giorno'], 'piatto' => $dish, 'motivo' => count($choices) > 1 ? 'ambiguo' : 'senza ricetta'];
            }
        }
    }
    return ['ricette' => array_values($matched), 'non_associati' => $unmatched];
}
