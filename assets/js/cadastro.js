function submitCadastroForm(event) {
  event.preventDefault();

  const form = document.getElementById("cadastroForm");
  const formData = new FormData(form);

  // Validação do lado do cliente
  const nome = formData.get("nome").trim();
  const telefone = formData.get("telefone").trim();
  const data = formData.get("data");
  const horario = formData.get("horario");

  // Validações básicas
  if (!nome || !telefone || !data || !horario) {
    Swal.fire({
      icon: "error",
      title: "Erro!",
      text: "Por favor, preencha todos os campos obrigatórios.",
    });
    return false;
  }

  // Validar data
  const selectedDate = new Date(data);
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  if (selectedDate < today) {
    Swal.fire({
      icon: "error",
      title: "Data inválida!",
      text: "Por favor, selecione uma data futura.",
    });
    return false;
  }

  // Mostrar loading
  Swal.fire({
    title: "Processando...",
    text: "Por favor, aguarde.",
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  // Enviar formulário via AJAX
  fetch("process_cadastro.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error("Erro na requisição");
      }
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        Swal.fire({
          icon: "success",
          title: "Sucesso!",
          text: data.message,
          allowOutsideClick: false,
        }).then(() => {
          // Limpar formulário
          form.reset();
        });
      } else {
        throw new Error(data.message || "Erro ao processar o agendamento");
      }
    })
    .catch((error) => {
      console.error("Erro:", error);
      Swal.fire({
        icon: "error",
        title: "Erro!",
        text: error.message || "Ocorreu um erro ao processar o agendamento.",
      });
    });

  return false;
}

// Adicionar máscara para o telefone
document.addEventListener("DOMContentLoaded", function () {
  const telefoneInput = document.getElementById("cadastro-telefone");
  if (telefoneInput) {
    telefoneInput.addEventListener("input", function (e) {
      // Remove tudo que não é número
      let value = e.target.value.replace(/\D/g, "");

      // Se não começar com 55, adiciona
      if (!value.startsWith("55")) {
        value = "55" + value;
      }

      // Limita o tamanho total para 13 dígitos (55 + DDD + número)
      if (value.length > 13) {
        value = value.slice(0, 13);
      }

      e.target.value = value;
    });

    // Define o valor inicial como 55 se o campo estiver vazio
    if (!telefoneInput.value) {
      telefoneInput.value = "55";
    }
  }

  // Definir data mínima como hoje
  const dataInput = document.getElementById("cadastro-data");
  if (dataInput) {
    const today = new Date();
    const dd = String(today.getDate()).padStart(2, "0");
    const mm = String(today.getMonth() + 1).padStart(2, "0");
    const yyyy = today.getFullYear();
    dataInput.min = `${yyyy}-${mm}-${dd}`;
  }
});
