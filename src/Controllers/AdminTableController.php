<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Dish;
use App\Models\Order;
use App\Models\Table;
use App\Services\PrintException;
use App\Services\PrintService;
use DomainException;

/**
 * Pagina di un tavolo: il cameriere prende gli ordini dal tablet (+ / − sui piatti), scrive le note,
 * invia l'ordine in cucina (stampa del biglietto) e, a fine pasto, segna il conto come pagato.
 * Login e token CSRF sono già controllati da AdminBaseController e dal Router.
 */
final class AdminTableController extends AdminBaseController
{
    public function show(string $number): void
    {
        $table = $this->findTable($number, false, false);
        $this->renderOrderPage($table, false);
    }

    public function newOrder(string $number): void
    {
        $table = $this->findTable($number, false, false);
        $this->renderOrderPage($table, true);
    }

    public function showDelivery(string $number): void
    {
        $table = $this->findTable($number, false, true);
        $this->renderOrderPage($table, false);
    }

    public function newDeliveryOrder(string $number): void
    {
        $table = $this->findTable($number, false, true);
        $this->renderOrderPage($table, true);
    }

    public function newTable(): void
    {
        view('admin/new_table');
    }

    public function createTable(): void
    {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        if ($identifier === '' || mb_strlen($identifier) > 100 ||
            (ctype_digit($identifier) && ((int) $identifier < 1 || (int) $identifier > 999999999))) {
            flash('error', 'Inserisci un nome o un numero di tavolo valido (massimo 100 caratteri).');
            redirect('/admin/tables/new');
        }

        try {
            $table = Table::createForOrder($identifier);
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/tables/new');
        }

        redirect('/admin/tables/' . $table['number'] . '/order');
    }

    public function deliveries(): void
    {
        $tables = Table::overview(true);
        view('admin/deliveries', [
            'tables' => $tables,
            'grandTotal' => array_sum(array_column($tables, 'total_cents')),
        ]);
    }

    public function createDelivery(): void
    {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        if ($identifier === '' || mb_strlen($identifier) > 100) {
            flash('error', 'Inserisci un nome per la spedizione (massimo 100 caratteri).');
            redirect('/admin/deliveries');
        }

        try {
            $table = Table::createForOrder($identifier, true);
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/deliveries');
        }

        redirect('/admin/deliveries/' . $table['number'] . '/order');
    }

    /** @param array{id:int,number:int,label:?string,is_delivery:bool} $table */
    private function renderOrderPage(array $table, bool $orderOnly): void
    {
        $tableId = (int) $table['id'];
        $baseUrl = ($table['is_delivery'] ? '/admin/deliveries/' : '/admin/tables/') . (int) $table['number'];

        view('admin/table', [
            'table' => $table,
            'menu' => Dish::menuByCategory(true),
            'state' => $this->state($tableId),
            'sent' => Order::inProgressOrders($tableId),
            'orderOnly' => $orderOnly,
            'baseUrl' => $baseUrl,
            'backUrl' => $table['is_delivery'] ? '/admin/deliveries' : '/admin/tables',
        ]);
    }

    /** POST /admin/tables/{number}/items  (dish_id oppure item_id, delta = 1 o -1). Risponde in JSON. */
    public function change(string $number): void
    {
        $table   = $this->findTable($number, true);
        $tableId = (int) $table['id'];

        $delta  = (int) ($_POST['delta'] ?? 0);
        $dishId = self::positiveInt($_POST['dish_id'] ?? null);
        $itemId = self::positiveInt($_POST['item_id'] ?? null);

        if (!in_array($delta, [1, -1], true) || ($dishId === null && $itemId === null)) {
            $this->json(['error' => 'Richiesta non valida.'], 422);
        }
        if ($itemId === null && Dish::find($dishId) === null) {
            $this->json(['error' => 'Piatto non trovato.'], 404);
        }

        try {
            Order::change($tableId, $delta, $dishId, $itemId);
        } catch (DomainException $e) {
            // Rispondo con lo stato aggiornato: la pagina si riallinea al carrello vero.
            $this->json(['error' => $e->getMessage()] + $this->state($tableId), 409);
        }

        $this->json($this->state($tableId));
    }

    /** POST /admin/tables/{number}/note  (item_id, note). Risponde in JSON. */
    public function note(string $number): void
    {
        $table   = $this->findTable($number, true);
        $tableId = (int) $table['id'];

        $itemId = self::positiveInt($_POST['item_id'] ?? null);
        if ($itemId === null) {
            $this->json(['error' => 'Richiesta non valida.'], 422);
        }

        Order::setNote($tableId, $itemId, self::cleanNote($_POST['note'] ?? ''));

        $this->json($this->state($tableId));
    }

