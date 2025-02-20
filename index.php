<?php
session_start();
if (!isset($_SESSION['login_user'])) {
    header("Location: login/");
    exit();
}

require_once "db/Database.php";
$conn = getConnection();

require_once "src/functions/usuarios.php";
require_once "src/functions/agendamentos.php";
require_once "src/functions/servicos.php";
require_once "src/functions/atendentes.php";
require_once "src/functions/horarios.php";

// Verificar se o usuário tem permissão para acessar a página
if (!function_exists('userHasPermission')) {
    function userHasPermission($tabName) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Se for super admin, tem acesso a tudo
        if (isset($_SESSION['super_admin']) && $_SESSION['super_admin']) {
            return true;
        }
        
        // Se não for super admin, verifica as permissões específicas
        if (!isset($_SESSION['permissions'])) {
            return false;
        }
        
        return in_array($tabName, $_SESSION['permissions']);
    }
}

// Incluir os arquivos de funções
require_once 'src/functions/agendamentos.php';
require_once 'src/functions/servicos.php';
require_once 'src/functions/horarios.php';
require_once 'src/functions/atendentes.php';
require_once 'src/functions/usuarios.php';
require_once 'src/functions/dashboard.php';

// Define o fuso horário
date_default_timezone_set('America/Sao_Paulo');
$data_hora_atual = date('d/m/Y H:i:s');

// Inicializa a conexão com o banco
$conn = getConnection();

