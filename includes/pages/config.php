<?php
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();

// Verificar se o usuário está logado
if (!is_user_logged_in()) {
    wp_die('Você precisa estar logado para acessar esta página.');
}

// Verificar permissões para Termos de Serviço
$user_roles = $current_user->roles;
$can_edit_terms = in_array('administrator', $user_roles) || in_array('editor', $user_roles);

$success_message = '';
$error_message = '';

// Processar formulário
if ($_POST) {
    if (isset($_POST['inbwp_nonce']) && wp_verify_nonce($_POST['inbwp_nonce'], 'inbwp_config')) {
        
        // Salvar perfil pessoal
        if (isset($_POST['action']) && $_POST['action'] === 'save_profile') {
            $telefone = sanitize_text_field($_POST['telefone']);
            $cpf_cnpj = sanitize_text_field($_POST['cpf_cnpj']);
            
            update_user_meta($current_user->ID, 'inbwp_telefone', $telefone);
            update_user_meta($current_user->ID, 'inbwp_cpf_cnpj', $cpf_cnpj);
            
            $success_message = 'Perfil atualizado com sucesso!';
        }
        
        // Salvar Pix
        if (isset($_POST['action']) && $_POST['action'] === 'save_pix') {
            $pix_data = array(
                'tipo_chave' => sanitize_text_field($_POST['pix_tipo_chave']),
                'chave' => sanitize_text_field($_POST['pix_chave'])
            );
            
            update_user_meta($current_user->ID, 'inbwp_pix', $pix_data);
            
            $success_message = 'Dados do Pix atualizados com sucesso!';
        }
        
        // Salvar endereço
        if (isset($_POST['action']) && $_POST['action'] === 'save_address') {
            $endereco_data = array(
                'cep' => sanitize_text_field($_POST['cep']),
                'logradouro' => sanitize_text_field($_POST['logradouro']),
                'numero' => sanitize_text_field($_POST['numero']),
                'complemento' => sanitize_text_field($_POST['complemento']),
                'bairro' => sanitize_text_field($_POST['bairro']),
                'cidade' => sanitize_text_field($_POST['cidade']),
                'estado' => sanitize_text_field($_POST['estado'])
            );
            
            update_user_meta($current_user->ID, 'inbwp_endereco', $endereco_data);
            
            $success_message = 'Endereço atualizado com sucesso!';
        }
        
        // Salvar configurações da empresa (apenas administradores)
        if (isset($_POST['action']) && $_POST['action'] === 'save_company' && in_array('administrator', $user_roles)) {
            $empresa_nome = sanitize_text_field($_POST['empresa_nome']);
            $empresa_cnpj = sanitize_text_field($_POST['empresa_cnpj']);
            $empresa_telefone = sanitize_text_field($_POST['empresa_telefone']);
            $empresa_email = sanitize_email($_POST['empresa_email']);
            $empresa_site = esc_url_raw($_POST['empresa_site']);
            
            update_option('inbwp_empresa_nome', $empresa_nome);
            update_option('inbwp_empresa_cnpj', $empresa_cnpj);
            update_option('inbwp_empresa_telefone', $empresa_telefone);
            update_option('inbwp_empresa_email', $empresa_email);
            update_option('inbwp_empresa_site', $empresa_site);
            
            $success_message = 'Configurações da empresa atualizadas com sucesso!';
        }
        
        // Salvar descontos progressivos (apenas administradores)
        if (isset($_POST['action']) && $_POST['action'] === 'save_discounts' && in_array('administrator', $user_roles)) {
            $descontos = array(
                '1_dia' => floatval($_POST['desconto_1_dia']),
                '1_semana' => floatval($_POST['desconto_1_semana']),
                '1_mes' => floatval($_POST['desconto_1_mes'])
            );
            
            update_option('inbwp_descontos_progressivos', $descontos);
            
            $success_message = 'Descontos progressivos atualizados com sucesso!';
        }
        
        // Salvar termos de serviço (apenas administradores e editores)
        if (isset($_POST['action']) && $_POST['action'] === 'save_terms' && $can_edit_terms) {
            $termos_servico = wp_kses_post($_POST['termos_servico']);
            
            update_option('inbwp_termos_servico', $termos_servico);
            
            $success_message = 'Termos de serviço atualizados com sucesso!';
        }
        
    } else {
        $error_message = 'Falha na verificação de segurança.';
    }
}

