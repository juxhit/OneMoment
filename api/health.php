<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (request_method() !== 'GET') {
    json_error('method_not_allowed', 405);
}

AdminAuth::startSession();
if (!AdminAuth::check()) {
    json_error('unauthorized', 401);
}

json_response(QuotaService::health(Event::getManagedId()));