<?php
require_once "db/Database.php";

header('Content-Type: application/json');

try {
    $conn = getConnection();
    
    // Pega o mês e ano da requisição, ou usa o atual
    $month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    
    // Calcula o intervalo de datas no formato correto (dd-mm-yyyy)
    $startDate = date('d-m-Y', strtotime("-30 days", strtotime("$year-$month-01")));
    $endDate = date('d-m-Y', strtotime("+60 days", strtotime("$year-$month-01")));
    
    // Modifica a query para usar STR_TO_DATE para converter a string de data
    $sql = "SELECT id, nome, telefone, data_hora, servico, atendente, status 
            FROM agendamentos 
            WHERE STR_TO_DATE(SUBSTRING_INDEX(data_hora, ' ', 1), '%d-%m-%Y') 
            BETWEEN STR_TO_DATE(?, '%d-%m-%Y') 
            AND STR_TO_DATE(?, '%d-%m-%Y')
            ORDER BY STR_TO_DATE(data_hora, '%d-%m-%Y %H:%i')";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    while ($row = $result->fetch_assoc()) {
        // Converte a data do formato dd-mm-yyyy HH:mm para yyyy-mm-dd HH:mm:ss
        $dateParts = explode(' ', $row['data_hora']);
        $date = explode('-', $dateParts[0]);
        $formattedDate = $date[2] . '-' . $date[1] . '-' . $date[0] . ' ' . $dateParts[1] . ':00';
        
        // Define a cor baseada no status
        $color = '';
        switch ($row['status']) {
            case 'Aguardando Atendimento':
                $color = '#4e73df'; // Azul
                break;
            case 'Atendimento Iniciado':
                $color = '#f6c23e'; // Laranja
                break;
            case 'Atendimento Finalizado':
                $color = '#1cc88a'; // Verde
                break;
            case 'Agendamento Cancelado':
                $color = '#e74a3b'; // Vermelho
                break;
            default:
                $color = '#858796'; // Cinza
        }
        
        $events[] = [
            'id' => $row['id'],
            'title' => $row['nome'],
            'start' => $formattedDate,
            'color' => $color,
            'details' => [
                'telefone' => $row['telefone'],
                'servico' => $row['servico'],
                'atendente' => $row['atendente'],
                'status' => $row['status']
            ]
        ];
    }
    
    echo json_encode(['success' => true, 'events' => $events]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 