// Buscar dados atuais
$telefone = get_user_meta($current_user->ID, 'inbwp_telefone', true);
$cpf_cnpj = get_user_meta($current_user->ID, 'inbwp_cpf_cnpj', true);
$pix_data = get_user_meta($current_user->ID, 'inbwp_pix', true);
$endereco = get_user_meta($current_user->ID, 'inbwp_endereco', true);

// Dados da empresa
$empresa_nome = get_option('inbwp_empresa_nome', '');
$empresa_cnpj = get_option('inbwp_empresa_cnpj', '');
$empresa_telefone = get_option('inbwp_empresa_telefone', '');
$empresa_email = get_option('inbwp_empresa_email', '');
$empresa_site = get_option('inbwp_empresa_site', '');

// Descontos progressivos
$descontos = get_option('inbwp_descontos_progressivos', array(
    '1_dia' => 0,
    '1_semana' => 5,
    '1_mes' => 15
));

// Termos de serviço
$termos_servico = get_option('inbwp_termos_servico', '');

// Garantir que pix_data seja um array
if (!is_array($pix_data)) {
    $pix_data = array(
        'tipo_chave' => '',
        'chave' => ''
    );
}

// Garantir que endereco seja um array
if (!is_array($endereco)) {
    $endereco = array(
        'cep' => '',
        'logradouro' => '',
        'numero' => '',
        'complemento' => '',
        'bairro' => '',
        'cidade' => '',
        'estado' => ''
    );
}
?>

