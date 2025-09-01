<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$current_user = wp_get_current_user();

// Verificar se o usuário está logado
if (!is_user_logged_in()) {
    wp_die('Você precisa estar logado para acessar esta página.');
}

// Determinar nível de acesso baseado no role do usuário
$user_roles = $current_user->roles;
$is_owner = false;
$is_professional = false;
$is_client = false;

if (in_array('administrator', $user_roles) || in_array('editor', $user_roles) || in_array('author', $user_roles)) {
    $is_owner = true;
} elseif (in_array('contributor', $user_roles)) {
    $is_professional = true;
} elseif (in_array('subscriber', $user_roles)) {
    $is_client = true;
}

if (!$is_owner && !$is_professional && !$is_client) {
    wp_die('Acesso negado. Você não tem um role válido para acessar esta página.');
}

$success_message = '';
$error_message = '';

// Verificar se as tabelas existem
$table_negocios = $wpdb->prefix . 'inbwp_negocios';
$table_catalogo = $wpdb->prefix . 'inbwp_catalogo';
$table_financeiro = $wpdb->prefix . 'inbwp_financeiro';
$table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_negocios'") == $table_negocios);

if (!$table_exists) {
    $error_message = 'Tabela de negócios não existe. Por favor, desative e reative o plugin.';
} else {
    // Processar atualização de status
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        if (isset($_POST['inbwp_nonce']) && wp_verify_nonce($_POST['inbwp_nonce'], 'inbwp_negocios')) {
            $negocio_id = intval($_POST['negocio_id']);
            $novo_status = sanitize_text_field($_POST['novo_status']);
            
            $where_conditions = array('id' => $negocio_id);
            if (!$is_owner) {
                if ($is_professional) {
                    $where_conditions['servidor_id'] = $current_user->ID;
                } else {
                    $where_conditions['cliente_id'] = $current_user->ID;
                }
            }
            
            $result = $wpdb->update(
                $table_negocios,
                array('status' => $novo_status),
                $where_conditions,
                array('%s'),
                array('%d', '%d')
            );
            
            if ($result !== false) {
                $success_message = 'Status atualizado com sucesso!';
            } else {
                $error_message = 'Erro ao atualizar status ou negócio não encontrado.';
            }
        }
    }
}

// Buscar estatísticas para os cards
$stats_where = '';
$stats_values = array();

if ($is_professional) {
    $stats_where = 'WHERE servidor_id = %d';
    $stats_values[] = $current_user->ID;
} elseif ($is_client) {
    $stats_where = 'WHERE cliente_id = %d';
    $stats_values[] = $current_user->ID;
}

$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as ativos,
    SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos,
    SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as cancelados
    FROM $table_negocios $stats_where";

if (!empty($stats_values)) {
    $stats = $wpdb->get_row($wpdb->prepare($stats_query, $stats_values));
} else {
    $stats = $wpdb->get_row($stats_query);
}

// Buscar negócios baseado no role do usuário
$where_conditions = array();
$where_values = array();

