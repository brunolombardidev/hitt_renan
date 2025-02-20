function showMessageModal(phoneNumber, appointmentData) {
    document.getElementById('phoneNumber').value = phoneNumber;
    document.getElementById('appointmentData').value = JSON.stringify(appointmentData);
    const modal = new bootstrap.Modal(document.getElementById('messageModal'));
    modal.show();
}

// Função para substituir as variáveis no template
function replaceTemplateVariables(template) {
    const appointmentData = JSON.parse(document.getElementById('appointmentData').value || '{}');
    
    return template
        .replace(/\$nome/g, appointmentData.nome || '')
        .replace(/\$data_hora/g, appointmentData.data_hora || '')
        .replace(/\$atendente/g, appointmentData.atendente || '')
        .replace(/\$servico/g, appointmentData.servico || '');
}

// Adicionar evento para quando selecionar um template
document.addEventListener('DOMContentLoaded', function() {
    const templateSelect = document.getElementById('templateMessage');
    if (templateSelect) {
        templateSelect.addEventListener('change', function() {
            const template = this.value;
            if (template) {
                const messageWithVariables = replaceTemplateVariables(template);
                document.getElementById('message').value = messageWithVariables;
            }
        });
    }
});

async function sendMessage() {
    const phoneNumber = document.getElementById('phoneNumber').value;
    const message = document.getElementById('message').value;
    
    if (!message.trim()) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção!',
            text: 'Por favor, digite uma mensagem'
        });
        return;
    }

    const sendButton = document.querySelector('#messageModal .btn-primary');
    sendButton.disabled = true;
    sendButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...';

    try {
        const response = await fetch('evolutionapi.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'action': 'send_message',
                'number': phoneNumber,
                'message': message
            })
        });

        const result = await response.json();
        console.log('API Response:', result);

        if (result.success && result.data && result.data.key) {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: result.message
            }).then(() => {
                const messageModal = document.getElementById('messageModal');
                const modal = bootstrap.Modal.getInstance(messageModal);
                modal.hide();
                document.getElementById('message').value = '';
                document.getElementById('templateMessage').value = '';
            });
        } else {
            throw new Error(result.message || 'Erro ao enviar mensagem');
        }
    } catch (error) {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: error.message || 'Erro ao enviar mensagem'
        });
    } finally {
        sendButton.disabled = false;
        sendButton.innerHTML = 'Enviar';
    }
}
