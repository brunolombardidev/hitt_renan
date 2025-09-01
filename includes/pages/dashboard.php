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

// Verificar se as tabelas existem
$table_negocios = $wpdb->prefix . 'inbwp_negocios';
$table_catalogo = $wpdb->prefix . 'inbwp_catalogo';
$table_financeiro = $wpdb->prefix . 'inbwp_financeiro';

// Buscar estatísticas baseadas no role do usuário (igual à página Negócios)
$stats_where = '';
$stats_values = array();

if ($is_professional) {
    $stats_where = 'WHERE servidor_id = %d';
    $stats_values[] = $current_user->ID;
} elseif ($is_client) {
    $stats_where = 'WHERE cliente_id = %d';
    $stats_values[] = $current_user->ID;
}

// Query igual à página Negócios para os cards principais
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

// Estatísticas financeiras
$receitas_totais_query = "SELECT COALESCE(SUM(valor_total), 0) FROM $table_negocios $stats_where AND status IN ('ativo', 'concluido')";
$despesas_totais_query = "SELECT COALESCE(SUM(valor), 0) FROM $table_financeiro f INNER JOIN $table_negocios n ON f.negocio_id = n.id $stats_where AND f.tipo = 'despesa'";

if (!empty($stats_values)) {
    $receitas_totais = $wpdb->get_var($wpdb->prepare($receitas_totais_query, $stats_values));
    $despesas_totais = $wpdb->get_var($wpdb->prepare($despesas_totais_query, $stats_values));
} else {
    $receitas_totais = $wpdb->get_var(str_replace('WHERE ', 'WHERE 1=1 AND ', $receitas_totais_query));
    $despesas_totais = $wpdb->get_var(str_replace('WHERE ', 'WHERE 1=1 AND ', $despesas_totais_query));
}

$saldo = $receitas_totais - $despesas_totais;

// Estatísticas da segunda linha
$catalogo_ativo_query = "SELECT COUNT(*) FROM $table_catalogo WHERE status = 'ativo'";
$catalogo_ativo = $wpdb->get_var($catalogo_ativo_query);
$profissionais_ativos = count(get_users(array('role' => 'contributor')));
$clientes_ativos = count(get_users(array('role' => 'subscriber')));

// Buscar negócios recentes com status "ativo"
$recent_where = str_replace('WHERE ', 'WHERE 1=1 AND ', $stats_where) . " AND n.status = 'ativo'";
$recent_query = "
    SELECT n.*, 
           c.display_name as cliente_nome,
           s.display_name as servidor_nome
    FROM $table_negocios n
    LEFT JOIN {$wpdb->users} c ON n.cliente_id = c.ID 
    LEFT JOIN {$wpdb->users} s ON n.servidor_id = s.ID
    $recent_where
    ORDER BY n.created_at DESC
    LIMIT 5
";

if (!empty($stats_values)) {
    $negocios_recentes = $wpdb->get_results($wpdb->prepare($recent_query, $stats_values));
} else {
    $negocios_recentes = $wpdb->get_results($recent_query);
}
?>

