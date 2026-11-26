const dados = JSON.parse(document.getElementById('dados-painel').textContent);
const css       = n => getComputedStyle(document.documentElement).getPropertyValue(n).trim();
const corAccent = css('--accent');
const corGrid   = css('--chart-grid');
const corTexto  = css('--chart-text');
const corSurf   = css('--surface');
Chart.defaults.font.family = css('--font');

new Chart(document.getElementById('chart-timeline'), {
  type: 'bar',
  data: {
    labels: dados.dias,
    datasets: [{
      label: 'Alertas',
      data: dados.totais,
      backgroundColor: corAccent,
      borderRadius: 8,
      borderSkipped: false,
      maxBarThickness: 38,
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { precision: 0, color: corTexto }, grid: { color: corGrid }, border: { display: false } },
      x: { ticks: { color: corTexto }, grid: { display: false }, border: { display: false } }
    }
  }
});

if (dados.tipos.length) {
new Chart(document.getElementById('chart-types'), {
  type: 'doughnut',
  data: {
    labels: dados.tipos,
    datasets: [{
      data: dados.tiposTotais,
      backgroundColor: [corAccent, '#fbbf24', '#f43f5e', '#a78bfa', '#38bdf8', '#78716c'],
      borderColor: corSurf,
      borderWidth: 3,
      hoverOffset: 6,
    }]
  },
  options: {
    cutout: '68%',
    plugins: { legend: { position: 'bottom', labels: { color: corTexto, boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 14, font: { size: 11.5 } } } }
  }
});
} else {
document.getElementById('chart-types').closest('.panel-body').innerHTML =
  '<div class="mini-empty">Sem dados suficientes ainda.</div>';
}
