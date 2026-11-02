-- Categorie geribili, disponibilità dei piatti e ordini di consegna.
ALTER TABLE categories
    ADD COLUMN IF NOT EXISTS parent_id INTEGER REFERENCES categories(id) ON DELETE RESTRICT;
CREATE INDEX IF NOT EXISTS idx_categories_parent ON categories(parent_id);

UPDATE categories
SET parent_id = NULL
WHERE name IN ('Antipasti', 'Primi', 'Secondi', 'Contorni', 'Dolci', 'Bevande', 'Alcolici');

ALTER TABLE dishes
    ADD COLUMN IF NOT EXISTS is_available BOOLEAN NOT NULL DEFAULT TRUE;

ALTER TABLE tables
    ADD COLUMN IF NOT EXISTS is_delivery BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE tables ALTER COLUMN label TYPE VARCHAR(100);

-- Conserva l'identità originaria dell'ordine quando il tavolo viene eliminato
-- alla chiusura del conto.
ALTER TABLE orders ADD COLUMN IF NOT EXISTS table_number INTEGER;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS table_label VARCHAR(100);
UPDATE orders o
SET table_number = t.number, table_label = t.label
FROM tables t
WHERE o.table_id = t.id AND o.table_number IS NULL;

ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_table_id_fkey;
ALTER TABLE orders ALTER COLUMN table_id DROP NOT NULL;
ALTER TABLE orders
    ADD CONSTRAINT orders_table_id_fkey
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE SET NULL;
