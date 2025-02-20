<?php
error_reporting(0); // Disable error reporting for production
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db/Database.php';

try {
    $conn = getConnection();
    
    if (!$conn) {
        throw new Exception("Conexão com o banco de dados falhou");
    }

    // Primeiro, vamos verificar a estrutura da tabela
    $tableInfo = $conn->query("SHOW COLUMNS FROM agendamentos");
    $columns = [];
    while($col = $tableInfo->fetch_assoc()) {
        $columns[] = $col['Field'];
    }

    // Agora vamos construir a query baseada nas colunas existentes
    $sql = "SELECT * FROM agendamentos WHERE visualizado = 0 ORDER BY id DESC";
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Erro ao executar consulta: " . $conn->error);
    }

    $newAppointments = array();

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $appointment = array();
            
            // Mapeamento correto das colunas
            $appointment['id'] = $row['id'] ?? null;
            
            // Nome do cliente pode estar em diferentes colunas
            $appointment['cliente'] = $row['nome_cliente'] ?? $row['cliente'] ?? $row['nome'] ?? 'Cliente não especificado';
            
            // Data e hora podem estar em diferentes formatos
            if (isset($row['data_hora'])) {
                $dateTime = new DateTime($row['data_hora']);
                $appointment['data'] = $dateTime->format('d/m/Y');
                $appointment['hora'] = $dateTime->format('H:i');
            } else {
                $appointment['data'] = $row['data'] ?? date('d/m/Y');
                $appointment['hora'] = $row['hora'] ?? date('H:i');
            }
            
            $appointment['servico'] = $row['servico'] ?? $row['nome_servico'] ?? $row['tipo_servico'] ?? 'Serviço não especificado';
            
            $newAppointments[] = $appointment;
        }
    }

    // Adicionar informações de debug em ambiente de desenvolvimento
    if (isset($customizacao['debug']) && $customizacao['debug']) {
        $debug = [
            'columns' => $columns,
            'sample_row' => $result->num_rows > 0 ? $result->fetch_assoc() : null,
            'error' => null
        ];
        echo json_encode(['appointments' => $newAppointments, 'debug' => $debug]);
    } else {
        echo json_encode($newAppointments);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
