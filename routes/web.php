<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});
$router->get('/caca', 'MyController@test');
$router->get('/pipi', 'MyController@test2');
$router->get('getproduct/{barCode}','MyController@getProduct');
$router->get('/bonjour/{nom}','MyController@bonjour');
$router->get('/search/{input}', 'MyController@search');
$router->get('/getrecipe/{id}', 'MyController@getRecipe');
$router->get('/searchrecipe/{products}/{cat}/{page}', 'MyController@searchRecipe');
