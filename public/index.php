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

$app->get('/rating/:id', function($poll_id)use($app, $db){
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

$app->get('/poll/list', function()use($app, $db){
    $select = $db->prepare(' SELECT * FROM poll');
    $select -> execute();

    $app->response()->setStatus('200');
    $app->response()->header('Content-Type', 'application/json');
    $app->response()->setBody(json_encode($select->fetchAll(PDO::FETCH_ASSOC)));
    return true;
});

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

$app->get('/person/', function()use($app, $db){
    $select = $db->prepare('SELECT * FROM person');
    $select -> execute();
    $app->response()->setStatus('200');
    $app->response()->header('Content-Type', 'application/json');
    $app->response()->setBody(json_encode($select->fetchAll(PDO::FETCH_ASSOC)));
});


$app->post('/vote/', function()use($app, $db){
    $data = json_decode($app->request->getBody(), true);
    if($data['token']!=$app->config('token')){
        $app->response()->setStatus('400');
        $app->response()->header('Content-Type', 'application/json');
        $app->response()->setBody(json_encode(['error'=>'Bad Token']));
        return true;
    }
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

    foreach($data['vote'] as $person){
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
try {
    $app->run();
}
catch (Exception $e){
    $app->response()->setStatus('500');
    $app->response()->header('Content-Type', 'application/json');
    $app->response()->setBody(json_encode(['error'=>'Ошибка: Что-то пошло не так']));
}