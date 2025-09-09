<?php
// hotel-api-php/src/controllers/AuthController.php

// ===== CORS =====
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowed = [
  'http://localhost',
  'http://127.0.0.1',
  'http://localhost:8000',
  'http://127.0.0.1:8000',
  'https://nhom7.itimit.id.vn/hotel'
];

if (in_array($origin, $allowed, true)) {
  header("Access-Control-Allow-Origin: $origin");
} else {
  header("Access-Control-Allow-Origin: https://nhom7.itimit.id.vn/hotel");
}
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

// ===== JSON lỗi thay vì HTML =====
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_exception_handler(function($e){
  http_response_code(500);
  echo json_encode(["ok"=>false, "error"=>"Server error: ".$e->getMessage()]);
});
set_error_handler(function($no,$str){
  http_response_code(500);
  echo json_encode(["ok"=>false, "error"=>"PHP error: ".$str]);
  return true;
});

// ===== Session =====
if (session_status() === PHP_SESSION_NONE) session_start();

// ===== Bootstrap & DB =====
require_once __DIR__ . '/../../config/bootstrap.php';
require_once ROOT_PATH . '/config/Database.php';

// Helpers
function read_json(){
  $raw = file_get_contents('php://input');
  if($raw === '' || $raw === false) return [];
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}
function ok($data){ echo json_encode($data); }

$action = $_GET['action'] ?? '';

switch ($action) {
  case 'me': {
    if (!isset($_SESSION['user'])) {
      http_response_code(401);
      ok(["ok"=>false,"error"=>"Chưa đăng nhập"]);
      break;
    }
    ok($_SESSION['user']); // FE expect object user
    break;
  }

  case 'login': {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      http_response_code(405);
      ok(["ok"=>false,"error"=>"Method not allowed"]);
      break;
    }

    $data = read_json();
    $username = trim($data['username'] ?? '');
    $password = (string)($data['password'] ?? '');
    if ($username === '' || $password === '') {
      http_response_code(400);
      ok(["ok"=>false,"error"=>"Thiếu username/password"]);
      break;
    }

    $db = (new Database())->getConnection();

    // DB của bạn dùng MD5 và không có cột full_name trong account:contentReference[oaicite:2]{index=2}
    // Lấy thêm tên nhân viên (nếu có) từ bảng staff (LEFT JOIN)
    $sql = "SELECT a.account_id, a.username, a.password_hash, a.role, s.full_name
            FROM account a
            LEFT JOIN staff s ON s.account_id = a.account_id
            WHERE a.username = :u
            LIMIT 1";
    $st = $db->prepare($sql);
    $st->execute([':u'=>$username]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    // So sánh MD5 thay vì password_verify
    if (!$u || md5($password) !== $u['password_hash']) {
      http_response_code(401);
      ok(["ok"=>false,"error"=>"Sai thông tin đăng nhập"]);
      break;
    }

    // Lưu session
    $_SESSION['user'] = [
      "account_id" => (int)$u["account_id"],
      "username"   => $u["username"],
      "role"       => $u["role"],               // 'Admin' | 'Staff' theo schema
      "full_name"  => $u["full_name"] ?: $u["username"] // nếu không có staff.full_name thì fallback username
    ];

    ok($_SESSION['user']); // 200
    break;
  }

  case 'logout': {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(session_name(), '', time()-42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
    ok(["ok"=>true]);
    break;
  }

  default: {
    http_response_code(400);
    ok(["ok"=>false,"error"=>"Unknown action"]);
  }
}
