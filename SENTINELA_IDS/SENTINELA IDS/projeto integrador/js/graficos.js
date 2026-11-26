function cssVar(n){ return getComputedStyle(document.documentElement).getPropertyValue(n).trim(); }

function initCharts(){
  const accent = cssVar('--accent'), grid = cssVar('--chart-grid'), txt = cssVar('--chart-text'), surf = cssVar('--surface');
  Chart.defaults.font.family = cssVar('--font');
  const ctx = document.getElementById('chart-timeline').getContext('2d');
  const g = ctx.createLinearGradient(0, 0, 0, 260);
  g.addColorStop(0, accent + '55'); g.addColorStop(1, accent + '00');
  timelineChart = new Chart(ctx, {
    type:'line',
    data:{ labels:[], datasets:[{ data:[], borderColor:accent, backgroundColor:g, borderWidth:2.5, tension:.38, fill:true,
      pointRadius:3, pointHoverRadius:6, pointBackgroundColor:surf, pointBorderColor:accent, pointBorderWidth:2 }]},
    options:{ responsive:true, interaction:{ intersect:false, mode:'index' }, plugins:{ legend:{display:false} },
      scales:{ x:{ ticks:{ color:txt, font:{size:11} }, grid:{ display:false }, border:{ display:false } },
               y:{ beginAtZero:true, ticks:{ color:txt, font:{size:11}, precision:0 }, grid:{ color:grid }, border:{ display:false } } } }
  });
  typesChart = new Chart(document.getElementById('chart-types'), {
    type:'doughnut',
    data:{ labels:[], datasets:[{ data:[], backgroundColor:[accent,'#fbbf24','#f43f5e','#a78bfa','#38bdf8','#78716c'], borderColor:surf, borderWidth:3, hoverOffset:6 }]},
    options:{ responsive:true, cutout:'68%', plugins:{ legend:{ position:'bottom', labels:{ color:txt, font:{size:11.5}, boxWidth:10, boxHeight:10, usePointStyle:true, padding:14 } } } }
  });
}

function updateCharts(){
  timelineChart.data.labels = timelineBuckets.map(b => b.label);
  timelineChart.data.datasets[0].data = timelineBuckets.map(b => b.count);
  timelineChart.update();

  typesChart.data.labels = Object.keys(typeCounts);
  typesChart.data.datasets[0].data = Object.values(typeCounts);
  typesChart.update();
}
