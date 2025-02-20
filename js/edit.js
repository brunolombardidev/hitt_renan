document.addEventListener("DOMContentLoaded", function () {
  // Editar Serviço
  const editServicoBtns = document.querySelectorAll(".edit-servico-btn");
  editServicoBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      const id = this.dataset.id;
      const nome = this.dataset.nome;
      const preco = this.dataset.preco;
      const duracao = this.dataset.duracao;
      const disponivel = this.dataset.disponivel === "1";

      document.getElementById("edit-servico-id").value = id;
      document.getElementById("edit-servico-nome").value = nome;
      document.getElementById("edit-servico-preco").value = preco;
      document.getElementById("edit-servico-duracao").value = duracao;
      document.getElementById("edit-servico-disponivel").checked = disponivel;

      const modal = new bootstrap.Modal(
        document.getElementById("editServicoModal")
      );
      modal.show();
    });
  });

  // Editar Horário
  const editHorarioBtns = document.querySelectorAll(".edit-horario-btn");
  editHorarioBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      const id = this.dataset.id;
      const horario = this.dataset.horario;

      document.getElementById("edit-horario-id").value = id;
      document.getElementById("edit-horario-time").value = horario;

      $("#editHorarioModal").modal("show");
    });
  });

  // Botões de disponibilidade de horário
  const disponibilidadeBtns = document.querySelectorAll(".disponibilidade-btn");
  disponibilidadeBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      const form = this.closest("form");
      if (form) {
        Swal.fire({
          title: "Confirmar alteração",
          text: "Deseja alterar a disponibilidade deste horário?",
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#3085d6",
          cancelButtonColor: "#d33",
          confirmButtonText: "Sim",
          cancelButtonText: "Não",
        }).then((result) => {
          if (result.isConfirmed) {
            form.submit();
          }
        });
      }
    });
  });

  // Editar Atendente
  const editAtendenteBtns = document.querySelectorAll(".edit-atendente-btn");
  editAtendenteBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      const id = this.dataset.id;
      const nome = this.dataset.nome;
      const cargo = this.dataset.cargo;
      const disponivel = this.dataset.disponivel === "1";

      document.getElementById("edit-atendente-id").value = id;
      document.getElementById("edit-atendente-nome").value = nome;
      document.getElementById("edit-atendente-cargo").value = cargo;
      document.getElementById("edit-atendente-disponivel").checked = disponivel;

      $("#editAtendenteModal").modal("show");
    });
  });

  // Botões de disponibilidade de atendente
  const disponibilidadeAtendenteBtns = document.querySelectorAll(
    ".disponibilidade-atendente-btn"
  );
  disponibilidadeAtendenteBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      const form = this.closest("form");
      if (form) {
        Swal.fire({
          title: "Confirmar alteração",
          text: "Deseja alterar a disponibilidade deste atendente?",
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#3085d6",
          cancelButtonColor: "#d33",
          confirmButtonText: "Sim",
          cancelButtonText: "Não",
        }).then((result) => {
          if (result.isConfirmed) {
            form.submit();
          }
        });
      }
    });
  });

  // Editar Serviço Atribuído ao Atendente
  const editServAtendenteBtn = document.querySelectorAll(
    ".edit-serv-atendente-btn"
  );
  editServAtendenteBtn.forEach((btn) => {
    btn.addEventListener("click", function () {
      const id = this.dataset.id;
      const atendente = this.dataset.atendente;
      const servico = this.dataset.servico;

      document.getElementById("edit-atendente-servico-id").value = id;

      // Selecionar o atendente correto no select
      const atendenteSelect = document.getElementById("edit-atendente-serv-id");
      for (let i = 0; i < atendenteSelect.options.length; i++) {
        if (atendenteSelect.options[i].text === atendente) {
          atendenteSelect.selectedIndex = i;
          break;
        }
      }

      // Selecionar o serviço correto no select
      const servicoSelect = document.getElementById("edit-servico-serv-id");
      for (let i = 0; i < servicoSelect.options.length; i++) {
        if (servicoSelect.options[i].text === servico) {
          servicoSelect.selectedIndex = i;
          break;
        }
      }

      $("#editAtendenteServicoModal").modal("show");
    });
  });

  // Controle de visibilidade das permissões quando super admin
  const superAdminCheckbox = document.getElementById("super-admin");
  const permissionsSection = document.getElementById("permissions-section");

  if (superAdminCheckbox && permissionsSection) {
    superAdminCheckbox.addEventListener("change", function () {
      if (this.checked) {
        permissionsSection.style.display = "none";
      } else {
        permissionsSection.style.display = "block";
      }
    });
  }

  const editSuperAdminCheckbox = document.getElementById("edit-super-admin");
  const editPermissionsSection = document.getElementById(
    "edit-permissions-section"
  );

  if (editSuperAdminCheckbox && editPermissionsSection) {
    editSuperAdminCheckbox.addEventListener("change", function () {
      if (this.checked) {
        editPermissionsSection.style.display = "none";
      } else {
        editPermissionsSection.style.display = "block";
      }
    });
  }

  // Editar Usuário
  const editUserBtns = document.querySelectorAll(".edit-user-btn");
  editUserBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      const id = this.dataset.id;
      const username = this.dataset.username;
      const email = this.dataset.email;

      document.getElementById("edit-usuario-id").value = id;
      document.getElementById("edit-usuario-username").value = username;
      document.getElementById("edit-usuario-email").value = email;

      // Carregar permissões do usuário via AJAX
      fetch(`get_user_permissions.php?user_id=${id}`)
        .then((response) => response.json())
        .then((permissions) => {
          // Resetar todas as checkboxes primeiro
          document
            .querySelectorAll('#editUsuarioForm input[type="checkbox"]')
            .forEach((checkbox) => {
              checkbox.checked = false;
            });

          // Marcar as permissões que o usuário tem
          permissions.forEach((permission) => {
            const checkbox = document.querySelector(
              `#editUsuarioForm input[name="permissions[]"][value="${permission}"]`
            );
            if (checkbox) {
              checkbox.checked = true;
            }
          });
        })
        .catch((error) => console.error("Erro ao carregar permissões:", error));

      const modal = new bootstrap.Modal(
        document.getElementById("editUsuarioModal")
      );
      modal.show();
    });
  });

  // Botões de exclusão
  document.querySelectorAll(".delete-btn").forEach((btn) => {
    btn.addEventListener("click", function (e) {
      e.preventDefault(); // Previne o comportamento padrão
      const form = this.closest(".delete-form");

      if (form) {
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
            form.submit();
          }
        });
      }
    });
  });

  // Inicializar tooltips do Bootstrap
  $('[data-toggle="tooltip"]').tooltip();

  // Botões de editar agendamento
  document.querySelectorAll(".edit-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const id = this.dataset.id;
      const nome = this.dataset.nome;
      const telefone = this.dataset.telefone;
      const data = this.dataset.data;
      const horario = this.dataset.horario;

      document.getElementById("edit-id").value = id;
      document.getElementById("edit-nome").value = nome;
      document.getElementById("edit-telefone").value = telefone;
      document.getElementById("edit-data").value = data;
      document.getElementById("edit-horario").value = horario;
      document.getElementById("edit-servico").value = this.dataset.servico;
      document.getElementById("edit-atendente").value = this.dataset.atendente;

      $("#editModal").modal("show");
    });
  });

  // Funcionalidade de filtragem de agendamentos
  const searchInput = document.getElementById("search-agendamento");
  const filterAtendente = document.getElementById("filter-atendente");
  const filterStatus = document.getElementById("filter-status");
  const filterData = document.getElementById("filter-data");
  const clearFiltersBtn = document.getElementById("clear-filters");

  if (
    searchInput &&
    filterAtendente &&
    filterStatus &&
    filterData &&
    clearFiltersBtn
  ) {
    function filterAgendamentos() {
      const searchTerm = searchInput.value.toLowerCase();
      const atendenteFilter = filterAtendente.value;
      const statusFilter = filterStatus.value;
      const dataFilter = filterData.value;

      const rows = document.querySelectorAll("table tbody tr");

      rows.forEach((row) => {
        const nome =
          row.querySelector("td:nth-child(1)")?.textContent.toLowerCase() || "";
        const telefone =
          row.querySelector("td:nth-child(2)")?.textContent.toLowerCase() || "";
        const data = row.querySelector("td:nth-child(3)")?.textContent || "";
        const servico =
          row.querySelector("td:nth-child(4)")?.textContent.toLowerCase() || "";
        const atendente =
          row.querySelector("td:nth-child(5)")?.textContent || "";
        const status = row.getAttribute("data-status") || "";

        const matchesSearch =
          nome.includes(searchTerm) ||
          telefone.includes(searchTerm) ||
          servico.includes(searchTerm);

        const matchesAtendente =
          !atendenteFilter || atendente === atendenteFilter;
        const matchesStatus = !statusFilter || status === statusFilter;

        const matchesData =
          !dataFilter ||
          data.includes(dataFilter.split("-").reverse().join("/"));

        row.style.display =
          matchesSearch && matchesAtendente && matchesStatus && matchesData
            ? ""
            : "none";
      });
    }

    // Event listeners para os filtros
    searchInput.addEventListener("input", filterAgendamentos);
    filterAtendente.addEventListener("change", filterAgendamentos);
    filterStatus.addEventListener("change", filterAgendamentos);
    filterData.addEventListener("change", filterAgendamentos);

    // Limpar filtros
    clearFiltersBtn.addEventListener("click", function () {
      searchInput.value = "";
      filterAtendente.value = "";
      filterStatus.value = "";
      filterData.value = "";
      filterAgendamentos();
    });
  }
});