// Função para verificar licença
function verificarLicencaValida($conn) {
    $hoje = date('Y-m-d');
    $stmt = $conn->prepare("SELECT * FROM license_codes WHERE is_active = 1 AND valid_until >= ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $hoje);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Verifica se a licença ainda é válida
if (!verificarLicencaValida($conn)) {
    $_SESSION['purchase_code_required'] = true;
    header("Location: login/purchase_code.php");
    exit;
}

// Obtenção do nome do usuário logado
$username = $_SESSION['username'] ?? 'Usuário';

// Inicializa a variável da data
$data_selecionada = "";

// Verifica se a data foi selecionada
if (isset($_POST["data"])) {
    $data_selecionada = $_POST["data"];
}

// Inicializa a variável do horário
$horario_selecionado = "";

// Verifica se o horário foi selecionado
if (isset($_POST["horario"])) {
    $horario_selecionado = $_POST["horario"];
}

// Adicione no início do arquivo, após as inclusões
$customizacaoFile = 'customizacao.json';
if (!is_writable($customizacaoFile)) {
    chmod($customizacaoFile, 0666);
    if (!is_writable($customizacaoFile)) {
        $_SESSION['msg'] = 'Erro: O arquivo customizacao.json não tem permissão de escrita';
        $_SESSION['msg_type'] = 'error';
    }
}

// Processamento do Formulário de Reserva
if (isset($_POST["agendar"])) {
    $nome = $_POST["nome"];
    $telefone = $_POST["telefone"];
    $servico = $_POST["servico"];
    $atendente = $_POST["atendente"];
    
    // Formatar a data e hora no formato dd-mm-aaaa hh:mm
    $data_hora = date('d-m-Y', strtotime($data_selecionada)) . " " . $horario_selecionado;
    
    if (verificaHorarioDisponivel($conn, $data_hora) && verificaHorarioDisponibilidade($conn, $horario_selecionado)) {
        $sql = "INSERT INTO agendamentos (nome, telefone, data_hora, servico, atendente) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $nome, $telefone, $data_hora, $servico, $atendente);
        if ($stmt->execute()) {
            $_SESSION['msg'] = 'Agendamento realizado com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao realizar o agendamento: ' . $stmt->error;
            $_SESSION['msg_type'] = 'error';
        }
    } else {
        $_SESSION['msg'] = 'Horário já está ocupado ou indisponível.';
        $_SESSION['msg_type'] = 'error';
    }
    $_SESSION['active_tab'] = 'agendamentos';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Carregar estatísticas do dashboard
$total_agendamentos = getTotalAgendamentosAtivos($conn);
$total_finalizados = getTotalFinalizados($conn);
$total_atendentes = getTotalAtendentes($conn);
$total_servicos = getTotalServicos($conn);

// Adicione estas linhas para inicializar os dados de vendas
$vendas_hoje = getVendasHoje($conn);
$vendas_periodo = getVendasPeriodo($conn);

// Calcular tickets médios
$ticket_medio_hoje = calculaTicketMedio(
    $vendas_hoje['valor_total'], 
    $vendas_hoje['total_vendas']
);
$ticket_medio_periodo = calculaTicketMedio(
    $vendas_periodo['valor_total'], 
    $vendas_periodo['total_vendas']
);

// Processamento do Formulário de Cadastro de Agendamento
if (isset($_POST["action"]) && $_POST["action"] == "addAgendamento") {
    $nome = $_POST["nome"];
    $telefone = $_POST["telefone"];
    $data = $_POST["data"];
    $horario = $_POST["horario"];
    $servico = $_POST["servico"];
    $atendente = $_POST["atendente"];

    $data_hora = date('d-m-Y', strtotime($data)) . " " . $horario;

    if (verificaHorarioDisponivel($conn, $data_hora) && verificaHorarioDisponibilidade($conn, $horario)) {
        $sql = "INSERT INTO agendamentos (nome, telefone, data_hora, servico, atendente) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $nome, $telefone, $data_hora, $servico, $atendente);
        if ($stmt->execute()) {
            $_SESSION['msg'] = 'Agendamento cadastrado com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao cadastrar o agendamento: ' . $stmt->error;
            $_SESSION['msg_type'] = 'error';
        }
    } else {
        $_SESSION['msg'] = 'Horário já está ocupado ou indisponível.';
        $_SESSION['msg_type'] = 'error';
    }
    $_SESSION['active_tab'] = 'cadastro';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Processar a ação de arquivar agendamento
if (isset($_POST['action']) && $_POST['action'] == 'archiveAgendamento') {
    $id = intval($_POST['id']);
    
    // Primeiro atualiza o status para "Atendimento Finalizado"
    $sql_status = "UPDATE agendamentos SET status = 'Atendimento Finalizado' WHERE id = ?";
    $stmt_status = $conn->prepare($sql_status);
    $stmt_status->bind_param("i", $id);
    $status_updated = $stmt_status->execute();
    
    // Depois arquiva o agendamento
    if ($status_updated && archiveAgendamento($conn, $id)) {
        $_SESSION['msg'] = 'Agendamento finalizado e arquivado com sucesso!';
        $_SESSION['msg_type'] = 'success';
    } else {
        $_SESSION['msg'] = 'Erro ao arquivar o agendamento.';
        $_SESSION['msg_type'] = 'error';
    }
    $_SESSION['active_tab'] = 'agendamentos';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Processar a ação de exclusão de agendamento
if (isset($_POST['action']) && $_POST['action'] == 'deleteAgendamento') {
    $id = intval($_POST['id']);
    if (deleteAgendamento($conn, $id)) {
        $_SESSION['msg'] = 'Agendamento excluído com sucesso!';
        $_SESSION['msg_type'] = 'success';
    } else {
        $_SESSION['msg'] = 'Erro ao excluir o agendamento.';
        $_SESSION['msg_type'] = 'error';
    }
    $_SESSION['active_tab'] = 'agendamentos';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Processar a ação de desarquivamento de agendamento
if (isset($_POST['action']) && $_POST['action'] == 'unarchiveAgendamento') {
    $id = intval($_POST['id']);
    
    // Primeiro atualiza o status para "Aguardando Atendimento"
    $sql_status = "UPDATE agendamentos SET status = 'Aguardando Atendimento' WHERE id = ?";
    $stmt_status = $conn->prepare($sql_status);
    $stmt_status->bind_param("i", $id);
    $status_updated = $stmt_status->execute();
    
    // Depois desarquiva o agendamento
    $sql = "UPDATE agendamentos SET arquivado = FALSE WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    if ($status_updated && $stmt->execute()) {
        $_SESSION['msg'] = 'Agendamento desarquivado com sucesso!';
        $_SESSION['msg_type'] = 'success';
    } else {
        $_SESSION['msg'] = 'Erro ao desarquivar o agendamento.';
        $_SESSION['msg_type'] = 'error';
    }
    $_SESSION['active_tab'] = 'agendamentos';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Gerenciar mudanças de status (finalizado)
if (isset($_POST["action"]) && $_POST["action"] == "setFinalizado") {
    $id = intval($_POST['id']);
    if (updateAgendamento($conn, $id, $nome, $telefone, $data_hora, $servico, $atendente)) {
        archiveAgendamento($conn, $id);  // Arquivar ao finalizar
        $_SESSION['msg'] = 'Agendamento finalizado e arquivado com sucesso!';
        $_SESSION['msg_type'] = 'success';
    } else {
        $_SESSION['msg'] = 'Erro ao finalizar e arquivar o agendamento.';
        $_SESSION['msg_type'] = 'error';
    }
    $_SESSION['active_tab'] = 'agendamentos';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Processar as ações do formulário
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action == 'addServico') {
        $nome = $_POST['nome'];
        $preco = floatval($_POST['preco']);
        $duracao = intval($_POST['duracao']);
        $disponivel = isset($_POST['disponivel']) ? 1 : 0;
        
        if (addServico($conn, $nome, $preco, $duracao, $disponivel)) {
            $_SESSION['msg'] = 'Serviço adicionado com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao adicionar serviço.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'servicos';
    } elseif ($action == 'editAgendamento') {
        $id = $_POST['id'];
        $nome = $_POST['nome'];
        $telefone = $_POST['telefone'];
        $data = $_POST['data'];
        $horario = $_POST['horario'];
        $data_hora = $data . ' ' . $horario;
        $servico = $_POST['servico'];
        $atendente = $_POST['atendente'];

        if (updateAgendamento($conn, $id, $nome, $telefone, $data_hora, $servico, $atendente)) {
            $_SESSION['msg'] = 'Agendamento atualizado com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao atualizar agendamento.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'agendamentos';
    } elseif ($action == 'updateServico') {
        $id = $_POST['id'];
        $nome = $_POST['nome'];
        $preco = floatval($_POST['preco']);
        $duracao = intval($_POST['duracao']);
        $disponivel = isset($_POST['disponivel']) ? 1 : 0;
        
        if (updateServico($conn, $id, $nome, $preco, $duracao, $disponivel)) {
            $_SESSION['msg'] = 'Serviço atualizado com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao atualizar serviço.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'servicos';
    } elseif ($action == 'deleteServico') {
        $id = $_POST['id'];
        if (deleteServico($conn, $id)) {
            $_SESSION['msg'] = 'Serviço excluído com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao excluir serviço.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'servicos';
    } elseif ($action == 'addHorario') {
        $horario = $_POST['horario'];
        if (addHorario($conn, $horario)) {
            $_SESSION['msg'] = 'Horário adicionado com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao adicionar horário.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'horarios';
    } elseif ($action == 'updateHorario') {
        $id = $_POST['id'];
        $horario = $_POST['horario'];
        if (updateHorario($conn, $id, $horario)) {
            $_SESSION['msg'] = 'Horário atualizado com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao atualizar horário.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'horarios';
    } elseif ($action == 'deleteHorario') {
        $id = $_POST['id'];
        if (deleteHorario($conn, $id)) {
            $_SESSION['msg'] = 'Horário excluído com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao excluir horário.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'horarios';
    } elseif ($action == 'setDisponivel') {
        $id = $_POST['id'];
        $disponivel = $_POST['disponivel'];
        if (setHorarioDisponibilidade($conn, $id, $disponivel)) {
            $_SESSION['msg'] = 'Disponibilidade do horário alterada com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao alterar disponibilidade do horário.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'horarios';
    } elseif ($action == 'addAtendente') {
        $nome = $_POST['nome'];
        $cargo = $_POST['cargo'];
        $disponivel = isset($_POST['disponivel']) ? 1 : 0;
        if (addAtendente($conn, $nome, $cargo, $disponivel)) {
            $_SESSION['msg'] = 'Atendente adicionado com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao adicionar atendente.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'atendentes';
    } elseif ($action == 'updateAtendente') {
        $id = $_POST['id'];
        $nome = $_POST['nome'];
        $cargo = $_POST['cargo'];
        $disponivel = isset($_POST['disponivel']) ? 1 : 0;
        if (updateAtendente($conn, $id, $nome, $cargo, $disponivel)) {
            $_SESSION['msg'] = 'Atendente atualizado com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao atualizar atendente.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'atendentes';
    } elseif ($action == 'deleteAtendente') {
        $id = $_POST['id'];
        if (deleteAtendente($conn, $id)) {
            $_SESSION['msg'] = 'Atendente excluído com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao excluir atendente.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'atendentes';
    } elseif ($action == 'setAtendenteDisponibilidade') {
        $id = $_POST['id'];
        $disponivel = $_POST['disponivel'];
        if (setAtendenteDisponibilidade($conn, $id, $disponivel)) {
            $_SESSION['msg'] = 'Disponibilidade do atendente alterada com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao alterar disponibilidade do atendente.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'atendentes';
    } elseif ($action == 'addUser') {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $permissions = $_POST['permissions'];
        $super_admin = isset($_POST['super_admin']) ? 1 : 0;
        if (addUser($conn, $username, $email, $password, $permissions, $super_admin)) {
            $_SESSION['msg'] = 'Usuário adicionado com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao adicionar usuário.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'usuarios';
    } elseif ($action == 'updateUser') {
        $id = $_POST['id'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $permissions = $_POST['permissions'];
        $super_admin = isset($_POST['super_admin']) ? 1 : 0;
        if (updateUser($conn, $id, $username, $email, $password, $permissions, $super_admin)) {
            $_SESSION['msg'] = 'Usuário atualizado com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao atualizar usuário.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'usuarios';
    } elseif ($action == 'deleteUser') {
        $id = $_POST['id'];
        if (deleteUser($conn, $id)) {
            $_SESSION['msg'] = 'Usuário excluído com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao excluir usuário.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'usuarios';
    } elseif ($action == 'addAtendenteServico') {
        $atendente_id = $_POST['atendente_id'];
        $servico_id = $_POST['servico_id'];
        if (addAtendenteServico($conn, $atendente_id, $servico_id)) {
            $_SESSION['msg'] = 'Serviço atribuído ao atendente com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao atribuir serviço ao atendente.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'servicos';
    } elseif ($action == 'updateAtendenteServico') {
        $id = $_POST['id'];
        $atendente_id = $_POST['atendente_id'];
        $servico_id = $_POST['servico_id'];
        if (updateAtendenteServico($conn, $id, $atendente_id, $servico_id)) {
            $_SESSION['msg'] = 'Serviço atribuído ao atendente atualizado com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao atualizar serviço atribuído ao atendente.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'servicos';
    } elseif ($action == 'deleteAtendenteServico') {
        $id = $_POST['id'];
        if (deleteAtendenteServico($conn, $id)) {
            $_SESSION['msg'] = 'Serviço atribuído ao atendente excluído com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao excluir serviço atribuído ao atendente.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'servicos';
    } elseif ($action == 'addHorarioAtendente') {
        $atendente_id = $_POST['atendente_id'];
        $horario_id = $_POST['horario_id'];
        if (addHorarioAtendente($conn, $atendente_id, $horario_id)) {
            $_SESSION['msg'] = 'Horário atribuído com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao atribuir horário.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'horarios';
    } elseif ($action == 'deleteHorarioAtendente') {
        $id = $_POST['id'];
        if (deleteHorarioAtendente($conn, $id)) {
            $_SESSION['msg'] = 'Horário removido com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao remover horário.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'horarios';
    } elseif ($action == 'updateEvolutionApi') {
        // Atualizar configurações da Evolution API
        $base_url = $_POST['evolution_base_url'];
        $instance = $_POST['evolution_instance'];
        $api_key = $_POST['evolution_api_key'];

        $configs = [
            'evolution_base_url' => $base_url,
            'evolution_instance' => $instance,
            'evolution_api_key' => $api_key
        ];

        $success = true;
        foreach ($configs as $chave => $valor) {
            $stmt = $conn->prepare("UPDATE configuracoes SET valor = ? WHERE chave = ?");
            $stmt->bind_param("ss", $valor, $chave);
            if (!$stmt->execute()) {
                $success = false;
                break;
            }
        }

        if ($success) {
            $_SESSION['msg'] = 'Configurações da Evolution API atualizadas com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao atualizar as configurações da Evolution API';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'configuracoes';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    elseif ($action == 'addMensagem') {
        $nome_mensagem = $_POST['nome_mensagem'];
        $template = $_POST['template'];
        
        $sql = "INSERT INTO mensagens_padronizadas (nome_mensagem, template) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $nome_mensagem, $template);
        
        if ($stmt->execute()) {
            $_SESSION['msg'] = 'Mensagem padronizada adicionada com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao adicionar mensagem padronizada!';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'configuracoes';
        $_SESSION['active_section'] = 'mensagens';
        header("Location: " . $_SERVER['PHP_SELF'] . "#mensagens-section");
        exit;
    }
    elseif ($action == 'updateMensagem') {
        $id = $_POST['id'];
        $nome_mensagem = $_POST['nome_mensagem'];
        $template = $_POST['template'];
        
        $sql = "UPDATE mensagens_padronizadas SET nome_mensagem = ?, template = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $nome_mensagem, $template, $id);
        
        if ($stmt->execute()) {
            $_SESSION['msg'] = 'Mensagem padronizada atualizada com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao atualizar mensagem padronizada!';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'configuracoes';
        $_SESSION['active_section'] = 'mensagens';
        header("Location: " . $_SERVER['PHP_SELF'] . "#mensagens-section");
        exit;
    }
    elseif ($action == 'deleteMensagem') {
        $id = $_POST['id'];
        
        $sql = "DELETE FROM mensagens_padronizadas WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['msg'] = 'Mensagem padronizada excluída com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao excluir mensagem padronizada!';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'configuracoes';
        $_SESSION['active_section'] = 'mensagens';
        header("Location: " . $_SERVER['PHP_SELF'] . "#mensagens-section");
        exit;
    }
    elseif ($action == 'customizacao') {
        // Get customization values from POST
        $customizacao = [
            'logo_url' => $_POST['logo_url'],
            'dashboard_logo_url' => $_POST['dashboard_logo_url'],
            'dashboard_logo_height' => $_POST['dashboard_logo_height'],
            'dashboard_info_text' => $_POST['dashboard_info_text'],
            'navbar_color' => $_POST['navbar_color'],
            'primary_color' => $_POST['primary_color'],
            'footer_text' => $_POST['footer_text']
        ];
        
        // Save to customizacao.json
        if (file_put_contents('customizacao.json', json_encode($customizacao, JSON_PRETTY_PRINT))) {
            $_SESSION['msg'] = 'Customizações salvas com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao salvar customizações.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'customizacao';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    elseif ($action == 'setServicoDisponibilidade') {
        $id = $_POST['id'];
        $disponivel = $_POST['disponivel'];
        if (setServicoDisponibilidade($conn, $id, $disponivel)) {
            $_SESSION['msg'] = 'Disponibilidade do serviço atualizada com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao atualizar disponibilidade do serviço.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'servicos';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    elseif ($action == 'setHorarioDisponibilidade') {
        $id = intval($_POST['id']);
        $disponivel = intval($_POST['disponivel']);
        
        if (setHorarioDisponibilidade($conn, $id, $disponivel)) {
            $_SESSION['msg'] = 'Disponibilidade do horário atualizada com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao atualizar disponibilidade do horário.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'horarios';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    elseif ($action == 'setAtendenteDisponibilidade') {
        $id = intval($_POST['id']);
        $disponivel = intval($_POST['disponivel']);
        
        if (setAtendenteDisponibilidade($conn, $id, $disponivel)) {
            $_SESSION['msg'] = 'Disponibilidade do atendente atualizada com sucesso!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['msg'] = 'Erro ao atualizar disponibilidade do atendente.';
            $_SESSION['msg_type'] = 'error';
        }
        $_SESSION['active_tab'] = 'atendentes';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    elseif ($action == 'updateCustomization') {
        try {
            // Debug temporário
            error_log('Valores dos gradientes:');
            error_log('Hoje Start: ' . ($_POST['card_atendimentos_hoje_gradient_start'] ?? 'não definido'));
            error_log('Hoje End: ' . ($_POST['card_atendimentos_hoje_gradient_end'] ?? 'não definido'));
            error_log('Período Start: ' . ($_POST['card_atendimentos_periodo_gradient_start'] ?? 'não definido'));
            error_log('Período End: ' . ($_POST['card_atendimentos_periodo_gradient_end'] ?? 'não definido'));

            $customizacao = [
                'logo_url' => $_POST['logo_url'],
                'login_logo_url' => $_POST['login_logo_url'],
                'dashboard_logo_url' => $_POST['dashboard_logo_url'],
                'dashboard_logo_height' => $_POST['dashboard_logo_height'],
                'dashboard_info_text' => $_POST['dashboard_info_text'],
                'navbar_color' => $_POST['navbar_color'],
                'primary_color' => $_POST['primary_color'] ?? '#4789eb',
                'primary_hover_color' => $_POST['primary_hover_color'] ?? '#3672c9',
                'footer_text' => $_POST['footer_text'],
                
                // Novas configurações
                'table_striped' => isset($_POST['table_striped']),
                'table_hover' => isset($_POST['table_hover']),
                'table_responsive' => isset($_POST['table_responsive']),
                
                'button_style' => $_POST['button_style'],
                'button_radius' => $_POST['button_radius'],
                
                'font_family' => $_POST['font_family'],
                'font_size' => $_POST['font_size'],
                'font_weight' => $_POST['font_weight'],
                
                'background_color' => $_POST['background_color'],
                
                // Gradientes dos Cards
                'card_agendamentos_gradient_start' => $_POST['card_agendamentos_gradient_start'] ?? '#4e54c8',
                'card_agendamentos_gradient_end' => $_POST['card_agendamentos_gradient_end'] ?? '#8f94fb',
                'card_finalizados_gradient_start' => $_POST['card_finalizados_gradient_start'] ?? '#11998e',
                'card_finalizados_gradient_end' => $_POST['card_finalizados_gradient_end'] ?? '#38ef7d',
                'card_atendentes_gradient_start' => $_POST['card_atendentes_gradient_start'] ?? '#ff758c',
                'card_atendentes_gradient_end' => $_POST['card_atendentes_gradient_end'] ?? '#ff7eb3',
                'card_servicos_gradient_start' => $_POST['card_servicos_gradient_start'] ?? '#fc466b',
                'card_servicos_gradient_end' => $_POST['card_servicos_gradient_end'] ?? '#3f5efb',
                'card_atendimentos_hoje_gradient_start' => $_POST['card_atendimentos_hoje_gradient_start'] ?? '#00c6fb',
                'card_atendimentos_hoje_gradient_end' => $_POST['card_atendimentos_hoje_gradient_end'] ?? '#005bea',
                'card_atendimentos_periodo_gradient_start' => $_POST['card_atendimentos_periodo_gradient_start'] ?? '#f5576c',
                'card_atendimentos_periodo_gradient_end' => $_POST['card_atendimentos_periodo_gradient_end'] ?? '#f093fb',
            ];

            // Salvar no arquivo customizacao.json
            if (file_put_contents('customizacao.json', json_encode($customizacao, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))) {
                // Limpar cache do navegador forçando reload do CSS
                header("Clear-Site-Data: \"cache\"");
                
                $_SESSION['msg'] = 'Customizações salvas com sucesso!';
                $_SESSION['msg_type'] = 'success';
            } else {
                throw new Exception('Erro ao salvar o arquivo de customização');
            }

        } catch (Exception $e) {
            $_SESSION['msg'] = 'Erro ao salvar customizações: ' . $e->getMessage();
            $_SESSION['msg_type'] = 'error';
        }

        $_SESSION['active_tab'] = 'customizacao';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    $_SESSION['active_tab'] = $_POST['active_tab'];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Processamento da ação de arquivamento
if (isset($_POST['action']) && $_POST['action'] == 'archiveAgendamento') {
    $id = intval($_POST['id']);
    if (archiveAgendamento($conn, $id)) {
        $_SESSION['msg'] = 'Agendamento arquivado com sucesso!';
        $_SESSION['msg_type'] = 'success';
    } else {
        $_SESSION['msg'] = 'Erro ao arquivar o agendamento.';
        $_SESSION['msg_type'] = 'error';
    }
    $_SESSION['active_tab'] = 'agendamentos';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_POST['action']) && $_POST['action'] == 'deleteAgendamento') {
    $id = intval($_POST['id']);
    if (deleteAgendamento($conn, $id)) {
        $_SESSION['msg'] = 'Agendamento excluído com sucesso!';
        $_SESSION['msg_type'] = 'success';
    } else {
        $_SESSION['msg'] = 'Erro ao excluir o agendamento.';
        $_SESSION['msg_type'] = 'error';
    }
    $_SESSION['active_tab'] = 'agendamentos';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Carregar customizações
$customizacao_json = file_get_contents('customizacao.json');
if ($customizacao_json === false) {
    // Se não conseguir ler o arquivo, inicializa com valores padrão
    $customizacao = [
        'logo_url' => 'https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/agendapro.png',
        'login_logo_url' => 'https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/login-agendapro.png',
        'dashboard_logo_url' => 'https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/logo-dashboard-agendapro.png',
        'dashboard_logo_height' => '120px',
        'dashboard_info_text' => '<div>\r\n<h1>Bem-vindo ao Agenda AtrativoZap</h1>\r\n<p>Seja bem-vindo ao seu painel de controle!</p>\r\n</div>',
        'navbar_color' => '#0d6efd',
        'primary_color' => '#4789eb',
        'footer_text' => 'Copyright © 2025 Agenda AtrativoZap'
    ];
} else {
    $customizacao = json_decode($customizacao_json, true);
    if ($customizacao === null) {
        // Se o JSON for inválido, usa valores padrão
        $customizacao = [
            'logo_url' => 'https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/agendapro.png',
            'login_logo_url' => 'https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/login-agendapro.png',
            'dashboard_logo_url' => 'https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/logo-dashboard-agendapro.png',
            'dashboard_logo_height' => '120px',
            'dashboard_info_text' => '<div>\r\n<h1>Bem-vindo ao Agenda AtrativoZap</h1>\r\n<p>Seja bem-vindo ao seu painel de controle!</p>\r\n</div>',
            'navbar_color' => '#0d6efd',
            'primary_color' => '#4789eb',
            'footer_text' => 'Copyright © 2025 Agenda AtrativoZap️'
        ];
    }
}

// Adicione estas funções junto com as outras funções existentes

function addHorarioAtendente($conn, $atendente_id, $horario_id) {
    $sql = "INSERT INTO horarios_atendentes (atendente_id, horario_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $atendente_id, $horario_id);
    return $stmt->execute();
}

function deleteHorarioAtendente($conn, $id) {
    $sql = "DELETE FROM horarios_atendentes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

// Adicionar no início do arquivo, junto com os outros processamentos de formulário
if (isset($_POST['action']) && $_POST['action'] == 'setServicoDisponibilidade') {
    $id = intval($_POST['id']);
    $disponivel = intval($_POST['disponivel']);
    
    if (setServicoDisponibilidade($conn, $id, $disponivel)) {
        $_SESSION['msg'] = 'Disponibilidade do serviço atualizada com sucesso!';
        $_SESSION['msg_type'] = 'success';
    } else {
        $_SESSION['msg'] = 'Erro ao atualizar disponibilidade do serviço.';
        $_SESSION['msg_type'] = 'error';
    }
    $_SESSION['active_tab'] = 'servicos';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Processar a ação de reativar agendamento cancelado
if (isset($_POST['action']) && $_POST['action'] == 'unarchiveAgendamento') {
    $id = intval($_POST['id']);
    
    // Atualiza o status para "Aguardando Atendimento"
    $sql = "UPDATE agendamentos SET status = 'Aguardando Atendimento' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = 'Agendamento reativado com sucesso!';
        $_SESSION['msg_type'] = 'success';
    } else {
        $_SESSION['msg'] = 'Erro ao reativar o agendamento.';
        $_SESSION['msg_type'] = 'error';
    }
    $_SESSION['active_tab'] = 'agendamentos';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda AtrativoZap</title>
    <meta name="robots" content="noindex, nofollow">
    <!-- Use Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts (Exemplo - você pode escolher outras fontes) -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- CSS -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/tooltip.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <link rel="stylesheet" href="css/calendar.css">
    <script src="assets/js/calendar.js"></script>

    <!-- No head, após os outros CSS -->
    <link rel="stylesheet" href="css/mobile-menu.css">

    <style>
    /* Sua paleta de cores personalizada */
    :root {
        --primary-color: <?php echo isset($customizacao['primary_color']) ? $customizacao['primary_color'] : '#0042DA'; ?>;
    }

    /* Barra de navegação */
    .sidebar {
        width: 250px;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        background-color: <?php echo isset($customizacao['navbar_color']) ? $customizacao['navbar_color'] : '#007BFF'; ?>;
        padding: 15px;
        color: #fff;
        transition: width 0.3s;
        display: flex;
        flex-direction: column;
        overflow-y: auto; /* Habilitar a barra de rolagem vertical */
    }

    .collapse-btn {
        background-color: <?php echo isset($customizacao['primary_color']) ? $customizacao['primary_color'] : '#0042DA'; ?>;
        border: none;
        color: #fff;
        display: block;
        width: 100%;
        text-align: center;
        padding: 10px;
        cursor: pointer;
        transition: background-color 0.3s;
        display: flex;
        justify-content: center;
        align-items: center;
    }
</style>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Roboto:wght@300;400;500;600&family=Open+Sans:wght@300;400;500;600&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dynamic-styles.php"> <!-- Adicione esta linha -->
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($customizacao['font_family']); ?>:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
<div id="blur-overlay">
    <!-- Todo o conteúdo existente aqui -->
    <div class="sidebar">
    <img src="<?php echo isset($customizacao['logo_url']) ? $customizacao['logo_url'] : 'https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/agendapro.png'; ?>" alt="Logo" class="logo">
        <button class="collapse-btn">
            <span class="collapse-text">Menu</span>
            <i class="fas fa-bars collapse-icon"></i>
        </button>
        <ul class="nav flex-column">
        <?php
        if (!function_exists('renderMenuItem')) {
            function renderMenuItem($tabName, $icon, $label) {
                if (userHasPermission($tabName)) {
                    echo '<li class="nav-item">
                            <a class="nav-link" id="'.$tabName.'-tab" data-toggle="tab" href="#'.$tabName.'">
                                <i class="'.$icon.'"></i> <span class="link-text">'.$label.'</span>
                            </a>
                          </li>';
                }
            }
        }
        ?>
        <li class="nav-item">
            <a class="nav-link" id="inicio-tab" data-toggle="tab" href="#inicio">
                <i class="fas fa-tachometer-alt"></i> <span class="link-text">Dashboard</span>
            </a>
        </li>
        <?php
        renderMenuItem('kanban', 'fas fa-table', 'Atendimentos');
        renderMenuItem('agendamentos', 'fas fa-calendar-alt', 'Agendamentos');
        renderMenuItem('cadastro', 'fas fa-user-plus', 'Cadastro');
        renderMenuItem('servicos', 'fas fa-list', 'Serviços');
        renderMenuItem('horarios', 'fas fa-clock', 'Horários');
        renderMenuItem('atendentes', 'fas fa-user', 'Atendentes');
        renderMenuItem('usuarios', 'fas fa-users', 'Usuários');
        renderMenuItem('relatorios', 'fas fa-chart-bar', 'Relatórios');
        renderMenuItem('customizacao', 'fas fa-palette', 'Customização');
        renderMenuItem('configuracoes', 'fas fa-cogs', 'Configurações');
        ?>
        </ul>

        <div class="user-info">
            <a class="nav-link" href="login/logout.php">
                <i class="fas fa-sign-out-alt"></i> <span class="link-text">Sair</span>
            </a>
        </div>
    </div>
    <div class="content">
    <div class="container">
        <div class="tab-content mt-4">
            <!-- Dashboard (Início) -->
            <div id="inicio" class="tab-pane fade <?php echo (isset($_SESSION['active_tab']) && $_SESSION['active_tab'] == 'inicio') ? 'show active' : ''; ?>">
                <div class="text-center mb-4">
                    <!-- Substituir a tag img da logo -->
                    <div class="logo-container">
                        <img src="<?php echo isset($customizacao['dashboard_logo_url']) ? $customizacao['dashboard_logo_url'] : 'https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/logo-dashboard-agendapro.png'; ?>" 
                             alt="Logo" 
                             class="logo img-fluid"
                             style="max-height: <?php echo isset($customizacao['dashboard_logo_height']) ? $customizacao['dashboard_logo_height'] : '120px'; ?>;">
                    </div>
                    <p class="mt-3 lead"><?php echo isset($customizacao['dashboard_info_text']) ? $customizacao['dashboard_info_text'] : 'Bem-vindo ao seu painel de controle Agenda AtrativoZap'; ?></p>
                </div>

                <div class="dashboard-stats">
                    <!-- Card Agendamentos -->
                    <div class="col-md-3 mb-4">
                        <div class="dashboard-card agendamentos-card">
                            <div class="card-body">
                                <h5 class="card-title">Agendamentos</h5>
                                <h2 class="card-number"><?php echo $total_agendamentos; ?></h2>
                                <p class="card-text">Total de agendamentos ativos</p>
                            </div>
                        </div>
                    </div>

                    <!-- Card Finalizados -->
                    <div class="col-md-3 mb-4">
                        <div class="dashboard-card finalizados-card">
                            <div class="card-body">
                                <h5 class="card-title">Finalizados</h5>
                                <h2 class="card-number"><?php echo $total_finalizados; ?></h2>
                                <p class="card-text">Agendamentos concluídos</p>
                            </div>
                        </div>
                    </div>

                    <!-- Card Atendentes -->
                    <div class="col-md-3 mb-4">
                        <div class="dashboard-card atendentes-card">
                            <div class="card-body">
                                <h5 class="card-title">Atendentes</h5>
                                <h2 class="card-number"><?php echo $total_atendentes; ?></h2>
                                <p class="card-text">Atendentes disponíveis</p>
                            </div>
                        </div>
                    </div>

                    <!-- Card Serviços -->
                    <div class="col-md-3 mb-4">
                        <div class="dashboard-card servicos-card">
                            <div class="card-body">
                                <h5 class="card-title">Serviços</h5>
                                <h2 class="card-number"><?php echo $total_servicos; ?></h2>
                                <p class="card-text">Serviços disponíveis</p>
                            </div>
                        </div>
                    </div>

                    <!-- Card de Atendimentos Hoje -->
                    <div class="col-md-3">
                        <div class="dashboard-card atendimentos-hoje-card">
                            <div class="card-body">
                                <h5 class="card-title">Atendimentos Hoje</h5>
                                <?php 
                                $vendas_hoje = getVendasHoje($conn);
                                $ticket_medio_hoje = calculaTicketMedio(
                                    $vendas_hoje['valor_total'], 
                                    $vendas_hoje['total_vendas']
                                );
                                ?>
                                <h2 class="card-number">R$ <?php echo number_format($vendas_hoje['valor_total'] ?? 0, 2, ',', '.'); ?></h2>
                                <p class="card-text">
                                    Ticket Médio R$ <?php echo number_format($ticket_medio_hoje ?? 0, 2, ',', '.'); ?> 
                                    - Ref: <?php echo $vendas_hoje['total_vendas'] ?? 0; ?> Agendamentos
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Card de Atendimentos Período -->
                    <div class="col-md-3">
                        <div class="dashboard-card atendimentos-periodo-card">
                            <div class="card-body">
                                <h5 class="card-title">Atendimentos (Período)</h5>
                                <?php 
                                $vendas_periodo = getVendasPeriodo($conn);
                                $ticket_medio_periodo = calculaTicketMedio(
                                    $vendas_periodo['valor_total'], 
                                    $vendas_periodo['total_vendas']
                                );
                                ?>
                                <h2 class="card-number">R$ <?php echo number_format($vendas_periodo['valor_total'] ?? 0, 2, ',', '.'); ?></h2>
                                <p class="card-text">
                                    Ticket Médio R$ <?php echo number_format($ticket_medio_periodo ?? 0, 2, ',', '.'); ?> 
                                    - Ref: <?php echo $vendas_periodo['total_vendas'] ?? 0; ?> Agendamentos
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Dentro da div id="inicio" após os cards do dashboard -->
                <div id="calendar"></div>
            </div>
            <?php if (userHasPermission('kanban')): ?>
            <div id="kanban" class="tab-pane fade <?php echo (isset($_SESSION['active_tab']) && $_SESSION['active_tab'] == 'kanban') ? 'show active' : ''; ?>">
            <h2 class="text-center">Gestão de Atendimento - <small id="relogio"><?php echo $data_hora_atual; ?></small></h2>
                <div class="kanban-board">
                    <div class="kanban-column" id="column-aguardando">
                        <h4><i class="fas fa-hourglass-half"></i> Aguardando Atendimento</h4>
                        <ul id="aguardando-list" class="kanban-list">
                            <!-- Lista de agendamentos aguardando atendimento -->
                        </ul>
                    </div>
                    <div class="kanban-column" id="column-iniciado">
                        <h4><i class="fas fa-play-circle"></i> Atendimento Iniciado</h4>
                        <ul id="iniciado-list" class="kanban-list">
                            <!-- Lista de agendamentos com atendimento iniciado -->
                        </ul>
                    </div>
                    <div class="kanban-column" id="column-finalizado">
                        <h4><i class="fas fa-check-circle"></i> Atendimento Finalizado</h4>
                        <ul id="finalizado-list" class="kanban-list">
                            <!-- Lista de agendamentos finalizados -->
                        </ul>
                    </div>
                    <div class="kanban-column" id="column-cancelado">
                        <h4><i class="fas fa-times-circle"></i> Agendamento Cancelado</h4>
                        <ul id="cancelado-list" class="kanban-list">
                            <!-- Lista de agendamentos cancelados -->
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (userHasPermission('relatorios')): ?>
            <div id="relatorios" class="tab-pane fade <?php echo (isset($_SESSION['active_tab']) && $_SESSION['active_tab'] == 'relatorios') ? 'show active' : ''; ?>">
                <h2>Relatórios</h2>
                
                <!-- Filtros -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <select id="periodo-filtro" class="form-select">
                            <option value="7">Últimos 7 dias</option>
                            <option value="30" selected>Últimos 30 dias</option>
                            <option value="90">Últimos 90 dias</option>
                        </select>
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-success" id="exportar-csv">
                            <i class="fas fa-file-csv"></i> Exportar CSV
                        </button>
                    </div>
                </div>

                <!-- Grid de Relatórios -->
                <div class="row">
                    <!-- Profissionais Mais Requisitados -->
                    <div class="col-md-6 mb-4">
                        <div class="small">
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="grafico-profissionais"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Serviços Mais Requisitados -->
                    <div class="col-md-6 mb-4">
                        <div class="small">
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="grafico-servicos"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Serviços por Profissional -->
                    <div class="col-md-6 mb-4">
                        <div class="small">
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="grafico-servicos-profissional"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <div class="small">    
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="grafico-faturamento"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
            </div>
            <?php endif; ?>
            <div id="agendamentos" class="tab-pane fade <?php echo (isset($_SESSION['active_tab']) && $_SESSION['active_tab'] == 'agendamentos') ? 'show active' : ''; ?>">
    <?php
    // Parâmetros de paginação
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $archived_page = isset($_GET['archived_page']) ? (int)$_GET['archived_page'] : 1;
    $per_page = 5;

    // Buscar total de registros
    $total_agendamentos = getTotalAgendamentos($conn, false);
    $total_archived = getTotalAgendamentos($conn, true);

    // Calcular total de páginas
    $total_pages = ceil($total_agendamentos / $per_page);
    $total_archived_pages = ceil($total_archived / $per_page);

    // Buscar agendamentos com paginação
    $agendamentos = getAgendamentos($conn, $page, $per_page);
    $archivedAgendamentos = getArchivedAgendamentos($conn, $archived_page, $per_page);
    ?>

    
    <div class="filter-bar mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="input-group">
                    <input type="text" id="search-agendamento" class="form-control" placeholder="Buscar por nome, telefone ou serviço...">
                    <select id="filter-atendente" class="form-select">
                        <option value="">Todos os Atendentes</option>
                        <?php
                        $atendentes = getAtendentes($conn);
                        while ($atendente = $atendentes->fetch_assoc()) {
                            echo "<option value='" . htmlspecialchars($atendente['nome']) . "'>" . htmlspecialchars($atendente['nome']) . "</option>";
                        }
                        ?>
                    </select>
                    <select id="filter-status" class="form-select">
                        <option value="">Todos os Status</option>
                        <option value="Aguardando Atendimento">Aguardando Atendimento</option>
                        <option value="Atendimento Iniciado">Atendimento Iniciado</option>
                        <option value="Atendimento Finalizado">Atendimento Finalizado</option>
                        <option value="Agendamento Cancelado">Agendamento Cancelado</option>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="input-group">
                    <input type="date" id="filter-data" class="form-control">
                    <button class="btn btn-primary" id="clear-filters">
                        <i class="fas fa-sync-alt"></i> Limpar Filtros
                    </button>
                </div>
            </div>
        </div>
    </div>

    <h2>Agendamentos Ativos</h2>
    <div class="table-scroll-container">
        <div class="table-responsive">
            <table class="table">
                <thead class="table-fixed-header">
                    <tr>
                        <th>Nome</th>
                        <th>Telefone</th>
                        <th>Data/Hora</th>
                        <th>Serviço</th>
                        <th>Atendente</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = getAgendamentos($conn, $page, $per_page);
                    if ($result->num_rows > 0) {
                        while ($agendamento = $result->fetch_assoc()): ?>
                            <tr data-status="<?php echo htmlspecialchars($agendamento['status']); ?>">
                                <td><?php echo htmlspecialchars($agendamento['nome']); ?></td>
                                <td><?php echo htmlspecialchars($agendamento['telefone']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($agendamento['data_hora'])); ?></td>
                                <td><?php echo htmlspecialchars($agendamento['servico']); ?></td>
                                <td><?php echo htmlspecialchars($agendamento['atendente']); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <div class="tooltip-container">
                                            <button type="button" class="btn btn-primary edit-btn" 
                                                data-id="<?php echo $agendamento['id']; ?>"
                                                data-nome="<?php echo htmlspecialchars($agendamento['nome']); ?>"
                                                data-telefone="<?php echo htmlspecialchars($agendamento['telefone']); ?>"
                                                data-data="<?php echo date('Y-m-d', strtotime($agendamento['data_hora'])); ?>"
                                                data-horario="<?php echo date('H:i', strtotime($agendamento['data_hora'])); ?>"
                                                data-servico="<?php echo htmlspecialchars($agendamento['servico']); ?>"
                                                data-atendente="<?php echo htmlspecialchars($agendamento['atendente']); ?>"
                                                data-bs-toggle="tooltip" 
                                                data-bs-placement="top" 
                                                >
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <span class="tooltip-text">Editar agendamento</span>
                                        </div>
                                        
                                        <form method="post" class="d-inline delete-form">
                                            <input type="hidden" name="id" value="<?php echo $agendamento['id']; ?>">
                                            <input type="hidden" name="action" value="deleteAgendamento">
                                            <input type="hidden" name="active_tab" value="agendamentos">
                                            <div class="tooltip-container">
                                                <button type="button" class="btn btn-danger delete-btn"
                                                    data-bs-toggle="tooltip" 
                                                    data-bs-placement="top" 
                                                    >
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                                <span class="tooltip-text">Excluir agendamento</span>
                                            </div>
                                        </form>

                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="id" value="<?php echo $agendamento['id']; ?>">
                                            <input type="hidden" name="action" value="archiveAgendamento">
                                            <input type="hidden" name="active_tab" value="agendamentos">
                                            <div class="tooltip-container">
                                                <button type="submit" class="btn btn-success"
                                                    data-bs-toggle="tooltip" 
                                                    data-bs-placement="top" 
                                                    >
                                                    <i class="fas fa-archive"></i>
                                                </button>
                                                <span class="tooltip-text">Arquivar agendamento</span>
                                            </div>
                                        </form>

                                        <div class="tooltip-container">
                                            <a href="src/functions/print.php?id=<?php echo $agendamento['id']; ?>" 
                                                target="_blank" 
                                                class="btn btn-warning">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <span class="tooltip-text">Imprimir agendamento</span>
                                        </div>

                                        <div class="tooltip-container">
                                            <button type="button" 
                                                class="btn btn-info send-message-btn" 
                                                onclick='showMessageModal("<?php echo htmlspecialchars($agendamento['telefone']); ?>",
                                                <?php echo json_encode([
                                                    'nome' => $agendamento['nome'],
                                                    'data_hora' => date('d/m/Y H:i', strtotime($agendamento['data_hora'])),
                                                    'atendente' => $agendamento['atendente'],
                                                    'servico' => $agendamento['servico']
                                                ]); ?>)'
                                                data-bs-toggle="tooltip" 
                                                data-bs-placement="top" 
                                                >
                                                <i class="fas fa-comment"></i>
                                            </button>
                                            <span class="tooltip-text">Enviar mensagem</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile;
                    } else {
                        echo "<tr><td colspan='7' class='text-center'>Nenhum agendamento ativo encontrado.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginação para agendamentos ativos -->
    <?php if ($total_pages > 1): ?>
    <nav aria-label="Navegação de agendamentos ativos">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo ($page - 1); ?>&active_tab=agendamentos" aria-label="Anterior">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&active_tab=agendamentos"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo ($page + 1); ?>&active_tab=agendamentos" aria-label="Próximo">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <h2>Agendamentos Arquivados</h2>
    <div class="table-scroll-container">
        <div class="table-responsive">
            <table class="table">
                <thead class="table-fixed-header">
                    <tr>
                        <th>Nome</th>
                        <th>Telefone</th>
                        <th>Data/Hora</th>
                        <th>Serviço</th>
                        <th>Atendente</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = getArchivedAgendamentos($conn, $archived_page, $per_page);
                    if ($result->num_rows > 0) {
                        while ($agendamento = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($agendamento['nome']); ?></td>
                                <td><?php echo htmlspecialchars($agendamento['telefone']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($agendamento['data_hora'])); ?></td>
                                <td><?php echo htmlspecialchars($agendamento['servico']); ?></td>
                                <td><?php echo htmlspecialchars($agendamento['atendente']); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">

                                        <div class="tooltip-container">
                                            <button type="button" class="btn btn-primary edit-btn" 
                                                data-id="<?php echo $agendamento['id']; ?>"
                                                data-nome="<?php echo htmlspecialchars($agendamento['nome']); ?>"
                                                data-telefone="<?php echo htmlspecialchars($agendamento['telefone']); ?>"
                                                data-data="<?php echo date('Y-m-d', strtotime($agendamento['data_hora'])); ?>"
                                                data-horario="<?php echo date('H:i', strtotime($agendamento['data_hora'])); ?>"
                                                data-servico="<?php echo htmlspecialchars($agendamento['servico']); ?>"
                                                data-atendente="<?php echo htmlspecialchars($agendamento['atendente']); ?>"
                                                data-bs-toggle="tooltip" 
                                                data-bs-placement="top" 
                                                >
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <span class="tooltip-text">Editar agendamento</span>
                                        </div>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="unarchiveAgendamento">
                                            <input type="hidden" name="id" value="<?php echo $agendamento['id']; ?>">
                                            <div class="tooltip-container">
                                                <button type="submit" class="btn btn-success"
                                                    data-bs-toggle="tooltip" 
                                                    data-bs-placement="top" 
                                                    >
                                                    <i class="fas fa-box-open"></i>
                                                </button>
                                                <span class="tooltip-text">Desarquivar agendamento</span>
                                            </div>
                                        </form>

                                        <form method="post" class="d-inline delete-form">
                                            <input type="hidden" name="id" value="<?php echo $agendamento['id']; ?>">
                                            <input type="hidden" name="action" value="deleteAgendamento">
                                            <input type="hidden" name="active_tab" value="agendamentos">
                                            <div class="tooltip-container">
                                                <button type="button" class="btn btn-danger delete-btn"
                                                    data-bs-toggle="tooltip" 
                                                    data-bs-placement="top" 
                                                    >
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                                <span class="tooltip-text">Excluir agendamento</span>
                                            </div>
                                        </form>

                                        <div class="tooltip-container">
                                            <a href="src/functions/print.php?id=<?php echo $agendamento['id']; ?>" 
                                                target="_blank" 
                                                class="btn btn-warning">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <span class="tooltip-text">Imprimir agendamento</span>
                                        </div>

                                        <div class="tooltip-container">
                                            <button type="button" 
                                                class="btn btn-info send-message-btn" 
                                                onclick='showMessageModal("<?php echo htmlspecialchars($agendamento['telefone']); ?>",
                                                <?php echo json_encode([
                                                    'nome' => $agendamento['nome'],
                                                    'data_hora' => date('d/m/Y H:i', strtotime($agendamento['data_hora'])),
                                                    'atendente' => $agendamento['atendente'],
                                                    'servico' => $agendamento['servico']
                                                ]); ?>)'
                                                data-bs-toggle="tooltip" 
                                                data-bs-placement="top" 
                                                >
                                                <i class="fas fa-comment"></i>
                                            </button>
                                            <span class="tooltip-text">Enviar mensagem</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile;
                    } else {
                        echo "<tr><td colspan='7' class='text-center'>Nenhum agendamento arquivado encontrado.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginação para agendamentos arquivados -->
    <?php if ($total_archived_pages > 1): ?>
    <nav aria-label="Navegação de agendamentos arquivados">
        <ul class="pagination justify-content-center">
            <?php if ($archived_page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?archived_page=<?php echo ($archived_page - 1); ?>&active_tab=agendamentos" aria-label="Anterior">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_archived_pages; $i++): ?>
                <li class="page-item <?php echo ($archived_page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?archived_page=<?php echo $i; ?>&active_tab=agendamentos"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($archived_page < $total_archived_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?archived_page=<?php echo ($archived_page + 1); ?>&active_tab=agendamentos" aria-label="Próximo">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <!-- Dentro da div id="agendamentos" após a seção de Agendamentos Arquivados -->

    <!-- Agendamentos Cancelados -->
    <div class="mt-4">
        <h2>Agendamentos Cancelados</h2>
        <div class="table-scroll-container">
            <div class="table-responsive">
                <table class="table">
                    <thead class="table-fixed-header">
                        <tr>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>Data/Hora</th>
                            <th>Serviço</th>
                            <th>Atendente</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    
                    <tbody>
                        <?php
                        // Buscar agendamentos cancelados
                        $sql = "SELECT * FROM agendamentos WHERE status = 'Agendamento Cancelado' ORDER BY data_hora DESC";
                        $result = $conn->query($sql);
                        
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr data-status='{$row['status']}'>";
                                echo "<td>" . $row['nome'] . "</td>";
                                echo "<td>" . $row['telefone'] . "</td>";
                                echo "<td>" . $row['data_hora'] . "</td>";
                                echo "<td>" . $row['servico'] . "</td>";
                                echo "<td>" . $row['atendente'] . "</td>";
                                echo "<td>
                                        <div class='btn-group' role='group'>

                                            <div class='tooltip-container'>
                                                <form method='post' class='d-inline'>
                                                    <input type='hidden' name='id' value='" . $row['id'] . "'>
                                                    <input type='hidden' name='action' value='unarchiveAgendamento'>
                                                    <button type='submit' class='btn btn-success btn-sm rounded-3'>
                                                        <i class='fas fa-undo'></i>
                                                    </button>
                                                    <span class='tooltip-text'>Reativar agendamento</span>
                                                </form>
                                            </div>
                                            
                                            <form method='post' class='d-inline delete-form'>
                                            <input type='hidden' name='id' value='" . $row['id'] . "'>
                                            <input type='hidden' name='action' value='deleteAgendamento'>
                                            <input type='hidden' name='active_tab' value='agendamentos'>
                                            <div class='tooltip-container'>
                                                <button type='button' class='btn btn-danger delete-btn'
                                                    data-bs-toggle='tooltip' 
                                                    data-bs-placement='top' 
                                                    >
                                                    <i class='fas fa-trash-alt'></i>
                                                </button>
                                                <span class='tooltip-text'>Excluir agendamento</span>
                                            </div>
                                           </form>
                                            
                                            <div class='tooltip-container'>
                                                <a href='src/functions/print.php?id=" . $row['id'] . "' 
                                                    target='_blank' 
                                                    class='btn btn-warning btn-sm rounded-3'>
                                                    <i class='fas fa-print'></i>
                                                </a>
                                                <span class='tooltip-text'>Imprimir agendamento</span>
                                            </div>

                                            <div class='tooltip-container'>
                                                <button type='button' 
                                                    class='btn btn-info btn-sm rounded-3 me-2' 
                                                    onclick='showMessageModal(\"" . $row['telefone'] . "\", " . json_encode($row) . ")'>
                                                    <i class='fas fa-comment'></i>
                                                </button>
                                                <span class='tooltip-text'>Enviar mensagem</span>
                                            </div>
                                            
                                        </div>
                                    </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' class='text-center'>Nenhum agendamento cancelado encontrado.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php if (userHasPermission('cadastro')): ?>
        <div id="cadastro" class="tab-pane fade <?php echo (isset($_SESSION['active_tab']) && $_SESSION['active_tab'] == 'cadastro') ? 'show active' : ''; ?>">
            <h2>Cadastrar Agendamento</h2>
            <form id="cadastroForm" method="post" class="mb-4" onsubmit="return submitCadastroForm(event)">
                <div class="form-group">
                    <label for="cadastro-nome">Nome:</label>
                    <input type="text" class="form-control" id="cadastro-nome" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="cadastro-telefone">Telefone:</label>
                    <input type="text" 
                           class="form-control" 
                           id="cadastro-telefone" 
                           name="telefone" 
                           pattern="55[0-9]{11}"
                           title="Digite o número no formato: 5511999999999"
                           required>
                    <small class="form-text text-muted">Digite o número no formato: 5511999999999</small>
                </div>
                <div class="form-group">
                    <label for="cadastro-data">Data:</label>
                    <input type="date" class="form-control" id="cadastro-data" name="data" required>
                </div>
                <div class="form-group">
                    <label for="cadastro-horario">Horário:</label>
                    <input type="time" class="form-control" id="cadastro-horario" name="horario" required>
                </div>
                <div class="form-group">
                    <label for="cadastro-servico">Serviço:</label>
                    <select class="form-control" id="cadastro-servico" name="servico" required>
                        <?php
                        $servicos = getServicos($conn);
                        while ($servico = $servicos->fetch_assoc()) {
                            echo "<option value='{$servico['nome']}'>{$servico['nome']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cadastro-atendente">Atendente:</label>
                    <select class="form-control" id="cadastro-atendente" name="atendente" required>
                        <?php
                        $atendentes = getAtendentes($conn);
                        while ($atendente = $atendentes->fetch_assoc()) {
                            echo "<option value='{$atendente['nome']}'>{$atendente['nome']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <input type="hidden" name="action" value="addAgendamento">
                <input type="hidden" name="active_tab" value="cadastro">
                <input type="hidden" name="status" value="Aguardando Atendimento">
                <br>
                <button type="submit" class="btn btn-primary rounded-3"><i class="fas fa-plus"></i> Cadastrar</button>
            </form>
        </div>
<?php endif; ?>
        <?php if (userHasPermission('servicos')): ?>
        <div id="servicos" class="tab-pane fade <?php echo (isset($_SESSION['active_tab']) && $_SESSION['active_tab'] == 'servicos') ? 'show active' : ''; ?>">
            <h2>Gerenciamento de Serviços</h2>
            <form method="post" class="mb-4">
                <h3>Adicionar Novo Serviço</h3>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nome">Nome do Serviço:</label>
                            <input type="text" class="form-control" name="nome" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="preco">Preço (R$):</label>
                            <input type="number" step="0.01" class="form-control" name="preco" required>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="duracao">Duração (minutos):</label>
                            <input type="number" class="form-control" name="duracao" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group form-check mt-4">
                            <input type="checkbox" class="form-check-input" id="disponivel" name="disponivel" checked>
                            <label class="form-check-label" for="disponivel">Disponível</label>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="action" value="addServico">
                <input type="hidden" name="active_tab" value="servicos">
                <br>
                <button type="submit" class="btn btn-primary rounded-3"><i class="fas fa-plus"></i> Adicionar</button>
            </form>
            <?php
            $servicos = getServicosList($conn);
            ?>
            <h2>Serviços Existentes</h2>
            <div class="table-scroll-container">
                <div class="table-responsive">
                    <table class="table">
                        <thead class="table-fixed-header">
                            <tr>
                                <th>Serviço</th>
                                <th>Preço</th>
                                <th>Duração (min)</th>
                                <th>Disponibilidade</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            while ($servico = $servicos->fetch_assoc()) {
                                $disponibilidade = $servico['disponivel'] ? 'Disponível' : 'Indisponível';
                                $disponibilidadeBtn = $servico['disponivel'] ? 'Indisponibilizar' : 'Disponibilizar';
                                
                                echo "<tr>";
                                echo "<td>" . $servico['nome'] . "</td>";
                                echo "<td>R$ " . number_format($servico['preco'], 2, ',', '.') . "</td>";
                                echo "<td>" . $servico['duracao'] . "</td>";
                                echo "<td>" . $disponibilidade . "</td>";
                                echo "<td>
                                        <div class='btn-group' role='group'>
                                            <div class='tooltip-container'>
                                                <form method='post' class='delete-form d-inline'>
                                                    <input type='hidden' name='id' value='".$servico['id']."'>
                                                    <input type='hidden' name='action' value='deleteServico'>
                                                    <input type='hidden' name='active_tab' value='servicos'>
                                                    <button type='button' class='btn btn-danger btn-sm rounded-3 delete-btn me-2'>
                                                        <i class='fas fa-trash-alt'></i> Excluir
                                                    </button>
                                                </form>
                                            </div>
                                            <button type='button' 
                                                class='btn btn-primary btn-sm rounded-3 edit-servico-btn me-2' 
                                                data-id='".$servico['id']."' 
                                                data-nome='".htmlspecialchars($servico['nome'], ENT_QUOTES)."'
                                                data-preco='".$servico['preco']."'
                                                data-duracao='".$servico['duracao']."'
                                                data-disponivel='".$servico['disponivel']."'>
                                                <i class='fas fa-edit'></i> Editar
                                            </button>
                                            <form method='post' style='display:inline-block' class='disponibilidade-form'>
                                                <input type='hidden' name='id' value='" . $servico['id'] . "'>
                                                <input type='hidden' name='action' value='setServicoDisponibilidade'>
                                                <input type='hidden' name='disponivel' value='".($servico['disponivel'] ? 0 : 1)."'>
                                                <input type='hidden' name='active_tab' value='servicos'>
                                                <button type='submit' class='btn btn-secondary btn-sm rounded-3'>
                                                    <i class='fas fa-".($servico['disponivel'] ? 'times' : 'check')."'></i> 
                                                    ".$disponibilidadeBtn."
                                                </button>
                                            </form>
                                        </div>
                                      </td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Formulário para adicionar serviço atribuído -->
            <h2>Adicionar Serviço Atribuído</h2>
            <form method="post" class="mb-4">
                <div class="form-group">
                    <label for="atendente_id">Atendente:</label>
                    <select class="form-control" name="atendente_id" required>
                        <?php
                        $atendentes = getAtendentes($conn);
                        while ($atendente = $atendentes->fetch_assoc()) {
                            echo "<option value='{$atendente['id']}'>{$atendente['nome']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="servico_id">Serviço:</label>
                    <select class="form-control" name="servico_id" required>
                        <?php
                        $servicos = getServicosList($conn); // Re-fetching the services for dropdown
                        while ($servico = $servicos->fetch_assoc()) {
                            echo "<option value='{$servico['id']}'>{$servico['nome']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <input type="hidden" name="action" value="addAtendenteServico">
                <input type="hidden" name="active_tab" value="servicos">
                <br>
                <button type="submit" class="btn btn-primary rounded-3"><i class="fas fa-plus"></i> Adicionar</button>
            </form>

            <!-- Lista de Serviços Atribuídos -->
            <h2>Serviços Atribuídos</h2>
            <?php
            $atendenteServicos = getAtendenteServicos($conn);
            ?>
                        <div class="table-scroll-container">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead class="table-fixed-header">
                                        <tr>
                                            <th>Atendente</th>
                                            <th>Serviço</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        while ($atendenteServico = $atendenteServicos->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . $atendenteServico['atendente'] . "</td>";
                                            echo "<td>" . $atendenteServico['servico'] . "</td>";
                                            echo "<td>
                                                    <div class='btn-group' role='group'>
                                                        <div class='tooltip-container'>
                                                            <form method='post' class='delete-form d-inline'>
                                                                <input type='hidden' name='id' value='".$atendenteServico['id']."'>
                                                                <input type='hidden' name='action' value='deleteAtendenteServico'>
                                                                <input type='hidden' name='active_tab' value='servicos'>
                                                                <button type='button' class='btn btn-danger btn-sm rounded-3 delete-btn me-2'>
                                                                    <i class='fas fa-trash-alt'></i> Excluir
                                                                </button>
                                                            </form>
                                                        </div>
                                                        <button type='button' 
                                                            class='btn btn-primary btn-sm rounded-3 edit-serv-atendente-btn' 
                                                            data-id='".$atendenteServico['id']."' 
                                                            data-atendente='".$atendenteServico['atendente']."' 
                                                            data-servico='".$atendenteServico['servico']."'>
                                                            <i class='fas fa-edit'></i> Editar
                                                        </button>
                                                    </div>
                                                  </td>";
                                            echo "</tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
        </div>
<?php endif; ?>
        <?php if (userHasPermission('horarios')): ?>
        <div id="horarios" class="tab-pane fade <?php echo (isset($_SESSION['active_tab']) && $_SESSION['active_tab'] == 'horarios') ? 'show active' : ''; ?>">
            <h2>Gerenciamento de Horários</h2>
            <form method="post" class="mb-4">
                <div class="form-group">
                    <label for="horario">Adicionar Novo Horário:</label>
                    <input type="time" class="form-control" name="horario" required>
                    <input type="hidden" name="action" value="addHorario">
                    <input type="hidden" name="active_tab" value="horarios">
                </div>
                <br>        
                <button type="submit" class="btn btn-primary rounded-3"><i class="fas fa-plus"></i> Adicionar</button>
            </form>
            <?php
            $horarios = getHorariosList($conn);
            ?>
            <h2>Horários Existentes</h2>
            <div class="table-scroll-container">
                <div class="table-responsive">
                    <table class="table">
                        <thead class="table-fixed-header">
                            <tr>
                                <th>Horário</th>
                                <th>Disponibilidade</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            while ($horario = $horarios->fetch_assoc()) {
                                $disponibilidade = $horario['disponivel'] ? 'Disponível' : 'Indisponível';
                                $disponibilidadeBtn = $horario['disponivel'] ? 'Indisponibilizar' : 'Disponibilizar';
                                
                                echo "<tr>";
                                echo "<td>" . $horario['horario'] . "</td>";
                                echo "<td>" . $disponibilidade . "</td>";
                                echo "<td>
                                        <div class='btn-group' role='group'>
                                            <div class='tooltip-container'>
                                                <form method='post' class='delete-form d-inline'>
                                                    <input type='hidden' name='id' value='".$horario['id']."'>
                                                    <input type='hidden' name='action' value='deleteHorario'>
                                                    <input type='hidden' name='active_tab' value='horarios'>
                                                    <button type='button' class='btn btn-danger btn-sm rounded-3 delete-btn me-2'>
                                                        <i class='fas fa-trash-alt'></i> Excluir
                                                    </button>
                                                </form>
                                            </div>
                                            <button type='button' 
                                                class='btn btn-primary btn-sm rounded-3 edit-horario-btn me-2' 
                                                data-id='".$horario['id']."' 
                                                data-horario='".$horario['horario']."'>
                                                <i class='fas fa-edit'></i> Editar
                                            </button>
                                
                                            <form method='post' style='display:inline-block' class='disponibilidade-form'>
                                                <input type='hidden' name='id' value='" . $horario['id'] . "'>
                                                <input type='hidden' name='action' value='setHorarioDisponibilidade'>
                                                <input type='hidden' name='disponivel' value='".($horario['disponivel'] ? 0 : 1)."'>
                                                <input type='hidden' name='active_tab' value='horarios'>
                                                <button type='submit' class='btn btn-secondary btn-sm rounded-3'>
                                                    <i class='fas fa-".($horario['disponivel'] ? 'times' : 'check')."'></i> 
                                                    ".$disponibilidadeBtn."
                                                </button>
                                            </form>
                                        </div>
                                      </td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Dentro da div id="horarios" após a tabela de Horários Existentes -->

            <h2>Atribuição de Horários aos Atendentes</h2>
            <form method="post" class="mb-4">
                <div class="form-group">
                    <label for="atendente_id">Selecione o Atendente:</label>
                    <select class="form-control" name="atendente_id" id="atendente_id" required>
                        <?php
                        $atendentes = getAtendentes($conn);
                        while ($atendente = $atendentes->fetch_assoc()) {
                            echo "<option value='{$atendente['id']}'>{$atendente['nome']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="horario_id">Horários Existentes:</label>
                    <select class="form-control" name="horario_id" id="horario_id" required>
                        <?php
                        $horarios = getHorariosList($conn);
                        while ($horario = $horarios->fetch_assoc()) {
                            echo "<option value='{$horario['id']}'>{$horario['horario']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <input type="hidden" name="action" value="addHorarioAtendente">
                <input type="hidden" name="active_tab" value="horarios">
                <br>
                <button type="submit" class="btn btn-primary rounded-3"><i class="fas fa-plus"></i> Adicionar</button>
            </form>

            <h2>Horários Atribuídos</h2>
            <div class="table-scroll-container">
                <div class="table-responsive">
                    <table class="table">
                        <thead class="table-fixed-header">
                            <tr>
                                <th>Atendente</th>
                                <th>Horário</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="horarios-atribuidos">
                            <?php
                            $sql = "SELECT ha.id, a.nome as atendente, h.horario 
                                    FROM horarios_atendentes ha 
                                    JOIN atendentes a ON ha.atendente_id = a.id 
                                    JOIN horarios h ON ha.horario_id = h.id";
                            $result = mysqli_query($conn, $sql);
                            while ($row = mysqli_fetch_assoc($result)) {
                                echo "<tr>";
                                echo "<td>{$row['atendente']}</td>";
                                echo "<td>{$row['horario']}</td>";
                                echo "<td>
                                        <form method='post' style='display:inline-block' class='delete-form'>
                                            <input type='hidden' name='id' value='{$row['id']}'>
                                            <input type='hidden' name='action' value='deleteHorarioAtendente'>
                                            <input type='hidden' name='active_tab' value='horarios'>
                                            <div class='tooltip-container'>
                                                <button type='button' class='btn btn-danger rounded-3 delete-btn'><i class='fas fa-trash-alt'></i> Excluir</button>
                                            </div>
                                        </form>
                                      </td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
<?php endif; ?>
        <?php if (userHasPermission('atendentes')): ?>
        <div id="atendentes" class="tab-pane fade <?php echo (isset($_SESSION['active_tab']) && $_SESSION['active_tab'] == 'atendentes') ? 'show active' : ''; ?>">
            <h2>Gerenciamento de Atendentes</h2>
            <form method="post" class="mb-4">
                <div class="form-group">
                    <label for="nome">Nome:</label>
                    <input type="text" class="form-control" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="cargo">Cargo:</label>
                    <input type="text" class="form-control" name="cargo" required>
                </div>
                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" name="disponivel" id="disponivel">
                    <label class="form-check-label" for="disponivel">Disponível</label>
                </div>
                <input type="hidden" name="action" value="addAtendente">
                <input type="hidden" name="active_tab" value="atendentes">
                <br>
                <button type="submit" class="btn btn-primary rounded-3"><i class="fas fa-plus"></i> Adicionar</button>
            </form>
            <?php
            $atendentes = getAtendentes($conn);
            ?>
            <h2>Atendentes Existentes</h2>
            <div class="table-scroll-container">
                <div class="table-responsive">
                    <table class="table">
                        <thead class="table-fixed-header">
                            <tr>
                                <th>Nome</th>
                                <th>Cargo</th>
                                <th>Disponibilidade</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            while ($atendente = $atendentes->fetch_assoc()) {
                                $disponibilidade = $atendente['disponivel'] ? 'Disponível' : 'Indisponível';
                                $disponibilidadeBtn = $atendente['disponivel'] ? 'Indisponibilizar' : 'Disponibilizar';
                                
                                echo "<tr>";
                                echo "<td>" . $atendente['nome'] . "</td>";
                                echo "<td>" . $atendente['cargo'] . "</td>";
                                echo "<td>" . $disponibilidade . "</td>";
                                echo "<td>
                                        <div class='btn-group' role='group'>
                                            <div class='tooltip-container'>
                                                <form method='post' class='delete-form d-inline'>
                                                    <input type='hidden' name='id' value='".$atendente['id']."'>
                                                    <input type='hidden' name='action' value='deleteAtendente'>
                                                    <input type='hidden' name='active_tab' value='atendentes'>
                                                    <button type='button' class='btn btn-danger btn-sm rounded-3 delete-btn me-2'>
                                                        <i class='fas fa-trash-alt'></i> Excluir
                                                    </button>
                                                </form>
                                            </div>
                                            <button type='button' 
                                                class='btn btn-primary btn-sm rounded-3 edit-atendente-btn' 
                                                data-id='".$atendente['id']."' 
                                                data-nome='".$atendente['nome']."' 
                                                data-cargo='".$atendente['cargo']."' 
                                                data-disponivel='".$atendente['disponivel']."'>
                                                <i class='fas fa-edit'></i> Editar
                                            </button>
                                        </div>
                                      </td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
<?php endif; ?>
        <?php if (userHasPermission('usuarios')): ?>
        <div id="usuarios" class="tab-pane fade <?php echo (isset($_SESSION['active_tab']) && $_SESSION['active_tab'] == 'usuarios') ? 'show active' : ''; ?>">
            <h2>Gerenciamento de Usuários</h2>
            <form method="post" class="mb-4">
                <div class="form-group">
                    <label for="username">Usuário:</label>
                    <input type="text" class="form-control" name="username" required>
                </div>
                <div class="form-group">
                    <label for="email">E-mail:</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Senha:</label>
                    <input type="password" class="form-control" name="password" required>
                </div><br>
                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" name="super_admin" id="super-admin" value="1">
                    <label class="form-check-label" for="super-admin">Super Administrador (acesso total)</label>
                </div>
                <div id="permissions-section">
                    <div class="form-group">
                        <label>Permissões de Acesso:</label>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="inicio" id="perm-inicio">
                            <label class="form-check-label" for="perm-inicio">Dashboard</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="kanban" id="perm-kanban">
                            <label class="form-check-label" for="perm-kanban">Atendimentos</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="agendamentos" id="perm-agendamentos">
                            <label class="form-check-label" for="perm-agendamentos">Agendamentos</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="cadastro" id="perm-cadastro">
                            <label class="form-check-label" for="perm-cadastro">Cadastro</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="servicos" id="perm-servicos">
                            <label class="form-check-label" for="perm-servicos">Serviços</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="horarios" id="perm-horarios">
                            <label class="form-check-label" for="perm-horarios">Horários</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="atendentes" id="perm-atendentes">
                            <label class="form-check-label" for="perm-atendentes">Atendentes</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="usuarios" id="perm-usuarios">
                            <label class="form-check-label" for="perm-usuarios">Usuários</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="relatorios" id="perm-relatorios">
                            <label class="form-check-label" for="perm-relatorios">Relatórios</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="customizacao" id="perm-customizacao">
                            <label class="form-check-label" for="perm-customizacao">Customização</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="configuracoes" id="perm-configuracoes">
                            <label class="form-check-label" for="perm-configuracoes">Configurações</label>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="action" value="addUser">
                <input type="hidden" name="active_tab" value="usuarios">
                <br>
                <button type="submit" class="btn btn-primary rounded-3"><i class="fas fa-plus"></i> Adicionar</button>
            </form>
            <?php
            $users = getUsers($conn);
            ?>
            <h2>Usuários Existentes</h2>
            <div class="table-scroll-container">
                <div class="table-responsive">
                    <table class="table">
                        <thead class="table-fixed-header">
                            <tr>
                                <th>Usuário</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            while ($user = $users->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . $user['username'] . "</td>";
                                echo "<td>
                                        <div class='btn-group' role='group'>
                                            <div class='tooltip-container'>
                                                <form method='post' class='delete-form d-inline'>
                                                    <input type='hidden' name='id' value='".$user['id']."'>
                                                    <input type='hidden' name='action' value='deleteUser'>
                                                    <input type='hidden' name='active_tab' value='usuarios'>
                                                    <button type='button' class='btn btn-danger btn-sm rounded-3 delete-btn me-2'>
                                                        <i class='fas fa-trash-alt'></i> Excluir
                                                    </button>
                                                </form>
                                            </div>
                                            <button type='button' 
                                                class='btn btn-primary btn-sm rounded-3 edit-user-btn' 
                                                data-id='".$user['id']."' 
                                                data-username='".$user['username']."' 
                                                data-email='".$user['email']."'>
                                                <i class='fas fa-edit'></i> Editar
                                            </button>
                                        </div>
                                      </td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
<?php endif; ?>
        <?php if (userHasPermission('customizacao')): ?>
        <div id="customizacao" class="tab-pane fade <?php echo (isset($_SESSION['active_tab']) && $_SESSION['active_tab'] == 'customizacao') ? 'show active' : ''; ?>">
            <h2>Customização do Sistema</h2>
            <form method="post" class="mb-4">
                <!-- Adicionar campos hidden necessários -->
                <input type="hidden" name="action" value="updateCustomization">
                <input type="hidden" name="active_tab" value="customizacao">

                <h4 class="mt-4">Logo do Sistema</h4>
                <div class="form-group">
                    <label for="logo_url">URL da Logo do Sistema:</label>
                    <input type="text" class="form-control" id="logo_url" name="logo_url" value="<?php echo htmlspecialchars($customizacao['logo_url']); ?>">
                </div>

                <div class="form-group">
                    <label for="login_logo_url">URL da Logo da Tela de Login:</label>
                    <input type="text" class="form-control" id="login_logo_url" name="login_logo_url" value="<?php echo htmlspecialchars($customizacao['login_logo_url']); ?>">
                </div>

                <h4 class="mt-4">Logo do Dashboard</h4>
                <div class="form-group">
                    <label for="dashboard_logo_url">URL da Logo do Dashboard:</label>
                    <input type="text" class="form-control" id="dashboard_logo_url" name="dashboard_logo_url" value="<?php echo htmlspecialchars($customizacao['dashboard_logo_url']); ?>">
                </div>
                <div class="form-group">
                    <label for="dashboard_logo_height">Altura da Logo do Dashboard:</label>
                    <input type="text" class="form-control" name="dashboard_logo_height" value="<?php echo isset($customizacao['dashboard_logo_height']) ? $customizacao['dashboard_logo_height'] : '120px'; ?>" placeholder="Ex: 120px">
                </div>
                <div class="form-group">
                    <label for="dashboard_info_text">Texto de Boas-vindas do Dashboard:</label>
                    <textarea 
                        class="form-control" 
                        name="dashboard_info_text" 
                        id="dashboard_info_text"
                        rows="10" 
                        style="min-height: 200px; resize: vertical;"
                    ><?php echo isset($customizacao['dashboard_info_text']) ? $customizacao['dashboard_info_text'] : 'Bem-vindo ao seu painel de controle Agenda AtrativoZap'; ?></textarea>
                </div><p>Use HTML para formatar o texto.</p>

                <h4 class="mt-4">Cores do Sistema</h4>
                <div class="form-group">
                    <label for="navbar_color">Cor da Barra de Navegação:</label>
                    <input type="color" class="form-control" name="navbar_color" value="<?php echo isset($customizacao['navbar_color']) ? $customizacao['navbar_color'] : '#002aff'; ?>" required>
                </div>
                <div class="form-group">
                    <label for="background_color">Cor de Fundo do Sistema:</label>
                    <input type="color" class="form-control" name="background_color" id="background_color" 
                        value="<?php echo isset($customizacao['background_color']) ? $customizacao['background_color'] : '#f8f9fa'; ?>">
                </div>
                <!-- Após a seção de cores do sistema, antes da seção de botões -->
                <h5 class="mt-4">Cards do Dashboard</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Gradiente do Card Agendamentos:</label>
                            <div class="d-flex gap-2">
                                <input type="color" class="form-control" name="card_agendamentos_gradient_start" 
                                    value="<?php echo isset($customizacao['card_agendamentos_gradient_start']) ? $customizacao['card_agendamentos_gradient_start'] : '#4e54c8'; ?>">
                                <input type="color" class="form-control" name="card_agendamentos_gradient_end" 
                                    value="<?php echo isset($customizacao['card_agendamentos_gradient_end']) ? $customizacao['card_agendamentos_gradient_end'] : '#8f94fb'; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Gradiente do Card Finalizados:</label>
                            <div class="d-flex gap-2">
                                <input type="color" class="form-control" name="card_finalizados_gradient_start" 
                                    value="<?php echo isset($customizacao['card_finalizados_gradient_start']) ? $customizacao['card_finalizados_gradient_start'] : '#11998e'; ?>">
                                <input type="color" class="form-control" name="card_finalizados_gradient_end" 
                                    value="<?php echo isset($customizacao['card_finalizados_gradient_end']) ? $customizacao['card_finalizados_gradient_end'] : '#38ef7d'; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Gradiente do Card Atendentes:</label>
                            <div class="d-flex gap-2">
                                <input type="color" class="form-control" name="card_atendentes_gradient_start" 
                                    value="<?php echo isset($customizacao['card_atendentes_gradient_start']) ? $customizacao['card_atendentes_gradient_start'] : '#ff758c'; ?>">
                                <input type="color" class="form-control" name="card_atendentes_gradient_end" 
                                    value="<?php echo isset($customizacao['card_atendentes_gradient_end']) ? $customizacao['card_atendentes_gradient_end'] : '#ff7eb3'; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Gradiente do Card Serviços:</label>
                            <div class="d-flex gap-2">
                                <input type="color" class="form-control" name="card_servicos_gradient_start" 
                                    value="<?php echo isset($customizacao['card_servicos_gradient_start']) ? $customizacao['card_servicos_gradient_start'] : '#fc466b'; ?>">
                                <input type="color" class="form-control" name="card_servicos_gradient_end" 
                                    value="<?php echo isset($customizacao['card_servicos_gradient_end']) ? $customizacao['card_servicos_gradient_end'] : '#3f5efb'; ?>">
                            </div>
                        </div>
                    </div>
                    <!-- Dentro da seção Cards do Dashboard, após os outros cards -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Gradiente do Card Atendimentos Hoje:</label>
                            <div class="d-flex gap-2">
                                <input type="color" class="form-control" name="card_atendimentos_hoje_gradient_start" 
                                    value="<?php echo isset($customizacao['card_atendimentos_hoje_gradient_start']) ? $customizacao['card_atendimentos_hoje_gradient_start'] : '#00c6fb'; ?>">
                                <input type="color" class="form-control" name="card_atendimentos_hoje_gradient_end" 
                                    value="<?php echo isset($customizacao['card_atendimentos_hoje_gradient_end']) ? $customizacao['card_atendimentos_hoje_gradient_end'] : '#005bea'; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Gradiente do Card Atendimentos Período:</label>
                            <div class="d-flex gap-2">
                                <input type="color" class="form-control" name="card_atendimentos_periodo_gradient_start" 
                                    value="<?php echo isset($customizacao['card_atendimentos_periodo_gradient_start']) ? $customizacao['card_atendimentos_periodo_gradient_start'] : '#f5576c'; ?>">
                                <input type="color" class="form-control" name="card_atendimentos_periodo_gradient_end" 
                                    value="<?php echo isset($customizacao['card_atendimentos_periodo_gradient_end']) ? $customizacao['card_atendimentos_periodo_gradient_end'] : '#f093fb'; ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <h4 class="mt-4">Tabelas</h4>
                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="table_striped" id="table_striped" 
                            <?php echo isset($customizacao['table_striped']) && $customizacao['table_striped'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="table_striped">Tabelas Zebradas</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="table_hover" id="table_hover"
                            <?php echo isset($customizacao['table_hover']) && $customizacao['table_hover'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="table_hover">Efeito Hover nas Tabelas</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="table_responsive" id="table_responsive"
                            <?php echo isset($customizacao['table_responsive']) && $customizacao['table_responsive'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="table_responsive">Tabelas Responsivas</label>
                    </div>
                </div>

                <h4 class="mt-4">Botões</h4>
                <div class="form-group">
                    <label for="primary_color">Cor do Botão Primário:</label>
                    <input type="color" class="form-control" name="primary_color" value="<?php echo isset($customizacao['primary_color']) ? $customizacao['primary_color'] : '#4789eb'; ?>" required>
                </div>
                <div class="form-group">
                    <label for="primary_hover_color">Cor do Hover do Botão Primário:</label>
                    <input type="color" class="form-control" name="primary_hover_color" value="<?php echo isset($customizacao['primary_hover_color']) ? $customizacao['primary_hover_color'] : '#3672c9'; ?>" required>
                </div>
                <div class="form-group">
                    <label for="button_style">Estilo dos Botões:</label>
                    <select class="form-control" name="button_style" id="button_style">
                        <option value="modern" <?php echo isset($customizacao['button_style']) && $customizacao['button_style'] == 'modern' ? 'selected' : ''; ?>>Moderno</option>
                        <option value="classic" <?php echo isset($customizacao['button_style']) && $customizacao['button_style'] == 'classic' ? 'selected' : ''; ?>>Clássico</option>
                        <option value="minimal" <?php echo isset($customizacao['button_style']) && $customizacao['button_style'] == 'minimal' ? 'selected' : ''; ?>>Minimalista</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="button_radius">Formato dos Botões:</label>
                    <select class="form-control" name="button_radius" id="button_radius">
                        <option value="square" <?php echo isset($customizacao['button_radius']) && $customizacao['button_radius'] == 'square' ? 'selected' : ''; ?>>Quadrado</option>
                        <option value="rounded" <?php echo isset($customizacao['button_radius']) && $customizacao['button_radius'] == 'rounded' ? 'selected' : ''; ?>>Arredondado</option>
                        <option value="pill" <?php echo isset($customizacao['button_radius']) && $customizacao['button_radius'] == 'pill' ? 'selected' : ''; ?>>Pílula</option>
                    </select>
                </div>

                <h4 class="mt-4">Tipografia</h4>
                <div class="form-group">
                    <label for="font_family">Fonte Principal:</label>
                    <select class="form-control" name="font_family" id="font_family">
                        <option value="Poppins" <?php echo isset($customizacao['font_family']) && $customizacao['font_family'] == 'Poppins' ? 'selected' : ''; ?>>Poppins</option>
                        <option value="Roboto" <?php echo isset($customizacao['font_family']) && $customizacao['font_family'] == 'Roboto' ? 'selected' : ''; ?>>Roboto</option>
                        <option value="Open Sans" <?php echo isset($customizacao['font_family']) && $customizacao['font_family'] == 'Open Sans' ? 'selected' : ''; ?>>Open Sans</option>
                        <option value="Montserrat" <?php echo isset($customizacao['font_family']) && $customizacao['font_family'] == 'Montserrat' ? 'selected' : ''; ?>>Montserrat</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="font_size">Tamanho da Fonte Base:</label>
                    <select class="form-control" name="font_size" id="font_size">
                        <option value="14px" <?php echo isset($customizacao['font_size']) && $customizacao['font_size'] == '14px' ? 'selected' : ''; ?>>14px</option>
                        <option value="16px" <?php echo isset($customizacao['font_size']) && $customizacao['font_size'] == '16px' ? 'selected' : ''; ?>>16px</option>
                        <option value="18px" <?php echo isset($customizacao['font_size']) && $customizacao['font_size'] == '18px' ? 'selected' : ''; ?>>18px</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="font_weight">Peso da Fonte:</label>
                    <select class="form-control" name="font_weight" id="font_weight">
                        <option value="300" <?php echo isset($customizacao['font_weight']) && $customizacao['font_weight'] == '300' ? 'selected' : ''; ?>>Light</option>
                        <option value="400" <?php echo isset($customizacao['font_weight']) && $customizacao['font_weight'] == '400' ? 'selected' : ''; ?>>Regular</option>
                        <option value="500" <?php echo isset($customizacao['font_weight']) && $customizacao['font_weight'] == '500' ? 'selected' : ''; ?>>Medium</option>
                        <option value="600" <?php echo isset($customizacao['font_weight']) && $customizacao['font_weight'] == '600' ? 'selected' : ''; ?>>Semi-Bold</option>
                    </select>
                </div>

                <h4 class="mt-4">Rodapé</h4>
                <div class="form-group">
                    <label for="footer_text">Texto do Rodapé:</label>
                    <input type="text" class="form-control" name="footer_text" value="<?php echo isset($customizacao['footer_text']) ? $customizacao['footer_text'] : ''; ?>" required>
                </div>



                <button type="submit" class="btn btn-primary mt-4">Salvar Alterações</button>
            </form>
        </div>
<?php endif; ?>
        <?php if (userHasPermission('configuracoes')): ?>
        <div id="configuracoes" class="tab-pane fade <?php echo (isset($_SESSION['active_tab']) && $_SESSION['active_tab'] == 'configuracoes') ? 'show active' : ''; ?>">
            <div class="mb-4">
                <div>
                    <h2>Evolution API</h2>
                </div>
                <div class="card-body">
                    <form id="evolutionApiForm" method="post">
                        <input type="hidden" name="action" value="updateEvolutionApi">
                        <input type="hidden" name="active_tab" value="configuracoes">
                        
                        <div class="form-group">
                            <label for="evolution_base_url">URL Base:</label>
                            <input type="text" class="form-control" id="evolution_base_url" name="evolution_base_url" 
                                   value="<?php 
                                   $stmt = $conn->prepare('SELECT valor FROM configuracoes WHERE chave = ?');
                                   $chave = 'evolution_base_url';
                                   $stmt->bind_param('s', $chave);
                                   $stmt->execute();
                                   $result = $stmt->get_result();
                                   echo $result->fetch_assoc()['valor'] ?? '';
                                   ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="evolution_instance">Instância:</label>
                            <input type="text" class="form-control" id="evolution_instance" name="evolution_instance" 
                                   value="<?php 
                                   $chave = 'evolution_instance';
                                   $stmt->bind_param('s', $chave);
                                   $stmt->execute();
                                   $result = $stmt->get_result();
                                   echo $result->fetch_assoc()['valor'] ?? '';
                                   ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="evolution_api_key">Chave API:</label>
                            <input type="text" class="form-control" id="evolution_api_key" name="evolution_api_key" 
                                   value="<?php 
                                   $chave = 'evolution_api_key';
                                   $stmt->bind_param('s', $chave);
                                   $stmt->execute();
                                   $result = $stmt->get_result();
                                   echo $result->fetch_assoc()['valor'] ?? '';
                                   ?>" required>
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary rounded-3">Salvar Configurações</button>
                    </form>
                </div>
            </div>
            
            <div class="mb-4">
                <div>
                    <h2>Mensagens Personalizadas</h2>
                </div>
                <div class="card-body">
                    <form method="post" class="mb-4">
                        <input type="hidden" name="action" value="addMensagem">
                        <input type="hidden" name="active_tab" value="configuracoes">
                        <input type="hidden" name="active_section" value="mensagens">
                        <div class="form-group">
                            <label for="nome_mensagem">Nome da Mensagem:</label>
                            <input type="text" class="form-control" name="nome_mensagem" required>
                        </div>
                        <div class="form-group">
                            <label for="template">Template da Mensagem:</label>
                            <textarea class="form-control" name="template" rows="4" required></textarea>
                            <small class="form-text text-muted">
                                Variáveis disponíveis: $nome, $data_hora, $atendente, $servico
                            </small>
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary rounded-3">Adicionar Mensagem</button>
                    </form>

                    <div class="table-scroll-container">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="table-fixed-header">
                                    <tr>
                                        <th>Nome</th>
                                        <th>Template</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "SELECT id, nome_mensagem, template FROM mensagens_padronizadas";
                                    $result = mysqli_query($conn, $sql);
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($row['nome_mensagem']) . "</td>";
                                        echo "<td><div class='text-truncate' style='max-width: 500px;' title='" . htmlspecialchars($row['template']) . "'>" 
                                             . htmlspecialchars($row['template']) . "</div></td>";
                                        echo "<td>
                                                <div class='btn-group' role='group'>
                                                    <div class='tooltip-container'>
                                                        <button type='button' class='btn btn-danger btn-sm rounded-3 delete-mensagem-btn me-2' 
                                                            data-id='".$row['id']."'>
                                                            <i class='fas fa-trash-alt'></i> Excluir
                                                        </button>
                                                    </div>
                                                    <button type='button' class='btn btn-primary btn-sm rounded-3 edit-mensagem-btn' data-id='".$row['id']."' data-nome='".htmlspecialchars($row['nome_mensagem'], ENT_QUOTES)."' data-template='".htmlspecialchars($row['template'], ENT_QUOTES)."'>
                                                            <i class='fas fa-edit'></i> Editar
                                                        </button>
                                                </div>
                                              </td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php endif; ?>
        <div id="kanban" class="tab-pane fade">
<h2>Gestão de Atendimento - <small id="relogio"><?php echo $data_hora_atual; ?></small></h2>
    <div class="kanban-board">
        <div class="kanban-column" id="column-aguardando">
            <h4><i class="fas fa-hourglass-half"></i> Aguardando Atendimento</h4>
            <ul id="aguardando-list" class="kanban-list">
                <!-- Lista de agendamentos aguardando atendimento -->
            </ul>
        </div>
        <div class="kanban-column" id="column-iniciado">
            <h4><i class="fas fa-play-circle"></i> Atendimento Iniciado</h4>
            <ul id="iniciado-list" class="kanban-list">
                <!-- Lista de agendamentos com atendimento iniciado -->
            </ul>
        </div>
        <div class="kanban-column" id="column-finalizado">
            <h4><i class="fas fa-check-circle"></i> Atendimento Finalizado</h4>
            <ul id="finalizado-list" class="kanban-list">
                <!-- Lista de agendamentos finalizados -->
            </ul>
        </div>
        <div class="kanban-column" id="column-cancelado">
            <h4><i class="fas fa-times-circle"></i> Agendamento Cancelado</h4>
            <ul id="cancelado-list" class="kanban-list">
                <!-- Lista de agendamentos cancelados -->
            </ul>
        </div>
    </div>
</div>

<!-- Cadastro -->

<!-- Modais -->

<!-- Modal Editar Agendamento -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Editar Agendamento</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editForm" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <input type="hidden" name="action" value="editAgendamento">
                    <input type="hidden" name="id" id="edit-id">
                    <input type="hidden" name="active_tab" value="agendamentos">
                    <div class="form-group">
                        <label for="edit-nome">Nome:</label>
                        <input type="text" class="form-control" id="edit-nome" name="nome" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-telefone">Telefone:</label>
                        <input type="text" class="form-control" id="edit-telefone" name="telefone" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-data">Data:</label>
                        <input type="date" class="form-control" id="edit-data" name="data" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-horario">Horário:</label>
                        <input type="time" class="form-control" id="edit-horario" name="horario" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-servico">Tipo de Serviço:</label>
                        <select class="form-control" id="edit-servico" name="servico" required>
                            <?php
                            $servicos = getServicos($conn);
                            while ($servico = $servicos->fetch_assoc()) {
                                echo "<option value='{$servico['nome']}'>{$servico['nome']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-atendente">Atendente:</label>
                        <select class="form-control" id="edit-atendente" name="atendente" required>
                            <?php
                            $atendentes = getAtendentes($conn);
                            while ($atendente = $atendentes->fetch_assoc()) {
                                echo "<option value='{$atendente['nome']}'>{$atendente['nome']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <br>
                    <button type="submit" class="btn btn-primary rounded-3">Salvar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar serviço -->
<div class="modal fade" id="editServicoModal" tabindex="-1" aria-labelledby="editServicoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editServicoLabel">Editar Serviço</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit-servico-nome">Nome do Serviço:</label>
                        <input type="text" class="form-control" id="edit-servico-nome" name="nome" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-servico-preco">Preço (R$):</label>
                        <input type="number" step="0.01" class="form-control" id="edit-servico-preco" name="preco" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-servico-duracao">Duração (minutos):</label>
                        <input type="number" class="form-control" id="edit-servico-duracao" name="duracao" required>
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="edit-servico-disponivel" name="disponivel">
                        <label class="form-check-label" for="edit-servico-disponivel">Disponível</label>
                    </div>
                    <input type="hidden" id="edit-servico-id" name="id">
                    <input type="hidden" name="action" value="updateServico">
                    <input type="hidden" name="active_tab" value="servicos">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary rounded-3">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Serviço Atribuído -->
<div class="modal fade" id="editAtendenteServicoModal" tabindex="-1" aria-labelledby="editAtendenteServicoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAtendenteServicoLabel">Editar Serviço Atribuído</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editAtendenteServicoForm" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <input type="hidden" name="action" value="updateAtendenteServico">
                    <input type="hidden" name="id" id="edit-atendente-servico-id">
                    <input type="hidden" name="active_tab" value="servicos">
                    <div class="form-group">
                        <label for="edit-atendente-serv-id">Atendente:</label>
                        <select class="form-control" id="edit-atendente-serv-id" name="atendente_id" required>
                            <?php
                            $atendentes = getAtendentes($conn);
                            while ($atendente = $atendentes->fetch_assoc()) {
                                echo "<option value='{$atendente['id']}'>{$atendente['nome']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-servico-serv-id">Serviço:</label>
                        <select class="form-control" id="edit-servico-serv-id" name="servico_id" required>
                            <?php
                            $servicos = getServicosList($conn); // Re-fetching the services for dropdown
                            while ($servico = $servicos->fetch_assoc()) {
                                echo "<option value='{$servico['id']}'>{$servico['nome']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <br>
                    <button type="submit" class="btn btn-primary rounded-3">Salvar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Horário -->
<div class="modal fade" id="editHorarioModal" tabindex="-1" aria-labelledby="editHorarioLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editHorarioLabel">Editar Horário</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editHorarioForm" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <input type="hidden" name="action" value="updateHorario">
                    <input type="hidden" name="id" id="edit-horario-id">
                    <input type="hidden" name="active_tab" value="horarios">
                    <div class="form-group">
                        <label for="edit-horario-time">Horário:</label>
                        <input type="time" class="form-control" id="edit-horario-time" name="horario" required>
                    </div>
                    <br>
                    <button type="submit" class="btn btn-primary rounded-3">Salvar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Atendente -->
<div class="modal fade" id="editAtendenteModal" tabindex="-1" aria-labelledby="editAtendenteLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAtendenteLabel">Editar Atendente</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editAtendenteForm" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <input type="hidden" name="action" value="updateAtendente">
                    <input type="hidden" name="id" id="edit-atendente-id">
                    <input type="hidden" name="active_tab" value="atendentes">
                    <div class="form-group">
                        <label for="edit-atendente-nome">Nome do Atendente:</label>
                        <input type="text" class="form-control" id="edit-atendente-nome" name="nome" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-atendente-cargo">Cargo:</label>
                        <input type="text" class="form-control" id="edit-atendente-cargo" name="cargo" required>
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="edit-atendente-disponivel" name="disponivel">
                        <label class="form-check-label" for="edit-atendente-disponivel">Disponível</label>
                    </div>
                    <br>
                    <button type="submit" class="btn btn-primary rounded-3">Salvar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Usuário -->
<div class="modal fade" id="editUsuarioModal" tabindex="-1" aria-labelledby="editUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUsuarioLabel">Editar Usuário</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editUsuarioForm" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <input type="hidden" name="action" value="updateUser">
                    <input type="hidden" name="id" id="edit-usuario-id">
                    <input type="hidden" name="active_tab" value="usuarios">
                    <div class="form-group">
                        <label for="edit-usuario-username">Nome de Usuário:</label>
                        <input type="text" class="form-control" id="edit-usuario-username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-usuario-email">E-mail:</label>
                        <input type="email" class="form-control" id="edit-usuario-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-usuario-password">Nova Senha (deixe em branco para manter a atual):</label>
                        <input type="password" class="form-control" id="edit-usuario-password" name="password">
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" name="super_admin" id="edit-super-admin" value="1">
                        <label class="form-check-label" for="edit-super-admin">Super Administrador (acesso total)</label>
                    </div>
                    <div class="form-group">
                        <label>Permissões de Acesso:</label>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="inicio" id="edit-usuario-perm-inicio">
                            <label class="form-check-label" for="edit-usuario-perm-inicio">Dashboard</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="kanban" id="edit-usuario-perm-kanban">
                            <label class="form-check-label" for="edit-usuario-perm-kanban">Atendimentos</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="agendamentos" id="edit-usuario-perm-agendamentos">
                            <label class="form-check-label" for="edit-usuario-perm-agendamentos">Agendamentos</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="cadastro" id="edit-usuario-perm-cadastro">
                            <label class="form-check-label" for="edit-usuario-perm-cadastro">Cadastro</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="servicos" id="edit-usuario-perm-servicos">
                            <label class="form-check-label" for="edit-usuario-perm-servicos">Serviços</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="horarios" id="edit-usuario-perm-horarios">
                            <label class="form-check-label" for="edit-usuario-perm-horarios">Horários</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="atendentes" id="edit-usuario-perm-atendentes">
                            <label class="form-check-label" for="edit-usuario-perm-atendentes">Atendentes</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="usuarios" id="edit-usuario-perm-usuarios">
                            <label class="form-check-label" for="edit-usuario-perm-usuarios">Usuários</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="relatorios" id="edit-perm-relatorios">
                            <label class="form-check-label" for="edit-perm-relatorios">Relatórios</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="customizacao" id="edit-usuario-perm-customizacao">
                            <label class="form-check-label" for="edit-usuario-perm-customizacao">Customização</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="permissions[]" value="configuracoes" id="edit-usuario-perm-configuracoes">
                            <label class="form-check-label" for="edit-usuario-perm-configuracoes">Configurações</label>
                        </div>
                    </div>
                    <br>
                    <button type="submit" class="btn btn-primary rounded-3">Salvar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Mensagem -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="messageModalLabel">Enviar Mensagem</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="templateMessage">Selecione uma mensagem padrão:</label>
                    <select class="form-control" id="templateMessage">
                        <option value="">Selecione uma mensagem...</option>
                        <?php
                        $sql = "SELECT id, nome_mensagem, template FROM mensagens_padronizadas";
                        $result = mysqli_query($conn, $sql);
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<option value='" . htmlspecialchars($row['template']) . "'>" . htmlspecialchars($row['nome_mensagem']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="message">Mensagem:</label>
                    <textarea class="form-control" id="message" rows="4"></textarea>
                </div>
                <input type="hidden" id="phoneNumber">
                <input type="hidden" id="appointmentData">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" onclick="sendMessage()">Enviar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Mensagem -->
<div class="modal fade" id="editMensagemModal" tabindex="-1" aria-labelledby="editMensagemLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMensagemLabel">Editar Mensagem</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" name="action" value="updateMensagem">
                    <input type="hidden" name="id" id="edit-mensagem-id">
                    <input type="hidden" name="active_tab" value="configuracoes">
                    <input type="hidden" name="active_section" value="mensagens">
                    
                    <div class="form-group">
                        <label for="edit-mensagem-nome">Nome da Mensagem:</label>
                        <input type="text" class="form-control" id="edit-mensagem-nome" name="nome_mensagem" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-mensagem-template">Template da Mensagem:</label>
                        <textarea class="form-control" id="edit-mensagem-template" name="template" rows="4" required></textarea>
                        <small class="form-text text-muted">
                            Variáveis disponíveis: $nome, $data_hora, $atendente, $servico
                        </small>
                    </div>
                    <br>
                    <button type="submit" class="btn btn-primary rounded-3">Salvar</button>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Scripts principais -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<!-- Scripts da aplicação -->
<script src="assets/js/kanban.js"></script>
<script src="assets/js/clock.js"></script>
<script src="assets/js/alerts.js"></script>
<script src="assets/js/init.js"></script>
<script src="assets/js/sidebar.js"></script>
<script src="assets/js/whatsapp-message.js"></script>
<script src="assets/js/horarios_atendente.js"></script>
<script src="assets/js/message-modal.js"></script>
<script src="assets/js/edit.js"></script>

<script>
function showAlert(title, text, icon) {
    Swal.fire({
        title: title,
        text: text,
        icon: icon
    });
}
</script>
<script>
// Função para atualizar o relógio
function atualizarRelogio() {
    const now = new Date();
    const data = now.toLocaleDateString('pt-BR');
    const opcoes = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
    const hora = now.toLocaleTimeString('pt-BR', opcoes);
    document.getElementById('relogio').textContent = `${data} ${hora}`;
}

    // Atualiza o relógio a cada segundo
    setInterval(atualizarRelogio, 1000);
</script>
<script src="js/notifications.js"></script>
<script src="js/edit.js"></script>
<script src="assets/js/horarios_atendente.js"></script>
<script src="assets/js/message-modal.js"></script>
<script src="assets/js/cadastro.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Relatórios -->
<script src="assets/js/relatorios.js"></script>
<footer>
    <div class="footer">
        <p><?php echo isset($customizacao['footer_text']) ? $customizacao['footer_text'] : 'Copyright  2025 Agenda AtrativoZap '; ?></p>
    </div>
</footer>

<!-- No final do body, após os outros scripts -->
<script src="assets/js/responsive-menu.js"></script>
<script>
document.querySelector('form[action="updateCustomization"]')?.addEventListener('submit', function(e) {
    // Debug temporário
    console.log('Form data:', new FormData(this));
});
</script>
<!-- No final do body -->
<script src="assets/js/customization.js"></script>
</body>
</html>