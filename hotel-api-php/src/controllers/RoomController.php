<?php
header("Access-Control-Allow-Origin: http://localhost:8000");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH.'/config/Database.php';
require_once ROOT_PATH.'/src/models/Room.php';

$db = (new Database())->getConnection();
$room = new Room($db);

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        $status = $_GET['status'] ?? null; // filter theo query string ?status=AVAILABLE
        echo json_encode($room->read($status)->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($room->create($data) ? ["success"=>true] : ["success"=>false]);
        break;

    case 'PUT':
        parse_str($_SERVER['QUERY_STRING'], $q); 
        $id = $q['id'] ?? null;
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode(($id && $room->update($id, $data)) ? ["success"=>true] : ["success"=>false]);
        break;

    case 'DELETE':
        parse_str($_SERVER['QUERY_STRING'], $q); 
        $id = $q['id'] ?? null;
        echo json_encode(($id && $room->delete($id)) ? ["success"=>true] : ["success"=>false]);
        break;

    default:
        http_response_code(405);
        echo json_encode(["error"=>"Method not allowed"]);
}
?>
