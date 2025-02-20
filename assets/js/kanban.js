// Funções do Kanban
async function loadAgendamentos() {
  try {
    const response = await fetch("get_agendamentos.php");
    return await response.json();
  } catch (error) {
    console.error("Erro ao carregar agendamentos:", error);
    return [];
  }
}

function createKanbanItem(agendamento) {
  const li = document.createElement("li");
  li.classList.add("kanban-item");
  li.dataset.id = agendamento.id;
  li.innerHTML = `
        <h4><i class="fas fa-id-card"></i>  ${agendamento.nome}</h4>
        <p><i class="fas fa-phone"></i>  ${agendamento.telefone}</p>
        <p><i class="fas fa-calendar-alt"></i>  ${agendamento.data_hora}</p>
        <p><i class="fas fa-tools"></i>  ${agendamento.servico}</p>
        <p><i class="fas fa-user-tie"></i>  ${agendamento.atendente}</p>
    `;
  return li;
}

function initializeSortable() {
  const lists = [
    "aguardando-list",
    "iniciado-list",
    "cancelado-list",
    "finalizado-list",
  ];
  lists.forEach((listId) => {
    Sortable.create(document.getElementById(listId), {
      group: "shared",
      animation: 150,
      onEnd: function (evt) {
        const itemEl = evt.item;
        const newStatus = getStatusFromListId(evt.to.id);
        updateAgendamentoStatus(itemEl.dataset.id, newStatus);
      },
    });
  });
}

function getStatusFromListId(listId) {
  switch (listId) {
    case "aguardando-list":
      return "Aguardando Atendimento";
    case "iniciado-list":
      return "Atendimento Iniciado";
    case "cancelado-list":
      return "Agendamento Cancelado";
    case "finalizado-list":
      return "Atendimento Finalizado";
  }
}

async function updateAgendamentoStatus(id, status) {
  try {
    const response = await fetch("update_agendamento_status.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ id, status }),
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error("Erro ao atualizar status do agendamento");
    }

    if (data.success) {
      if (data.archived) {
        Swal.fire({
          icon: "success",
          title: "Arquivado!",
          text: "O agendamento foi arquivado automaticamente.",
        });
      } else if (data.unarchived) {
        Swal.fire({
          icon: "success",
          title: "Sucesso!",
          text: "Status do agendamento atualizado com sucesso.",
        });
      } else {
        Swal.fire({
          icon: "success",
          title: "Sucesso!",
          text: "Status do agendamento atualizado com sucesso.",
        });
      }
    } else {
      throw new Error(data.message || "Erro desconhecido ao atualizar status");
    }
  } catch (error) {
    console.error("Erro ao atualizar status do agendamento:", error);
    Swal.fire({
      icon: "error",
      title: "Erro",
      text: "Erro ao atualizar status do agendamento.",
    });
  }
}

// Inicializa o Kanban quando o documento estiver pronto
document.addEventListener("DOMContentLoaded", async () => {
  const agendamentos = await loadAgendamentos();
  agendamentos.forEach((agendamento) => {
    const kanbanItem = createKanbanItem(agendamento);
    switch (agendamento.status) {
      case "Aguardando Atendimento":
        document.getElementById("aguardando-list").appendChild(kanbanItem);
        break;
      case "Atendimento Iniciado":
        document.getElementById("iniciado-list").appendChild(kanbanItem);
        break;
      case "Agendamento Cancelado":
        document.getElementById("cancelado-list").appendChild(kanbanItem);
        break;
      case "Atendimento Finalizado":
        document.getElementById("finalizado-list").appendChild(kanbanItem);
        break;
    }
  });
  initializeSortable();
});
