<?php
// themes/default/header.php — سربرگ قالب پیش‌فرض
$menus = mp_pdo()->query("SELECT * FROM menus ORDER BY position ASC")->fetchAll();
?><!DOCTYPE html>
<html lang="<?= e(mp_lang()) ?>" dir="<?= mp_dir() ?>">
<head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? $siteTitle) ?></title>
<?= mp_seo_meta($pageTitle ?? $siteTitle) ?>
<link rel="stylesheet" href="assets/css/style.css">
</head><body>
<header class="site-header"><div class="wrap">
  <a class="logo" href="index.php"><?= e($siteTitle) ?></a>
  <p class="tagline"><?= e($siteDesc) ?></p>
  <nav class="main-nav">
    <?php foreach ($menus as $m): ?><a href="<?= e(mp_safe_menu_url((string)$m['url'])) ?>"><?= e($m['title']) ?></a><?php endforeach; ?>
    <?php if (mp_module_enabled('shop')): ?><a href="index.php?cart=1">🛒 <?= t('cart') ?> (<?= count(mp_cart()) ?>)</a><?php endif; ?>
  </nav>
  <form class="search-form" method="get" action="index.php">
    <input type="search" name="s" placeholder="<?= t('search') ?>…" value="<?= e($_GET['s'] ?? '') ?>">
    <button type="submit"><?= t('search') ?></button>
  </form>
</div></header>
<main class="wrap">
<?= mp_flash_show() ?>
