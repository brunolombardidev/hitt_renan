<?php
error_reporting(0); // Desabilita mensagens de erro no output
require_once "db/Database.php";
require_once "src/functions/agendamentos.php"; // Adiciona as funções de agendamento

header('Content-Type: application/json');

try {
    if (!isset($_POST['nome']) || !isset($_POST['telefone']) || !isset($_POST['data']) || !isset($_POST['horario'])) {
        throw new Exception('Todos os campos são obrigatórios');
    }
    
    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Erro ao conectar ao banco de dados');
    }
    
    $nome = trim($_POST["nome"]);
    $telefone = trim($_POST["telefone"]);
    $data = trim($_POST["data"]);
    $horario = trim($_POST["horario"]);
    $servico = trim($_POST["servico"]);
    $atendente = trim($_POST["atendente"]);
    $action = $_POST["action"] ?? '';
    
    // Se for uma edição
    if ($action === 'editAgendamento') {
        $id = $_POST['id'];
        
        // Converter a data de Y-m-d para d-m-Y
        $data_partes = explode('-', $data); // $data está no formato 2024-12-11
        if (count($data_partes) === 3) {
            // Reorganiza as partes da data: ano[0], mês[1], dia[2]
            $ano = $data_partes[0];
            $mes = $data_partes[1];
            $dia = $data_partes[2];
            
            // Monta no formato correto: dd-mm-yyyy
            $data_formatada = $dia . '-' . $mes . '-' . $ano;
            $data_hora = $data_formatada . ' ' . $horario;
            
            if (updateAgendamento($conn, $id, $nome, $telefone, $data_hora, $servico, $atendente)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Agendamento atualizado com sucesso!'
                ]);
                exit;
            } else {
                throw new Exception('Erro ao atualizar o agendamento');
            }
        } else {
            throw new Exception('Formato de data inválido');
        }
    }
    
    // Se for um novo cadastro
    $status = "Aguardando Atendimento";
    
    // Validações básicas
    if (empty($nome) || empty($telefone) || empty($data) || empty($horario)) {
        throw new Exception('Todos os campos são obrigatórios');
    }
    
    // Validar data
    $selectedDate = new DateTime($data);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    if ($selectedDate < $today) {
        throw new Exception('A data selecionada não pode ser no passado');
    }
    
    // Formatar a data e hora no formato dd-mm-aaaa hh:mm
    $data_hora = $selectedDate->format('d-m-Y') . " " . $horario;
    
    // Verificar disponibilidade
    if (!verificaHorarioDisponivel($conn, $data_hora)) {
        throw new Exception('Este horário já está ocupado');
    }
    
    if (!verificaHorarioDisponibilidade($conn, $horario)) {
        throw new Exception('Este horário não está disponível');
    }
    
    // Inserir agendamento
    $sql = "INSERT INTO agendamentos (nome, telefone, data_hora, servico, atendente, status) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar a consulta: ' . $conn->error);
    }
    
    $stmt->bind_param("ssssss", $nome, $telefone, $data_hora, $servico, $atendente, $status);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Agendamento realizado com sucesso!'
        ]);
    } else {
        throw new Exception('Erro ao realizar o agendamento: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 