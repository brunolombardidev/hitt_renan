document.addEventListener("DOMContentLoaded", function () {
  // Verificar tanto notificações quanto som do localStorage
  let notificationsEnabled =
    localStorage.getItem("notificationsEnabled") === "true" ||
    Notification.permission === "granted";
  let soundEnabled = localStorage.getItem("soundEnabled") === "true";
  const notificationSound = new Audio("assets/audio/notification.mp3");

  // Se as notificações estiverem habilitadas, salvar no localStorage
  if (Notification.permission === "granted") {
    localStorage.setItem("notificationsEnabled", "true");
  }

  // Verificar se é dispositivo móvel ou tela pequena
  function isMobileOrSmallScreen() {
    return (
      window.innerWidth <= 768 ||
      /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
        navigator.userAgent
      )
    );
  }

  // Criar o container de notificações
  const notificationContainer = document.createElement("div");
  notificationContainer.style.cssText = `
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
  `;
  document.body.appendChild(notificationContainer);

  // Criar o botão de permissão apenas se não for mobile
  if (!isMobileOrSmallScreen()) {
    const permissionButton = document.createElement("div");
    permissionButton.id = "notification-permission-button";
    permissionButton.style.cssText = `
      position: fixed;
      bottom: 20px;
      right: 20px;
      background-color: #fff;
      padding: 15px 25px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      z-index: 10000;
    `;

    // Botão fechar
    const closeButton = document.createElement("span");
    closeButton.innerHTML = "✕";
    closeButton.style.cssText = `
      position: absolute;
      top: -10px;
      right: -10px;
      background-color: #ff4444;
      color: white;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 12px;
    `;

    // Verificar permissões existentes
    if (notificationsEnabled) {
      permissionButton.style.display = "none";
      startCheckingAppointments();
    } else {
      permissionButton.innerHTML = "🔔 Ativar Notificações e Som";
      permissionButton.appendChild(closeButton);
      document.body.appendChild(permissionButton);
    }

    // Evento de clique no botão de permissão
    permissionButton.addEventListener("click", async function (e) {
      if (e.target === closeButton) {
        permissionButton.remove();
        const message = document.createElement("div");
        message.style.cssText = `
          position: fixed;
          top: 20px;
          right: 20px;
          background-color: #f8f9fa;
          padding: 15px 25px;
          border-radius: 8px;
          box-shadow: 0 2px 10px rgba(0,0,0,0.1);
          z-index: 10000;
        `;
        message.textContent =
          "Você pode ativar as notificações mais tarde nas configurações do navegador";
        document.body.appendChild(message);
        setTimeout(() => message.remove(), 5000);
        return;
      }

      try {
        const permission = await Notification.requestPermission();
        if (permission === "granted") {
          notificationsEnabled = true;
          soundEnabled = true;

          // Salvar ambos os estados no localStorage
          localStorage.setItem("notificationsEnabled", "true");
          localStorage.setItem("soundEnabled", "true");

          // Testar o som com interação do usuário
          try {
            await notificationSound.play();
            notificationSound.volume = 0.5;
          } catch (error) {
            console.warn("Não foi possível reproduzir o som:", error);
          }

          permissionButton.innerHTML = "🔔 Notificações e Som Ativados";
          setTimeout(() => {
            permissionButton.style.display = "none";
          }, 2000);
          startCheckingAppointments();
        }
      } catch (error) {
        console.error("Erro ao solicitar permissão:", error);
      }
    });
  }

  function startCheckingAppointments() {
    checkNewAppointments();
    setInterval(checkNewAppointments, 30000);
  }

  async function checkNewAppointments() {
    try {
      const response = await fetch("check_new_appointments.php");
      const appointments = await response.json();

      // Se houver novos agendamentos, tocar o som antes de criar as notificações
      if (appointments.length > 0) {
        try {
          const sound = new Audio("assets/audio/notification.mp3");
          sound.volume = 0.5;
          await sound.play();
        } catch (error) {
          console.warn("Erro ao reproduzir som:", error);
        }
      }

      appointments.forEach((appointment) => {
        createNotification(appointment);
      });
    } catch (error) {
      console.error("Erro ao verificar agendamentos:", error);
    }
  }

  function createNotification(appointment) {
    const notification = document.createElement("div");
    notification.style.cssText = `
      background-color: #ffffff;
      border-left: 4px solid #4CAF50;
      box-shadow: 0 2px 5px rgba(0,0,0,0.2);
      padding: 16px;
      margin-bottom: 10px;
      border-radius: 4px;
      width: 300px;
      position: relative;
      opacity: 0;
      transform: translateX(100%);
      animation: slideIn 0.5s ease-out forwards;
    `;

    notification.innerHTML = `
      <div style="margin-bottom: 8px;">
        <strong>Novo Agendamento!</strong>
        <span style="position: absolute; right: 10px; top: 10px; cursor: pointer;" onclick="closeNotification(this, ${appointment.id}, true)">✕</span>
      </div>
      <div><i class="fas fa-id-card"></i> Cliente: ${appointment.cliente}</div>
      <div><i class="fas fa-calendar-alt"></i> Data: ${appointment.data}</div>
      <div><i class="fas fa-clock"></i> Hora: ${appointment.hora}</div>
      <div><i class="fas fa-tools"></i> Serviço: ${appointment.servico}</div>
    `;

    notificationContainer.appendChild(notification);

    // Configurar o timeout para auto-fechamento após 10 segundos
    setTimeout(() => {
      if (notification.parentElement) {
        // Apenas anima o fechamento sem marcar como visualizado
        notification.style.animation = "fadeOut 1s ease-out forwards";
      }
    }, 10000);
  }

  window.closeNotification = function (
    element,
    appointmentId,
    immediate = false
  ) {
    const notificationDiv = element.closest("div").parentElement;

    if (immediate) {
      // Só marca como visualizado quando clica no X (immediate = true)
      fetch("mark_appointment_viewed.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "id=" + appointmentId,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            notificationDiv.style.animation = "slideOut 0.5s ease-out forwards";
          }
        })
        .catch((error) =>
          console.error("Erro ao marcar como visualizado:", error)
        );
    } else {
      // Fechamento automático apenas anima, sem marcar como visualizado
      notificationDiv.style.animation = "fadeOut 1s ease-out forwards";
    }
  };

  // Atualizar os estilos de animação
  const style = document.createElement("style");
  style.textContent = `
    @keyframes slideIn {
      0% { 
        transform: translateX(100%);
        opacity: 0;
      }
      50% { 
        transform: translateX(-10px);
        opacity: 0.8;
      }
      100% { 
        transform: translateX(0);
        opacity: 1;
      }
    }

    @keyframes slideOut {
      0% { 
        transform: translateX(0);
        opacity: 1;
      }
      100% { 
        transform: translateX(100%);
        opacity: 0;
      }
    }

    @keyframes fadeOut {
      0% {
        opacity: 1;
        transform: translateY(0);
      }
      100% {
        opacity: 0;
        transform: translateY(20px);
      }
    }
  `;
  document.head.appendChild(style);

  // Adicionar função para desativar notificações e som
  window.disableNotificationsAndSound = function () {
    notificationsEnabled = false;
    soundEnabled = false;
    localStorage.removeItem("notificationsEnabled");
    localStorage.removeItem("soundEnabled");
  };

  // Verificar se as permissões ainda são válidas ao iniciar
  async function checkPermissions() {
    if (Notification.permission !== "granted") {
      notificationsEnabled = false;
      soundEnabled = false;
      localStorage.removeItem("notificationsEnabled");
      localStorage.removeItem("soundEnabled");

      if (!isMobileOrSmallScreen()) {
        permissionButton.style.display = "block";
      }
    }
  }

  // Chamar verificação de permissões ao iniciar
  checkPermissions();
});
