<?php
require_once __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("
  SELECT a.*, c.name AS category_name, c.slug AS category_slug,
         u.name AS seller_name, u.phone AS seller_phone, u.created_at AS seller_since, u.id AS seller_id
  FROM ads a
  JOIN categories c ON c.id = a.category_id
  JOIN users u ON u.id = a.user_id
  WHERE a.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$ad = $stmt->get_result()->fetch_assoc();

if (!$ad) {
    $page_title = 'Ad not found';
    include __DIR__ . '/includes/header.php';
    echo '<div class="container"><div class="empty-state"><div class="emoji">🙁</div><h3>This ad could not be found</h3><p>It may have been removed.</p><a href="browse.php">Browse other ads →</a></div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Count a view (skip if the owner is looking at their own ad)
if (current_user_id() !== (int)$ad['user_id']) {
    $conn->query("UPDATE ads SET views = views + 1 WHERE id = " . (int)$ad['id']);
    $ad['views']++;
}

$images = json_decode($ad['images'] ?? '[]', true) ?: [];
$isOwner = current_user_id() === (int)$ad['user_id'];
$revealPhone = isset($_GET['reveal']) && is_logged_in();

$page_title = $ad['title'];
include __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="breadcrumb">
    <a href="index.php">Home</a> &rsaquo; <a href="browse.php?category=<?= e($ad['category_slug']) ?>"><?= e($ad['category_name']) ?></a> &rsaquo; <?= e($ad['title']) ?>
  </div>

  <div class="ad-detail-layout">
    <div>
      <div class="gallery-main" id="galleryMain">
        <?php if (!empty($images)): ?>
          <img id="mainImg" src="uploads/<?= e($images[0]) ?>" alt="<?= e($ad['title']) ?>">
        <?php else: ?>
          <div class="placeholder">🛍️</div>
        <?php endif; ?>
      </div>
      <?php if (count($images) > 1): ?>
        <div class="gallery-thumbs">
          <?php foreach ($images as $i => $img): ?>
            <img src="uploads/<?= e($img) ?>" class="<?= $i === 0 ? 'active' : '' ?>" onclick="switchImg('<?= e($img) ?>', this)">
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="ad-detail-desc">
        <h3>Description</h3>
        <p><?= e($ad['description'] ?: 'No description provided.') ?></p>
      </div>
    </div>

    <div class="detail-panel">
      <div class="price"><?= money($ad['price']) ?></div>
      <?php if ($ad['negotiable']): ?><div class="negotiable-tag">Negotiable</div><?php endif; ?>
      <h1><?= e($ad['title']) ?></h1>
      <div class="meta-row">📍 <?= e($ad['location'] ?: 'Location not specified') ?></div>
      <div class="meta-row">🕒 Posted <?= time_ago($ad['created_at']) ?></div>
      <div class="meta-row">👁 <?= (int)$ad['views'] ?> views &middot; <?= $ad['condition'] === 'new' ? 'Brand new' : 'Used' ?></div>
      <hr>
      <div class="seller-box">
        <div class="seller-avatar"><?= e(initials($ad['seller_name'])) ?></div>
        <div>
          <div class="seller-name"><?= e($ad['seller_name']) ?></div>
          <div class="seller-since">Member since <?= date('Y', strtotime($ad['seller_since'])) ?></div>
        </div>
      </div>

      <?php if ($isOwner): ?>
        <a class="btn-block" href="post-ad.php?edit=<?= (int)$ad['id'] ?>">Edit this ad</a>
      <?php elseif ($revealPhone): ?>
        <a class="btn-reveal" href="tel:<?= e($ad['seller_phone']) ?>">📞 <?= e($ad['seller_phone'] ?: 'Not provided') ?></a>
        <div class="safety-tip">🛡️ Meet in a public place, inspect the item before paying, and never send money in advance to someone you haven't met.</div>
      <?php else: ?>
        <a class="btn-reveal" href="<?= is_logged_in() ? 'ad.php?id=' . (int)$ad['id'] . '&reveal=1' : 'login.php?next=' . urlencode('ad.php?id=' . (int)$ad['id'] . '&reveal=1') ?>">📞 Show phone number</a>
        <div class="safety-tip">🛡️ Meet in a public place, inspect the item before paying, and never send money in advance to someone you haven't met.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function switchImg(src, el) {
  document.getElementById('mainImg').src = 'uploads/' + src;
  document.querySelectorAll('.gallery-thumbs img').forEach(i => i.classList.remove('active'));
  el.classList.add('active');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
