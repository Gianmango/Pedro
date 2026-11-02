<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use DomainException;

final class AdminCategoryController extends AdminBaseController
{
    public function index(): void
    {
        view('admin/categories', [
            'categories' => Category::all(),
            'parents' => Category::parents(),
        ]);
    }

    public function store(): void
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $parentRaw = trim((string) ($_POST['parent_id'] ?? ''));
        $parentId = $parentRaw === '' ? null : filter_var($parentRaw, FILTER_VALIDATE_INT);

        if ($name === '' || mb_strlen($name) > 100) {
            flash('error', 'Il nome della categoria deve contenere da 1 a 100 caratteri.');
            redirect('/admin/categories');
        }
        if ($parentRaw !== '' && ($parentId === false || !in_array((int) $parentId, array_map('intval', array_column(Category::parents(), 'id')), true))) {
            flash('error', 'Scegli una categoria principale valida.');
            redirect('/admin/categories');
        }

        $parentId = $parentId === null ? null : (int) $parentId;
        if (Category::nameExists($name, $parentId)) {
            flash('error', 'Esiste già una categoria con questo nome in questa sezione.');
            redirect('/admin/categories');
        }

        try {
            Category::create($name, $parentId);
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/categories');
        }
        flash('ok', 'Categoria «' . $name . '» aggiunta.');
        redirect('/admin/categories');
    }

    public function rename(string $id): void
    {
        $category = $this->find($id);
        $name = trim((string) ($_POST['name'] ?? ''));
        $parentRaw = trim((string) ($_POST['parent_id'] ?? ''));
        $parentId = $parentRaw === '' ? null : filter_var($parentRaw, FILTER_VALIDATE_INT);
        if ($name === '' || mb_strlen($name) > 100) {
            flash('error', 'Il nome della categoria deve contenere da 1 a 100 caratteri.');
            redirect('/admin/categories');
        }
        $validParents = array_map('intval', array_column(Category::parents(), 'id'));
        if ($parentRaw !== '' && ($parentId === false || (int) $parentId === (int) $category['id'] ||
            !in_array((int) $parentId, $validParents, true))) {
            flash('error', 'Scegli una categoria principale valida.');
            redirect('/admin/categories');
        }

        $parentId = $parentId === null ? null : (int) $parentId;
        if (Category::nameExists($name, $parentId, (int) $category['id'])) {
            flash('error', 'Esiste già una categoria con questo nome in questa sezione.');
            redirect('/admin/categories');
        }

        try {
            Category::update((int) $category['id'], $name, $parentId);
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/categories');
        }
        flash('ok', 'Categoria aggiornata.');
        redirect('/admin/categories');
    }

    public function destroy(string $id): void
    {
        $category = $this->find($id);
        try {
            Category::delete((int) $category['id']);
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/categories');
        }

        flash('ok', 'Categoria eliminata.');
        redirect('/admin/categories');
    }

    /** @return array<string,mixed> */
    private function find(string $id): array
    {
        if (!ctype_digit($id) || strlen($id) > 9) {
            $this->notFound();
        }

        foreach (Category::all() as $category) {
            if ((int) $category['id'] === (int) $id) {
                return $category;
            }
        }

        $this->notFound();
    }
}
