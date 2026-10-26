<?php

declare(strict_types=1);

/**
 * Non-DB regression of Forms V2.4.4t row persistence and legacy compatibility.
 * php tests/forms-rows-v2_4_4t.php
 * This test does NOT modify CMS database tables or access .env.
 */

interface ModuleInterface {}
interface ModuleComponentProviderInterface {}
interface ModuleFrontendActionInterface {}
class Settings
{
    public function get(string $key, mixed $default = null): mixed { return $default; }
}
class Db
{
    public array $forms = [];
    public array $fields = [];
    public array $rows = [];
    public array $submissions = [];
    private ?array $backup = null;
    private int $nextId = 1;
    private int $lastId = 0;
    public bool $failRowInsert = false;

    public function beginTransaction(): void
    {
        if ($this->backup !== null) throw new RuntimeException('nested transaction');
        $this->backup = [$this->forms, $this->fields, $this->rows, $this->submissions, $this->nextId];
    }
    public function commit(): void { $this->backup = null; }
    public function rollback(): void
    {
        if ($this->backup === null) throw new RuntimeException('nothing to roll back');
        [$this->forms, $this->fields, $this->rows, $this->submissions, $this->nextId] = $this->backup;
        $this->backup = null;
    }
    public function getInsertId(): int { return $this->lastId; }
    public function fetchArray(string $sql, array $params = []): array
    {
        $id = (int)($params[0] ?? 0);
        if (str_starts_with($sql, 'SELECT * FROM module_forms WHERE id = ?')) {
            return isset($this->forms[$id]) ? [$this->forms[$id]] : [];
        }
        if (str_starts_with($sql, 'SELECT * FROM module_form_fields WHERE form_id = ?')) {
            $rows = array_values($this->fields[$id] ?? []);
            usort($rows, static fn($a, $b) => [$a['row_order'], $a['column_index']] <=> [$b['row_order'], $b['column_index']]);
            return $rows;
        }
        if (str_starts_with($sql, 'SELECT row_order, row_layout FROM module_form_rows WHERE form_id = ?')) {
            $rows = array_values($this->rows[$id] ?? []);
            usort($rows, static fn($a, $b) => $a['row_order'] <=> $b['row_order']);
            return $rows;
        }
        throw new RuntimeException('Unexpected SELECT: ' . $sql);
    }
    public function query(string $sql, array $params = []): void
    {
        if (str_starts_with($sql, 'INSERT INTO module_forms ')) {
            $id = $this->nextId++;
            $this->lastId = $id;
            $this->forms[$id] = ['id' => $id, 'name' => $params[0]];
            return;
        }
        if (str_starts_with($sql, 'UPDATE module_forms SET ')) {
            $id = (int)end($params);
            $this->forms[$id]['name'] = $params[0];
            return;
        }
        if (str_starts_with($sql, 'DELETE FROM module_form_fields WHERE')) {
            $this->fields[(int)$params[0]] = [];
            return;
        }
        if (str_starts_with($sql, 'DELETE FROM module_form_rows WHERE')) {
            $this->rows[(int)$params[0]] = [];
            return;
        }
        if (str_starts_with($sql, 'INSERT INTO module_form_rows ')) {
            if ($this->failRowInsert) throw new RuntimeException('Simulated SQL error');
            [$id, $order, $layout] = $params;
            $this->rows[$id][$order] = ['row_order' => $order, 'row_layout' => $layout];
            return;
        }
        if (str_starts_with($sql, 'INSERT INTO module_form_fields ')) {
            $id = (int)$params[0];
            $this->fields[$id][] = [
                'field_key' => $params[1], 'label' => $params[2],
                'field_type' => $params[3], 'row_layout' => $params[8],
                'row_order' => $params[9], 'column_index' => $params[10],
                'options_json' => $params[6],
            ];
            return;
        }
        throw new RuntimeException('Unexpected mutation: ' . $sql);
    }
}

require __DIR__ . '/../modules/forms/FormsModule.php';

function expect(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    echo "PASS: $message\n";
}

$module = (new ReflectionClass(FormsModule::class))->newInstanceWithoutConstructor();
$normalize = new ReflectionMethod(FormsModule::class, 'normalizeAdminSubmission');
$formPost = [
    'name' => 'Row foundation test', 'status' => 'active', 'submit_label' => 'Send',
    'success_message' => 'Tak', 'success_action' => 'message',
    'rows' => [['layout' => '50-50', 'fields' => []]],
];
[$form, $fields, $errors, $rowLayouts] = $normalize->invoke($module, $formPost);
expect($errors === [], 'Empty 50/50 row is accepted without fields');
expect($fields === [], 'Empty row does not invent form fields or field keys');
expect($rowLayouts === [['row_order' => 0, 'layout' => '50-50']], 'Empty row position and layout are normalized');

$db = new Db();
$repo = new FormsRepository($db);
$id = $repo->saveForm(0, $form, $fields, $rowLayouts);
expect($id === 1 && count($repo->rowsForForm($id)) === 1, 'New empty row persists and reopens');
expect($repo->rowsForForm($id)[0]['layout'] === '50-50', '50/50 row layout survives reopening');
expect($repo->fieldsForForm($id) === [], 'Empty row remains independent of fields');

