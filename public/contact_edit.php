<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_editor();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;

$contact = [
    'name' => '',
    'department' => '',
    'numbers' => [
        ['label' => 'Office', 'number' => ''],
        ['label' => 'Mobile', 'number' => ''],
        ['label' => 'Other',  'number' => ''],
    ],
    'tags' => [],
];

if ($editing) {
    $c = contact_get($id);
    if (!$c) {
        flash_set('warning', 'Kontakt nicht gefunden.');
        redirect('contacts.php');
    }
    $contact = $c;
    // Ensure at least 3 number rows
    while (count($contact['numbers']) < 3) {
        $contact['numbers'][] = ['label' => 'Extra', 'number' => ''];
    }
}

$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name = trim((string)($_POST['name'] ?? ''));
    $department = trim((string)($_POST['department'] ?? ''));
    $tags = (string)($_POST['tags'] ?? '');

    $numbers = [];
    if (isset($_POST['labels'], $_POST['numbers']) && is_array($_POST['labels']) && is_array($_POST['numbers'])) {
        foreach ($_POST['labels'] as $idx => $lbl) {
            $numbers[] = [
                'label' => (string)$lbl,
                'number' => (string)($_POST['numbers'][$idx] ?? ''),
            ];
        }
    }

    try {
        $data = [
            'id' => $editing ? $id : null,
            'name' => $name,
            'department' => $department,
            'numbers' => $numbers,
            'tags' => $tags,
        ];
        $savedId = contact_save($data);
        flash_set('success', 'Kontakt gespeichert.');
        redirect('contacts.php');
    } catch (Throwable $e) {
        $err = $e->getMessage();
        // Refill form
        $contact['name'] = $name;
        $contact['department'] = $department;
        $contact['numbers'] = normalize_numbers($numbers);
        $contact['tags'] = normalize_tag_list($tags);
        while (count($contact['numbers']) < 3) {
            $contact['numbers'][] = ['label' => 'Extra', 'number' => '', 'sort_order' => count($contact['numbers']) + 1];
        }
    }
}

$title = $editing ? 'Kontakt bearbeiten' : 'Neuer Kontakt';
render_header($title);

$tagsValue = '';
if (!empty($contact['tags']) && is_array($contact['tags'])) {
    $tagsValue = implode(',', $contact['tags']);
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 m-0"><?= h($title) ?></h1>
  <a class="btn btn-outline-secondary" href="contacts.php"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<?php if ($err): ?>
  <div class="alert alert-danger"><?= h($err) ?></div>
<?php endif; ?>

<form method="post" class="card shadow-sm">
  <div class="card-body">
    <?= csrf_field() ?>

    <div class="row g-3">
      <div class="col-12 col-lg-6">
        <label class="form-label">Name *</label>
        <input class="form-control" name="name" required value="<?= h((string)$contact['name']) ?>" autofocus>
      </div>
      <div class="col-12 col-lg-6">
        <label class="form-label">Abteilung</label>
        <input class="form-control" name="department" value="<?= h((string)$contact['department']) ?>" placeholder="z.B. Office, Family, ...">
      </div>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-12">
        <label class="form-label">Tags</label>
        <input class="form-control" name="tags" value="<?= h($tagsValue) ?>" placeholder="z.B. family,notfall,lieferant">
        <div class="form-text">Kommagetrennt. Beispiel: <code>family,notfall</code></div>
      </div>
    </div>

    <hr class="my-4">

    <h2 class="h5">Nummern</h2>

    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th style="width: 35%">Label</th>
            <th>Nummer</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contact['numbers'] as $n): ?>
            <tr>
              <td><input class="form-control" name="labels[]" value="<?= h((string)($n['label'] ?? '')) ?>"></td>
              <td><input class="form-control" name="numbers[]" value="<?= h((string)($n['number'] ?? '')) ?>"></td>
            </tr>
          <?php endforeach; ?>
          <?php for ($i = 0; $i < 3; $i++): ?>
            <tr>
              <td><input class="form-control" name="labels[]" placeholder="Extra Label"></td>
              <td><input class="form-control" name="numbers[]" placeholder="Extra Nummer"></td>
            </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>

    <div class="text-muted small">Tipp: Leere Nummern werden ignoriert.</div>
  </div>

  <div class="card-footer d-flex justify-content-end gap-2">
    <a class="btn btn-outline-secondary" href="contacts.php">Abbrechen</a>
    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Speichern</button>
  </div>
</form>

<?php render_footer(); ?>
