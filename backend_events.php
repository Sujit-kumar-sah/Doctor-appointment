<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);

$stmt = $db->prepare('SELECT * FROM appointment WHERE NOT ((appointment_end <= :start) OR (appointment_start >= :end))');
$stmt->bindParam(':start', $params->start);
$stmt->bindParam(':end', $params->end);
$stmt->execute();
$result = $stmt->fetchAll();

class Event {
  public $id;
  public $text;
  public $start;
  public $end;
  public $resource;
  public $tags;
}
class Tags {
  public $status;
}
$events = array();

foreach($result as $row) {
  $e = new Event();
  $e->id = (int) $row['appointment_id'];
  $e->text = $row['appointment_patient_name'] ?: "";
  $e->start = $row['appointment_start'];
  $e->end = $row['appointment_end'];
  $e->resource = (int) $row['doctor_id'];
  $e->tags = new Tags();
  $e->tags->status = $row['appointment_status'];
  $events[] = $e;
}

header('Content-Type: application/json');
echo json_encode($events);
