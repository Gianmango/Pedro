-- Allergeni (i 14 previsti dalla normativa UE) e nuove categorie del menù.

CREATE TABLE allergens (
    id          SERIAL PRIMARY KEY,
    code        VARCHAR(30) NOT NULL UNIQUE,   -- anche nome del file icona: public/assets/img/allergens/<code>.svg
    name        VARCHAR(80) NOT NULL,
    icon        VARCHAR(10),                   -- emoji di ripiego finché non c'è l'icona .svg
    sort_order  INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE dish_allergens (
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
    ('molluscs',    'Molluschi',        '🐚', 14);

-- Nuove categorie e ordine di visualizzazione
INSERT INTO categories (name, sort_order) VALUES
    ('Contorni', 0),
    ('Alcolici', 0);

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
