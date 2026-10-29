-- Schema iniziale per menù e ordini dell'agriturismo (PostgreSQL).
-- Gli importi sono sempre in centesimi (INTEGER): niente errori di arrotondamento.

CREATE TABLE categories (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    sort_order  INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE dishes (
    id            SERIAL PRIMARY KEY,
    category_id   INTEGER NOT NULL REFERENCES categories(id) ON DELETE RESTRICT,
    name          VARCHAR(150) NOT NULL,
    description   TEXT NOT NULL DEFAULT '',
    price_cents   INTEGER NOT NULL CHECK (price_cents >= 0),
    image_path    VARCHAR(255),                       -- nome file in storage/uploads/ (passo 8)
    is_available  BOOLEAN NOT NULL DEFAULT TRUE,      -- "finito" senza cancellare il piatto
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_dishes_category ON dishes(category_id);

CREATE TABLE tables (
    id      SERIAL PRIMARY KEY,
    number  INTEGER NOT NULL UNIQUE,                  -- numero scritto sul tavolo / nel QR
    label   VARCHAR(50)                               -- es. "Veranda 3" (facoltativo)
);

CREATE TABLE orders (
    id          SERIAL PRIMARY KEY,
    table_id    INTEGER NOT NULL REFERENCES tables(id),
    status      VARCHAR(20) NOT NULL DEFAULT 'new'
                CHECK (status IN ('new', 'preparing', 'served', 'paid')),
    notes       TEXT NOT NULL DEFAULT '',
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    printed_at  TIMESTAMPTZ                           -- NULL = non ancora stampato in cucina
);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_table  ON orders(table_id);

CREATE TABLE order_items (
    id                SERIAL PRIMARY KEY,
    order_id          INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    dish_id           INTEGER REFERENCES dishes(id) ON DELETE SET NULL,
    -- Copia di nome e prezzo al momento dell'ordine: se poi il menù cambia,
    -- i conti già fatti restano corretti.
    dish_name         VARCHAR(150) NOT NULL,
    unit_price_cents  INTEGER NOT NULL CHECK (unit_price_cents >= 0),
    quantity          INTEGER NOT NULL CHECK (quantity > 0),
    notes             TEXT NOT NULL DEFAULT ''
);
CREATE INDEX idx_order_items_order ON order_items(order_id);

CREATE TABLE users (
    id             SERIAL PRIMARY KEY,
    username       VARCHAR(50) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,             -- mai la password in chiaro
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);
