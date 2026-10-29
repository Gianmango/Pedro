-- Allergeni (i 14 previsti dalla normativa UE), categorie del menù e tavoli.
-- NECESSARIO (non sono dati di prova). Si può eseguire più volte senza errori.

CREATE TABLE IF NOT EXISTS allergens (
    id          SERIAL PRIMARY KEY,
    code        VARCHAR(30) NOT NULL UNIQUE,   -- anche nome del file icona: public/assets/img/allergens/<code>.svg
    name        VARCHAR(80) NOT NULL,
    icon        VARCHAR(10),                   -- emoji di ripiego finché non c'è l'icona .svg
    sort_order  INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS dish_allergens (
    dish_id      INTEGER NOT NULL REFERENCES dishes(id) ON DELETE CASCADE,
    allergen_id  INTEGER NOT NULL REFERENCES allergens(id) ON DELETE RESTRICT,
    PRIMARY KEY (dish_id, allergen_id)
);

INSERT INTO allergens (code, name, icon, sort_order) VALUES
    ('gluten',      'Glutine',          '🌾', 1),
    ('crustaceans', 'Crostacei',        '🦐', 2),
    ('eggs',        'Uova',             '🥚', 3),
    ('fish',        'Pesce',            '🐟', 4),
    ('peanuts',     'Arachidi',         '🥜', 5),
    ('soy',         'Soia',             '🫘', 6),
    ('milk',        'Latte',            '🥛', 7),
    ('nuts',        'Frutta a guscio',  '🌰', 8),
    ('celery',      'Sedano',           '🥬', 9),
    ('mustard',     'Senape',           NULL, 10),
    ('sesame',      'Sesamo',           NULL, 11),
    ('sulphites',   'Solfiti',          '🍷', 12),
    ('lupin',       'Lupini',           NULL, 13),
    ('molluscs',    'Molluschi',        '🐚', 14)
ON CONFLICT (code) DO NOTHING;

-- Le sette sezioni del menù, nell'ordine in cui appaiono
INSERT INTO categories (name, sort_order)
SELECT v.name, v.ord
FROM (VALUES
    ('Antipasti', 1),
    ('Primi',     2),
    ('Secondi',   3),
    ('Contorni',  4),
    ('Dolci',     5),
    ('Bevande',   6),
    ('Alcolici',  7)
) AS v(name, ord)
WHERE NOT EXISTS (SELECT 1 FROM categories c WHERE c.name = v.name);

UPDATE categories SET sort_order = CASE name
    WHEN 'Antipasti' THEN 1
    WHEN 'Primi'     THEN 2
    WHEN 'Secondi'   THEN 3
    WHEN 'Contorni'  THEN 4
    WHEN 'Dolci'     THEN 5
    WHEN 'Bevande'   THEN 6
    WHEN 'Alcolici'  THEN 7
    ELSE sort_order
END;

-- Tavoli da 1 a 10 (cambia 10 con il numero di tavoli che avete)
INSERT INTO tables (number)
SELECT n FROM generate_series(1, 10) AS n
ON CONFLICT (number) DO NOTHING;
