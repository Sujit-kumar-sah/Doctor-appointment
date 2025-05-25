<?php
require_once '_db.php';

$scheduler_doctors = $db->query('SELECT * FROM doctor ORDER BY doctor_name');

class Resource {
  public $id;
  public $name;
}

$result = array();

foreach($scheduler_doctors as $doctor) {
  $r = new Resource();
  $r->id = (int) $doctor['doctor_id'];
  $r->name = $doctor['doctor_name'];
  $result[] = $r;
}

header('Content-Type: application/json');
echo json_encode($result);
