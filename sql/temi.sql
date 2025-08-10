CREATE TABLE temi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(50) NOT NULL,
  background_color VARCHAR(7) NOT NULL,
  text_color VARCHAR(7) NOT NULL,
  primary_color VARCHAR(7) NOT NULL,
  secondary_color VARCHAR(7) NOT NULL
);

INSERT INTO temi (id, nome, background_color, text_color, primary_color, secondary_color) VALUES
  (1, 'Scuro', '#121212', '#ffffff', '#1f1f1f', '#2b2b2b'),
  (2, 'Chiaro', '#ffffff', '#000000', '#f8f9fa', '#e9ecef');