// Filtro por role
if ($is_professional) {
    $where_conditions[] = 'n.servidor_id = %d';
    $where_values[] = $current_user->ID;
} elseif ($is_client) {
    $where_conditions[] = 'n.cliente_id = %d';
    $where_values[] = $current_user->ID;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$query = "
    SELECT n.*, 
           c.display_name as cliente_nome, c.user_email as cliente_email,
           s.display_name as servidor_nome, s.user_email as servidor_email
    FROM $table_negocios n
    LEFT JOIN {$wpdb->users} c ON n.cliente_id = c.ID 
    LEFT JOIN {$wpdb->users} s ON n.servidor_id = s.ID
    $where_clause
    ORDER BY n.created_at DESC
";

if (!empty($where_values)) {
    $negocios = $wpdb->get_results($wpdb->prepare($query, $where_values));
} else {
    $negocios = $wpdb->get_results($query);
}

// Buscar clientes (subscribers) para o modal
$clientes = get_users(array('role' => 'subscriber'));

// Buscar profissionais (contributors) para o modal
$profissionais = get_users(array('role' => 'contributor'));

// Buscar itens do catálogo
$catalogo_items = $wpdb->get_results("SELECT * FROM $table_catalogo WHERE status = 'ativo' ORDER BY nome");
?>

<div class="wrap">
    <h1>Negócios</h1>
    
    <?php if ($success_message): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($success_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="stats-cards">
        <div class="stats-card">
            <div class="stats-number"><?php echo intval($stats->total); ?></div>
            <div class="stats-label">Total de Negócios</div>
        </div>
        <div class="stats-card">
            <div class="stats-number"><?php echo intval($stats->ativos); ?></div>
            <div class="stats-label">Ativos</div>
        </div>
        <div class="stats-card">
            <div class="stats-number"><?php echo intval($stats->concluidos); ?></div>
            <div class="stats-label">Concluídos</div>
        </div>
        <div class="stats-card">
            <div class="stats-number"><?php echo intval($stats->cancelados); ?></div>
            <div class="stats-label">Cancelados</div>
        </div>
    </div>
    
    <?php if ($is_owner || $is_professional): ?>
    <div class="tablenav top">
        <div class="alignleft actions">
            <button type="button" class="button button-primary" id="btn-novo-negocio">Novo Negócio</button>
        </div>
    </div>
    <?php endif; ?>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column">ID</th>
                <th scope="col" class="manage-column" style="width: 15%;">Cliente</th>
                <th scope="col" class="manage-column" style="width: 15%;">Profissional</th>
                <th scope="col" class="manage-column">Valor Total</th>
                <th scope="col" class="manage-column">Status</th>
                <th scope="col" class="manage-column">Data Criação</th>
                <th scope="col" class="manage-column" style="width: 20%;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($negocios)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 20px;">
                        Nenhum negócio encontrado.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($negocios as $negocio): ?>
                    <tr>
                        <td><?php echo esc_html($negocio->id); ?></td>
                        <td><?php echo esc_html($negocio->cliente_nome ?: 'N/A'); ?></td>
                        <td><?php echo esc_html($negocio->servidor_nome ?: 'N/A'); ?></td>
                        <td>R$ <?php echo number_format($negocio->valor_total, 2, ',', '.'); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($negocio->status); ?>">
                                <?php echo esc_html(ucfirst($negocio->status)); ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($negocio->created_at)); ?></td>
                        <td>
                            <button type="button" class="button button-small btn-detalhes" data-id="<?php echo esc_attr($negocio->id); ?>">
                                Detalhes
                            </button>
                            <?php if ($is_owner || ($is_professional && $negocio->servidor_id == $current_user->ID)): ?>
                                <form method="post" style="display: inline-block; margin-left: 5px;">
                                    <?php wp_nonce_field('inbwp_negocios', 'inbwp_nonce'); ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="negocio_id" value="<?php echo esc_attr($negocio->id); ?>">
                                    <select name="novo_status" onchange="this.form.submit()" class="small-text">
                                        <option value="ativo" <?php selected($negocio->status, 'ativo'); ?>>Ativo</option>
                                        <option value="concluido" <?php selected($negocio->status, 'concluido'); ?>>Concluído</option>
                                        <option value="cancelado" <?php selected($negocio->status, 'cancelado'); ?>>Cancelado</option>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="modal-novo-negocio" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Novo Negócio</h2>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <div id="etapa-1" class="etapa">
                <h3>Etapa 1: Seleção de Cliente e Itens</h3>
                
                <div class="cliente-section">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="cliente_id">Cliente</label></th>
                            <td>
                                <select id="cliente_id" name="cliente_id" required class="regular-text">
                                    <option value="">Selecione um cliente</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?php echo esc_attr($cliente->ID); ?>">
                                            <?php echo esc_html($cliente->display_name . ' (' . $cliente->user_email . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="items-layout">
                    <div class="item-form-section">
                        <h4>Adicionar Item</h4>
                        <form id="form-item">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="item_catalogo">Item do Catálogo</label></th>
                                    <td>
                                        <select id="item_catalogo" name="item_catalogo" required class="regular-text">
                                            <option value="">Selecione um item</option>
                                            <?php foreach ($catalogo_items as $item): ?>
                                                <option value="<?php echo esc_attr($item->id); ?>" 
                                                        data-preco="<?php echo esc_attr($item->preco); ?>" 
                                                        data-nome="<?php echo esc_attr($item->nome); ?>" 
                                                        data-descricao="<?php echo esc_attr($item->descricao); ?>">
                                                    <?php echo esc_html($item->nome . ' - R$ ' . number_format($item->preco, 2, ',', '.')); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="servidor_id">Profissional Disponível</label></th>
                                    <td>
                                        <select id="servidor_id" name="servidor_id" required class="regular-text">
                                            <option value="">Selecione um profissional</option>
                                            <?php foreach ($profissionais as $profissional): ?>
                                                <option value="<?php echo esc_attr($profissional->ID); ?>">
                                                    <?php echo esc_html($profissional->display_name . ' (' . $profissional->user_email . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="data_servico">Data do Serviço</label></th>
                                    <td>
                                        <input type="date" id="data_servico" name="data_servico" required class="regular-text" min="<?php echo date('Y-m-d'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="periodo_servico">Período do Serviço</label></th>
                                    <td>
                                        <select id="periodo_servico" name="periodo_servico" required class="regular-text">
                                            <option value="">Selecione o período</option>
                                            <option value="1_dia" data-multiplicador="1" data-desconto="0">1 Dia (sem desconto)</option>
                                            <option value="1_semana" data-multiplicador="7" data-desconto="5">1 Semana (5% desconto)</option>
                                            <option value="1_mes" data-multiplicador="30" data-desconto="15">1 Mês (15% desconto)</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="valor_item">Valor do Item</label></th>
                                    <td>
                                        <input type="text" id="valor_item" name="valor_item" readonly class="regular-text" placeholder="R$ 0,00">
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" id="btn-escolher-item" class="button button-secondary">Escolher Item</button>
                            </p>
                        </form>
                    </div>

                    <div class="selected-items-section">
                        <h4>Itens Selecionados</h4>
                        <div id="lista-itens-selecionados">
                            <p class="no-items">Nenhum item selecionado</p>
                        </div>
                        <div class="total-section">
                            <strong>Valor Total: <span id="valor-total-negocio">R$ 0,00</span></strong>
                        </div>
                    </div>
                </div>

                <p class="submit">
                    <button type="button" id="btn-proximo-etapa-2" class="button button-primary" disabled>Próximo</button>
                </p>
            </div>

            <div id="etapa-2" class="etapa" style="display: none;">
                <h3>Etapa 2: Confirmação de Endereço</h3>
                <form id="form-etapa-2">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="cep">CEP</label></th>
                            <td>
                                <input type="text" id="cep" name="cep" class="regular-text" placeholder="00000-000" maxlength="9">
                                <button type="button" id="btn-buscar-cep" class="button">Buscar CEP</button>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="logradouro">Logradouro</label></th>
                            <td>
                                <input type="text" id="logradouro" name="logradouro" required class="regular-text" placeholder="Rua, Avenida, etc.">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="numero">Número</label></th>
                            <td>
                                <input type="text" id="numero" name="numero" required class="regular-text" placeholder="123">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="complemento">Complemento</label></th>
                            <td>
                                <input type="text" id="complemento" name="complemento" class="regular-text" placeholder="Apto, Bloco, etc.">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bairro">Bairro</label></th>
                            <td>
                                <input type="text" id="bairro" name="bairro" required class="regular-text" placeholder="Nome do bairro">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cidade">Cidade</label></th>
                            <td>
                                <input type="text" id="cidade" name="cidade" required class="regular-text" placeholder="Nome da cidade">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="estado">Estado</label></th>
                            <td>
                                <select id="estado" name="estado" required class="regular-text">
                                    <option value="">Selecione o estado</option>
                                    <option value="AC">Acre</option>
                                    <option value="AL">Alagoas</option>
                                    <option value="AP">Amapá</option>
                                    <option value="AM">Amazonas</option>
                                    <option value="BA">Bahia</option>
                                    <option value="CE">Ceará</option>
                                    <option value="DF">Distrito Federal</option>
                                    <option value="ES">Espírito Santo</option>
                                    <option value="GO">Goiás</option>
                                    <option value="MA">Maranhão</option>
                                    <option value="MT">Mato Grosso</option>
                                    <option value="MS">Mato Grosso do Sul</option>
                                    <option value="MG">Minas Gerais</option>
                                    <option value="PA">Pará</option>
                                    <option value="PB">Paraíba</option>
                                    <option value="PR">Paraná</option>
                                    <option value="PE">Pernambuco</option>
                                    <option value="PI">Piauí</option>
                                    <option value="RJ">Rio de Janeiro</option>
                                    <option value="RN">Rio Grande do Norte</option>
                                    <option value="RS">Rio Grande do Sul</option>
                                    <option value="RO">Rondônia</option>
                                    <option value="RR">Roraima</option>
                                    <option value="SC">Santa Catarina</option>
                                    <option value="SP">São Paulo</option>
                                    <option value="SE">Sergipe</option>
                                    <option value="TO">Tocantins</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="button" id="btn-voltar-etapa-1" class="button">Voltar</button>
                        <button type="button" id="btn-proximo-etapa-3" class="button button-primary">Próximo</button>
                    </p>
                </form>
            </div>

            <div id="etapa-3" class="etapa" style="display: none;">
                <h3>Etapa 3: Termos de Serviço</h3>
                <form id="form-etapa-3">
                    <div class="termos-container">
                        <h4>Termos e Condições de Serviço</h4>
                        <div class="termos-content">
                            <p><strong>1. OBJETO DO CONTRATO</strong></p>
                            <p>O presente contrato tem por objeto a prestação de serviços conforme especificado nos itens selecionados, nas condições e prazos aqui estabelecidos.</p>
                            
                            <p><strong>2. OBRIGAÇÕES DO PRESTADOR</strong></p>
                            <p>2.1. Executar o serviço com qualidade, pontualidade e profissionalismo;</p>
                            <p>2.2. Cumprir os prazos estabelecidos;</p>
                            <p>2.3. Manter sigilo sobre informações confidenciais do cliente.</p>
                            
                            <p><strong>3. OBRIGAÇÕES DO CLIENTE</strong></p>
                            <p>3.1. Fornecer todas as informações necessárias para a execução do serviço;</p>
                            <p>3.2. Disponibilizar o local e condições adequadas para a prestação do serviço;</p>
                            <p>3.3. Efetuar o pagamento conforme acordado.</p>
                            
                            <p><strong>4. PAGAMENTO</strong></p>
                            <p>4.1. O pagamento será realizado conforme valor e condições estabelecidas;</p>
                            <p>4.2. Atrasos no pagamento poderão acarretar em juros e multa.</p>
                            
                            <p><strong>5. CANCELAMENTO</strong></p>
                            <p>5.1. Cancelamentos devem ser comunicados com antecedência mínima de 24 horas;</p>
                            <p>5.2. Cancelamentos em cima da hora poderão acarretar em cobrança de taxa.</p>
                            
                            <p><strong>6. DISPOSIÇÕES GERAIS</strong></p>
                            <p>6.1. Este contrato é regido pelas leis brasileiras;</p>
                            <p>6.2. Eventuais alterações devem ser acordadas por ambas as partes.</p>
                        </div>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"></th>
                            <td>
                                <label class="aceite-termos">
                                    <input type="checkbox" id="aceita_termos" name="aceita_termos" value="1" required>
                                    <strong>Eu li e aceito os termos e condições de serviço acima descritos</strong>
                                </label>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="button" id="btn-voltar-etapa-2" class="button">Voltar</button>
                        <button type="submit" id="btn-finalizar-negocio" class="button button-primary">Finalizar Negócio</button>
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="modal-detalhes-negocio" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Detalhes do Negócio</h2>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <div id="detalhes-content">
            </div>
        </div>
    </div>
</div>

<style>
/* Cards simples - estilo Catálogo */
.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stats-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: box-shadow 0.3s ease;
}

.stats-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.stats-number {
    font-size: 2.5em;
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
}

.stats-label {
    font-size: 0.9em;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Modal */
.modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: 2% auto;
    padding: 0;
    border: 1px solid #888;
    width: 95%;
    max-width: 1200px;
    border-radius: 8px;
    max-height: 95vh;
    overflow-y: auto;
}

.modal-header {
    padding: 20px;
    background-color: #f1f1f1;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 8px 8px 0 0;
}

.modal-header h2 {
    margin: 0;
    color: #333;
}

.modal-body {
    padding: 30px;
}

.close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.2s;
}

.close:hover,
.close:focus {
    color: #333;
}

/* Etapas do Modal */
.etapa {
    margin-bottom: 20px;
}

.etapa h3 {
    color: #0073aa;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

/* Layout da Etapa 1 */
.cliente-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    border-left: 4px solid #0073aa;
}

.items-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 20px;
}