<div class="wrap">
    <h1>Dashboard</h1>
    
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <div class="card-number"><?php echo intval($stats->total); ?></div>
            <div class="card-label">Negócios do Mês</div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-number">R$ <?php echo number_format($receitas_totais, 2, ',', '.'); ?></div>
            <div class="card-label">Receitas Totais</div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-number">R$ <?php echo number_format($despesas_totais, 2, ',', '.'); ?></div>
            <div class="card-label">Despesas Totais</div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-number <?php echo $saldo >= 0 ? 'positive' : 'negative'; ?>">
                R$ <?php echo number_format($saldo, 2, ',', '.'); ?>
            </div>
            <div class="card-label">Saldo</div>
        </div>
    </div>
    
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <div class="card-number"><?php echo intval($stats->ativos); ?></div>
            <div class="card-label">Negócios Ativos</div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-number"><?php echo number_format($catalogo_ativo); ?></div>
            <div class="card-label">Catálogo Ativo</div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-number"><?php echo number_format($profissionais_ativos); ?></div>
            <div class="card-label">Profissionais Ativos</div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-number"><?php echo number_format($clientes_ativos); ?></div>
            <div class="card-label">Clientes Ativos</div>
        </div>
    </div>
    
    <div class="dashboard-section">
        <h2>Ações Rápidas</h2>
        <div class="quick-actions">
            <?php if ($is_owner || $is_professional): ?>
                <a href="<?php echo admin_url('admin.php?page=inbwp-negocios'); ?>" class="quick-action-btn">
                    <span class="action-icon">📋</span>
                    <span class="action-text">Gerenciar Negócios</span>
                </a>
            <?php endif; ?>
            
            <?php if ($is_owner || $is_professional): ?>
                <a href="<?php echo admin_url('admin.php?page=inbwp-catalogo'); ?>" class="quick-action-btn">
                    <span class="action-icon">📦</span>
                    <span class="action-text">Gerenciar Catálogo</span>
                </a>
            <?php endif; ?>
            
            <?php if ($is_owner): ?>
                <a href="<?php echo admin_url('admin.php?page=inbwp-financeiro'); ?>" class="quick-action-btn">
                    <span class="action-icon">💰</span>
                    <span class="action-text">Financeiro</span>
                </a>
            <?php endif; ?>
            
            <?php if ($is_owner): ?>
                <a href="<?php echo admin_url('admin.php?page=inbwp-usuarios'); ?>" class="quick-action-btn">
                    <span class="action-icon">👥</span>
                    <span class="action-text">Gerenciar Usuários</span>
                </a>
            <?php endif; ?>
            
            <?php if ($is_client): ?>
                <a href="<?php echo admin_url('admin.php?page=inbwp-cliente-negocios'); ?>" class="quick-action-btn">
                    <span class="action-icon">📋</span>
                    <span class="action-text">Meus Negócios</span>
                </a>
            <?php endif; ?>
            
            <?php if ($is_client): ?>
                <a href="<?php echo admin_url('admin.php?page=inbwp-cliente-catalogo'); ?>" class="quick-action-btn">
                    <span class="action-icon">🛍️</span>
                    <span class="action-text">Catálogo de Serviços</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="dashboard-section">
        <h2>Negócios Ativos Recentes</h2>
        <div class="recent-business">
            <?php if (empty($negocios_recentes)): ?>
                <p class="no-data">Nenhum negócio ativo encontrado.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Profissional</th>
                            <th>Valor</th>
                            <th>Data Criação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($negocios_recentes as $negocio): ?>
                            <tr>
                                <td>#<?php echo esc_html($negocio->id); ?></td>
                                <td><?php echo esc_html($negocio->cliente_nome ?: 'N/A'); ?></td>
                                <td><?php echo esc_html($negocio->servidor_nome ?: 'N/A'); ?></td>
                                <td>R$ <?php echo number_format($negocio->valor_total, 2, ',', '.'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($negocio->created_at)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Cards simples - estilo Catálogo */
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.dashboard-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: box-shadow 0.3s ease;
}

.dashboard-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.card-number {
    font-size: 2.5em;
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
}

.card-number.positive {
    color: #28a745;
}

.card-number.negative {
    color: #dc3545;
}

.card-label {
    font-size: 0.9em;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.dashboard-section {
    background: white;
    padding: 25px;
    border-radius: 5px;
    border: 1px solid #ddd;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin: 20px 0;
}

.dashboard-section h2 {
    color: #333;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
    margin-bottom: 20px;
    font-size: 1.3em;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    text-decoration: none;
    color: #495057;
    transition: all 0.3s ease;
}

.quick-action-btn:hover {
    background: #e9ecef;
    color: #333;
    text-decoration: none;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.action-icon {
    font-size: 1.5em;
    margin-right: 10px;
}

.action-text {
    font-weight: 500;
}

.recent-business .no-data {
    text-align: center;
    color: #666;
    font-style: italic;
    padding: 20px;
}

.recent-business table {
    margin-top: 0;
}

@media (max-width: 768px) {
    .dashboard-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .quick-actions {
        grid-template-columns: 1fr;
    }
    
    .dashboard-card {
        padding: 15px;
    }
    
    .card-number {
        font-size: 2em;
    }
}
</style>