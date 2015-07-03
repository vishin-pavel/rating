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

/**
 * @param \PDO $db
 * @param $sql
 * @param $driverOptions
 * @return \PDOStatement
 */
function getAllFromDB($db, $sql, array $driverOptions = []){
    $request = $db->prepare($sql, $driverOptions);
    $request ->execute();
    return $request;
}

/**
 * Получить результаты одного голосования
 */
$app->get('/poll/last', function()use($app, $db){
    $select = $db->prepare("
                            SELECT
                              name,
                              SUM(rating) AS rating
                            FROM poll
                            LEFT JOIN vote ON poll.id = vote.poll_id
                            LEFT JOIN  person ON person.id = vote.person_id
                            WHERE poll.id = (SELECT p.id FROM poll AS p ORDER BY id DESC LIMIT 1)
                            GROUP BY person_id, poll_id
                  ");
    $rating = $select->fetchAll(PDO::FETCH_ASSOC);
    $app->response()->setStatus('200');
    $app->response()->header('Content-Type', 'application/json');
    $app->response()->setBody(json_encode($rating));
    return true;
});

/**
 * Получить результаты одного голосования
 */
$app->get('/poll/:id', function($poll_id)use($app, $db){
    $select = $db->prepare("
                            SELECT
                              date_start,
                              date_end,
                              name,
                              SUM(rating) AS rating
                            FROM poll
                            LEFT JOIN vote ON poll.id = vote.poll_id
                            LEFT JOIN  person ON person.id = vote.person_id
                            WHERE poll.id = :poll_id
                            GROUP BY person_id, poll_id
                  ");
    $select->execute([':poll_id'=>$poll_id]);
    $rating = $select->fetchAll(PDO::FETCH_ASSOC);
    $app->response()->setStatus('200');
    $app->response()->header('Content-Type', 'application/json');
    $app->response()->setBody(json_encode($rating));
    return true;
});

/**
 * Получить текущую дату с сервера
 */
$app->get('/date/', function()use($app, $db){
    $date = new DateTime('NOW');
    $app->response()->setStatus('200');
    $app->response()->header('Content-Type', 'application/json');
    $app->response()->setBody(json_encode($date->getTimestamp()));
});

/**
 * Получить список всех голосований
 */
$app->get('/poll/list', function()use($app, $db){
    $select = $db->prepare(' SELECT * FROM poll');
    $select -> execute();

    $app->response()->setStatus('200');
    $app->response()->header('Content-Type', 'application/json');
    $app->response()->setBody(json_encode($select->fetchAll(PDO::FETCH_ASSOC)));
    return true;
});

/**
 * Получить результаты всех голосований
 */
$app->get('/rating/list', function()use($app, $db){
    $select = $db->prepare("
                            SELECT
                              poll.id,
                              date_start,
                              date_end,
                              name,
                              SUM(rating) AS rating
                            FROM poll
                            LEFT JOIN vote ON poll.id = vote.poll_id
                            LEFT JOIN  person ON person.id = vote.person_id
                            GROUP BY person_id, poll_id
                  ");
    $select->execute();
    $pollList = $select->fetchAll(PDO::FETCH_ASSOC);
    $result =[];
    foreach($pollList as $poll){
        $result[$poll['id']][] = $poll;
    }
    $app->response()->setStatus('200');
    $app->response()->header('Content-Type', 'application/json');
    $app->response()->setBody(json_encode($result));
    return true;
});

/**
 * Получить список участников
 */
$app->get('/person/', function()use($app, $db){
    $select = $db->prepare('SELECT * FROM person');
    $select -> execute();
    $app->response()->setStatus('200');
    $app->response()->header('Content-Type', 'application/json');
    $app->response()->setBody(json_encode($select->fetchAll(PDO::FETCH_ASSOC)));
});

/**
 * Добавить пользователя
 */
$app->post('/person/', function()use($app, $db){
    $data = json_decode($app->request->getBody(), true);
    $insert = $db->prepare('INSERT INTO person (login, name) VALUES (:login, :name)');
    $insert->execute([':login'=>$data['login'], ':name'=>$data['name']]);

    $app->response()->setStatus('200');
    $app->response()->header('Content-Type', 'application/json');
    $app->response()->setBody(json_encode([
        'id' => $db->lastInsertId(),
        'login'=>$data['login'],
        'name'=>$data['name']
    ]));
    return true;
});

/**
 * Удалить пользователя
 */
$app->delete('/person/:id', function($id)use($app, $db){
    $delete = $db->prepare('DELETE FROM person where id=:id');
    $delete->execute([':id'=>$id]);

    $app->response()->setStatus('204');
});

/**
 * Проголосовать
 */
$app->post('/vote/', function()use($app, $db){
    $data = json_decode($app->request->getBody(), true);
    $now = time();
    $pollID = $db->prepare('SELECT *
                                FROM poll
                                WHERE :time > date_start
                                  AND :time < date_end');
    $pollID ->execute([':time'=>$now]);
    $pollID = $pollID->fetchColumn();
    if(!$pollID){
        $date_end = new DateTime("Monday next week");
        $date_start = new DateTime("last Monday");
        $insert = $db->prepare("INSERT INTO poll ('date_start', 'date_end') VALUES (:date_start, :date_end)");
        $result = $insert->execute([':date_start'=>$date_start->getTimestamp(), ':date_end'=>$date_end->getTimestamp()]);
        $pollID = $db->lastInsertId();
    }

    foreach($data as $person){
        $insert = $db->prepare("INSERT INTO Vote VALUES (:voter_id, :person_id, :poll_id, :rating)");
        $insert->execute([
            ':voter_id' => $person['voter_id'],
            ':person_id' => $person['person_id'],
            ':poll_id' => $pollID,
            ':rating' => $person['rating'],
        ]);
    }

    $app->response()->setStatus('200');
    $app->response()->header('Content-Type', 'application/json');
    $app->response()->setBody(json_encode(['success'=>'Голоса приняты']));
    return true;
});
/**
 * Запуск приложения
 */
$app->run();
