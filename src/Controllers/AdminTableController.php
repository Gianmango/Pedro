<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Dish;
use App\Models\Order;
use App\Models\Table;
use DomainException;

/**
 * Pagina di un tavolo: il cameriere prende gli ordini dal tablet (+ / − sui piatti),
 * vede il carrello e il conto, e a fine pasto segna il conto come pagato.
 * Login e token CSRF sono già controllati da AdminBaseController e dal Router.
 */
final class AdminTableController extends AdminBaseController
{
    public function show(string $number): void
    {
        $table   = $this->findTable($number, false);
        $tableId = (int) $table['id'];

        view('admin/table', [
            'table' => $table,
            'menu'  => Dish::menuByCategory(true),
            'cart'  => Order::cartLines($tableId),
            'sent'  => Order::inProgressOrders($tableId),
            'total' => Order::tableTotal($tableId),
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

    /** POST /admin/tables/{number}/pay: conto pagato, carrello azzerato, tavolo libero. */
    public function pay(string $number): void
    {
        $table = $this->findTable($number, false);
        $paid  = Order::pay((int) $table['id']);

        if ($paid === null) {
            flash('error', 'Il tavolo ' . (int) $table['number'] . ' non ha conti aperti.');
        } else {
            flash('ok', 'Tavolo ' . (int) $table['number'] . ': conto di ' . format_price($paid) . ' segnato come pagato.');
        }

        redirect('/admin/tables');
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

    /** @return array{id:int, number:int, label:?string} */
    private function findTable(string $number, bool $json): array
    {
        $table = null;
        if (ctype_digit($number) && strlen($number) <= 9) {
            $table = Table::findByNumber((int) $number);
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

    private function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
