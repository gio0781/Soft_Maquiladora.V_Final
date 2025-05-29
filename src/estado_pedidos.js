fetch('api/pedidos/estado_pedidos.php')
  .then(res => res.json())
  .then(data => {
    if (!data.success) {
      throw new Error(data.error || "Error al cargar datos");
    }

    const estados = data.data.map(e => e.estado);
    const totales = data.data.map(e => parseInt(e.total));

    const canvas = document.getElementById("graficoEstadoPedidos") || document.getElementById("graficaPedidos");

    if (!canvas) {
      console.warn("🎯 Canvas para gráfico de pedidos no encontrado.");
      return;
    }

    new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: estados,
        datasets: [{
          data: totales,
          backgroundColor: ['#0d9488', '#b45309', '#a855f7']
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: "bottom" },
          title: { display: true, text: "Estado de Pedidos" }
        }
      }
    });
  })
  .catch(err => {
    console.error("❌ Error en gráfico de pedidos:", err);
  });