    /** POST /admin/tables/{number}/send: stampa il biglietto per la cucina e chiude il carrello. */
    public function send(string $number): void
    {
        $this->sendCart($number);
    }

    public function sendNewOrder(string $number): void
    {
        $this->sendCart($number);
    }

    private function sendCart(string $number): void
    {
        $table   = $this->findTable($number, false);
        $tableNo = (int) $table['number'];
        $baseUrl = ($table['is_delivery'] ? '/admin/deliveries/' : '/admin/tables/') . $tableNo;
        $returnUrl = $table['is_delivery'] ? '/admin/deliveries' : '/admin/tables';

        try {
            $result = Order::sendCart(
                (int) $table['id'],
                static fn(array $lines): bool => (new PrintService())->printOrder($tableNo, $lines)
            );
        } catch (PrintException $e) {
            flash('error', $e->getMessage() . ' L\'ordine è ancora nel carrello: riprova.');
            redirect($returnUrl);
        }

        if ($result === null) {
            flash('error', 'Il carrello è vuoto: non c\'è nulla da inviare.');
        } elseif ($result['printed']) {
            flash('ok', 'Ordine inviato in cucina: biglietto stampato.');
        } else {
            flash('ok', 'Ordine segnato come inviato (la stampa è disattivata: PRINTER_MODE=off).');
        }

        redirect($returnUrl);
    }

    /** POST /admin/tables/{number}/orders/{order}/reprint: ristampa il biglietto di un ordine già inviato. */
    public function reprint(string $number, string $order): void
    {
        $table   = $this->findTable($number, false);
        $tableNo = (int) $table['number'];
        $baseUrl = ($table['is_delivery'] ? '/admin/deliveries/' : '/admin/tables/') . $tableNo;

        $lines = (ctype_digit($order) && strlen($order) <= 9)
            ? Order::sentOrderLines((int) $table['id'], (int) $order)
            : null;
        if ($lines === null) {
            $this->notFound();
        }

        try {
            $printed = (new PrintService())->printOrder($tableNo, $lines, reprint: true);
            if ($printed) {
                flash('ok', 'Biglietto ristampato.');
            } else {
                flash('error', 'La stampa è disattivata (PRINTER_MODE=off): non è stato stampato nulla.');
            }
        } catch (PrintException $e) {
            flash('error', $e->getMessage());
        }

        redirect($baseUrl);
    }

    /** POST /admin/tables/{number}/pay: conto pagato, carrello azzerato, tavolo libero. */
    public function pay(string $number): void
    {
        $table = $this->findTable($number, false);
        $paid  = Order::pay((int) $table['id']);

        if ($paid === null) {
            flash('error', 'Il tavolo ' . (int) $table['number'] . ' non ha conti aperti.');
        } else {
            $entity = $table['is_delivery'] ? 'Spedizione' : 'Tavolo';
            $label = $table['label'] ?: (string) $table['number'];
            flash('ok', $entity . ' ' . $label . ': conto di ' . format_price($paid) . ' segnato come pagato.');
        }

        redirect($table['is_delivery'] ? '/admin/deliveries' : '/admin/tables');
    }

    // ------------------------------------------------------------------

    /** @return array<string,mixed> carrello e totale, nel formato che legge order.js */
    private function state(int $tableId): array
    {
        $cart = array_map(
            static fn(array $line): array => $line + ['line_label' => format_price($line['line_cents'])],
            Order::cartLines($tableId)
        );
        $total = Order::tableTotal($tableId);

        return [
            'cart'        => $cart,
            'total_cents' => $total,
            'total_label' => format_price($total),
        ];
    }

    /** @return array{id:int, number:int, label:?string, is_delivery:bool} */
    private function findTable(string $number, bool $json, ?bool $isDelivery = null): array
    {
        $table = null;
        if (ctype_digit($number) && strlen($number) <= 9) {
            $table = Table::findByNumber((int) $number);
        }
        if ($table !== null && $isDelivery !== null && $table['is_delivery'] !== $isDelivery) {
            $table = null;
        }

        if ($table === null) {
            if ($json) {
                $this->json(['error' => 'Tavolo non trovato.'], 404);
            }
            $this->notFound();
        }

        return $table;
    }

    private static function positiveInt(mixed $value): ?int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);

        return ($int === false || $int < 1) ? null : $int;
    }

    /** Una nota è una sola riga di testo, lunga al massimo MAX_NOTE_LENGTH caratteri. */
    private static function cleanNote(mixed $value): string
    {
        $text = is_string($value) ? (preg_replace('/\s+/u', ' ', $value) ?? '') : '';

        return mb_substr(trim($text), 0, Order::MAX_NOTE_LENGTH);
    }

    private function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
