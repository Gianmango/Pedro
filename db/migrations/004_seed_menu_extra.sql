-- Dati di prova: contorni, alcolici e allergeni.
-- ATTENZIONE: gli allergeni qui sotto sono inventati per provare il menù.
-- Quelli veri vanno inseriti e controllati da chi conosce le ricette.

UPDATE dishes
SET category_id = (SELECT id FROM categories WHERE name = 'Alcolici')
WHERE name = 'Vino della casa (1/2 L)';

INSERT INTO dishes (category_id, name, description, price_cents) VALUES
    ((SELECT id FROM categories WHERE name = 'Contorni'), 'Patate arrosto',            'Con rosmarino dell''orto',                 400),
    ((SELECT id FROM categories WHERE name = 'Contorni'), 'Verdure grigliate',         'Zucchine, melanzane e peperoni',           450),
    ((SELECT id FROM categories WHERE name = 'Alcolici'), 'Birra artigianale (33 cl)', 'Chiedi al personale i gusti disponibili', 500);

INSERT INTO dish_allergens (dish_id, allergen_id)
SELECT d.id, a.id
FROM (VALUES
    ('Tagliere di salumi e formaggi', 'milk'),
    ('Bruschette miste',              'gluten'),
    ('Tagliatelle al ragù',           'gluten'),
    ('Tagliatelle al ragù',           'eggs'),
    ('Tagliatelle al ragù',           'celery'),
    ('Zuppa del giorno',              'celery'),
    ('Tagliata di manzo',             'milk'),
    ('Tiramisù',                      'gluten'),
    ('Tiramisù',                      'eggs'),
    ('Tiramisù',                      'milk'),
    ('Vino della casa (1/2 L)',       'sulphites'),
    ('Birra artigianale (33 cl)',     'gluten')
) AS v(dish_name, allergen_code)
JOIN dishes d    ON d.name = v.dish_name
JOIN allergens a ON a.code = v.allergen_code;
