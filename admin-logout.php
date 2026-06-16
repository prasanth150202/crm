<?php
require_once __DIR__ . '/config/security.php';
Security::secureSession();
session_destroy();
header('Location: admin-login.php');
exit;
