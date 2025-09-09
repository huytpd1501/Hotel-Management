<?php
header("Access-Control-Allow-Origin: https://nhom7.itimit.id.vn/hotel");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/Database.php';
require_once ROOT_PATH . '/src/models/Booking.php';
require_once ROOT_PATH . '/src/models/Invoice.php';

$database = new Database();
$db = $database->getConnection();
$bookingModel = new Booking($db);
$invoiceModel = new Invoice($db);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

function body_json() {
  $raw = file_get_contents("php://input");
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}

try {
  switch ($method) {
    /* ======================= GET ======================= */
    case 'GET': {
      // Hỗ trợ cả ?action=getById&id=... và ?id=...
      $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

      if ($action === 'getById' && $id > 0) {
        // Ưu tiên getById nếu model có
        if (method_exists($bookingModel, 'getById')) {
          $row = $bookingModel->getById($id);
        } else {
          $row = $bookingModel->getOne($id);
        }
        echo json_encode($row ?: ['success'=>false,'message'=>'Not found']);
        break;
      }

      if ($id > 0) {
        $row = method_exists($bookingModel, 'getById')
          ? $bookingModel->getById($id)
          : $bookingModel->getOne($id);
        echo json_encode($row ?: ['success'=>false,'message'=>'Not found']);
        break;
      }

      echo json_encode($bookingModel->getAll());
      break;
    }

    /* ======================= POST (CREATE) ======================= */
    case 'POST': {
      $payload  = body_json();
      $services = isset($payload['services']) && is_array($payload['services']) ? $payload['services'] : [];

      // Tạo booking — ưu tiên chữ ký (payload, services)
      $newId = null; $ok = false;
      try {
        if (method_exists($bookingModel, 'create')) {
          $ref = new ReflectionMethod($bookingModel, 'create');
          if ($ref->getNumberOfParameters() >= 2) {
            $newId = $bookingModel->create($payload, $services);
            $ok = (bool)$newId;
          } else {
            // Chữ ký cũ: create($payload) trả [ok, id]
            [$ok, $newId] = $bookingModel->create($payload);
          }
        }
      } catch (Throwable $e) {
        throw $e;
      }

      $breakdown = null;
      if ($ok && $newId) {
        $breakdown = $invoiceModel->recalcForBooking($newId);
      }

      echo json_encode(['success'=> (bool)$ok, 'booking_id'=>$newId, 'invoice'=>$breakdown]);
      break;
    }

    /* ======================= PUT (UPDATE) ======================= */
    case 'PUT': {
      $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
      if ($id <= 0) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Missing id']); break; }

      $payload  = body_json();
      $hasServicesKey = array_key_exists('services', $payload);
      $services = $hasServicesKey && is_array($payload['services']) ? $payload['services'] : null;

      $ok = false;
      // Ưu tiên chữ ký (id, payload, services)
      if (method_exists($bookingModel, 'update')) {
        $ref = new ReflectionMethod($bookingModel, 'update');
        if ($ref->getNumberOfParameters() >= 3) {
          $ok = (bool)$bookingModel->update($id, $payload, $services);
        } else {
          $ok = (bool)$bookingModel->update($id, $payload); // chữ ký cũ
        }
      }

      $breakdown = $ok ? $invoiceModel->recalcForBooking($id) : null;
      echo json_encode(['success'=>(bool)$ok, 'booking_id'=>$id, 'invoice'=>$breakdown]);
      break;
    }

    /* ======================= DELETE ======================= */
    case 'DELETE': {
      $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
      if ($id <= 0) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Missing id']); break; }

      // Model delete đã xử lý xóa service_usage → invoice → booking (transaction)
      $ok = (bool)$bookingModel->delete($id);
      echo json_encode(['success'=>$ok]);
      break;
    }

    default:
      http_response_code(405);
      echo json_encode(['success'=>false,'message'=>'Method not allowed']);
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
