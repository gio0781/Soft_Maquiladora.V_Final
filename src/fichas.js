export function cargarFichas() {
  fetch("api/fichas/get_fichas.php")
    .then(res => res.json())
    .then(data => {
      const contenedor = document.getElementById("contenedor-fichas");
      contenedor.innerHTML = "";
      data.forEach(f => {
        const div = document.createElement("div");
        div.className = "bg-white p-4 rounded-lg shadow-md neumorphic mb-4";
        const timestamp = Date.now();
        div.innerHTML = `
          <img src="img/${f.sku}.jpg?t=${timestamp}" alt="${f.nombre}" class="w-full h-48 object-cover rounded mb-3" onerror="this.src='img/placeholder.jpg'" />
          <h3 class="text-lg font-semibold mb-2">${f.nombre}</h3>
          <p class="text-sm text-gray-700">${f.descripcion}</p>
          <p class="text-sm text-gray-500 mt-1">SKU: ${f.sku}</p>
          <div class="flex justify-end gap-2 mt-3">
            <button onclick="editarFicha(${f.idfic})" class="bg-blue-500 text-white px-3 py-1 rounded">Editar</button>
            <button onclick="eliminarFicha(${f.idfic})" class="bg-red-500 text-white px-3 py-1 rounded">Eliminar</button>
          </div>
        `;
        contenedor.appendChild(div);
      });
    })
    .catch(err => console.error("Error al cargar fichas:", err));
}

export function guardarFicha(form) {
  const formData = new FormData(form); // Aquí viene la imagen y demás campos
  return fetch("api/fichas/crear_ficha.php", {
    method: "POST",
    body: formData
  }).then(res => res.json());
}

export function editarFicha(idfic) {
  fetch(`api/fichas/get_ficha.php?id=${idfic}`)
    .then(res => res.json())
    .then(data => {
      if (data) {
        document.getElementById("nombreFicha").value = data.nombre;
        document.getElementById("descripcionFicha").value = data.descripcion;
        document.getElementById("skuFicha").value = data.sku;
        document.getElementById("idFicha").value = data.idfic;
        document.getElementById("modoFicha").value = "editar";
        document.getElementById("formFichaContainer").classList.remove("hidden");
      }
    })
    .catch(err => console.error("Error al obtener ficha:", err));
}

export function guardarCambiosFicha(datos) {
  return fetch("api/fichas/editar_ficha.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(datos)
  }).then(res => res.json());
}

export function eliminarFicha(idfic) {
  if (confirm("¿Estás seguro de que deseas eliminar esta ficha?")) {
    fetch(`api/fichas/delete_ficha.php?id=${idfic}`, { method: "DELETE" })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert("Ficha eliminada correctamente.");
          cargarFichas();
        } else {
          alert("Error al eliminar la ficha.");
        }
      })
      .catch(err => console.error("Error al eliminar ficha:", err));
  }
}