.item-form-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
}

.item-form-section h4 {
    margin-top: 0;
    color: #333;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}

.selected-items-section {
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
}

.selected-items-section h4 {
    margin-top: 0;
    color: #333;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}

#lista-itens-selecionados {
    min-height: 200px;
    max-height: 400px;
    overflow-y: auto;
    margin-bottom: 20px;
}

.no-items {
    text-align: center;
    color: #666;
    font-style: italic;
    padding: 40px 20px;
}

.item-selecionado {
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 10px;
    position: relative;
}

.item-selecionado h5 {
    margin: 0 0 10px 0;
    color: #0073aa;
    font-size: 1.1em;
}

.item-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    font-size: 0.9em;
    color: #666;
}

.item-valor {
    font-weight: bold;
    color: #28a745;
    font-size: 1.1em;
    margin-top: 10px;
}

.btn-remover-item {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 3px;
    padding: 5px 8px;
    cursor: pointer;
    font-size: 12px;
}

.btn-remover-item:hover {
    background: #c82333;
}

.total-section {
    background: white;
    padding: 15px;
    border-radius: 5px;
    border: 2px solid #0073aa;
    text-align: center;
    font-size: 1.2em;
}

/* Termos de Serviço */
.termos-container {
    border: 2px solid #0073aa;
    border-radius: 8px;
    margin-bottom: 20px;
    background: #f8f9fa;
}

