-- Dati di prova. Sostituiscili con il menù vero dall'area admin.

INSERT INTO categories (name, sort_order) VALUES
    ('Antipasti', 1),
    ('Primi',     2),
    ('Secondi',   3),
    ('Dolci',     4),
    ('Bevande',   5);

INSERT INTO dishes (category_id, name, description, price_cents) VALUES
    (1, 'Tagliere di salumi e formaggi', 'Salumi e formaggi di produzione propria, con miele e confetture', 1200),
    (1, 'Bruschette miste',              'Pomodoro, fegatini, olio nuovo',                                    700),
    (2, 'Tagliatelle al ragù',           'Pasta fresca fatta in casa',                                        1000),
    (2, 'Zuppa del giorno',              'Chiedi al personale',                                                800),
    (3, 'Grigliata mista',               'Carni alla brace con contorno di stagione',                         1600),
    (3, 'Tagliata di manzo',             'Con rucola e scaglie di grana',                                     1800),
    (4, 'Tiramisù',                      'Ricetta della casa',                                                 500),
    (5, 'Acqua 1L',                      'Naturale o frizzante',                                               250),
    (5, 'Vino della casa (1/2 L)',       'Rosso o bianco',                                                     600);

-- Tavoli da 1 a 10
INSERT INTO tables (number)
SELECT generate_series(1, 10);
