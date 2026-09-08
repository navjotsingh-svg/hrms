<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$loginRequest = Illuminate\Http\Request::create(
    '/api/v1/auth/login',
    'POST',
    [],
    [],
    [],
    ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
    json_encode(['email' => 'admin@hrms.com', 'password' => 'Admin@123', 'device_name' => 'test'])
);

$loginResponse = $kernel->handle($loginRequest);
$loginData = json_decode($loginResponse->getContent(), true);

if (! ($loginData['success'] ?? false)) {
    echo 'Login failed: '.$loginResponse->getContent();
    exit(1);
}

$token = $loginData['data']['token'];

$sessionRequest = Illuminate\Http\Request::create(
    '/auth/session',
    'POST',
    [],
    [],
    [],
    [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    ]
);

$sessionResponse = $kernel->handle($sessionRequest);
echo "Login: OK\n";
echo "Session: ".$sessionResponse->getContent()."\n";
echo "Session status: ".$sessionResponse->getStatusCode()."\n";

$dashboardRequest = Illuminate\Http\Request::create('/dashboard', 'GET', [], $sessionRequest->cookies->all(), [], $sessionRequest->server->all());
$dashboardRequest->setLaravelSession($sessionRequest->getSession());
$dashboardResponse = $kernel->handle($dashboardRequest);
echo "Dashboard status: ".$dashboardResponse->getStatusCode()."\n";
