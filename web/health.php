<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
http_response_code(200);
echo json_encode(['status' => 'ok', 'time' => date('c')]), "\n";
