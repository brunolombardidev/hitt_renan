document.addEventListener("DOMContentLoaded", function () {
  // Criar botão do menu móvel
  const mobileMenuBtn = document.createElement("button");
  mobileMenuBtn.className = "mobile-menu-btn d-md-none";
  mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
  document.body.appendChild(mobileMenuBtn);

  // Criar overlay
  const overlay = document.createElement("div");
  overlay.className = "mobile-overlay";
  document.body.appendChild(overlay);

  const sidebar = document.querySelector(".sidebar");

  // Função para alternar menu
  function toggleMobileMenu() {
    sidebar.classList.toggle("mobile-open");
    overlay.classList.toggle("active");
    document.body.style.overflow = sidebar.classList.contains("mobile-open")
      ? "hidden"
      : "";
  }

  // Event listeners
  mobileMenuBtn.addEventListener("click", toggleMobileMenu);
  overlay.addEventListener("click", toggleMobileMenu);

  // Fechar menu ao clicar em links
  document.querySelectorAll(".sidebar .nav-link").forEach((link) => {
    link.addEventListener("click", () => {
      if (window.innerWidth <= 768) {
        toggleMobileMenu();
      }
    });
  });

  // Ajustar ao redimensionar
  window.addEventListener("resize", () => {
    if (window.innerWidth > 768) {
      sidebar.classList.remove("mobile-open");
      overlay.classList.remove("active");
      document.body.style.overflow = "";
    }
  });
});
