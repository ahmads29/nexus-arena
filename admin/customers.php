<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

header('Location: ' . ADMIN_BASE . '/index.php?toast=' . urlencode('Customers section has been removed.'));
exit;
