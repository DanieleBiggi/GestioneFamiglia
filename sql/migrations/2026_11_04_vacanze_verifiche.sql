-- VERIFICA NON DISTRUTTIVA PRIMA DELLA FASE 2.
-- Eseguire prima di CREATE OR REPLACE VIEW; confrontare conteggi e totali.
SELECT 'tratte' categoria, (SELECT COUNT(*) FROM viaggi_tratte WHERE id_viaggio_alternativa IS NOT NULL) legacy,
 (SELECT COUNT(*) FROM viaggi_tratte_alternative) associazioni
UNION ALL SELECT 'alloggi',(SELECT COUNT(*) FROM viaggi_alloggi WHERE id_viaggio_alternativa IS NOT NULL),(SELECT COUNT(*) FROM viaggi_alloggi_alternative)
UNION ALL SELECT 'pasti',(SELECT COUNT(*) FROM viaggi_pasti WHERE id_viaggio_alternativa IS NOT NULL),(SELECT COUNT(*) FROM viaggi_pasti_alternative)
UNION ALL SELECT 'altri_costi',(SELECT COUNT(*) FROM viaggi_altri_costi WHERE id_viaggio_alternativa IS NOT NULL),(SELECT COUNT(*) FROM viaggi_altri_costi_alternative);

-- Collegamenti cross-vacanza: devono essere ZERO.
SELECT 'tratte' categoria,COUNT(*) anomalie FROM viaggi_tratte_alternative x JOIN viaggi_tratte e ON e.id_tratta=x.id_tratta JOIN viaggi_alternative a ON a.id_viaggio_alternativa=x.id_viaggio_alternativa WHERE e.id_viaggio<>a.id_viaggio
UNION ALL SELECT 'alloggi',COUNT(*) FROM viaggi_alloggi_alternative x JOIN viaggi_alloggi e ON e.id_alloggio=x.id_alloggio JOIN viaggi_alternative a ON a.id_viaggio_alternativa=x.id_viaggio_alternativa WHERE e.id_viaggio<>a.id_viaggio
UNION ALL SELECT 'pasti',COUNT(*) FROM viaggi_pasti_alternative x JOIN viaggi_pasti e ON e.id_pasto=x.id_pasto JOIN viaggi_alternative a ON a.id_viaggio_alternativa=x.id_viaggio_alternativa WHERE e.id_viaggio<>a.id_viaggio
UNION ALL SELECT 'altri_costi',COUNT(*) FROM viaggi_altri_costi_alternative x JOIN viaggi_altri_costi e ON e.id_costo=x.id_costo JOIN viaggi_alternative a ON a.id_viaggio_alternativa=x.id_viaggio_alternativa WHERE e.id_viaggio<>a.id_viaggio;

-- Prima del cambio vista, esportare i risultati attuali.
SELECT * FROM v_totali_alternative ORDER BY id_viaggio,id_viaggio_alternativa;

-- Test manuale dopo rilascio, su una vacanza di prova:
-- 1) Creare due scenari A e B.
-- 2) Inserire un altro costo di EUR 100 collegato a entrambi.
-- 3) Verificare una sola riga in viaggi_altri_costi e due in viaggi_altri_costi_alternative.
-- 4) Verificare EUR 100 per A e EUR 100 per B (non EUR 200 per scenario).
-- 5) Modificare a EUR 120: entrambi devono aggiornarsi.
-- 6) Scollegare A: A=0, B=120; la spesa resta.
-- 7) Scollegare B: spesa nel catalogo non assegnate; totali A=B=0.
-- 8) Ricollegare a B, eliminare B: la spesa rimane non assegnata.
-- 9) Provare pagamento e cancellazione definitiva della spesa.
-- 10) Verificare lista vacanze, dettagli, checklist e altre sezioni di GestioneFamiglia.
