-- Ordini al tavolo: ogni tavolo ha al massimo UN carrello (ordine con status 'new').
-- Quando verrà aggiunta la stampa in cucina, "Invia" cambierà lo status del carrello
-- e il tavolo potrà aprirne uno nuovo. Si può eseguire più volte senza errori.

-- Quando il conto è stato pagato (utile per la cassa e per lo storico)
ALTER TABLE orders ADD COLUMN IF NOT EXISTS paid_at TIMESTAMPTZ;

-- Un solo carrello per tavolo, anche se due tablet toccano "+" nello stesso istante.
-- (Se questo comando dà errore, ci sono già due ordini 'new' sullo stesso tavolo:
--  sono dati di prova, cancellali o metti uno dei due in 'paid'.)
CREATE UNIQUE INDEX IF NOT EXISTS uq_orders_one_cart_per_table
    ON orders (table_id)
    WHERE status = 'new';
