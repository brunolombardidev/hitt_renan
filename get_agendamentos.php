<?php
require_once 'db/Database.php';

$conn = getConnection();

$sql = "SELECT * FROM agendamentos";
$result = $conn->query($sql);

$agendamentos = [];
while ($agendamento = $result->fetch_assoc()) {
    $agendamentos[] = $agendamento;
}

echo json_encode($agendamentos);
?>