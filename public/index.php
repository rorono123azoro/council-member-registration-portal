<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
// Phase 0 entry stub — replaced by registration form in Phase 2
\App\Services\Database::connection();
http_response_code(200);
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . e(appConfig('name')) . '</title></head><body><h1>Council Portal Ready</h1></body></html>';
