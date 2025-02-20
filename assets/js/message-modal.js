function showMessageModal(phoneNumber, appointmentData) {
  // Armazena os dados do agendamento para uso posterior
  document.getElementById("phoneNumber").value = phoneNumber;
  document.getElementById("appointmentData").value =
    JSON.stringify(appointmentData);

  // Adiciona listener para o select de template
  document
    .getElementById("templateMessage")
    .addEventListener("change", function () {
      let template = this.value;
      if (template) {
        // Substitui as variáveis pelos valores reais
        template = template
          .replace("$nome", appointmentData.nome || "")
          .replace("$data_hora", appointmentData.data_hora || "")
          .replace("$atendente", appointmentData.atendente || "")
          .replace("$servico", appointmentData.servico || "");

        document.getElementById("message").value = template;
      }
    });

  // Mostra o modal
  $("#messageModal").modal("show");
}

function sendMessage() {
  const phoneNumber = document.getElementById("phoneNumber").value;
  const message = document.getElementById("message").value;

  // Validação básica
  if (!message.trim()) {
    Swal.fire({
      icon: "error",
      title: "Erro",
      text: "Por favor, digite uma mensagem",
    });
    return;
  }

  // Formatar número de telefone
  let formattedPhone = phoneNumber.replace(/\D/g, "");
  if (!formattedPhone.startsWith("55")) {
    formattedPhone = "55" + formattedPhone;
  }

  // Desabilitar botão de envio
  const sendButton = document.querySelector("#messageModal .btn-primary");
  sendButton.disabled = true;
  sendButton.innerHTML =
    '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...';

  // Enviar requisição
  fetch("evolutionapi.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams({
      action: "send_message",
      number: formattedPhone,
      message: message,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      console.log("Resposta da API:", data);

      // Fecha o modal de mensagem usando jQuery
      $("#messageModal").modal("hide");

      // Limpa o campo de mensagem
      document.getElementById("message").value = "";
      document.getElementById("templateMessage").value = "";

      if (data.success === true) {
        // Aguarda o modal fechar antes de mostrar o sucesso
        setTimeout(() => {
          Swal.fire({
            title: "Sucesso!",
            text: "Mensagem enviada com sucesso!",
            icon: "success",
            showConfirmButton: true,
            confirmButtonText: "OK",
            confirmButtonColor: "#198754",
            customClass: {
              confirmButton: "btn btn-success",
              popup: "animated fadeInDown faster",
            },
            timer: 3000,
            timerProgressBar: true,
          });
        }, 300);
      } else {
        // Prepara a mensagem de erro apropriada
        let errorMessage = "Erro ao enviar mensagem";
        let errorTitle = "Erro!";

        if (data.httpCode === 400) {
          if (data.response?.message?.[0]?.exists === false) {
            errorMessage = "Número de WhatsApp inválido ou não existe";
            errorTitle = "Número Inválido";
          }
        } else if (data.httpCode === 404) {
          errorMessage =
            "Instância não encontrada. Verifique as configurações.";
          errorTitle = "Erro de Configuração";
        } else if (data.httpCode === 401) {
          errorMessage = "Chave API inválida. Verifique as configurações.";
          errorTitle = "Erro de Autenticação";
        }

        // Aguarda o modal fechar antes de mostrar o erro
        setTimeout(() => {
          Swal.fire({
            title: errorTitle,
            text: errorMessage,
            icon: "error",
            showConfirmButton: true,
            confirmButtonText: "OK",
            confirmButtonColor: "#dc3545",
            customClass: {
              confirmButton: "btn btn-danger",
              popup: "animated fadeInDown faster",
            },
          });
        }, 300);
      }
    })
    .catch((error) => {
      console.error("Erro:", error);

      // Fecha o modal de mensagem usando jQuery
      $("#messageModal").modal("hide");

      // Aguarda o modal fechar antes de mostrar o erro
      setTimeout(() => {
        Swal.fire({
          title: "Erro!",
          text: "Erro ao processar a requisição. Por favor, tente novamente.",
          icon: "error",
          showConfirmButton: true,
          confirmButtonText: "OK",
          confirmButtonColor: "#dc3545",
          customClass: {
            confirmButton: "btn btn-danger",
            popup: "animated fadeInDown faster",
          },
        });
      }, 300);
    })
    .finally(() => {
      // Reativa o botão de envio
      sendButton.disabled = false;
      sendButton.innerHTML = "Enviar";
    });
}

document.addEventListener("DOMContentLoaded", function () {
  // Manipulador para os botões de editar mensagem
  document.querySelectorAll(".edit-mensagem-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const id = this.getAttribute("data-id");
      const nome = this.getAttribute("data-nome");
      const template = this.getAttribute("data-template");

      // Preencher o formulário do modal
      document.getElementById("edit-mensagem-id").value = id;
      document.getElementById("edit-mensagem-nome").value = nome;
      document.getElementById("edit-mensagem-template").value = template;

      // Abrir o modal
      $("#editMensagemModal").modal("show");
    });
  });

  // Manipulador para os botões de excluir mensagem
  document.querySelectorAll(".delete-mensagem-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const id = this.getAttribute("data-id");

      Swal.fire({
        title: "Tem certeza?",
        text: "Esta ação não poderá ser revertida!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sim, excluir!",
        cancelButtonText: "Cancelar",
      }).then((result) => {
        if (result.isConfirmed) {
          // Criar e submeter o formulário de exclusão
          const form = document.createElement("form");
          form.method = "POST";
          form.style.display = "none";

          const inputId = document.createElement("input");
          inputId.type = "hidden";
          inputId.name = "id";
          inputId.value = id;

          const inputAction = document.createElement("input");
          inputAction.type = "hidden";
          inputAction.name = "action";
          inputAction.value = "deleteMensagem";

          const inputTab = document.createElement("input");
          inputTab.type = "hidden";
          inputTab.name = "active_tab";
          inputTab.value = "mensagens";

          form.appendChild(inputId);
          form.appendChild(inputAction);
          form.appendChild(inputTab);
          document.body.appendChild(form);
          form.submit();
        }
      });
    });
  });
});