<div class="wrap">
    <h1>Configurações</h1>
    
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

    <!-- Meu Perfil -->
    <div class="inbwp-config-section">
        <h2>Meu Perfil</h2>
        <form method="post" class="inbwp-form">
            <?php wp_nonce_field('inbwp_config', 'inbwp_nonce'); ?>
            <input type="hidden" name="action" value="save_profile">
            
            <div class="inbwp-form-row">
                <div class="inbwp-form-group">
                    <label for="nome">Nome Completo</label>
                    <input type="text" id="nome" value="<?php echo esc_attr($current_user->display_name); ?>" readonly>
                    <small>Para alterar o nome, acesse seu <a href="<?php echo admin_url('profile.php'); ?>">perfil do WordPress</a>.</small>
                </div>
                <div class="inbwp-form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" value="<?php echo esc_attr($current_user->user_email); ?>" readonly>
                    <small>Para alterar o e-mail, acesse seu <a href="<?php echo admin_url('profile.php'); ?>">perfil do WordPress</a>.</small>
                </div>
            </div>
            
            <div class="inbwp-form-row">
                <div class="inbwp-form-group">
                    <label for="telefone">Telefone</label>
                    <input type="tel" id="telefone" name="telefone" value="<?php echo esc_attr($telefone); ?>" placeholder="(11) 99999-9999">
                </div>
                <div class="inbwp-form-group">
                    <label for="cpf_cnpj">CPF/CNPJ</label>
                    <input type="text" id="cpf_cnpj" name="cpf_cnpj" value="<?php echo esc_attr($cpf_cnpj); ?>" placeholder="000.000.000-00">
                </div>
            </div>
            
            <div class="inbwp-form-actions">
                <input type="submit" class="button button-primary" value="Salvar Perfil">
            </div>
        </form>
    </div>

    <!-- Meu Pix -->
    <div class="inbwp-config-section">
        <h2>Meu Pix</h2>
        <form method="post" class="inbwp-form">
            <?php wp_nonce_field('inbwp_config', 'inbwp_nonce'); ?>
            <input type="hidden" name="action" value="save_pix">
            
            <div class="inbwp-form-row">
                <div class="inbwp-form-group">
                    <label for="pix_tipo_chave">Tipo de Chave</label>
                    <select id="pix_tipo_chave" name="pix_tipo_chave">
                        <option value="">Selecione o tipo de chave...</option>
                        <option value="cpf" <?php selected($pix_data['tipo_chave'], 'cpf'); ?>>CPF</option>
                        <option value="cnpj" <?php selected($pix_data['tipo_chave'], 'cnpj'); ?>>CNPJ</option>
                        <option value="email" <?php selected($pix_data['tipo_chave'], 'email'); ?>>E-mail</option>
                        <option value="telefone" <?php selected($pix_data['tipo_chave'], 'telefone'); ?>>Telefone</option>
                        <option value="aleatoria" <?php selected($pix_data['tipo_chave'], 'aleatoria'); ?>>Chave Aleatória</option>
                    </select>
                </div>
                <div class="inbwp-form-group">
                    <label for="pix_chave">Minha Chave</label>
                    <input type="text" id="pix_chave" name="pix_chave" value="<?php echo esc_attr($pix_data['chave']); ?>" placeholder="Digite sua chave Pix">
                </div>
            </div>
            
            <div class="inbwp-form-actions">
                <input type="submit" class="button button-primary" value="Salvar Pix">
            </div>
        </form>
    </div>

    <!-- Endereço -->
    <div class="inbwp-config-section">
        <h2>Endereço</h2>
        <form method="post" class="inbwp-form">
            <?php wp_nonce_field('inbwp_config', 'inbwp_nonce'); ?>
            <input type="hidden" name="action" value="save_address">
            
            <div class="inbwp-form-row">
                <div class="inbwp-form-group" style="flex: 0 0 200px;">
                    <label for="cep">CEP *</label>
                    <input type="text" id="cep" name="cep" value="<?php echo esc_attr($endereco['cep']); ?>" placeholder="00000-000" maxlength="9" onblur="buscarCEP()">
                </div>
                <div class="inbwp-form-group">
                    <label for="logradouro">Logradouro *</label>
                    <input type="text" id="logradouro" name="logradouro" value="<?php echo esc_attr($endereco['logradouro']); ?>" placeholder="Rua, Avenida, etc.">
                </div>
                <div class="inbwp-form-group" style="flex: 0 0 120px;">
                    <label for="numero">Número *</label>
                    <input type="text" id="numero" name="numero" value="<?php echo esc_attr($endereco['numero']); ?>" placeholder="123">
                </div>
            </div>
            
            <div class="inbwp-form-row">
                <div class="inbwp-form-group">
                    <label for="complemento">Complemento</label>
                    <input type="text" id="complemento" name="complemento" value="<?php echo esc_attr($endereco['complemento']); ?>" placeholder="Apto, Bloco, etc.">
                </div>
                <div class="inbwp-form-group">
                    <label for="bairro">Bairro *</label>
                    <input type="text" id="bairro" name="bairro" value="<?php echo esc_attr($endereco['bairro']); ?>" placeholder="Nome do bairro">
                </div>
            </div>
            
            <div class="inbwp-form-row">
                <div class="inbwp-form-group">
                    <label for="cidade">Cidade *</label>
                    <input type="text" id="cidade" name="cidade" value="<?php echo esc_attr($endereco['cidade']); ?>" placeholder="Nome da cidade">
                </div>
                <div class="inbwp-form-group" style="flex: 0 0 100px;">
                    <label for="estado">Estado *</label>
                    <select id="estado" name="estado">
                        <option value="">UF</option>
                        <option value="AC" <?php selected($endereco['estado'], 'AC'); ?>>AC</option>
                        <option value="AL" <?php selected($endereco['estado'], 'AL'); ?>>AL</option>
                        <option value="AP" <?php selected($endereco['estado'], 'AP'); ?>>AP</option>
                        <option value="AM" <?php selected($endereco['estado'], 'AM'); ?>>AM</option>
                        <option value="BA" <?php selected($endereco['estado'], 'BA'); ?>>BA</option>
                        <option value="CE" <?php selected($endereco['estado'], 'CE'); ?>>CE</option>
                        <option value="DF" <?php selected($endereco['estado'], 'DF'); ?>>DF</option>
                        <option value="ES" <?php selected($endereco['estado'], 'ES'); ?>>ES</option>
                        <option value="GO" <?php selected($endereco['estado'], 'GO'); ?>>GO</option>
                        <option value="MA" <?php selected($endereco['estado'], 'MA'); ?>>MA</option>
                        <option value="MT" <?php selected($endereco['estado'], 'MT'); ?>>MT</option>
                        <option value="MS" <?php selected($endereco['estado'], 'MS'); ?>>MS</option>
                        <option value="MG" <?php selected($endereco['estado'], 'MG'); ?>>MG</option>
                        <option value="PA" <?php selected($endereco['estado'], 'PA'); ?>>PA</option>
                        <option value="PB" <?php selected($endereco['estado'], 'PB'); ?>>PB</option>
                        <option value="PR" <?php selected($endereco['estado'], 'PR'); ?>>PR</option>
                        <option value="PE" <?php selected($endereco['estado'], 'PE'); ?>>PE</option>
                        <option value="PI" <?php selected($endereco['estado'], 'PI'); ?>>PI</option>
                        <option value="RJ" <?php selected($endereco['estado'], 'RJ'); ?>>RJ</option>
                        <option value="RN" <?php selected($endereco['estado'], 'RN'); ?>>RN</option>
                        <option value="RS" <?php selected($endereco['estado'], 'RS'); ?>>RS</option>
                        <option value="RO" <?php selected($endereco['estado'], 'RO'); ?>>RO</option>
                        <option value="RR" <?php selected($endereco['estado'], 'RR'); ?>>RR</option>
                        <option value="SC" <?php selected($endereco['estado'], 'SC'); ?>>SC</option>
                        <option value="SP" <?php selected($endereco['estado'], 'SP'); ?>>SP</option>
                        <option value="SE" <?php selected($endereco['estado'], 'SE'); ?>>SE</option>
                        <option value="TO" <?php selected($endereco['estado'], 'TO'); ?>>TO</option>
                    </select>
                </div>
            </div>
            
            <div class="inbwp-form-actions">
                <input type="submit" class="button button-primary" value="Salvar Endereço">
            </div>
        </form>
    </div>

    <?php if (in_array('administrator', $user_roles)): ?>
    <!-- Perfil da Empresa -->
    <div class="inbwp-config-section">
        <h2>Perfil da Empresa</h2>
        <form method="post" class="inbwp-form">
            <?php wp_nonce_field('inbwp_config', 'inbwp_nonce'); ?>
            <input type="hidden" name="action" value="save_company">
            
            <div class="inbwp-form-row">
                <div class="inbwp-form-group">
                    <label for="empresa_nome">Nome da Empresa</label>
                    <input type="text" id="empresa_nome" name="empresa_nome" value="<?php echo esc_attr($empresa_nome); ?>">
                </div>
                <div class="inbwp-form-group">
                    <label for="empresa_cnpj">CNPJ</label>
                    <input type="text" id="empresa_cnpj" name="empresa_cnpj" value="<?php echo esc_attr($empresa_cnpj); ?>" placeholder="00.000.000/0000-00">
                </div>
            </div>
            
            <div class="inbwp-form-row">
                <div class="inbwp-form-group">
                    <label for="empresa_telefone">Telefone</label>
                    <input type="tel" id="empresa_telefone" name="empresa_telefone" value="<?php echo esc_attr($empresa_telefone); ?>" placeholder="(11) 3333-3333">
                </div>
                <div class="inbwp-form-group">
                    <label for="empresa_email">E-mail</label>
                    <input type="email" id="empresa_email" name="empresa_email" value="<?php echo esc_attr($empresa_email); ?>" placeholder="contato@empresa.com">
                </div>
            </div>
            
            <div class="inbwp-form-row">
                <div class="inbwp-form-group">
                    <label for="empresa_site">Site</label>
                    <input type="url" id="empresa_site" name="empresa_site" value="<?php echo esc_attr($empresa_site); ?>" placeholder="https://www.empresa.com">
                </div>
            </div>
            
            <div class="inbwp-form-actions">
                <input type="submit" class="button button-primary" value="Salvar Empresa">
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if (in_array('administrator', $user_roles)): ?>
    <!-- Descontos Progressivos -->
    <div class="inbwp-config-section">
        <h2>Descontos Progressivos</h2>
        <form method="post" class="inbwp-form">
            <?php wp_nonce_field('inbwp_config', 'inbwp_nonce'); ?>
            <input type="hidden" name="action" value="save_discounts">
            
            <div class="inbwp-form-row">
                <div class="inbwp-form-group">
                    <label for="desconto_1_dia">Desconto para 1 Dia (%)</label>
                    <input type="number" id="desconto_1_dia" name="desconto_1_dia" value="<?php echo esc_attr($descontos['1_dia']); ?>" min="0" max="100" step="0.1">
                </div>
                <div class="inbwp-form-group">
                    <label for="desconto_1_semana">Desconto para 1 Semana (%)</label>
                    <input type="number" id="desconto_1_semana" name="desconto_1_semana" value="<?php echo esc_attr($descontos['1_semana']); ?>" min="0" max="100" step="0.1">
                </div>
                <div class="inbwp-form-group">
                    <label for="desconto_1_mes">Desconto para 1 Mês (%)</label>
                    <input type="number" id="desconto_1_mes" name="desconto_1_mes" value="<?php echo esc_attr($descontos['1_mes']); ?>" min="0" max="100" step="0.1">
                </div>
            </div>
            
            <div class="inbwp-form-actions">
                <input type="submit" class="button button-primary" value="Salvar Descontos">
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($can_edit_terms): ?>
    <!-- Termos de Serviço -->
    <div class="inbwp-config-section">
        <h2>Termos de Serviço</h2>
        <form method="post" class="inbwp-form">
            <?php wp_nonce_field('inbwp_config', 'inbwp_nonce'); ?>
            <input type="hidden" name="action" value="save_terms">
            
            <div class="inbwp-form-row">
                <div class="inbwp-form-group full-width">
                    <label for="termos_servico">Termos de Serviço</label>
                    <textarea id="termos_servico" name="termos_servico" rows="15" placeholder="Digite aqui os termos de serviço que serão apresentados aos clientes..."><?php echo esc_textarea($termos_servico); ?></textarea>
                    <small>Este texto será exibido para os clientes durante o processo de contratação de serviços.</small>
                </div>
            </div>
            
            <div class="inbwp-form-actions">
                <input type="submit" class="button button-primary" value="Salvar Termos">
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
// Função para buscar CEP
function buscarCEP() {
    const cep = document.getElementById('cep').value.replace(/\D/g, '');
    
    if (cep.length === 8) {
        // Mostrar loading
        document.getElementById('logradouro').value = 'Buscando...';
        document.getElementById('bairro').value = 'Buscando...';
        document.getElementById('cidade').value = 'Buscando...';
        document.getElementById('estado').value = '';
        
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(response => response.json())
            .then(data => {
                if (!data.erro) {
                    document.getElementById('logradouro').value = data.logradouro || '';
                    document.getElementById('bairro').value = data.bairro || '';
                    document.getElementById('cidade').value = data.localidade || '';
                    document.getElementById('estado').value = data.uf || '';
                    
                    // Focar no campo número
                    document.getElementById('numero').focus();
                } else {
                    alert('CEP não encontrado!');
                    limparCamposEndereco();
                }
            })
            .catch(error => {
                console.error('Erro ao buscar CEP:', error);
                alert('Erro ao buscar CEP. Tente novamente.');
                limparCamposEndereco();
            });
    }
}

