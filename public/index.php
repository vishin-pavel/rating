<?php

/**
 * 
 * @author Vishin Pavel
 * @date 01.07.15
 * @time 15:13
 */
require '../vendor/autoload.php';
$config = require('../config/config.php');

$app = new Slim\Slim($config);
$dbName = $app->config('database')['dbname'];
$db = new PDO("sqlite:$dbName");

$app->get('/rating/:weekNum', function($weekNum)use($app, $db){

});

$app->post('/vote/', function()use($app, $db){
    $data = json_decode($app->request->getBody());
    if($data['token']!=$app->config('token')){
        $app->response()->setStatus('400');
        $app->response()->setBody(json_encode(['error'=>'Bad Token']));
        return true;
    }
    //Todo:Проверить есть ли голосование на текущий период. Если нет, то создать
    foreach($data['vote'] as $pserson){
        $db->prepare("INSERT INTO Vote ()");
    }
});