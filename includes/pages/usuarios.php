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

// Verificar se o usuário tem permissão (apenas owners)
$user_roles = $current_user->roles;
$is_owner = false;

if (in_array('administrator', $user_roles) || in_array('editor', $user_roles) || in_array('author', $user_roles)) {
    $is_owner = true;
}

if (!$is_owner) {
    wp_die('Acesso negado. Apenas administradores podem acessar esta página.');
}

$success_message = '';
$error_message = '';

// Processar adição de usuário
if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
    if (isset($_POST['inbwp_nonce']) && wp_verify_nonce($_POST['inbwp_nonce'], 'inbwp_usuarios')) {
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $password = $_POST['password'];
        $role = sanitize_text_field($_POST['role']);
        
        if (empty($username) || empty($email) || empty($password) || empty($role)) {
            $error_message = 'Todos os campos obrigatórios devem ser preenchidos.';
        } elseif (username_exists($username)) {
            $error_message = 'Nome de usuário já existe.';
        } elseif (email_exists($email)) {
            $error_message = 'E-mail já está em uso.';
        } else {
            $user_id = wp_create_user($username, $password, $email);
            
            if (is_wp_error($user_id)) {
                $error_message = 'Erro ao criar usuário: ' . $user_id->get_error_message();
            } else {
                // Atualizar informações adicionais
                wp_update_user(array(
                    'ID' => $user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'display_name' => $first_name . ' ' . $last_name,
                    'role' => $role
                ));
                
                $success_message = 'Usuário criado com sucesso!';
            }
        }
    } else {
        $error_message = 'Falha na verificação de segurança.';
    }
}

// Processar edição de usuário
if (isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    if (isset($_POST['inbwp_nonce']) && wp_verify_nonce($_POST['inbwp_nonce'], 'inbwp_usuarios')) {
        $user_id = intval($_POST['user_id']);
        $email = sanitize_email($_POST['email']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $role = sanitize_text_field($_POST['role']);
        $password = $_POST['password'];
        
        if (empty($email) || empty($role)) {
            $error_message = 'E-mail e role são obrigatórios.';
        } else {
            $user_data = array(
                'ID' => $user_id,
                'user_email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $first_name . ' ' . $last_name,
                'role' => $role
            );
            
            if (!empty($password)) {
                $user_data['user_pass'] = $password;
            }
            
            $result = wp_update_user($user_data);
            
            if (is_wp_error($result)) {
                $error_message = 'Erro ao atualizar usuário: ' . $result->get_error_message();
            } else {
                $success_message = 'Usuário atualizado com sucesso!';
            }
        }
    }
}

// Processar exclusão de usuário
if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    if (isset($_POST['inbwp_nonce']) && wp_verify_nonce($_POST['inbwp_nonce'], 'inbwp_usuarios')) {
        $user_id = intval($_POST['user_id']);
        
        if ($user_id === $current_user->ID) {
            $error_message = 'Você não pode excluir sua própria conta.';
        } else {
            require_once(ABSPATH.'wp-admin/includes/user.php');
            $result = wp_delete_user($user_id);
            
            if ($result) {
                $success_message = 'Usuário excluído com sucesso!';
            } else {
                $error_message = 'Erro ao excluir usuário.';
            }
        }
    }
}

// Buscar usuários
$usuarios = get_users(array(
    'orderby' => 'display_name',
    'order' => 'ASC'
));

// Filtros
$role_filter = isset($_GET['role_filter']) ? sanitize_text_field($_GET['role_filter']) : '';

if ($role_filter) {
    $usuarios = get_users(array(
        'role' => $role_filter,
        'orderby' => 'display_name',
        'order' => 'ASC'
    ));
}

// Estatísticas
$total_usuarios = count(get_users());
$total_admins = count(get_users(array('role__in' => array('administrator', 'editor', 'author'))));
$total_profissionais = count(get_users(array('role' => 'contributor')));
$total_clientes = count(get_users(array('role' => 'subscriber')));
?>

