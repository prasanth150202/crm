<?php
// logout.php
require_once 'config/security.php';
Security::secureSession();
session_destroy();
header('Location: login.php');
exit;
