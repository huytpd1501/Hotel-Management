<?php
class Invoice {
  private $conn;

  // table names per hotel_db.sql
  private $tblInvoice      = 'invoice';
  private $tblBooking      = 'booking';
  private $tblRoom         = 'room';
  private $tblServiceUsage = 'service_usage';
  private $tblService      = 'service';
  private $tblCustomer     = 'customer';

  public function __construct($db) { $this->conn = $db; }

  /** List all invoices (basic columns) */
  public function getAll() {
    $sql = "SELECT invoice_id, staff_id, booking_id, total_amount, created_date, payment_status
            FROM {$this->tblInvoice}
            ORDER BY created_date DESC, invoice_id DESC";
    $st  = $this->conn->prepare($sql);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }

  /** Get one invoice row */
  public function getOne($id) {
    $st = $this->conn->prepare("SELECT * FROM {$this->tblInvoice} WHERE invoice_id = ?");
    $st->execute([(int)$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
  }

  /** Delete invoice by id */
  public function delete($id) {
    $st = $this->conn->prepare("DELETE FROM {$this->tblInvoice} WHERE invoice_id = ?");
    return $st->execute([(int)$id]);
  }

  /**
   * Compute totals for a booking (preview / print)
   * Returns: [
   *   booking_id, room_id, checkin_date, checkout_date,
   *   nights, room_price, room_subtotal,
   *   services: [{service_name, quantity, unit_price, line_total}, ...],
   *   service_total, total_amount
   * ]
   */
  public function recalcForBooking($bookingId) {
    $bookingId = (int)$bookingId;
    // 1) Load booking with room price_per_night
    $sqlB = "SELECT b.booking_id, b.customer_id, b.room_id, b.checkin_date, b.checkout_date,
                    r.price_per_night AS room_price, r.room_number
             FROM {$this->tblBooking} b
             JOIN {$this->tblRoom} r ON r.room_id = b.room_id
             WHERE b.booking_id = :bid";
    $stB = $this->conn->prepare($sqlB);
    $stB->execute([':bid' => $bookingId]);
    $bk = $stB->fetch(PDO::FETCH_ASSOC);
    if(!$bk) { throw new Exception("Booking not found"); }

    // Nights = max(1, dateDiff)
    $ci = new DateTime($bk['checkin_date']);
    $co = new DateTime($bk['checkout_date']);
    $diff = (int)$ci->diff($co)->days;
    $nights = max(1, $diff);

    $roomPrice     = (int)$bk['room_price'];
    $roomSubtotal  = $nights * $roomPrice;

    // 2) Load services for this booking
    $sqlS = "SELECT s.service_name, s.unit_price, su.quantity,
                    (su.quantity * s.unit_price) AS line_total
             FROM {$this->tblServiceUsage} su
             JOIN {$this->tblService} s ON s.service_id = su.service_id
             WHERE su.booking_id = :bid
             ORDER BY su.usage_date ASC, s.service_name ASC";
    $stS = $this->conn->prepare($sqlS);
    $stS->execute([':bid' => $bookingId]);
    $services = $stS->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $service_total = 0;
    foreach($services as $row) { $service_total += (int)$row['line_total']; }

    $total = $roomSubtotal + $service_total;

    // Optional: customer name for printing
    $custName = null;
    if (!empty($bk['customer_id'])) {
      $stC = $this->conn->prepare("SELECT full_name FROM {$this->tblCustomer} WHERE customer_id = ?");
      $stC->execute([(int)$bk['customer_id']]);
      $c = $stC->fetch(PDO::FETCH_ASSOC);
      $custName = $c ? $c['full_name'] : null;
    }

    return [
      'booking_id'     => $bookingId,
      'room_id'        => (int)$bk['room_id'],
      'room_number'    => $bk['room_number'] ?? null,
      'customer_name'  => $custName,
      'checkin_date'   => $bk['checkin_date'],
      'checkout_date'  => $bk['checkout_date'],
      'nights'         => $nights,
      'room_price'     => (int)$roomPrice,
      'room_subtotal'  => (int)$roomSubtotal,
      'services'       => $services,
      'service_total'  => (int)$service_total,
      'total_amount'   => (int)$total
    ];
  }

  /** Create invoice (totals auto từ booking nếu không truyền vào) */
  public function create($payload) {
    $staff_id      = isset($payload['staff_id']) ? (int)$payload['staff_id'] : null;
    $booking_id    = isset($payload['booking_id']) ? (int)$payload['booking_id'] : null;
    if(!$booking_id) throw new Exception("booking_id is required");
    if(!$staff_id) throw new Exception("staff_id is required");

    // compute totals if not provided
    if (!isset($payload['total_amount'])) {
      $calc = $this->recalcForBooking($booking_id);
      $total_amount = (int)$calc['total_amount'];
    } else {
      $total_amount = (int)$payload['total_amount'];
    }

    $created_date   = !empty($payload['created_date']) ? $payload['created_date'] : date('Y-m-d');
    $payment_status = !empty($payload['payment_status']) ? $payload['payment_status'] : 'UNPAID';

    $sql = "INSERT INTO {$this->tblInvoice} (staff_id, booking_id, total_amount, created_date, payment_status)
            VALUES (:sid, :bid, :total, :cd, :ps)";
    $st  = $this->conn->prepare($sql);
    $ok = $st->execute([
      ':sid'=>$staff_id, ':bid'=>$booking_id, ':total'=>$total_amount,
      ':cd'=>$created_date, ':ps'=>$payment_status
    ]);
    if(!$ok) { throw new Exception("Failed to create invoice"); }
    $id = (int)$this->conn->lastInsertId();
    return $this->getOne($id);
  }

  /** Update invoice fields */
  public function update($id, $payload) {
    $id = (int)$id;
    if($id<=0) throw new Exception("Invalid invoice id");

    // Optionally recalc from booking if requested
    if (!empty($payload['recalc'])) {
      $booking_id = isset($payload['booking_id']) ? (int)$payload['booking_id'] : null;
      if (!$booking_id) {
        // fallback to invoice's current booking
        $cur = $this->getOne($id);
        if ($cur) $booking_id = (int)$cur['booking_id'];
      }
      if ($booking_id) {
        $calc = $this->recalcForBooking($booking_id);
        $payload['total_amount'] = (int)$calc['total_amount'];
      }
    }

    $fields = [];
    $params = [':id' => $id];
    foreach (['staff_id','booking_id','total_amount','created_date','payment_status'] as $k) {
      if (array_key_exists($k, $payload)) {
        $fields[] = "$k = :$k";
        $params[":$k"] = $payload[$k];
      }
    }
    if (empty($fields)) return $this->getOne($id);

    $sql = "UPDATE {$this->tblInvoice} SET ".implode(', ', $fields)." WHERE invoice_id = :id";
    $st = $this->conn->prepare($sql);
    $st->execute($params);
    return $this->getOne($id);
  }
}
?>