<div class="wrap">
    <!-- ALTERADO: Botão "Adicionar Usuário" movido para a direita do título -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <h1 class="wp-heading-inline" style="margin: 0;">Usuários</h1>
        <a href="#" class="page-title-action" onclick="openAddModal(); return false;">Adicionar Usuário</a>
    </div>
    <hr class="wp-header-end">
    
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

    <!-- Estatísticas -->
    <div class="inbwp-stats-row">
        <div class="inbwp-stat-box">
            <div class="inbwp-stat-number"><?php echo number_format($total_usuarios); ?></div>
            <div class="inbwp-stat-label">Total de Usuários</div>
        </div>
        <div class="inbwp-stat-box">
            <div class="inbwp-stat-number"><?php echo number_format($total_admins); ?></div>
            <div class="inbwp-stat-label">Administradores</div>
        </div>
        <div class="inbwp-stat-box">
            <div class="inbwp-stat-number"><?php echo number_format($total_profissionais); ?></div>
            <div class="inbwp-stat-label">Profissionais</div>
        </div>
        <div class="inbwp-stat-box">
            <div class="inbwp-stat-number"><?php echo number_format($total_clientes); ?></div>
            <div class="inbwp-stat-label">Clientes</div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" style="display: inline-flex; gap: 10px; align-items: center;">
                <input type="hidden" name="page" value="inbwp-usuarios">
                
                <select name="role_filter">
                    <option value="">Todos os roles</option>
                    <option value="administrator" <?php selected($role_filter, 'administrator'); ?>>Administrador</option>
                    <option value="editor" <?php selected($role_filter, 'editor'); ?>>Editor</option>
                    <option value="author" <?php selected($role_filter, 'author'); ?>>Autor</option>
                    <option value="contributor" <?php selected($role_filter, 'contributor'); ?>>Profissional</option>
                    <option value="subscriber" <?php selected($role_filter, 'subscriber'); ?>>Cliente</option>
                </select>
                
                <input type="submit" class="button" value="Filtrar">
                
                <?php if ($role_filter): ?>
                    <a href="<?php echo admin_url('admin.php?page=inbwp-usuarios'); ?>" class="button">Limpar Filtros</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Lista de Usuários -->
    <?php if (empty($usuarios)): ?>
        <div class="inbwp-empty-state">
            <div class="inbwp-empty-icon">👥</div>
            <h3>Nenhum usuário encontrado</h3>
            <?php if ($role_filter): ?>
                <p>Tente ajustar os filtros ou <a href="<?php echo admin_url('admin.php?page=inbwp-usuarios'); ?>">remover todos os filtros</a>.</p>
            <?php else: ?>
                <p>Adicione seu primeiro usuário clicando no botão "Adicionar Usuário".</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Username</th>
                    <th style="width: 120px;">Role</th>
                    <th style="width: 120px;">Data Registro</th>
                    <th style="width: 150px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td><strong><?php echo esc_html($usuario->ID); ?></strong></td>
                        <td>
                            <strong><?php echo esc_html($usuario->display_name); ?></strong>
                            <?php if ($usuario->first_name || $usuario->last_name): ?>
                                <br><small style="color: #666;"><?php echo esc_html($usuario->first_name . ' ' . $usuario->last_name); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($usuario->user_email); ?></td>
                        <td><?php echo esc_html($usuario->user_login); ?></td>
                        <td>
                            <span class="inbwp-role inbwp-role-<?php echo esc_attr($usuario->roles[0] ?? 'none'); ?>">
                                <?php 
                                $role_names = array(
                                    'administrator' => 'Administrador',
                                    'editor' => 'Editor',
                                    'author' => 'Autor',
                                    'contributor' => 'Profissional',
                                    'subscriber' => 'Cliente'
                                );
                                echo esc_html($role_names[$usuario->roles[0] ?? 'none'] ?? 'Indefinido');
                                ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($usuario->user_registered)); ?></td>
                        <td>
                            <button type="button" class="button button-small" onclick="editUser(<?php echo esc_js(json_encode($usuario)); ?>)">
                                Editar
                            </button>
                            
                            <?php if ($usuario->ID !== $current_user->ID): ?>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este usuário?');">
                                    <?php wp_nonce_field('inbwp_usuarios', 'inbwp_nonce'); ?>
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr($usuario->ID); ?>">
                                    <button type="submit" class="button button-small button-link-delete">Excluir</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <!-- Modal para Adicionar/Editar Usuário -->
    <div id="inbwp-modal-overlay" class="inbwp-modal-overlay">
        <div id="inbwp-modal" class="inbwp-modal">
            <div class="inbwp-modal-header">
                <h2 id="modal-title">Adicionar Novo Usuário</h2>
                <button type="button" class="inbwp-modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="inbwp-modal-content">
                <form method="post" id="usuarios-form" class="inbwp-form">
                    <?php wp_nonce_field('inbwp_usuarios', 'inbwp_nonce'); ?>
                    <input type="hidden" name="action" value="add_user" id="form-action">
                    <input type="hidden" name="user_id" value="" id="form-user-id">
                    
                    <!-- Nome de usuário -->
                    <div class="inbwp-form-row">
                        <div class="inbwp-form-group">
                            <label for="username">Nome de usuário *</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        <div class="inbwp-form-group">
                            <label for="email">E-mail *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <!-- Nome completo -->
                    <div class="inbwp-form-row">
                        <div class="inbwp-form-group">
                            <label for="first_name">Primeiro Nome</label>
                            <input type="text" id="first_name" name="first_name">
                        </div>
                        <div class="inbwp-form-group">
                            <label for="last_name">Sobrenome</label>
                            <input type="text" id="last_name" name="last_name">
                        </div>
                    </div>
                    
                    <!-- Senha e Role -->
                    <div class="inbwp-form-row">
                        <div class="inbwp-form-group">
                            <label for="password">Senha *</label>
                            <input type="password" id="password" name="password" required>
                            <small id="password-help">Deixe em branco para manter a senha atual (apenas na edição)</small>
                        </div>
                        <div class="inbwp-form-group">
                            <label for="role">Role *</label>
                            <select id="role" name="role" required>
                                <option value="">Selecione um role...</option>
                                <option value="administrator">Administrador</option>
                                <option value="editor">Editor</option>
                                <option value="author">Autor</option>
                                <option value="contributor">Profissional</option>
                                <option value="subscriber">Cliente</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="inbwp-form-actions">
                        <input type="submit" class="button button-primary" value="Adicionar Usuário" id="submit-button">
                        <button type="button" class="button" onclick="closeModal()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Funções para manipular o modal
