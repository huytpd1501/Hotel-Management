<?php
header("Access-Control-Allow-Origin: http://localhost:8000");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/Database.php';
require_once ROOT_PATH . '/src/models/Invoice.php';

$database = new Database();
$db = $database->getConnection();
$model = new Invoice($db);

$method = $_SERVER['REQUEST_METHOD'];

function body_json(){
  $raw = file_get_contents("php://input");
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}

try {
  switch ($method) {
    /* ======================= GET ======================= */
    case 'GET': {
      // calc preview for booking
      if (isset($_GET['calc'])) {
        $bid = (int)$_GET['calc'];
        $res = $model->recalcForBooking($bid);
        echo json_encode($res);
        break;
      }

      // get one by id
      if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $res = $model->getOne($id);
        echo json_encode($res ?: ["error"=>"Not found"]);
        break;
      }

      // list all
      echo json_encode($model->getAll());
      break;
    }

    /* ======================= POST (create) ======================= */
    case 'POST': {
      $payload = body_json();
      $created = $model->create($payload);
      echo json_encode(['success'=>true, 'data'=>$created]);
      break;
    }

    /* ======================= PUT (update) ======================= */
    case 'PUT': {
      parse_str($_SERVER["QUERY_STRING"] ?? "", $q);
      $id = isset($q['id']) ? (int)$q['id'] : 0;
      if (!$id) { http_response_code(400); echo json_encode(['success'=>false, 'message'=>'Missing id']); break; }

      $payload = body_json();
      // allow /?action=recalc to force recompute
      if (($q['action'] ?? '') === 'recalc') {
        $payload['recalc'] = true;
      }

      $updated = $model->update($id, $payload);
      echo json_encode(['success'=>true, 'data'=>$updated]);
      break;
    }

    /* ======================= DELETE ======================= */
    case 'DELETE': {
      parse_str($_SERVER["QUERY_STRING"] ?? "", $q);
      $id = (int)($q['id'] ?? 0);
      $ok = $id ? $model->delete($id) : false;
      echo json_encode(['success' => (bool)$ok]);
      break;
    }

    default: {
      http_response_code(405);
      echo json_encode(["error" => "Method not allowed"]);
    }
  }
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
