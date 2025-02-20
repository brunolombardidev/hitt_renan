// Inicializações e configurações gerais
document.addEventListener("DOMContentLoaded", function () {
  // Exibe mensagem de sucesso/erro se existir
  if (typeof initialMessage !== "undefined" && initialMessage) {
    showAlert(
      initialMessage.type === "success" ? "Sucesso" : "Erro",
      initialMessage.text,
      initialMessage.type
    );
  }

  // Mantém a aba ativa
  const activeTab = sessionStorage.getItem("activeTab") || "inicio";
  const activeTabElement = document.getElementById(activeTab + "-tab");
  const activeTabContent = document.getElementById(activeTab);

  if (activeTabElement && activeTabContent) {
    activeTabElement.classList.add("active");
    activeTabContent.classList.add("show", "active");
  }

  // Salva a aba ativa quando mudar
  const tabLinks = document.querySelectorAll(".nav-link");
  tabLinks.forEach((tab) => {
    tab.addEventListener("click", function (e) {
      const tabId = this.getAttribute("href").replace("#", "");
      sessionStorage.setItem("activeTab", tabId);

      // Recarrega a página quando a aba de agendamentos ou início (dashboard) for selecionada
      if (tabId === "agendamentos" || tabId === "inicio") {
        e.preventDefault(); // Previne o comportamento padrão da aba

        // Envia para o servidor primeiro
        fetch("save_active_tab.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: "active_tab=" + tabId,
        }).then(() => {
          window.location.reload();
        });
        return;
      }

      // Envia para o servidor também
      fetch("save_active_tab.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "active_tab=" + tabId,
      });
    });
  });

  // Adiciona listener para o evento de mudança de aba do Bootstrap
  $('a[data-toggle="tab"]').on("shown.bs.tab", function (e) {
    if (
      e.target.getAttribute("href") === "#agendamentos" ||
      e.target.getAttribute("href") === "#inicio"
    ) {
      window.location.reload();
    }
  });

  // Configura recarregamento automático
  setInterval(() => {
    window.location.reload();
  }, 60000); // Recarrega a cada 60 segundos = 60000
});