$formPost['rows'] = [
    ['layout' => '50-50', 'fields' => [
        0 => ['field_key' => 'first_name', 'label' => 'Fornavn', 'field_type' => 'text', 'is_required' => 1],
        1 => ['field_key' => 'last_name', 'label' => 'Efternavn', 'field_type' => 'text', 'is_required' => 1],
    ]],
    ['layout' => '50-50', 'fields' => []],
    ['layout' => '100', 'fields' => [0 => ['field_key' => 'email', 'label' => 'E-mail', 'field_type' => 'email']]],
];
[$form, $fields, $errors, $rowLayouts] = $normalize->invoke($module, $formPost);
expect($errors === [] && count($fields) === 3, 'Populated 50/50 and full-width rows normalize normally');
$repo->saveForm($id, $form, $fields, $rowLayouts);
$reopenedRows = $repo->rowsForForm($id);
$reopenedFields = $repo->fieldsForForm($id);
expect(count($reopenedRows) === 3 && $reopenedRows[1]['row_order'] === 1, 'Middle empty row persists between populated rows');
expect(array_column($reopenedFields, 'field_key') === ['first_name', 'last_name', 'email'], 'Technical field keys remain unchanged');
expect(array_column($reopenedFields, 'row_order') === [0, 0, 2], 'Field positions survive an intervening empty row');

$db->submissions[$id] = [['data_json' => '{"first_name":"Ada"}']];
$repo->saveForm($id, $form, $fields, $rowLayouts);
expect(count($db->submissions[$id]) === 1, 'Saving rows never deletes stored submissions');

$before = [$db->forms, $db->fields, $db->rows, $db->submissions];
$db->failRowInsert = true;
try {
    $repo->saveForm($id, $form, $fields, $rowLayouts);
    throw new RuntimeException('Expected simulated SQL error');
} catch (RuntimeException $error) {
    if ($error->getMessage() !== 'Simulated SQL error') throw $error;
}
$db->failRowInsert = false;
expect([$db->forms, $db->fields, $db->rows, $db->submissions] === $before, 'Failed row save fully rolls back all edits');

// Legacy Forms rows have only field-side metadata until first save.
$legacyDb = new Db();
$legacyDb->forms[7] = ['id' => 7, 'name' => 'Legacy'];
$legacyDb->fields[7] = [[
    'field_key' => 'old_key', 'label' => 'Old label', 'field_type' => 'text',
    'row_layout' => '100', 'row_order' => 4, 'column_index' => 0, 'options_json' => null,
]];
$legacyRepo = new FormsRepository($legacyDb);
expect($legacyRepo->rowsForForm(7) === [] && count($legacyRepo->fieldsForForm(7)) === 1, 'Legacy form reads without a data migration');
$legacyFields = [[
    'field_key' => 'old_key', 'label' => 'Old label', 'field_type' => 'text',
    'placeholder' => '', 'help_text' => '', 'options' => [], 'is_required' => 0,
    'row_layout' => '100', 'row_order' => 4, 'column_index' => 0,
]];
$legacyRepo->saveForm(7, $form, $legacyFields);
expect($legacyRepo->rowsForForm(7) === [['row_order' => 4, 'layout' => '100']], 'Legacy save synthesizes row metadata from fields');
expect($legacyRepo->fieldsForForm(7)[0]['field_key'] === 'old_key', 'Legacy field key survives first save');


// Render the real admin partial: an empty 50/50 row must produce two usable slots.
function t(string $value): string { return $value; }
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function admin_csrf_token(): string { return 'test-csrf'; }
require_once dirname(__DIR__) . '/core/classes/Autoload.php';
function admin_icon(string $name, string $class = ''): string { return AdminIcons::render($name, $class); }
function admin_icon_action_button(string $name, string $label, array $attributes = [], string $tone = 'neutral'): string { return AdminIcons::actionButton($name, $label, $attributes, $tone); }
$formData = ['name' => 'Test', 'status' => 'inactive', 'success_message' => 'Tak'];
$fields = [];
$builderRows = [['row_order' => 0, 'layout' => '50-50']];
$fieldTypes = ['text' => 'Tekst'];
ob_start();
require __DIR__ . '/../modules/forms/admin/views/partials/form-editor.php';
$html = (string)ob_get_clean();
expect(str_contains($html, 'name="rows[0][layout]" value="50-50"'), 'Admin editor restores saved empty row');
expect(str_contains($html, 'data-column-index="0"') && str_contains($html, 'data-column-index="1"'), 'Saved empty row renders both field slots');
$groups = forms_admin_group_rows($reopenedFields, $reopenedRows);
expect(count($groups) === 3 && $groups[1]['fields'] === [], 'Row grouping retains empty middle row between populated rows');
$legacyGroups = forms_admin_group_rows($legacyRepo->fieldsForForm(7), []);
expect(count($legacyGroups) === 1 && $legacyGroups[0]['fields'][0]['field_key'] === 'old_key', 'Admin grouping remains compatible with legacy field-only data');

echo "Forms V2.4.4t regression: ALL TESTS PASSED\n";