.termos-container h4 {
    background: #0073aa;
    color: white;
    margin: 0;
    padding: 15px 20px;
    border-radius: 6px 6px 0 0;
    font-size: 1.1em;
}

.termos-content {
    padding: 20px;
    max-height: 300px;
    overflow-y: auto;
    line-height: 1.6;
}

.termos-content p {
    margin-bottom: 10px;
}

.termos-content strong {
    color: #0073aa;
}

.aceite-termos {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px;
    background: #e7f3ff;
    border: 1px solid #0073aa;
    border-radius: 5px;
    cursor: pointer;
}

.aceite-termos input[type="checkbox"] {
    transform: scale(1.2);
}

/* Status badges */
.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-ativo {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-concluido {
    background-color: #cce5ff;
    color: #004085;
    border: 1px solid #b3d7ff;
}

.status-cancelado {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f1b0b7;
}

/* Detalhes do Negócio */
#detalhes-content {
    line-height: 1.6;
}

#detalhes-content .negocio-header {
    background: linear-gradient(135deg, #0073aa, #005a87);
    color: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    text-align: center;
}

#detalhes-content .negocio-header h3 {
    margin: 0 0 10px 0;
    font-size: 1.5em;
}

#detalhes-content .negocio-status {
    display: inline-block;
    padding: 5px 15px;
    border-radius: 20px;
    background: rgba(255,255,255,0.2);
    font-weight: bold;
}

#detalhes-content .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

#detalhes-content .info-section {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
}

#detalhes-content .info-section h4 {
    background: #e9ecef;
    margin: 0;
    padding: 15px 20px;
    color: #495057;
    font-size: 1.1em;
    border-bottom: 1px solid #dee2e6;
}

#detalhes-content .info-content {
    padding: 20px;
}

#detalhes-content .info-row {
    display: flex;
    margin-bottom: 12px;
    align-items: flex-start;
}

#detalhes-content .info-row:last-child {
    margin-bottom: 0;
}

#detalhes-content .info-label {
    font-weight: 600;
    min-width: 120px;
    color: #495057;
    margin-right: 15px;
}

#detalhes-content .info-value {
    flex: 1;
    color: #212529;
}

#detalhes-content .valor-destaque {
    font-size: 1.2em;
    font-weight: bold;
    color: #28a745;
}

