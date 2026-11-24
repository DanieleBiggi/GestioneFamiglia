"""HTTP integration tests against a disposable application and MySQL 5.6."""
from concurrent.futures import ThreadPoolExecutor
from hashlib import sha256
from http.client import HTTPConnection
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import os
from pathlib import Path
import re
import socket
import ssl
import subprocess
import threading
import time
from urllib.error import HTTPError
from urllib.parse import urlencode
from urllib.request import ProxyHandler, HTTPSHandler, Request, build_opener

app = Path(os.environ["GF_MENU_TEST_APP"])
temp = Path(os.environ["GF_MENU_TEST_DIR"])
base = "https://127.0.0.1:38082/Gestionale25/"
token = "menu_ci_token_local_only_0123456789"
api_headers = {"X-GF-Api-Key": token}
oauth_token = "menu_ci_oauth_token_local_only_012345"
oauth_headers = {"Authorization": f"Bearer {oauth_token}"}
opener = build_opener(ProxyHandler({}), HTTPSHandler(context=ssl.create_default_context(cafile=temp / "tls.crt")))


class TestProxy(BaseHTTPRequestHandler):
    def forward(self):
        # TLS terminates here, like a front-end proxy. The PHP router is test-only.
        size = int(self.headers.get("Content-Length", 0))
        conn = HTTPConnection("127.0.0.1", 38081, timeout=15)
        try:
            conn.request(self.command, self.path, self.rfile.read(size), dict(self.headers))
            response = conn.getresponse()
            body = response.read()
            self.send_response(response.status)
            self.send_header("Content-Type", response.getheader("Content-Type", "text/plain"))
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)
        finally:
            conn.close()

    do_GET = forward
    do_POST = forward

    def log_message(self, *_):
        pass


def request(path, *, family=None, form=None, payload=None, headers=None, status=200, raw=False):
    headers = dict(headers or {})
    if family:
        headers["Cookie"] = f"PHPSESSID={family}"
    body = None
    if form is not None:
        headers["Content-Type"] = "application/x-www-form-urlencoded"
        body = urlencode(form).encode()
    if payload is not None:
        headers["Content-Type"] = "application/json"
        body = json.dumps(payload).encode()
    try:
        response = opener.open(Request(base + path, data=body, headers=headers), timeout=20)
    except HTTPError as error:
        response = error
    with response:
        result = response.read().decode()
        allowed = status if isinstance(status, tuple) else (status,)
        assert response.status in allowed, (path, response.status, result[:500])
    return result if raw else json.loads(result)


def sql(command):
    return subprocess.check_output([
        "docker", "exec", "-e", f"MYSQL_PWD={os.environ['DB_PASS']}", os.environ["GF_MENU_TEST_CONTAINER"],
        "mysql", "-h127.0.0.1", "-uroot", "-N", "--default-character-set=utf8mb4",
        os.environ["DB_NAME"], "-e", command,
    ], text=True).strip()


def editor(family="family-a", kind="master"):
    return request(f"ajax/menu_cene_config.php?kind={kind}", family=family)


def save(config, revision, family="family-a", kind="master", status=200, csrf="test-csrf"):
    return request("ajax/menu_cene_config.php", family=family, status=status, form={
        "kind": kind, "json": json.dumps(config), "revision": revision, "csrf_token": csrf,
    })


def migration(family, family_id, operation=None, preview=None, status=200):
    path = "menu_cene_migrazione.php"
    if operation is None:
        return request(path, family=family, raw=True, status=status)
    fields = dict(re.findall(r'name="(csrf_token|expected_hash)" value="([^"]+)"', preview))
    assert set(fields) == {"csrf_token", "expected_hash"}, preview[:400]
    return request(path, family=family, raw=True, status=status, form={
        **fields, "operation": operation, "confirm": "1", "family_id": family_id,
    })


def api(path, payload=None, request_id=None, status=200):
    headers = dict(api_headers)
    if request_id:
        headers["X-GF-Request-Id"] = request_id
    return request("api/v1/" + path, headers=headers, payload=payload, status=status)


def mcp(name, arguments=None, headers=api_headers):
    result = request("api/mcp.php", headers=headers, payload={
        "jsonrpc": "2.0", "id": 1, "method": "tools/call",
        "params": {"name": name, "arguments": arguments or {}},
    })["result"]
    assert not result["isError"], result
    return json.loads(result["content"][0]["text"])


