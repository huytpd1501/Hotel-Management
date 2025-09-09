<?php
header("Access-Control-Allow-Origin: https://nhom7.itimit.id.vn/hotel");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/Database.php';
require_once ROOT_PATH . '/src/models/ServiceUsage.php';
require_once ROOT_PATH . '/src/models/Invoice.php';

$database = new Database();
$db = $database->getConnection();
$model = new ServiceUsage($db);
$invoice = new Invoice($db);

$method = $_SERVER['REQUEST_METHOD'];

function body_json(){
  $raw = file_get_contents("php://input");
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}

switch ($method) {
  case 'GET':
    parse_str($_SERVER["QUERY_STRING"] ?? "", $q);
    if (!empty($q['booking_id'])) {
      echo json_encode($model->getByBooking((int)$q['booking_id']));
    } else {
      echo json_encode($model->getAll());
    }
    break;

  case 'POST':
    $d = body_json();
    $ok = $model->create($d);
    $breakdown = null;
    if ($ok && !empty($d['booking_id'])) {
      $breakdown = $invoice->recalcForBooking((int)$d['booking_id']);
    }
    echo json_encode(['success'=>$ok?true:false, 'invoice'=>$breakdown]);
    break;

  case 'PUT':
    parse_str($_SERVER["QUERY_STRING"] ?? "", $q);
    $usage_id = (int)($q['id'] ?? $q['usage_id'] ?? 0);
    $d = body_json();
    // booking_id để recalc (nếu không có thì tra theo usage_id)
    $booking_id = (int)($d['booking_id'] ?? $model->getBookingIdByUsage($usage_id));
    $ok = $model->update($usage_id, $d);
    $breakdown = $booking_id ? $invoice->recalcForBooking($booking_id) : null;
    echo json_encode(['success'=>$ok?true:false, 'invoice'=>$breakdown]);
    break;

  case 'DELETE':
    parse_str($_SERVER["QUERY_STRING"] ?? "", $q);
    $usage_id = (int)($q['id'] ?? $q['usage_id'] ?? 0);
    $booking_id = $model->getBookingIdByUsage($usage_id);
    $ok = $model->delete($usage_id);
    $breakdown = $booking_id ? $invoice->recalcForBooking((int)$booking_id) : null;
    echo json_encode(['success'=>$ok?true:false, 'invoice'=>$breakdown]);
    break;

  default:
    http_response_code(405);
    echo json_encode(['error'=>'Method not allowed']);
}
