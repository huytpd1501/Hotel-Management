<?php
header("Access-Control-Allow-Origin: http://localhost:8000");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/src/models/Service.php';

$database = new Database();
$db = $database->getConnection();
$model = new Service($db);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo json_encode($model->getAll());
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        if (isset($data['service_name'], $data['unit_price'], $data['description'])) {
            echo json_encode(["success" => $model->create($data)]);
        } else {
            echo json_encode(["error" => "Thiếu thông tin cần thiết."]);
        }
        break;

    case 'PUT':
        parse_str($_SERVER["QUERY_STRING"], $q);
        $id = $q['id'] ?? null;
        $data = json_decode(file_get_contents("php://input"), true);
        if ($id && isset($data['service_name'], $data['unit_price'], $data['description'])) {
            echo json_encode(["success" => $model->update($id, $data)]);
        } else {
            echo json_encode(["error" => "Thiếu ID hoặc thông tin cần thiết."]);
        }
        break;

    case 'DELETE':
        parse_str($_SERVER["QUERY_STRING"], $q);
        $id = $q['id'] ?? null;
        if ($id) {
            echo json_encode(["success" => $model->delete($id)]);
        } else {
            echo json_encode(["error" => "Thiếu ID để xóa."]);
        }
        break;

    default:
        echo json_encode(["error" => "Phương thức không hợp lệ."]);
        break;
}
