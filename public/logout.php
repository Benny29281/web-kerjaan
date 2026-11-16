<?php
require __DIR__ . '/../app/bootstrap.php';
require_post();
$_SESSION = [];
session_regenerate_id(true);
flash('Anda sudah keluar dari ruang kerja.');
redirect('');
