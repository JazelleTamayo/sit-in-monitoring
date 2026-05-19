<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'CCS Sit-in Monitoring System'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>assets/css/navbar.css">

    <?php if(isset($extraCSS)): ?>
        <link rel="stylesheet" href="<?= $basePath ?? '' ?>assets/css/<?php echo $extraCSS; ?>.css">
    <?php endif; ?>
</head>
<body>