<?php
header("Access-Control-Allow-Origin: http://localhost:8000");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }
require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/src/models/Customer.php';
require_once ROOT_PATH . '/config/Database.php';

$database = new Database();
$db = $database->getConnection();
$model = new Customer($db);

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        echo json_encode($model->getAll());
        break;
    case 'POST':
    $data = json_decode(file_get_contents("php://input"), true);
    try {
        $result = $model->create($data);
    } catch (Exception $e) {
        $result = [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
    echo json_encode($result);
    break;
    case 'PUT':
    parse_str($_SERVER["QUERY_STRING"], $query);
    $id = $query['id'] ?? null;
    $data = json_decode(file_get_contents("php://input"), true);
    
    $result = $model->update($id, $data);
    echo json_encode($result);
    break;
    case 'DELETE':
    parse_str($_SERVER["QUERY_STRING"], $query);
    $id = $query['id'] ?? null;

    $result = $model->delete($id);
    echo json_encode($result);
    break;
}
