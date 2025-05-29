document.addEventListener('DOMContentLoaded', function () {
  fetch("api/produccion/get_seguimiento.php")
    .then(response => response.json())
    .then(data => {
      const tabla = document.getElementById("tabla-seguimiento-produccion");
      if (!tabla) {
        console.error("No se encontró el tbody con id tabla-seguimiento-produccion");
        return;
      }
      tabla.innerHTML = ""; // Limpiar tabla

      data.forEach(item => {
        const fila = document.createElement("tr");

        let estadoHTML = item.estado === "Completado"
          ? '<span class="bg-green-200 text-green-800 px-2 py-1 rounded">Completado</span>'
          : '<span class="bg-yellow-200 text-yellow-800 px-2 py-1 rounded">' + item.estado + '</span>';

        fila.innerHTML = `
          <td class="border px-4 py-2">${item.id_orden}</td>
          <td class="border px-4 py-2">${item.producto}</td>
          <td class="border px-4 py-2">${item.cantidad}</td>
          <td class="border px-4 py-2">${item.area}</td>
          <td class="border px-4 py-2">${estadoHTML}</td>
          <td class="border px-4 py-2">${item.operadoras.join(', ')}</td>
        `;
        tabla.appendChild(fila);
      });
    })
    .catch(error => {
      console.error("Error al cargar seguimiento:", error);
    });
});