/* Estilos para cards de serviço no modal de detalhes */
.servico-item-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.servico-item-header {
    background: linear-gradient(135deg, #0073aa, #005a87);
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.servico-item-header h5 {
    margin: 0;
    font-size: 1.1em;
    font-weight: 600;
}

.servico-valor-badge {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 0.9em;
    border: 1px solid rgba(255,255,255,0.3);
}

.servico-item-body {
    padding: 20px;
}

.servico-item-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.servico-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.field-label {
    font-weight: 600;
    color: #495057;
    font-size: 0.9em;
}

.field-value {
    color: #212529;
    font-size: 0.95em;
    padding: 10px 12px;
    background: #f8f9fa;
    border-radius: 5px;
    border-left: 3px solid #0073aa;
}

.field-value.valor-destaque {
    background: #e8f5e8;
    border-left-color: #28a745;
    font-weight: bold;
    color: #155724;
}

/* Estilos para detalhes do endereço */
.endereco-detalhes-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.endereco-detalhes-grid {
    padding: 20px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    border-bottom: 1px solid #eee;
}

.endereco-detail-row {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.detail-label {
    font-weight: 600;
    color: #495057;
    font-size: 0.9em;
}

.detail-value {
    color: #212529;
    font-size: 0.95em;
    padding: 8px 12px;
    background: #f8f9fa;
    border-radius: 4px;
    border-left: 3px solid #0073aa;
}

.endereco-completo-preview {
    padding: 20px;
    background: #f8f9fa;
}

.endereco-completo-preview strong {
    color: #0073aa;
    display: block;
    margin-bottom: 10px;
}

.endereco-formatado {
    background: white;
    padding: 15px;
    border-radius: 5px;
    border-left: 4px solid #0073aa;
    line-height: 1.6;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Responsividade */
@media (max-width: 768px) {
    .stats-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .modal-content {
        width: 98%;
        margin: 1% auto;
    }
    
    .items-layout {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    #detalhes-content .info-grid {
        grid-template-columns: 1fr;
    }
    
    .item-info,
    .servico-item-grid,
    .endereco-detalhes-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .servico-item-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .servico-valor-badge {
        align-self: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    let itensSelecionados = [];
    let valorTotalNegocio = 0;

    // Abrir modal novo negócio
    $('#btn-novo-negocio').click(function() {
        $('#modal-novo-negocio').show();
        $('#etapa-1').show();
        $('#etapa-2, #etapa-3').hide();
        
        // Resetar tudo
        $('#cliente_id').val('');
        $('#form-item')[0].reset();
        $('#form-etapa-2')[0].reset();
        $('#form-etapa-3')[0].reset();
        itensSelecionados = [];
        valorTotalNegocio = 0;
        atualizarListaItens();
        $('#btn-proximo-etapa-2').prop('disabled', true);
    });

    // Fechar modais
    $('.close').click(function() {
        $('.modal').hide();
    });

    // Fechar modal clicando fora
    $(window).click(function(event) {
        if ($(event.target).hasClass('modal')) {
            $('.modal').hide();
        }
    });

    // Calcular valor do item baseado no item e período
    $('#item_catalogo, #periodo_servico').change(function() {
        var itemSelecionado = $('#item_catalogo option:selected');
        var periodoSelecionado = $('#periodo_servico option:selected');
        
        if (itemSelecionado.val() && periodoSelecionado.val()) {
            var precoBase = parseFloat(itemSelecionado.data('preco'));
            var multiplicador = parseInt(periodoSelecionado.data('multiplicador'));
            var desconto = parseInt(periodoSelecionado.data('desconto'));
            
            var valorBruto = precoBase * multiplicador;
            var valorDesconto = valorBruto * (desconto / 100);
            var valorFinal = valorBruto - valorDesconto;
            
            $('#valor_item').val('R$ ' + valorFinal.toFixed(2).replace('.', ','));
        } else {
            $('#valor_item').val('');
        }
    });

    // Escolher item
    $('#btn-escolher-item').click(function() {
        var clienteId = $('#cliente_id').val();
        var itemId = $('#item_catalogo').val();
        var servidorId = $('#servidor_id').val();
        var dataServico = $('#data_servico').val();
        var periodo = $('#periodo_servico').val();

        if (!clienteId) {
            alert('Selecione um cliente primeiro');
            return;
        }

        if (!itemId || !servidorId || !dataServico || !periodo) {
            alert('Preencha todos os campos do item');
            return;
        }

        var itemSelecionado = $('#item_catalogo option:selected');
        var periodoSelecionado = $('#periodo_servico option:selected');
        var servidorSelecionado = $('#servidor_id option:selected');
        
        var precoBase = parseFloat(itemSelecionado.data('preco'));
        var multiplicador = parseInt(periodoSelecionado.data('multiplicador'));
        var desconto = parseInt(periodoSelecionado.data('desconto'));
        
        var valorBruto = precoBase * multiplicador;
        var valorDesconto = valorBruto * (desconto / 100);
        var valorFinal = valorBruto - valorDesconto;

        var novoItem = {
            item_id: itemId,
            item_nome: itemSelecionado.data('nome'),
            item_descricao: itemSelecionado.data('descricao'),
            servidor_id: servidorId,
            servidor_nome: servidorSelecionado.text(),
            data_servico: dataServico,
            periodo: periodo,
            periodo_texto: periodoSelecionado.text(),
            preco_base: precoBase,
            multiplicador: multiplicador,
            desconto_percentual: desconto,
            valor_bruto: valorBruto,
            valor_desconto: valorDesconto,
            valor_final: valorFinal
        };

        itensSelecionados.push(novoItem);
        valorTotalNegocio += valorFinal;
        
        atualizarListaItens();
        
        // Limpar formulário do item
        $('#form-item')[0].reset();
        $('#valor_item').val('');
        
        // Habilitar botão próximo
        $('#btn-proximo-etapa-2').prop('disabled', false);
    });

    // Função para atualizar a lista de itens
    function atualizarListaItens() {
        var container = $('#lista-itens-selecionados');
        
        if (itensSelecionados.length === 0) {
            container.html('<p class="no-items">Nenhum item selecionado</p>');
        } else {
            var html = '';
            itensSelecionados.forEach(function(item, index) {
                html += `
                    <div class="item-selecionado">
                        <button class="btn-remover-item" data-index="${index}">×</button>
                        <h5>${item.item_nome}</h5>
                        <div class="item-info">
                            <div><strong>Profissional:</strong> ${item.servidor_nome.split('(')[0].trim()}</div>
                            <div><strong>Data:</strong> ${new Date(item.data_servico).toLocaleDateString('pt-BR')}</div>
                            <div><strong>Período:</strong> ${item.periodo_texto}</div>
                            <div><strong>Desconto:</strong> ${item.desconto_percentual}%</div>
                        </div>
                        <div class="item-valor">R$ ${item.valor_final.toFixed(2).replace('.', ',')}</div>
                    </div>
                `;
            });
            container.html(html);
        }
        
        $('#valor-total-negocio').text('R$ ' + valorTotalNegocio.toFixed(2).replace('.', ','));
    }

    // Remover item
    $(document).on('click', '.btn-remover-item', function() {
        var index = $(this).data('index');
        valorTotalNegocio -= itensSelecionados[index].valor_final;
        itensSelecionados.splice(index, 1);
        atualizarListaItens();
        
        if (itensSelecionados.length === 0) {
            $('#btn-proximo-etapa-2').prop('disabled', true);
        }
    });

    // Máscara para CEP
    $('#cep').on('input', function() {
        var value = $(this).val().replace(/\D/g, '');
        value = value.replace(/^(\d{5})(\d)/, '$1-$2');
        $(this).val(value);
    });

    // Buscar CEP
    $('#btn-buscar-cep').click(function() {
        var cep = $('#cep').val().replace(/\D/g, '');
        
        if (cep.length !== 8) {
            alert('CEP deve ter 8 dígitos');
            return;
        }
        
        $(this).prop('disabled', true).text('Buscando...');
        
        $.getJSON('https://viacep.com.br/ws/' + cep + '/json/', function(data) {
            if (!data.erro) {
                $('#logradouro').val(data.logradouro);
                $('#bairro').val(data.bairro);
                $('#cidade').val(data.localidade);
                $('#estado').val(data.uf);
            } else {
                alert('CEP não encontrado');
            }
        }).fail(function() {
            alert('Erro ao buscar CEP');
        }).always(function() {
            $('#btn-buscar-cep').prop('disabled', false).text('Buscar CEP');
        });
    });

    // Navegação entre etapas
    $('#btn-proximo-etapa-2').click(function() {
        if (itensSelecionados.length === 0) {
            alert('Adicione pelo menos um item antes de continuar');
            return;
        }
        
        $('#etapa-1').hide();
        $('#etapa-2').show();
    });

    $('#btn-voltar-etapa-1').click(function() {
        $('#etapa-2').hide();
        $('#etapa-1').show();
    });

    $('#btn-proximo-etapa-3').click(function() {
        if ($('#form-etapa-2')[0].checkValidity()) {
            $('#etapa-2').hide();
            $('#etapa-3').show();
        } else {
            $('#form-etapa-2')[0].reportValidity();
        }
    });

    $('#btn-voltar-etapa-2').click(function() {
        $('#etapa-3').hide();
        $('#etapa-2').show();
    });

    // Finalizar negócio
    $('#form-etapa-3').submit(function(e) {
        e.preventDefault();
        
        if (!$('#aceita_termos').is(':checked')) {
            alert('Você deve aceitar os termos e condições para continuar.');
            return;
        }

        if (itensSelecionados.length === 0) {
            alert('Adicione pelo menos um item antes de finalizar');
            return;
        }

        // Montar endereço completo
        var enderecoCompleto = JSON.stringify({
            cep: $('#cep').val(),
            logradouro: $('#logradouro').val(),
            numero: $('#numero').val(),
            complemento: $('#complemento').val(),
            bairro: $('#bairro').val(),
            cidade: $('#cidade').val(),
            estado: $('#estado').val()
        });

        // Calcular data de início e fim baseado no primeiro item
        var primeiroItem = itensSelecionados[0];
        var dataInicio = primeiroItem.data_servico;
        var dataFim = new Date(dataInicio);
        dataFim.setDate(dataFim.getDate() + primeiroItem.multiplicador - 1);
        var dataFimFormatada = dataFim.toISOString().split('T')[0];

        $('#btn-finalizar-negocio').prop('disabled', true).text('Criando...');

        $.post(inbwp_ajax.url, {
            action: 'inbwp_create_negocio',
            nonce: inbwp_ajax.nonce,
            cliente_id: $('#cliente_id').val(),
            servidor_id: primeiroItem.servidor_id,
            itens_negocio: JSON.stringify(itensSelecionados),
            endereco_cliente: enderecoCompleto,
            data_inicio: dataInicio,
            data_fim: dataFimFormatada,
            valor_total: valorTotalNegocio,
            aceita_termos: $('#aceita_termos').is(':checked') ? 1 : 0
        }, function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                alert('Negócio criado com sucesso!');
                location.reload();
            } else {
                alert('Erro: ' + data.data);
                $('#btn-finalizar-negocio').prop('disabled', false).text('Finalizar Negócio');
            }
        });
    });

    // Abrir modal detalhes
    $('.btn-detalhes').click(function() {
        var negocioId = $(this).data('id');
        
        $.post(inbwp_ajax.url, {
            action: 'inbwp_get_negocio_details',
            nonce: inbwp_ajax.nonce,
            negocio_id: negocioId
        }, function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                var negocio = data.data;
                
                // Processar informações dos itens
                var itensInfo = '';
                try {
                    var itensData = JSON.parse(negocio.itens_negocio);
                    
                    if (Array.isArray(itensData)) {
                        itensInfo = '<div class="info-section"><h4>📋 Informações do Serviço</h4><div class="info-content">';
                        
                        itensData.forEach(function(item, index) {
                            var dataServicoFormatada = item.data_servico ? new Date(item.data_servico + 'T00:00:00').toLocaleDateString('pt-BR') : 'N/A';
                            var periodoTexto = '';
                            
                            switch(item.periodo) {
                                case '1_dia':
                                    periodoTexto = '1 Dia';
                                    break;
                                case '1_semana':
                                    periodoTexto = '1 Semana';
                                    break;
                                case '1_mes':
                                    periodoTexto = '1 Mês';
                                    break;
                                default:
                                    periodoTexto = item.periodo ? item.periodo.replace('_', ' ').toUpperCase() : 'N/A';
                            }
                            
                            itensInfo += '<div class="servico-item-card">';
                            itensInfo += '<div class="servico-item-header">';
                            itensInfo += '<h5>Serviço ' + (index + 1) + '</h5>';
                            itensInfo += '<div class="servico-valor-badge">R$ ' + (item.valor_final ? item.valor_final.toFixed(2).replace('.', ',') : '0,00') + '</div>';
                            itensInfo += '</div>';
                            itensInfo += '<div class="servico-item-body">';
                            itensInfo += '<div class="servico-item-grid">';
                            itensInfo += '<div class="servico-field">';
                            itensInfo += '<span class="field-label">📦 Item do Catálogo:</span>';
                            itensInfo += '<span class="field-value">' + (item.item_nome || 'Item não especificado') + '</span>';
                            itensInfo += '</div>';
                            itensInfo += '<div class="servico-field">';
                            itensInfo += '<span class="field-label">👨‍💼 Profissional Disponível:</span>';
                            itensInfo += '<span class="field-value">' + (item.servidor_nome ? item.servidor_nome.split('(')[0].trim() : 'Profissional não especificado') + '</span>';
                            itensInfo += '</div>';
                            itensInfo += '<div class="servico-field">';
                            itensInfo += '<span class="field-label">📅 Data do Serviço:</span>';
                            itensInfo += '<span class="field-value">' + dataServicoFormatada + '</span>';
                            itensInfo += '</div>';
                            itensInfo += '<div class="servico-field">';
                            itensInfo += '<span class="field-label">⏰ Período do Serviço:</span>';
                            itensInfo += '<span class="field-value">' + periodoTexto + '</span>';
                            itensInfo += '</div>';
                            itensInfo += '<div class="servico-field">';
                            itensInfo += '<span class="field-label">💰 Valor do Item:</span>';
                            itensInfo += '<span class="field-value valor-destaque">R$ ' + (item.valor_final ? item.valor_final.toFixed(2).replace('.', ',') : '0,00') + '</span>';
                            itensInfo += '</div>';
                            itensInfo += '</div>';
                            itensInfo += '</div>';
                            itensInfo += '</div>';
                        });
                        
                        itensInfo += '</div></div>';
                    } else {
                        // Formato antigo (objeto único)
                        var item = itensData;
                        var dataServicoFormatada = item.data_servico ? new Date(item.data_servico + 'T00:00:00').toLocaleDateString('pt-BR') : 'N/A';
                        var periodoTexto = '';
                        
                        switch(item.periodo) {
                            case '1_dia':
                                periodoTexto = '1 Dia';
                                break;
                            case '1_semana':
                                periodoTexto = '1 Semana';
                                break;
                            case '1_mes':
                                periodoTexto = '1 Mês';
                                break;
                            default:
                                periodoTexto = item.periodo ? item.periodo.replace('_', ' ').toUpperCase() : 'N/A';
                        }
                        
                        itensInfo = '<div class="info-section"><h4>📋 Informações do Serviço</h4><div class="info-content">';
                        itensInfo += '<div class="servico-item-card">';
                        itensInfo += '<div class="servico-item-header">';
                        itensInfo += '<h5>Serviço Contratado</h5>';
                        itensInfo += '<div class="servico-valor-badge">R$ ' + (item.valor_final ? item.valor_final.toFixed(2).replace('.', ',') : negocio.valor_total ? parseFloat(negocio.valor_total).toFixed(2).replace('.', ',') : '0,00') + '</div>';
                        itensInfo += '</div>';
                        itensInfo += '<div class="servico-item-body">';
                        itensInfo += '<div class="servico-item-grid">';
                        itensInfo += '<div class="servico-field">';
                        itensInfo += '<span class="field-label">📦 Item do Catálogo:</span>';
                        itensInfo += '<span class="field-value">' + (item.item_nome || 'Item não especificado') + '</span>';
                        itensInfo += '</div>';
                        itensInfo += '<div class="servico-field">';
                        itensInfo += '<span class="field-label">👨‍💼 Profissional Disponível:</span>';
                        itensInfo += '<span class="field-value">' + (negocio.servidor_nome || 'Profissional não especificado') + '</span>';
                        itensInfo += '</div>';
                        itensInfo += '<div class="servico-field">';
                        itensInfo += '<span class="field-label">📅 Data do Serviço:</span>';
                        itensInfo += '<span class="field-value">' + dataServicoFormatada + '</span>';
                        itensInfo += '</div>';
                        itensInfo += '<div class="servico-field">';
                        itensInfo += '<span class="field-label">⏰ Período do Serviço:</span>';
                        itensInfo += '<span class="field-value">' + periodoTexto + '</span>';
                        itensInfo += '</div>';
                        itensInfo += '<div class="servico-field">';
                        itensInfo += '<span class="field-label">💰 Valor do Item:</span>';
                        itensInfo += '<span class="field-value valor-destaque">R$ ' + (item.valor_final ? item.valor_final.toFixed(2).replace('.', ',') : negocio.valor_total ? parseFloat(negocio.valor_total).toFixed(2).replace('.', ',') : '0,00') + '</span>';
                        itensInfo += '</div>';
                        itensInfo += '</div>';
                        itensInfo += '</div>';
                        itensInfo += '</div>';
                        itensInfo += '</div></div>';
                    }
                } catch (e) {
                    // Se não conseguir fazer parse, mostrar estrutura básica
                    itensInfo = '<div class="info-section"><h4>📋 Informações do Serviço</h4><div class="info-content">';
                    itensInfo += '<div class="servico-item-card">';
                    itensInfo += '<div class="servico-item-header">';
                    itensInfo += '<h5>Serviço Contratado</h5>';
                    itensInfo += '<div class="servico-valor-badge">R$ ' + (negocio.valor_total ? parseFloat(negocio.valor_total).toFixed(2).replace('.', ',') : '0,00') + '</div>';
                    itensInfo += '</div>';
                    itensInfo += '<div class="servico-item-body">';
                    itensInfo += '<div class="servico-item-grid">';
                    itensInfo += '<div class="servico-field">';
                    itensInfo += '<span class="field-label">👨‍💼 Profissional:</span>';
                    itensInfo += '<span class="field-value">' + (negocio.servidor_nome || 'Não especificado') + '</span>';
                    itensInfo += '</div>';
                    itensInfo += '<div class="servico-field">';
                    itensInfo += '<span class="field-label">📅 Data de Início:</span>';
                    itensInfo += '<span class="field-value">' + new Date(negocio.data_inicio).toLocaleDateString('pt-BR') + '</span>';
                    itensInfo += '</div>';
                    itensInfo += '<div class="servico-field">';
                    itensInfo += '<span class="field-label">📅 Data de Fim:</span>';
                    itensInfo += '<span class="field-value">' + new Date(negocio.data_fim).toLocaleDateString('pt-BR') + '</span>';
                    itensInfo += '</div>';
                    itensInfo += '<div class="servico-field">';
                    itensInfo += '<span class="field-label">💰 Valor Total:</span>';
                    itensInfo += '<span class="field-value valor-destaque">R$ ' + (negocio.valor_total ? parseFloat(negocio.valor_total).toFixed(2).replace('.', ',') : '0,00') + '</span>';
                    itensInfo += '</div>';
                    itensInfo += '</div>';
                    itensInfo += '</div>';
                    itensInfo += '</div>';
                    itensInfo += '</div></div>';
                }
                
                // Processar endereço
                var enderecoInfo = '';
                try {
                    var enderecoData = JSON.parse(negocio.endereco_cliente);
                    
                    enderecoInfo = '<div class="info-section"><h4>📍 Endereço do Serviço</h4><div class="info-content">';
                    enderecoInfo += '<div class="endereco-detalhes-card">';
                    enderecoInfo += '<div class="endereco-detalhes-grid">';
                    enderecoInfo += '<div class="endereco-detail-row">';
                    enderecoInfo += '<span class="detail-label">🏠 Logradouro:</span>';
                    enderecoInfo += '<span class="detail-value">' + (enderecoData.logradouro || 'Não informado') + '</span>';
                    enderecoInfo += '</div>';
                    enderecoInfo += '<div class="endereco-detail-row">';
                    enderecoInfo += '<span class="detail-label">🔢 Número:</span>';
                    enderecoInfo += '<span class="detail-value">' + (enderecoData.numero || 'N/A') + '</span>';
                    enderecoInfo += '</div>';
                    enderecoInfo += '<div class="endereco-detail-row">';
                    enderecoInfo += '<span class="detail-label">🏢 Complemento:</span>';
                    enderecoInfo += '<span class="detail-value">' + (enderecoData.complemento || 'N/A') + '</span>';
                    enderecoInfo += '</div>';
                    enderecoInfo += '<div class="endereco-detail-row">';
                    enderecoInfo += '<span class="detail-label">🏘️ Bairro:</span>';
                    enderecoInfo += '<span class="detail-value">' + (enderecoData.bairro || 'Não informado') + '</span>';
                    enderecoInfo += '</div>';
                    enderecoInfo += '<div class="endereco-detail-row">';
                    enderecoInfo += '<span class="detail-label">🏙️ Cidade:</span>';
                    enderecoInfo += '<span class="detail-value">' + (enderecoData.cidade || 'Não informado') + '</span>';
                    enderecoInfo += '</div>';
                    enderecoInfo += '<div class="endereco-detail-row">';
                    enderecoInfo += '<span class="detail-label">🗺️ Estado:</span>';
                    enderecoInfo += '<span class="detail-value">' + (enderecoData.estado || 'N/A') + '</span>';
                    enderecoInfo += '</div>';
                    enderecoInfo += '<div class="endereco-detail-row">';
                    enderecoInfo += '<span class="detail-label">📮 CEP:</span>';
                    enderecoInfo += '<span class="detail-value">' + (enderecoData.cep || 'Não informado') + '</span>';
                    enderecoInfo += '</div>';
                    enderecoInfo += '</div>';
                    enderecoInfo += '<div class="endereco-completo-preview">';
                    enderecoInfo += '<strong>📍 Endereço Completo:</strong>';
                    enderecoInfo += '<div class="endereco-formatado">';
                    enderecoInfo += (enderecoData.logradouro ? enderecoData.logradouro : '') + (enderecoData.numero ? ', ' + enderecoData.numero : '') + (enderecoData.complemento ? ', ' + enderecoData.complemento : '') + '<br>';
                    enderecoInfo += (enderecoData.bairro ? enderecoData.bairro + '<br>' : '');
                    enderecoInfo += (enderecoData.cidade ? enderecoData.cidade : '') + (enderecoData.estado ? ' - ' + enderecoData.estado : '') + '<br>';
                    enderecoInfo += (enderecoData.cep ? 'CEP: ' + enderecoData.cep : '');
                    enderecoInfo += '</div>';
                    enderecoInfo += '</div>';
                    enderecoInfo += '</div>';
                    enderecoInfo += '</div></div>';
                } catch (e) {
                    // Se não conseguir fazer parse, mostrar estrutura básica sem dados brutos
                    enderecoInfo = '<div class="info-section"><h4>📍 Endereço do Serviço</h4><div class="info-content">';
                    enderecoInfo += '<div class="endereco-detalhes-card">';
                    enderecoInfo += '<div class="endereco-detalhes-body" style="padding: 20px; text-align: center;">';
                    enderecoInfo += '<p style="color: #666; font-style: italic;">';
                    enderecoInfo += '<span style="font-size: 1.2em;">📍</span><br>';
                    enderecoInfo += 'Endereço não informado ou formato inválido';
                    enderecoInfo += '</p>';
                    enderecoInfo += '<p style="color: #999; font-size: 0.9em; margin-top: 15px;">';
                    enderecoInfo += 'As informações de endereço podem ter sido cadastradas em um formato diferente ou não foram preenchidas durante a criação do negócio.';
                    enderecoInfo += '</p>';
                    enderecoInfo += '</div>';
                    enderecoInfo += '</div>';
                    enderecoInfo += '</div></div>';
                }
                
                var statusClass = negocio.status.toLowerCase();
                var statusText = negocio.status.charAt(0).toUpperCase() + negocio.status.slice(1);
                
                var html = '<div class="negocio-header">';
                html += '<h3>Negócio #' + negocio.id + '</h3>';
                html += '<div class="negocio-status status-' + statusClass + '">' + statusText + '</div>';
                html += '</div>';
                
                html += '<div class="info-grid">';
                html += '<div class="info-section">';
                html += '<h4>👥 Participantes</h4>';
                html += '<div class="info-content">';
                html += '<div class="info-row">';
                html += '<span class="info-label">Cliente:</span>';
                html += '<span class="info-value">' + (negocio.cliente_nome || 'N/A') + '</span>';
                html += '</div>';
                html += '<div class="info-row">';
                html += '<span class="info-label">Profissional:</span>';
                html += '<span class="info-value">' + (negocio.servidor_nome || 'N/A') + '</span>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                
                html += '<div class="info-section">';
                html += '<h4>📅 Datas</h4>';
                html += '<div class="info-content">';
                html += '<div class="info-row">';
                html += '<span class="info-label">Data Início:</span>';
                html += '<span class="info-value">' + new Date(negocio.data_inicio).toLocaleDateString('pt-BR') + '</span>';
                html += '</div>';
                html += '<div class="info-row">';
                html += '<span class="info-label">Data Fim:</span>';
                html += '<span class="info-value">' + new Date(negocio.data_fim).toLocaleDateString('pt-BR') + '</span>';
                html += '</div>';
                html += '<div class="info-row">';
                html += '<span class="info-label">Criado em:</span>';
                html += '<span class="info-value">' + new Date(negocio.created_at).toLocaleString('pt-BR') + '</span>';
                html += '</div>';
                html += '<div class="info-row">';
                html += '<span class="info-label">Valor Total:</span>';
                html += '<span class="info-value valor-destaque">R$ ' + parseFloat(negocio.valor_total).toFixed(2).replace('.', ',') + '</span>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                
                html += itensInfo;
                html += enderecoInfo;
                
                $('#detalhes-content').html(html);
                $('#modal-detalhes-negocio').show();
            } else {
                alert('Erro ao carregar detalhes: ' + data.data);
            }
        });
    });
});
</script>
