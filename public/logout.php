<?php
// public/logout.php
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION = [];
session_destroy();
setcookie(session_name(), '', time() - 3600, '/');
header('Location: /public/login.php');
exit;
