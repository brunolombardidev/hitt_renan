// Funções do relógio
function atualizarRelogio() {
  const now = new Date();
  const data = now.toLocaleDateString("pt-BR");
  const opcoes = {
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hour12: false,
  };
  const hora = now.toLocaleTimeString("pt-BR", opcoes);
  document.getElementById("relogio").textContent = `${data} ${hora}`;
}

// Inicializa o relógio quando o documento estiver pronto
document.addEventListener("DOMContentLoaded", () => {
  // Atualiza imediatamente
  atualizarRelogio();

  // Configura a atualização a cada segundo
  setInterval(atualizarRelogio, 1000);
});
