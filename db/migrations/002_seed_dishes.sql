-- DATI DI PROVA (facoltativo): servono solo per vedere subito un menù pieno.
-- Si può eseguire più volte senza duplicare nulla.
-- Se preferisci inserire il menù vero dall'area admin, salta questo file e il 004.

INSERT INTO categories (name, sort_order)
SELECT v.name, v.ord
FROM (VALUES
    ('Antipasti', 1),
    ('Primi',     2),
    ('Secondi',   3),
    ('Dolci',     4),
    ('Bevande',   5)
) AS v(name, ord)
WHERE NOT EXISTS (SELECT 1 FROM categories c WHERE c.name = v.name);

INSERT INTO dishes (category_id, name, description, price_cents)
SELECT c.id, v.name, v.description, v.price
FROM (VALUES
    ('Antipasti', 'Tagliere di salumi e formaggi', 'Salumi e formaggi di produzione propria, con miele e confetture', 1200),
) AS v(category, name, description, price)
JOIN categories c ON c.name = v.category
WHERE NOT EXISTS (SELECT 1 FROM dishes d WHERE d.name = v.name);
