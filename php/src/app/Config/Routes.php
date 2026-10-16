<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
//$routes->get('/', 'Home::index');
$routes->get('/', function() {
    return redirect()->to('/admin/materials');
});

$routes->group('admin/materials', function($routes) {
    $routes->get('/', '\App\Controllers\Admin\Materials::index');
    $routes->post('create-profession', '\App\Controllers\Admin\Materials::createProfession');
    $routes->post('upload', '\App\Controllers\Admin\Materials::uploadFile');
});