function openModal() {
    document.getElementById('inbwp-modal-overlay').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('inbwp-modal-overlay').style.display = 'none';
    document.body.style.overflow = 'auto';
    resetForm();
}

function openAddModal() {
    resetForm();
    document.getElementById('modal-title').textContent = 'Adicionar Novo Usuário';
    document.getElementById('form-action').value = 'add_user';
    document.getElementById('submit-button').value = 'Adicionar Usuário';
    document.getElementById('username').disabled = false;
    document.getElementById('password').required = true;
    document.getElementById('password-help').style.display = 'none';
    openModal();
}

function editUser(user) {
    document.getElementById('modal-title').textContent = 'Editar Usuário';
    document.getElementById('form-action').value = 'edit_user';
    document.getElementById('form-user-id').value = user.ID;
    document.getElementById('username').value = user.user_login;
    document.getElementById('username').disabled = true;
    document.getElementById('email').value = user.user_email;
    document.getElementById('first_name').value = user.first_name || '';
    document.getElementById('last_name').value = user.last_name || '';
    document.getElementById('role').value = user.roles[0] || '';
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    document.getElementById('password-help').style.display = 'block';
    document.getElementById('submit-button').value = 'Atualizar Usuário';
    openModal();
}

function resetForm() {
    document.getElementById('usuarios-form').reset();
    document.getElementById('form-user-id').value = '';
    document.getElementById('username').disabled = false;
    document.getElementById('password').required = true;
    document.getElementById('password-help').style.display = 'none';
}

// Fechar modal ao clicar fora dele
document.getElementById('inbwp-modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Fechar modal com a tecla ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<style>
/* Estilos para o modal */
.inbwp-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 100000;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.inbwp-modal {
    background-color: #fff;
    border-radius: 4px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.inbwp-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #dcdcde;
    background-color: #f6f7f7;
}

.inbwp-modal-header h2 {
    margin: 0;
    font-size: 18px;
    line-height: 1.4;
}

.inbwp-modal-close {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 24px;
    color: #646970;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.inbwp-modal-close:hover {
    background-color: #dcdcde;
    color: #1d2327;
}

.inbwp-modal-content {
    padding: 20px;
    overflow-y: auto;
}

/* Estilos para o formulário */
.inbwp-form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.inbwp-form-group {
    flex: 1;
}

.inbwp-form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
}

.inbwp-form-group input,
.inbwp-form-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    font-size: 14px;
}

.inbwp-form-group small {
    color: #646970;
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

.inbwp-form-actions {
    border-top: 1px solid #dcdcde;
    padding-top: 15px;
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

/* Estatísticas */
.inbwp-stats-row {
    display: flex;
    gap: 20px;
    margin: 20px 0;
}

.inbwp-stat-box {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
    flex: 1;
}

.inbwp-stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #1d2327;
    line-height: 1;
}

.inbwp-stat-label {
    font-size: 14px;
    color: #646970;
    margin-top: 5px;
}

.inbwp-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
}

.inbwp-empty-icon {
    font-size: 48px;
    margin-bottom: 20px;
}

.inbwp-role {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.inbwp-role-administrator {
    background: #d1e7dd;
    color: #0f5132;
}

.inbwp-role-editor {
    background: #cff4fc;
    color: #055160;
}

.inbwp-role-author {
    background: #e2e3ff;
    color: #3d348b;
}

.inbwp-role-contributor {
    background: #fff3cd;
    color: #664d03;
}

.inbwp-role-subscriber {
    background: #f8d7da;
    color: #721c24;
}

@media (max-width: 768px) {
    .inbwp-form-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .inbwp-stats-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .inbwp-modal {
        width: 95%;
        max-height: 80vh;
    }
}
</style>