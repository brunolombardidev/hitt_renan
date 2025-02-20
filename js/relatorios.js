// Carrega a biblioteca Chart.js
document.addEventListener("DOMContentLoaded", function () {
  // Referências aos elementos
  const periodoFiltro = document.getElementById("periodo-filtro");
  const exportarBtn = document.getElementById("exportar-csv");

  // Objetos para armazenar as instâncias dos gráficos
  let graficos = {
    profissionais: null,
    servicos: null,
    faturamento: null,
    servicosProfissional: null,
  };

  // Cores para os gráficos
  const cores = [
    "#4e73df",
    "#1cc88a",
    "#36b9cc",
    "#f6c23e",
    "#e74a3b",
    "#858796",
    "#5a5c69",
    "#2e59d9",
    "#17a673",
    "#2c9faf",
  ];

  // Função para carregar dados dos relatórios
  async function carregarDados(periodo) {
    try {
      const response = await fetch(`get_relatorios.php?periodo=${periodo}`);
      const data = await response.json();

      if (data.success) {
        atualizarGraficos(data);
      } else {
        throw new Error(data.message || "Erro ao carregar dados");
      }
    } catch (error) {
      console.error("Erro:", error);
      Swal.fire({
        icon: "error",
        title: "Erro",
        text: "Erro ao carregar dados dos relatórios",
      });
    }
  }

  // Função para atualizar os gráficos
  function atualizarGraficos(data) {
    // Profissionais mais requisitados
    if (graficos.profissionais) graficos.profissionais.destroy();
    graficos.profissionais = new Chart(
      document.getElementById("grafico-profissionais"),
      {
        type: "bar",
        data: {
          labels: data.profissionais.map((p) => p.atendente),
          datasets: [
            {
              label: "Total de Agendamentos",
              data: data.profissionais.map((p) => p.total_agendamentos),
              backgroundColor: cores,
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              position: "top",
            },
            title: {
              display: true,
              text: "Profissionais Mais Requisitados",
            },
          },
        },
      }
    );

    // Serviços mais requisitados
    if (graficos.servicos) graficos.servicos.destroy();
    graficos.servicos = new Chart(document.getElementById("grafico-servicos"), {
      type: "bar",
      data: {
        labels: data.servicos.map((s) => s.servico),
        datasets: [
          {
            label: "Total de Agendamentos",
            data: data.servicos.map((s) => s.total_agendamentos),
            backgroundColor: cores,
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: "top",
          },
          title: {
            display: true,
            text: "Serviços Mais Requisitados",
          },
        },
      },
    });

    // Faturamento projetado x realizado
    if (graficos.faturamento) graficos.faturamento.destroy();
    graficos.faturamento = new Chart(
      document.getElementById("grafico-faturamento"),
      {
        type: "line",
        data: {
          labels: data.faturamento.map((f) => f.data),
          datasets: [
            {
              label: "Realizado",
              data: data.faturamento.map((f) => f.realizado),
              borderColor: "#1cc88a",
              tension: 0.1,
            },
            {
              label: "Projetado",
              data: data.faturamento.map((f) => f.projetado),
              borderColor: "#4e73df",
              tension: 0.1,
            },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              position: "top",
            },
            title: {
              display: true,
              text: "Faturamento Projetado x Realizado",
            },
          },
        },
      }
    );

    // Serviços por profissional
    if (graficos.servicosProfissional) graficos.servicosProfissional.destroy();

    // Organizar dados para gráfico empilhado
    const profissionais = [
      ...new Set(data.servicosProfissional.map((sp) => sp.atendente)),
    ];
    const servicos = [
      ...new Set(data.servicosProfissional.map((sp) => sp.servico)),
    ];

    const datasets = servicos.map((servico, index) => ({
      label: servico,
      data: profissionais.map((prof) => {
        const item = data.servicosProfissional.find(
          (sp) => sp.atendente === prof && sp.servico === servico
        );
        return item ? item.total_servicos : 0;
      }),
      backgroundColor: cores[index % cores.length],
    }));

    graficos.servicosProfissional = new Chart(
      document.getElementById("grafico-servicos-profissional"),
      {
        type: "bar",
        data: {
          labels: profissionais,
          datasets: datasets,
        },
        options: {
          responsive: true,
          scales: {
            x: {
              stacked: true,
            },
            y: {
              stacked: true,
            },
          },
          plugins: {
            legend: {
              position: "top",
            },
            title: {
              display: true,
              text: "Serviços por Profissional",
            },
          },
        },
      }
    );
  }

  // Event Listeners
  periodoFiltro.addEventListener("change", function () {
    carregarDados(this.value);
  });

  exportarBtn.addEventListener("click", async function () {
    try {
      const periodo = periodoFiltro.value;
      const response = await fetch(`exportar_relatorio.php?periodo=${periodo}`);
      const blob = await response.blob();

      const url = window.URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `relatorio_${periodo}dias.csv`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      a.remove();
    } catch (error) {
      console.error("Erro:", error);
      Swal.fire({
        icon: "error",
        title: "Erro",
        text: "Erro ao exportar relatório",
      });
    }
  });

  // Carregar dados iniciais
  carregarDados(periodoFiltro.value);
});
