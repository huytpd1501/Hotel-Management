<?php
class Account {
  private PDO $db;
  public function __construct(PDO $db){ $this->db = $db; }

  public function findByUsername(string $username){
    $sql = "SELECT account_id, username, password_hash, full_name, role FROM account WHERE username = :u LIMIT 1";
    $st = $this->db->prepare($sql);
    $st->execute([':u'=>$username]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }
}
