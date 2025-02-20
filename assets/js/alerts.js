// Funções de alertas
function showAlert(title, text, icon) {
  Swal.fire({
    title: title,
    text: text,
    icon: icon,
  });
}

function showLoading(indicatorId) {
  document.getElementById(indicatorId).style.display = "block";
}

function hideLoading(indicatorId) {
  document.getElementById(indicatorId).style.display = "none";
}
