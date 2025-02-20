// Gerenciamento de modais e eventos de edição
document.addEventListener("DOMContentLoaded", function () {
  // Botões de editar agendamento
  document.querySelectorAll(".edit-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const id = this.getAttribute("data-id");
      const nome = this.getAttribute("data-nome");
      const telefone = this.getAttribute("data-telefone");
      const data = this.getAttribute("data-data");
      const horario = this.getAttribute("data-horario");
      const servico = this.getAttribute("data-servico");
      const atendente = this.getAttribute("data-atendente");

      // Preencher o formulário do modal
      document.getElementById("edit-id").value = id;
      document.getElementById("edit-nome").value = nome;
      document.getElementById("edit-telefone").value = telefone;
      document.getElementById("edit-data").value = data;
      document.getElementById("edit-horario").value = horario;
      document.getElementById("edit-servico").value = servico;
      document.getElementById("edit-atendente").value = atendente;

      // Abrir o modal
      $("#editModal").modal("show");
    });
  });

  // Botões de editar serviço
  document.querySelectorAll(".edit-servico-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const id = this.getAttribute("data-id");
      const nome = this.getAttribute("data-nome");

      document.getElementById("edit-servico-id").value = id;
      document.getElementById("edit-servico-nome").value = nome;

      $("#editServicoModal").modal("show");
    });
  });

  // Botões de editar horário
  document.querySelectorAll(".edit-horario-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const id = this.getAttribute("data-id");
      const horario = this.getAttribute("data-horario");

      document.getElementById("edit-horario-id").value = id;
      document.getElementById("edit-horario-time").value = horario;

      $("#editHorarioModal").modal("show");
    });
  });

  // Botões de editar atendente
  document.querySelectorAll(".edit-atendente-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const id = this.getAttribute("data-id");
      const nome = this.getAttribute("data-nome");
      const cargo = this.getAttribute("data-cargo");
      const disponivel = this.getAttribute("data-disponivel") === "1";

      document.getElementById("edit-atendente-id").value = id;
      document.getElementById("edit-atendente-nome").value = nome;
      document.getElementById("edit-atendente-cargo").value = cargo;
      document.getElementById("edit-atendente-disponivel").checked = disponivel;

      $("#editAtendenteModal").modal("show");
    });
  });

  // Botões de editar usuário
  document.querySelectorAll(".edit-user-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const id = this.getAttribute("data-id");
      const username = this.getAttribute("data-username");
      const email = this.getAttribute("data-email");

      document.getElementById("edit-usuario-id").value = id;
      document.getElementById("edit-usuario-username").value = username;
      document.getElementById("edit-usuario-email").value = email;

      $("#editUsuarioModal").modal("show");
    });
  });

  // Botões de editar mensagem
  document.querySelectorAll(".edit-mensagem-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const id = this.getAttribute("data-id");
      const nome = this.getAttribute("data-nome");
      const template = this.getAttribute("data-template");

      document.getElementById("edit-mensagem-id").value = id;
      document.getElementById("edit-mensagem-nome").value = nome;
      document.getElementById("edit-mensagem-template").value = template;

      $("#editMensagemModal").modal("show");
    });
  });
});
