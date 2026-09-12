<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$ad = null;
$existingImages = [];

if ($editId) {
    $stmt = $conn->prepare("SELECT a.*, c.slug AS category_slug FROM ads a JOIN categories c ON c.id = a.category_id WHERE a.id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $ad = $stmt->get_result()->fetch_assoc();
    if (!$ad || (int)$ad['user_id'] !== current_user_id()) {
        flash_set('You can only edit your own ads.', 'error');
        header('Location: dashboard.php');
        exit;
    }
    $existingImages = json_decode($ad['images'] ?? '[]', true) ?: [];
}

$categories = get_categories($conn);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $negotiable = isset($_POST['negotiable']) ? 1 : 0;
    $condition = in_array($_POST['condition'] ?? '', ['new', 'used']) ? $_POST['condition'] : 'used';
    $location = trim($_POST['location'] ?? '');
    $categorySlug = $_POST['category_slug'] ?? '';

    if ($title === '') $errors[] = 'Please give your ad a title.';
    if ($location === '') $errors[] = 'Please add a location.';
    $category = get_category_by_slug($conn, $categorySlug);
    if (!$category) $errors[] = 'Please choose a category.';
    if ($price !== '' && !is_numeric($price)) $errors[] = 'Price must be a number.';

    // Work out final image list: kept existing + newly uploaded, capped at 5
    $keep = $_POST['keep_images'] ?? []; // array of filenames the user chose to keep (edit mode)
    if ($editId) {
        $removed = array_diff($existingImages, $keep);
        foreach ($removed as $r) delete_image_file($r);
        $finalImages = array_values(array_intersect($existingImages, $keep));
    } else {
        $finalImages = [];
    }
    $newUploads = handle_image_uploads('images', 5 - count($finalImages));
    $finalImages = array_merge($finalImages, $newUploads);

    if (empty($errors)) {
        $priceVal = $price === '' ? null : (float)$price;
        $imagesJson = json_encode($finalImages);

        if ($editId) {
            $stmt = $conn->prepare("
                UPDATE ads SET title=?, description=?, price=?, negotiable=?, `condition`=?, location=?, category_id=?, images=?
                WHERE id=? AND user_id=?
            ");
            $stmt->bind_param(
                "ssdissisii",
                $title, $description, $priceVal, $negotiable, $condition, $location,
                $category['id'], $imagesJson, $editId, $_SESSION['user_id']
            );
            $stmt->execute();
            flash_set('Ad updated!');
            header('Location: ad.php?id=' . $editId);
            exit;
        } else {
            $stmt = $conn->prepare("
                INSERT INTO ads (user_id, category_id, title, description, price, negotiable, `condition`, location, images)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $userId = current_user_id();
            $stmt->bind_param(
                "iissdisss",
                $userId, $category['id'], $title, $description, $priceVal, $negotiable, $condition, $location, $imagesJson
            );
            $stmt->execute();
            $newId = $conn->insert_id;
            flash_set('Ad posted!');
            header('Location: ad.php?id=' . $newId);
            exit;
        }
    } else {
        // Re-show form with what the user already typed
        $ad = [
            'title' => $title, 'description' => $description, 'price' => $price,
            'negotiable' => $negotiable, 'condition' => $condition, 'location' => $location,
            'category_slug' => $categorySlug,
        ];
        $existingImages = $finalImages;
    }
}

$page_title = $editId ? 'Edit your ad' : 'Post an ad';
include __DIR__ . '/includes/header.php';
?>

<div class="post-ad-page">
  <h1><?= $editId ? 'Edit your ad' : 'Post an ad' ?></h1>
  <p class="subtitle"><?= $editId ? 'Update the details below.' : "Fill in the details below — the more specific, the faster it sells." ?></p>

  <?php if ($errors): ?>
    <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" id="adForm">
    <div class="form-section">
      <h3>Category</h3>
      <div class="cat-select-grid">
        <?php foreach ($categories as $cat): ?>
          <label class="cat-select-btn <?= (($ad['category_slug'] ?? '') === $cat['slug']) ? 'selected' : '' ?>">
            <input type="radio" name="category_slug" value="<?= e($cat['slug']) ?>" <?= (($ad['category_slug'] ?? '') === $cat['slug']) ? 'checked' : '' ?> onchange="selectCatBtn(this)">
            <span class="emoji"><?= $cat['icon'] ?></span><?= e($cat['name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-section">
      <h3>Photos <span style="font-weight:400;color:var(--text-muted);font-size:12.5px;">(up to 5)</span></h3>
      <div class="image-drop" id="dropZone">
        <div class="emoji">📷</div>
        <div>Drag photos here, or click to choose files</div>
      </div>
      <input type="file" id="fileInput" name="images[]" accept="image/*" multiple hidden>
      <div class="image-previews" id="imagePreviews">
        <?php foreach ($existingImages as $img): ?>
          <div class="image-preview" data-existing="<?= e($img) ?>">
            <img src="uploads/<?= e($img) ?>">
            <span class="existing-badge">saved</span>
            <button type="button" class="remove-img" onclick="removeExisting(this)">✕</button>
            <input type="hidden" name="keep_images[]" value="<?= e($img) ?>">
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-section">
      <h3>Details</h3>
      <div class="field">
        <label>Title</label>
        <input type="text" name="title" required maxlength="150" placeholder="e.g. iPhone 13 Pro, 128GB, mint condition" value="<?= e($ad['title'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Description</label>
        <textarea name="description" placeholder="Describe the item — condition, age, why you're selling, anything a buyer would want to know."><?= e($ad['description'] ?? '') ?></textarea>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Price (Rs.)</label>
          <input type="number" name="price" min="0" placeholder="Leave blank for 'Contact for price'" value="<?= e($ad['price'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Condition</label>
          <select name="condition">
            <option value="used" <?= (($ad['condition'] ?? 'used') === 'used') ? 'selected' : '' ?>>Used</option>
            <option value="new" <?= (($ad['condition'] ?? '') === 'new') ? 'selected' : '' ?>>Brand new</option>
          </select>
        </div>
      </div>
      <div class="field checkbox-field">
        <input type="checkbox" name="negotiable" id="fNegotiable" <?= !empty($ad['negotiable']) ? 'checked' : '' ?>>
        <label for="fNegotiable" style="margin:0;">Price is negotiable</label>
      </div>
      <div class="field">
        <label>Location</label>
        <input type="text" name="location" required placeholder="e.g. Colombo 05" value="<?= e($ad['location'] ?? '') ?>">
      </div>
    </div>

    <button class="btn-submit" type="submit"><?= $editId ? 'Save changes' : 'Post ad' ?></button>
  </form>
</div>

<script>
function selectCatBtn(radio) {
  document.querySelectorAll('.cat-select-btn').forEach(b => b.classList.remove('selected'));
  radio.closest('.cat-select-btn').classList.add('selected');
}

const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const previews = document.getElementById('imagePreviews');
let stagedFiles = [];

dropZone.addEventListener('click', () => fileInput.click());
['dragover', 'dragleave', 'drop'].forEach(evt => {
  dropZone.addEventListener(evt, (e) => {
    e.preventDefault();
    dropZone.classList.toggle('dragover', evt === 'dragover');
  });
});
dropZone.addEventListener('drop', (e) => addFiles(e.dataTransfer.files));
fileInput.addEventListener('change', (e) => { addFiles(e.target.files); });

function countTotal() {
  return previews.querySelectorAll('.image-preview').length;
}

function addFiles(fileList) {
  const room = 5 - countTotal();
  if (room <= 0) { alert('You can add up to 5 photos.'); return; }
  Array.from(fileList).slice(0, room).forEach(f => {
    if (!f.type.startsWith('image/')) return;
    stagedFiles.push(f);
    const div = document.createElement('div');
    div.className = 'image-preview';
    div.innerHTML = `<img src="${URL.createObjectURL(f)}"><button type="button" class="remove-img">✕</button>`;
    div.querySelector('.remove-img').addEventListener('click', () => {
      stagedFiles = stagedFiles.filter(sf => sf !== f);
      div.remove();
      syncFileInput();
    });
    previews.appendChild(div);
  });
  syncFileInput();
}

function syncFileInput() {
  const dt = new DataTransfer();
  stagedFiles.forEach(f => dt.items.add(f));
  fileInput.files = dt.files;
}

function removeExisting(btn) {
  btn.closest('.image-preview').remove();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
