<?php

declare(strict_types=1);

use App\Support\Http\Request;
use App\Support\Http\Response;

/** @var callable(?string): callable(Request): Response $bootstrap */
$bootstrap = require dirname(__DIR__) . '/bootstrap/app.php';

$handle = $bootstrap(getenv('NOTES_STORAGE_PATH') ?: null);

$handle(Request::fromGlobals())->send();
