document.addEventListener("DOMContentLoaded", function () {
  // Atualizar lista de horários quando selecionar um atendente
  const atendenteSelect = document.getElementById("atendente_id");
  if (atendenteSelect) {
    atendenteSelect.addEventListener("change", function () {
      atualizarHorariosDisponiveis();
    });
  }

  function atualizarHorariosDisponiveis() {
    const atendenteId = document.getElementById("atendente_id").value;
    const horarioSelect = document.getElementById("horario_id");

    fetch(`get_horarios_disponiveis.php?atendente_id=${atendenteId}`)
      .then((response) => response.json())
      .then((data) => {
        horarioSelect.innerHTML = "";
        data.forEach((horario) => {
          const option = document.createElement("option");
          option.value = horario.id;
          option.textContent = horario.horario;
          horarioSelect.appendChild(option);
        });
      })
      .catch((error) => console.error("Erro:", error));
  }

  // Deletar horário atribuído
  document.querySelectorAll(".delete-btn").forEach((btn) => {
    btn.addEventListener("click", function () {
      const form = this.closest("form");
      Swal.fire({
        title: "Confirmar exclusão?",
        text: "Esta ação não pode ser desfeita!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Sim, excluir!",
        cancelButtonText: "Cancelar",
      }).then((result) => {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });
  });
});
