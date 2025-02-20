// Função para detectar dispositivo móvel pelo User Agent
function isMobileDevice() {
  return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
    navigator.userAgent
  );
}

// Função para verificar resolução
function isMobileResolution() {
  return window.innerWidth <= 768;
}

// Função principal de verificação
function checkMobileAccess() {
  if (isMobileDevice() || isMobileResolution()) {
    document.getElementById("mobile-warning").style.display = "flex";
    document.getElementById("blur-overlay").classList.add("blurred");
    return true;
  }
  document.getElementById("mobile-warning").style.display = "none";
  document.getElementById("blur-overlay").classList.remove("blurred");
  return false;
}

// Inicialização
document.addEventListener("DOMContentLoaded", checkMobileAccess);
window.addEventListener("resize", checkMobileAccess);
