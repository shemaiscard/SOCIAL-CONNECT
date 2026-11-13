<?php
/** Shared <head>. Expects $pageTitle. */
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="Social Connect: posts, photos, chat and groups with the people you know.">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f5f6f9" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#101218" media="(prefers-color-scheme: dark)">
<link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(asset('img/icon-192.png')) ?>">
<link rel="preload" href="<?= e(url('/assets/fonts/Inter-Variable.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<script src="<?= e(asset('theme.js')) ?>"></script>
<script src="<?= e(asset('app.js')) ?>" defer></script>
