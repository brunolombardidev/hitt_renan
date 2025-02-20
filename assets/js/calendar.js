class Calendar {
  constructor(container) {
    this.container = container;
    this.currentDate = new Date();
    this.events = [];
    this.init();
  }

  init() {
    this.createCalendarStructure();
    this.loadEvents();
    this.bindEvents();
  }

  createCalendarStructure() {
    this.container.innerHTML = `
            <div class="calendar-container">
                <div class="calendar-header">
                    <div class="calendar-nav">
                        <button class="prev-btn">&lt;</button>
                        <h2 class="current-month"></h2>
                        <button class="next-btn">&gt;</button>
                    </div>
                </div>
                <div class="calendar-grid"></div>
            </div>
        `;

    this.updateCalendar();
  }

  async loadEvents() {
    try {
      const month = this.currentDate.getMonth() + 1;
      const year = this.currentDate.getFullYear();
      const response = await fetch(
        `get_calendar_events.php?month=${month}&year=${year}`
      );
      const data = await response.json();

      if (data.success) {
        this.events = data.events;
        this.updateCalendar();
      }
    } catch (error) {
      console.error("Erro ao carregar eventos:", error);
    }
  }

  bindEvents() {
    this.container.querySelector(".prev-btn").addEventListener("click", () => {
      this.navigateMonth(-1);
    });

    this.container.querySelector(".next-btn").addEventListener("click", () => {
      this.navigateMonth(1);
    });
  }

  navigateMonth(direction) {
    this.currentDate.setMonth(this.currentDate.getMonth() + direction);
    this.loadEvents();
  }

  updateCalendar() {
    const monthNames = [
      "Janeiro",
      "Fevereiro",
      "Março",
      "Abril",
      "Maio",
      "Junho",
      "Julho",
      "Agosto",
      "Setembro",
      "Outubro",
      "Novembro",
      "Dezembro",
    ];

    this.container.querySelector(".current-month").textContent = `${
      monthNames[this.currentDate.getMonth()]
    } ${this.currentDate.getFullYear()}`;

    this.renderMonthView();
  }

  renderMonthView() {
    const grid = this.container.querySelector(".calendar-grid");
    grid.innerHTML = "";

    // Adiciona cabeçalho dos dias da semana
    const weekDays = ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"];
    weekDays.forEach((day) => {
      const dayElement = document.createElement("div");
      dayElement.className = "calendar-weekday";
      dayElement.textContent = day;
      grid.appendChild(dayElement);
    });

    // Gera os dias do mês
    const firstDay = new Date(
      this.currentDate.getFullYear(),
      this.currentDate.getMonth(),
      1
    );
    const lastDay = new Date(
      this.currentDate.getFullYear(),
      this.currentDate.getMonth() + 1,
      0
    );

    // Dias do mês anterior
    for (let i = 0; i < firstDay.getDay(); i++) {
      const dayElement = document.createElement("div");
      dayElement.className = "calendar-day other-month";
      grid.appendChild(dayElement);
    }

    // Dias do mês atual
    for (let day = 1; day <= lastDay.getDate(); day++) {
      const dayElement = document.createElement("div");
      dayElement.className = "calendar-day";

      const currentDate = new Date(
        this.currentDate.getFullYear(),
        this.currentDate.getMonth(),
        day
      );

      if (this.isToday(currentDate)) {
        dayElement.classList.add("today");
      }

      // Criar container para o número do dia
      const dayNumber = document.createElement("div");
      dayNumber.className = "day-number";
      dayNumber.textContent = day;
      dayElement.appendChild(dayNumber);

      // Criar container para os eventos
      const eventsContainer = document.createElement("div");
      eventsContainer.className = "calendar-events-container";
      dayElement.appendChild(eventsContainer);

      // Adiciona eventos do dia
      const dayEvents = this.getEventsForDay(currentDate);
      dayEvents.sort((a, b) => new Date(a.start) - new Date(b.start)); // Ordena por horário

      dayEvents.forEach((event) => {
        const eventElement = this.createEventElement(event);
        eventsContainer.appendChild(eventElement);
      });

      grid.appendChild(dayElement);
    }
  }

  createEventElement(event) {
    const eventElement = document.createElement("div");
    eventElement.className = "calendar-event";
    eventElement.style.backgroundColor = event.color;

    // Formata a hora para exibição
    const eventTime = new Date(event.start).toLocaleTimeString().slice(0, 5);
    eventElement.textContent = `${eventTime} - ${event.title}`;

    // Adiciona tooltip
    eventElement.addEventListener("mouseover", (e) => {
      const tooltip = document.createElement("div");
      tooltip.className = "calendar-event-tooltip";
      tooltip.innerHTML = `
        <strong>${event.title}</strong><br>
        <i class="fas fa-clock"></i> ${new Date(
          event.start
        ).toLocaleTimeString()}<br>
        <i class="fas fa-phone"></i> ${event.details.telefone}<br>
        <i class="fas fa-tools"></i> ${event.details.servico}<br>
        <i class="fas fa-user"></i> ${event.details.atendente}<br>
        <i class="fas fa-info-circle"></i> ${event.details.status}
      `;

      document.body.appendChild(tooltip);

      const rect = e.target.getBoundingClientRect();
      const tooltipHeight = tooltip.offsetHeight;
      const tooltipWidth = tooltip.offsetWidth;

      // Posiciona o tooltip evitando que saia da tela
      let left = rect.left;
      let top = rect.bottom + 5;

      if (left + tooltipWidth > window.innerWidth) {
        left = window.innerWidth - tooltipWidth - 10;
      }

      if (top + tooltipHeight > window.innerHeight) {
        top = rect.top - tooltipHeight - 5;
      }

      tooltip.style.left = `${left}px`;
      tooltip.style.top = `${top}px`;
      tooltip.style.display = "block";

      const removeTooltip = () => {
        tooltip.remove();
        eventElement.removeEventListener("mouseleave", removeTooltip);
      };

      eventElement.addEventListener("mouseleave", removeTooltip);
    });

    return eventElement;
  }

  isToday(date) {
    const today = new Date();
    return (
      date.getDate() === today.getDate() &&
      date.getMonth() === today.getMonth() &&
      date.getFullYear() === today.getFullYear()
    );
  }

  getEventsForDay(date) {
    return this.events.filter((event) => {
      const eventDate = new Date(event.start);
      return (
        eventDate.getDate() === date.getDate() &&
        eventDate.getMonth() === date.getMonth() &&
        eventDate.getFullYear() === date.getFullYear()
      );
    });
  }
}

// Inicialização do calendário
document.addEventListener("DOMContentLoaded", () => {
  const calendarContainer = document.getElementById("calendar");
  if (calendarContainer) {
    new Calendar(calendarContainer);
  }
});