def monday(master):
    return master["stagioni"]["Estivo"]["2"]["Lunedì"]


def configure_api(family):
    (temp / "api-family").write_text(str(family))
    config = app / "includes/api_v1_config.php"
    if not config.exists():
        config.write_text(
            "<?php return ['enabled'=>true,'family_id'=>(int)file_get_contents(getenv('GF_MENU_TEST_DIR').'/api-family')"
            + ",'token_sha256'=>'" + sha256(token.encode()).hexdigest()
            + "','mcp_rest_base_url'=>'" + base + "api/v1/'"
            + ",'mcp_oauth_enabled'=>true,'mcp_internal_key'=>'" + "f" * 64 + "'];\n"
        )


def run():
    configure_api(101)
    for _ in range(80):
        try:
            with socket.create_connection(("127.0.0.1", 38081), timeout=.25):
                break
        except OSError:
            time.sleep(.25)
    else:
        raise RuntimeError("PHP server did not start")
    original = editor()["data"]

    files = {p: p.read_bytes() for p in (app / "config/menu_cene").rglob("*.json")}
    history = sql("SELECT id_famiglia,HEX(piatto) FROM menu_cene_settimanale ORDER BY id")
    groceries = sql("SELECT * FROM lista_spesa")
    assert monday(original)["cena"] == "Pasta; INSALATA + tè 🍋"
    assert editor()["revision"] is None
    assert not save(original, "", status=409)["success"]
    assert "cfgImport" not in request("menu_cene_config.php?section=ricette", family="family-a", raw=True)

    preview = migration("family-a", 101)
    assert "Anteprima in sola lettura" in preview
    assert sql("SELECT COUNT(*) FROM menu_cene_master") == "0"
    migration("family-a", 202, "migrate", preview, status=403)
    source = app / "config/menu_cene/101/master.json"
    modified = json.loads(files[source])
    modified["anno_scolastico"] = "2027-2028"
    source.write_text(json.dumps(modified))
    assert "Migrazione non eseguita" in migration("family-a", 101, "migrate", preview)
    assert sql("SELECT COUNT(*) FROM menu_cene_master") == "0"
    source.write_bytes(files[source])

    error_preview = migration("family-error", 303)
    sql("CREATE TRIGGER fail_menu_slot BEFORE INSERT ON menu_cene_master_piatti FOR EACH ROW "
        "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced rollback'")
    assert "Migrazione non eseguita" in migration("family-error", 303, "migrate", error_preview)
    for table in ("menu_cene_master", "piatti", "ricette"):
        assert sql(f"SELECT COUNT(*) FROM {table}") == "0"
    sql("DROP TRIGGER fail_menu_slot")

    preview2 = migration("family-a2", 101)
    with ThreadPoolExecutor(max_workers=2) as pool:
        results = list(pool.map(lambda args: migration(*args),
                                [("family-a", 101, "migrate", preview), ("family-a2", 101, "migrate", preview2)]))
    assert sum("Migrazione completata" in result for result in results) == 1
    assert sql("SELECT COUNT(*) FROM menu_cene_master WHERE id_famiglia=101 AND attivo=0") == "1"
    assert api("menu_cene_master.php")["master"] == original

    activation = migration("family-a", 101)
    sql("UPDATE ricette SET istruzioni='alterata' WHERE id_famiglia=101")
    assert "Migrazione non eseguita" in migration("family-a", 101, "activate", activation)
    assert sql("SELECT SUM(attivo) FROM menu_cene_master") == "0"
    sql("UPDATE ricette SET istruzioni='Ricetta 101' WHERE id_famiglia=101")
    assert "Master attivato" in migration("family-a", 101, "activate", activation)
    assert editor()["data"] == original
    assert api("menu_cene_master.php")["master"] == original
    assert editor("family-b")["revision"] is None
    assert editor("family-b", "ricette")["data"]["ricette"][0]["istruzioni"] == "Ricetta 202"

    if os.environ.get("GF_MENU_BROWSER"):
        subprocess.run(["node", str(Path(__file__).with_name("editor_browser.cjs"))], check=True)
        assert monday(editor()["data"])["cena"] == "Modifica libera dal browser"
        assert editor()["data"]["stagioni"]["Estivo"]["2"]["Mercoledì"]["cena"] == "Prima riga\nSeconda riga"

    current = editor()
    monday(current["data"])["cena"] = "Libero;  tè + MELA"
    assert not save(current["data"], current["revision"], csrf="", status=403)["success"]
    assert save(current["data"], current["revision"])["success"]
    assert not save(original, current["revision"], "family-a2", status=409)["success"]
    assert not save(original, editor()["revision"], "family-b", status=409)["success"]
    b_preview = migration("family-b", 202)
    assert "Migrazione completata" in migration("family-b", 202, "migrate", b_preview)
    b_activation = migration("family-b", 202)
    assert "Master attivato" in migration("family-b", 202, "activate", b_activation)
    assert not save(original, editor()["revision"], "family-b", status=409)["success"]
    assert editor("family-b", "ricette")["data"]["ricette"][0]["istruzioni"] == "Ricetta 202"
    assert monday(api("menu_cene_master.php")["master"])["cena"] == "Libero;  tè + MELA"
    assert monday(mcp("leggi_menu_master_cene")["master"])["cena"] == "Libero;  tè + MELA"

    # Two simultaneous web requests must materialize only one set of five rows.
    week_path = "ajax/get_menu_cene.php?week_start=2026-09-14"
    with ThreadPoolExecutor(max_workers=2) as pool:
        weeks = list(pool.map(lambda family: request(week_path, family=family), ("family-a", "family-a2")))
    assert sum(week["generated"] for week in weeks) == 1
    assert weeks[0]["items"] == weeks[1]["items"]
    assert weeks[0]["items"][0]["piatto"] == "Libero;  tè + MELA\nZucchine"
    assert len(weeks[0]["items"][1]["piatto"].splitlines()[0]) == 500
    assert sql("SELECT COUNT(*) FROM menu_cene_settimanale WHERE id_famiglia=101 AND week_start='2026-09-14'") == "5"
    b_week = request(week_path, family="family-b")
    assert b_week["items"][0]["piatto"].startswith("Pasta; INSALATA")
    assert "Libero" in json.dumps(api("menu_cene.php?week_start=2026-09-14"))
    assert "Libero" in json.dumps(mcp("leggi_menu_settimanale", {"week_start": "2026-09-14"}))

    payload = {"ambito": "master", "stagione": "Estivo", "settimana_ciclo": 2, "operazioni": [
        {"azione": "sostituisci", "giorno": "Lunedì", "piatto": "Pasta"},
    ]}
    preview = api("menu_cene_modifica.php", dict(payload, dry_run=True))["anteprima"]
    assert monday(api("menu_cene_master.php")["master"])["cena"].startswith("Libero")
    stale = dict(payload, expected_hash="a" * 64)
    assert not api("menu_cene_modifica.php", stale, "stale-request-01", status=409)["success"]
    valid = dict(payload, expected_hash=preview["expected_hash"])
    assert api("menu_cene_modifica.php", valid, "valid-request-01")["aggiornato"]
    assert not api("menu_cene_modifica.php", valid, "valid-request-01", status=409)["success"]

    payload["operazioni"][0]["piatto"] = "MCP libero 🍋"
    preview = mcp("anteprima_modifica_menu_cene", {"payload": payload})["anteprima"]
    result = mcp("modifica_menu_cene", {"payload": dict(payload, expected_hash=preview["expected_hash"]),
                                     "confirm": True, "request_id": "mcp-save-request-001"})
    assert result["aggiornato"]
    assert monday(editor()["data"])["cena"] == "MCP libero 🍋"
    assert request(week_path, family="family-a")["items"] == weeks[0]["items"]

    weekly_payload = {"ambito": "settimana", "week_start": "2020-01-06", "operazioni": [
        {"azione": "sostituisci", "giorno": "Martedì", "piatto": "Modifica settimanale"},
    ]}
    weekly_preview = api("menu_cene_modifica.php", dict(weekly_payload, dry_run=True))["anteprima"]
    assert api("menu_cene_modifica.php", dict(weekly_payload, expected_hash=weekly_preview["expected_hash"]),
               "weekly-request-01")["aggiornato"]
    assert sql("SELECT COUNT(*) FROM menu_cene_settimanale WHERE id_famiglia=101 AND week_start='2020-01-06'") == "5"

    recipe = editor(kind="ricette")
    recipe["data"]["ricette"][0]["istruzioni"] = "Istruzioni aggiornate"
    assert save(recipe["data"], recipe["revision"], kind="ricette")["success"]
    assert editor(kind="ricette")["data"] == recipe["data"]
    assert editor("family-b", "ricette")["data"]["ricette"][0]["istruzioni"] == "Ricetta 202"

    # A failed version write must retain both the active version and version count.
    current = editor()
    count = sql("SELECT COUNT(*) FROM menu_cene_master WHERE id_famiglia=101")
    sql("CREATE TRIGGER fail_edit_slot BEFORE INSERT ON menu_cene_master_piatti FOR EACH ROW "
        "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced rollback'")
    monday(current["data"])["cena"] = "Scrittura fallita"
    assert not save(current["data"], current["revision"], status=500)["success"]
    sql("DROP TRIGGER fail_edit_slot")
    assert sql("SELECT COUNT(*) FROM menu_cene_master WHERE id_famiglia=101") == count
    assert sql("SELECT COUNT(*) FROM menu_cene_master WHERE id_famiglia=101 AND attivo=1") == "1"
    assert monday(editor()["data"])["cena"] == "MCP libero 🍋"

    sql("INSERT INTO temi (id,nome,background_color,text_color,primary_color,secondary_color) "
        "VALUES (1,'Test','#000000','#FFFFFF','#111111','#222222');"
        "INSERT INTO utenti (id,username,password,nome,cognome,email,id_famiglia_attuale,"
        "id_famiglia_gestione,admin,bilancio_voluto_fine_anno,userlevelid,profile) "
        "VALUES (1,'test','disabled','Test','Menu','test@example.invalid',101,101,0,0,0,'');"
        "INSERT INTO utenti2famiglie (id_utente,id_famiglia) VALUES (1,101);"
        "INSERT INTO gf_oauth_access_tokens (token_hash,id_utente,id_famiglia,client_id,resource,scope,expires_at) "
        f"VALUES ('{sha256(oauth_token.encode()).hexdigest()}',1,101,'test',"
        "'https://www.gestionefamiglia.it/Gestionale25/api/mcp.php','family:read',"
        "DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR));")
    assert monday(mcp("leggi_menu_master_cene", headers=oauth_headers)["master"])["cena"] == "MCP libero 🍋"

    configure_api(202)
    assert monday(api("menu_cene_master.php")["master"])["cena"] == monday(original)["cena"]
    request("api/mcp.php", headers=oauth_headers, status=401, payload={
        "jsonrpc": "2.0", "id": 2, "method": "tools/list",
    })
    configure_api(101)
    assert sql("SELECT id_famiglia,HEX(piatto) FROM menu_cene_settimanale WHERE week_start='2020-01-06' AND giorno='Lunedi' ORDER BY id") == history
    assert sql("SELECT * FROM lista_spesa") == groceries
    assert all(path.read_bytes() == data for path, data in files.items())

    # Operational rollback switches the source and preserves the relational version.
    active_id = sql("SELECT id_master FROM menu_cene_master WHERE id_famiglia=101 AND attivo=1")
    sql(f"UPDATE menu_cene_master SET attivo=0 WHERE id_famiglia=101 AND id_master={int(active_id)}")
    assert api("menu_cene_master.php")["master"] == original
    assert editor()["revision"] is None
    assert request(week_path, family="family-a")["items"] == weeks[0]["items"]
    sql(f"UPDATE menu_cene_master SET attivo=1 WHERE id_famiglia=101 AND id_master={int(active_id)}")
    assert monday(api("menu_cene_master.php")["master"])["cena"] == "MCP libero 🍋"
    print("OK: migrazione, attivazione, editor web, generazione concorrente, REST, MCP, isolamento, rollback e dati storici")


with ThreadingHTTPServer(("127.0.0.1", 38082), TestProxy) as server:
    tls = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    tls.load_cert_chain(temp / "tls.crt", temp / "tls.key")
    server.socket = tls.wrap_socket(server.socket, server_side=True)
    threading.Thread(target=server.serve_forever, daemon=True).start()
    try:
        run()
    finally:
        server.shutdown()
