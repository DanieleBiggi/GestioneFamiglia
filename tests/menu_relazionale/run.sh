#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
repo=$PWD
php_bin=${PHP_BIN:-php}
temp=$(mktemp -d "${RUNNER_TEMP:-/tmp}/gf-menu.XXXXXX")
db_container=gf-menu-test-${temp##*.}
server_pid=
cleanup() {
    if [[ -n "$server_pid" ]]; then kill -- "-$server_pid" 2>/dev/null || true; fi
    docker rm -f "$db_container" >/dev/null 2>&1 || true
    rm -rf "$temp"
}
trap cleanup EXIT

export DB_HOST=127.0.0.1 DB_USER=root DB_PASS=menu-ci-only DB_NAME=Sql1203781_2
export GF_MENU_TEST_CONTAINER="$db_container"
docker run -d --name "$db_container" -p 127.0.0.1::3306 \
    -e MYSQL_ROOT_PASSWORD="$DB_PASS" -e MYSQL_ROOT_HOST=% -e MYSQL_DATABASE="$DB_NAME" \
    mysql:5.6.51 >/dev/null
mysql_test() {
    docker exec -i -e MYSQL_PWD="$DB_PASS" "$db_container" \
        mysql -h127.0.0.1 -uroot --default-character-set=utf8mb4 "$DB_NAME" "$@"
}
for (( attempt=0; attempt<90; attempt++ )); do
    if mysql_test -Nse 'SELECT 1' >/dev/null 2>&1; then break; fi
    sleep 1
done
mysql_test -Nse 'SELECT VERSION()' | grep -E '^5\.6\.51'
export DB_PORT
DB_PORT=$(docker port "$db_container" 3306/tcp | cut -d: -f2)

# The dump includes DEFINER identifiers from Aruba. Use the disposable test account.
sed -E 's/^CREATE ALGORITHM=UNDEFINED DEFINER=`[^`]+`@`[^`]+` /CREATE ALGORITHM=UNDEFINED /' \
    sql/struttura.sql | mysql_test
mysql_test <<'SQL'
INSERT INTO famiglie (id_famiglia,nome_famiglia) VALUES (101,'A'),(202,'B'),(303,'Errore');
INSERT INTO menu_cene_settimanale (id_famiglia,week_start,giorno,piatto)
VALUES (101,'2020-01-06','Lunedi','  Piatto storico A  '),(202,'2020-01-06','Lunedì','Piatto storico B');
INSERT INTO lista_spesa (id_famiglia,nome) VALUES (101,'Spesa storica');
SQL
mysql_test < sql/migrations/20261124_menu_relazionale.sql
mysql_test < sql/mcp_oauth.sql

git ls-files -z '*.php' | "$php_bin" -r '
    foreach (glob("tests/*.php") as $test) {
        passthru(escapeshellarg(PHP_BINARY) . " " . escapeshellarg($test), $status);
        if ($status !== 0) exit($status);
    }
    foreach (explode("\0", rtrim(stream_get_contents(STDIN), "\0")) as $file) {
        exec(escapeshellarg(PHP_BINARY) . " -l " . escapeshellarg($file), $output, $status);
        if ($status !== 0) {
            fwrite(STDERR, "Errore di sintassi in $file\n");
            exit($status);
        }
    }
'
node --check js/menu_cene_config.js
node --check js/salvadanaio_dettaglio.js

mkdir -p "$temp/www/Gestionale25" "$temp/sessions"
git ls-files -z | tar --null -T - -cf - | tar -xf - -C "$temp/www/Gestionale25"
export GF_MENU_TEST_APP="$temp/www/Gestionale25"
export GF_MENU_TEST_DIR="$temp"

# Test-only sessions and HTTPS signalling. No fixture is installed on the real web root.
printf '%s\n' '<?php $_SERVER["HTTPS"] = "on"; return false;' > "$temp/router.php"
"$php_bin" -d "session.save_path=$temp/sessions" -r '
    require getenv("GF_MENU_TEST_APP") . "/includes/menu_cene_master.php";
    foreach (["family-a"=>101,"family-a2"=>101,"family-b"=>202,"family-error"=>303] as $sid=>$family) {
        session_id($sid);
        session_start();
        $_SESSION = ["utente_id"=>1, "userlevelid"=>-1, "id_famiglia_gestione"=>$family, "menu_config_csrf"=>"test-csrf"];
        session_write_close();
        $master = ["version"=>1,"anno_scolastico"=>"2026-2027",
            "ciclo"=>["settimane"=>4,"data_riferimento"=>"2026-09-14","settimana_riferimento"=>2],
            "stagioni"=>menu_cene_default_master()];
        $master["stagioni"]["Estivo"][2]["Lunedì"] = ["cena"=>"Pasta; INSALATA + tè 🍋","contorno"=>"  Zucchine  "];
        $master["stagioni"]["Estivo"][2]["Martedì"]["cena"] = str_repeat("è", 500);
        $master["stagioni"]["Estivo"][2]["Mercoledì"]["cena"] = "Prima riga\nSeconda riga";
        food_config_save($family, "master", $master);
        food_config_save($family, "ricette", ["version"=>1,"ricette"=>[["id"=>"pasta","nome"=>"Pasta","istruzioni"=>"Ricetta $family"]]]);
    }
'
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj /CN=127.0.0.1 \
    -addext subjectAltName=IP:127.0.0.1 -keyout "$temp/tls.key" -out "$temp/tls.crt" 2>/dev/null
export PHP_CLI_SERVER_WORKERS=4
setsid "$php_bin" -d "session.save_path=$temp/sessions" -d "curl.cainfo=$temp/tls.crt" \
    -d date.timezone=Europe/Rome -S 127.0.0.1:38081 -t "$temp/www" "$temp/router.php" >"$temp/php.log" 2>&1 &
server_pid=$!
if ! python3 tests/menu_relazionale/http_test.py; then
    tail -120 "$temp/php.log" >&2
    exit 1
fi
