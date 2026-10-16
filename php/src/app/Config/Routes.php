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

$routes->group('admin/generator', function($routes) {
    $routes->get('/', '\App\Controllers\Admin\Generator::index');
    $routes->post('create', '\App\Controllers\Admin\Generator::create');
    $routes->get('status/(:num)', '\App\Controllers\Admin\Generator::checkStatus/$1');
});