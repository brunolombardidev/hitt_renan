document.addEventListener("DOMContentLoaded", function () {
  const collapseBtn = document.querySelector(".collapse-btn");
  const sidebar = document.querySelector(".sidebar");
  const content = document.querySelector(".content");

  // Adiciona evento de clique para os links do menu
  const menuLinks = document.querySelectorAll(".sidebar .nav-link");
  menuLinks.forEach((link) => {
    link.addEventListener("click", function (e) {
      const targetId = this.getAttribute("href").replace("#", "");

      // Se for dashboard (inicio) ou agendamentos, recarrega a página
      if (targetId === "inicio" || targetId === "agendamentos") {
        e.preventDefault();

        // Salva a aba ativa
        sessionStorage.setItem("activeTab", targetId);

        // Recarrega a página
        window.location.reload();
      }
    });
  });

  collapseBtn.addEventListener("click", function () {
    sidebar.classList.toggle("collapsed");
    content.classList.toggle("collapsed-content");
  });
});