function limparCamposEndereco() {
    document.getElementById('logradouro').value = '';
    document.getElementById('bairro').value = '';
    document.getElementById('cidade').value = '';
    document.getElementById('estado').value = '';
}

// Máscara para CEP
document.getElementById('cep').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 8) {
        value = value.replace(/^(\d{5})(\d)/, '$1-$2');
        e.target.value = value;
    }
});

// Máscara para telefone
document.getElementById('telefone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        if (value.length <= 10) {
            value = value.replace(/^(\d{2})(\d{4})(\d)/, '($1) $2-$3');
        } else {
            value = value.replace(/^(\d{2})(\d{5})(\d)/, '($1) $2-$3');
        }
        e.target.value = value;
    }
});

// Máscara para CPF/CNPJ
document.getElementById('cpf_cnpj').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        // CPF
        value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d)/, '$1.$2.$3-$4');
    } else if (value.length <= 14) {
        // CNPJ
        value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d)/, '$1.$2.$3/$4-$5');
    }
    e.target.value = value;
});

<?php if (in_array('administrator', $user_roles)): ?>
// Máscara para CNPJ da empresa
document.getElementById('empresa_cnpj').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 14) {
        value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d)/, '$1.$2.$3/$4-$5');
        e.target.value = value;
    }
});

