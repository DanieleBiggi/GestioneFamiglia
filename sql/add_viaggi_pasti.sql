CREATE TABLE viaggi_pasti (
  id_pasto INT AUTO_INCREMENT PRIMARY KEY,
  id_viaggio INT NOT NULL,
  id_viaggio_alternativa INT,
  giorno_indice INT,
  tipo_pasto ENUM('colazione','pranzo','cena') NOT NULL,
  nome_locale VARCHAR(255),
  indirizzo VARCHAR(255),
  lat DECIMAL(9,6),
  lng DECIMAL(9,6),
  tipologia ENUM('ristorante','pizzeria','cucinato') DEFAULT 'ristorante',
  costo_medio_eur DECIMAL(10,2),
  note TEXT,
  FOREIGN KEY (id_viaggio) REFERENCES viaggi(id_viaggio),
  FOREIGN KEY (id_viaggio_alternativa) REFERENCES viaggi_alternative(id_viaggio_alternativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
