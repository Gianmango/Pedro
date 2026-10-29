<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Allergen;
use App\Models\Category;
use App\Models\Dish;
use App\Services\ImageUploader;
use RuntimeException;

/**
 * Gestione del menù: elenco, aggiunta, modifica ed eliminazione dei piatti.
 * Login e token CSRF sono già controllati da AdminBaseController e dal Router.
 */
final class AdminMenuController extends AdminBaseController
{
    public function index(): void
    {
        view('admin/menu', ['menu' => Dish::menuByCategory(false)]);
    }

    public function create(): void
    {
        $this->renderForm('Nuovo piatto', '/admin/menu', [
            'name' => '', 'description' => '', 'price' => '',
            'category_id' => 0, 'is_available' => true, 'image_path' => null,
        ], [], []);
    }

    public function store(): void
    {
        $input = $this->readInput();
        $errors = $input['errors'];

        $newImage = null;
        if (!$errors) {
            [$newImage, $photoError] = $this->storePhoto();
            if ($photoError !== null) {
                $errors['photo'] = $photoError;
            }
        }

        if ($errors) {
            $this->renderForm('Nuovo piatto', '/admin/menu', $input['form'] + ['image_path' => null], $input['allergenIds'], $errors, 422);
            return;
        }

        try {
            Dish::create($input['data'] + ['image_path' => $newImage], $input['allergenIds']);
        } catch (\Throwable $e) {
            ImageUploader::delete($newImage); // niente foto orfane se il salvataggio fallisce
            throw $e;
        }

        flash('ok', 'Piatto «' . $input['data']['name'] . '» aggiunto.');
        redirect('/admin/menu');
    }

    public function edit(string $id): void
    {
        $dish = $this->findOr404($id);

        $dish['price'] = number_format(((int) $dish['price_cents']) / 100, 2, ',', '');
        $this->renderForm('Modifica piatto', '/admin/menu/' . (int) $dish['id'], $dish, Dish::allergenIds((int) $dish['id']), []);
    }

    public function update(string $id): void
    {
        $dish = $this->findOr404($id);

        $input = $this->readInput();
        $errors = $input['errors'];

        $newImage = null;
        if (!$errors) {
            [$newImage, $photoError] = $this->storePhoto();
            if ($photoError !== null) {
                $errors['photo'] = $photoError;
            }
        }

        if ($errors) {
            $this->renderForm(
                'Modifica piatto', '/admin/menu/' . (int) $dish['id'],
                $input['form'] + ['image_path' => $dish['image_path']], $input['allergenIds'], $errors, 422
            );
            return;
        }

        $oldImage = $dish['image_path'] ?: null;
        $image = $oldImage;
        $replaceOld = false;

        if ($newImage !== null) {                  // nuova foto caricata
            $image = $newImage;
            $replaceOld = true;
        } elseif (isset($_POST['remove_photo'])) { // "rimuovi foto" spuntato
            $image = null;
            $replaceOld = true;
        }

        try {
            Dish::update((int) $dish['id'], $input['data'] + ['image_path' => $image], $input['allergenIds']);
        } catch (\Throwable $e) {
            ImageUploader::delete($newImage);
            throw $e;
        }

        if ($replaceOld && $oldImage !== null && !Dish::imageInUse($oldImage)) {
            ImageUploader::delete($oldImage);
        }

        flash('ok', 'Piatto «' . $input['data']['name'] . '» aggiornato.');
        redirect('/admin/menu');
    }

    public function destroy(string $id): void
    {
        $dish = $this->findOr404($id);

        $image = Dish::delete((int) $dish['id']);
        if ($image !== null && !Dish::imageInUse($image)) {
            ImageUploader::delete($image);
        }

        flash('ok', 'Piatto «' . $dish['name'] . '» eliminato.');
        redirect('/admin/menu');
    }

    // ------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function findOr404(string $id): array
    {
        if (!ctype_digit($id) || strlen($id) > 9) {
            $this->notFound();
        }
        $dish = Dish::find((int) $id);
        if ($dish === null) {
            $this->notFound();
        }

        return $dish;
    }

    /**
     * Salva la foto scelta nel form (se c'è).
     *
     * @return array{0:?string, 1:?string} [nome del file salvato, messaggio d'errore]
     */
    private function storePhoto(): array
    {
        $file = $_FILES['photo'] ?? [];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [null, null]; // nessuna foto scelta: va bene
        }

        try {
            return [ImageUploader::store($file), null];
        } catch (RuntimeException $e) {
            return [null, $e->getMessage()];
        }
    }

    /**
     * Legge e valida i campi del form.
     *
     * @return array{data:array<string,mixed>, form:array<string,mixed>, allergenIds:int[], errors:array<string,string>}
     */
    private function readInput(): array
    {
        $errors = [];

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Inserisci il nome del piatto.';
        } elseif (mb_strlen($name) > 150) {
            $errors['name'] = 'Massimo 150 caratteri.';
        }

        $description = trim((string) ($_POST['description'] ?? ''));
        if (mb_strlen($description) > 1000) {
            $errors['description'] = 'Massimo 1000 caratteri.';
        }

        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $validCategories = array_map('intval', array_column(Category::all(), 'id'));
        if (!in_array($categoryId, $validCategories, true)) {
            $errors['category_id'] = 'Scegli una categoria.';
        }

        $priceRaw = trim((string) ($_POST['price'] ?? ''));
        $priceCents = self::parsePrice($priceRaw);
        if ($priceCents === null) {
            $errors['price'] = 'Prezzo non valido (esempio: 12,50).';
        }

        $validAllergens = array_map('intval', array_column(Allergen::all(), 'id'));
        $allergenIds = array_values(array_intersect(
            array_map('intval', (array) ($_POST['allergens'] ?? [])),
            $validAllergens
        ));

        $available = isset($_POST['is_available']);

        return [
            'data' => [
                'category_id'  => $categoryId,
                'name'         => $name,
                'description'  => $description,
                'price_cents'  => $priceCents ?? 0,
                'is_available' => $available,
            ],
            'form' => [
                'name'         => $name,
                'description'  => $description,
                'price'        => $priceRaw,
                'category_id'  => $categoryId,
                'is_available' => $available,
            ],
            'allergenIds' => $allergenIds,
            'errors'      => $errors,
        ];
    }

    /** "12,50" / "12.5" / "12 €" -> 1250 centesimi; null se non è un prezzo valido. */
    private static function parsePrice(string $raw): ?int
    {
        $raw = str_replace([' ', '€'], '', $raw);
        $raw = str_replace(',', '.', $raw);

        if (!preg_match('/^\d{1,5}(\.\d{1,2})?$/', $raw)) {
            return null;
        }

        [$euros, $decimals] = array_pad(explode('.', $raw, 2), 2, '0');

        return ((int) $euros) * 100 + (int) str_pad($decimals, 2, '0');
    }

    /**
     * @param array<string,mixed> $dish
     * @param int[] $selected
     * @param array<string,string> $errors
     */
    private function renderForm(string $heading, string $action, array $dish, array $selected, array $errors, int $status = 200): void
    {
        http_response_code($status);
        view('admin/dish_form', [
            'heading'    => $heading,
            'action'     => $action,
            'dish'       => $dish,
            'selected'   => $selected,
            'errors'     => $errors,
            'categories' => Category::all(),
            'allergens'  => Allergen::all(),
        ]);
    }
}