// Máscara para telefone da empresa
document.getElementById('empresa_telefone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        if (value.length <= 10) {
            value = value.replace(/^(\d{2})(\d{4})(\d)/, '($1) $2-$3');
        } else {
            value = value.replace(/^(\d{2})(\d{5})(\d)/, '($1) $2-$3');
        }
        e.target.value = value;
    }
});
<?php endif; ?>
</script>

<style>
.inbwp-config-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    margin-bottom: 20px;
    padding: 20px;
}

.inbwp-config-section h2 {
    margin: 0 0 20px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #dcdcde;
    font-size: 18px;
}

.inbwp-form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.inbwp-form-group {
    flex: 1;
}

.inbwp-form-group.full-width {
    flex: 1 1 100%;
}

.inbwp-form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
}

.inbwp-form-group input,
.inbwp-form-group select,
.inbwp-form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    font-size: 14px;
}

.inbwp-form-group textarea {
    resize: vertical;
    font-family: monospace;
}

.inbwp-form-group small {
    display: block;
    margin-top: 5px;
    color: #646970;
    font-size: 12px;
}

.inbwp-form-actions {
    border-top: 1px solid #dcdcde;
    padding-top: 15px;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .inbwp-form-row {
        flex-direction: column;
        gap: 10px;
    }
}
</style>