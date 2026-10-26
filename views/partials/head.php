<?php
use App\Support\Security;
$identity = $identity ?? [];
$title = $title ?? ($identity['college_name'] ?? 'النظام الجامعي');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= Security::e($title) ?></title>
  <link rel="stylesheet" href="/assets/css/app.css?v=2">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Tajawal:wght@400;500;700&family=Noto+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet" media="print" onload="this.media='all'">
</head>
