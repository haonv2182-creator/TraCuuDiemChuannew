<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode(['universities'=>[],'majors'=>[]]); exit; }
$db   = getDB();
$like = "%$q%";
$u=$db->prepare("SELECT university_id AS id,university_name AS name,province FROM universities WHERE university_name LIKE ? LIMIT 5");
$u->execute([$like]);
$m=$db->prepare("SELECT major_id AS id,major_name AS name FROM majors WHERE major_name LIKE ? LIMIT 4");
$m->execute([$like]);
echo json_encode(['universities'=>$u->fetchAll(),'majors'=>$m->fetchAll()]);
