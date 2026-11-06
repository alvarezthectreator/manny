<?php

declare(strict_types=1);

require __DIR__ . '/common.php';
auth_require_method('GET');
auth_respond(['ok' => true, 'csrf_token' => auth_csrf_token()]);