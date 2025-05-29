document.addEventListener("DOMContentLoaded", () => {
  const nombreUsuario = localStorage.getItem("nombreUsuario");
  const jerarquiaUsuario = localStorage.getItem("jerarquiaUsuario");

  const botones = {
    resumen: document.getElementById("btn-resumen"),
    logistica: document.getElementById("btn-logistica"),
    fichas: document.getElementById("btn-fichas"),
    pedidos: document.getElementById("btn-pedidos"),
    asignacion: document.getElementById("btn-asignacion")
  };

  const content = document.getElementById("content");

  window.mostrarSeccion = function (nombre) {
    for (let btn in botones) {
      if (botones[btn]) {
        botones[btn].classList.remove("text-teal-600");
      }
    }

    if (botones[nombre]) {
      botones[nombre].classList.add("text-teal-600");
    }

    

    if (nombre === "logistica") {
      content.innerHTML = secciones[nombre];  // << ESTA LÍNEA FALTABA
      fetch("api/inventario/get_inventario.php")
      setTimeout(() => {
        const btnAgregar = document.getElementById("btnAgregarMaterial");
        const btnEditar = document.getElementById("btnEditarMaterial");
        const btnEliminar = document.getElementById("btnEliminarMaterial");
    
        if (btnAgregar) {
          btnAgregar.addEventListener("click", () => {
            mostrarFormularioInventario("agregar");
          });
        }
    
        if (btnEditar) {
          btnEditar.addEventListener("click", () => {
            const id = prompt("Ingresa el ID del material a editar:");
            if (id) mostrarFormularioInventario("editar", id);
          });
        }
    
        if (btnEliminar) {
          btnEliminar.addEventListener("click", () => {
            const id = prompt("Ingresa el ID del material a eliminar:");
            if (id && confirm("¿Estás seguro de eliminar este material?")) {
              eliminarMaterial(id);
            }
          });
        }
      }, 100);
    }

    if (nombre === "pedidos") {
      const select = document.querySelector("#selectFicha");
      if (select) {
        fetch("api/fichas/get_fichas_select.php")
          .then(response => response.json())
          .then(data => {
            select.innerHTML = '<option value="">Seleccione un producto</option>';
            data.forEach(ficha => {
              const option = document.createElement("option");
              option.value = ficha.idfic;
              option.textContent = ficha.nombre;
              select.appendChild(option);
            });
          })
          .catch(error => console.error("Error al cargar fichas:", error));
      }

      cargarPedidos();

      const formPedido = document.getElementById("formPedido");
      if (formPedido && !formPedido.dataset.listener) {
        formPedido.addEventListener("submit", async (e) => {
          e.preventDefault();
          const datos = {
            cliente: document.getElementById("cliente").value,
            cantidad: document.getElementById("cantidad").value,
            fecha_registro: document.getElementById("fechaRegistro").value,
            fecha_entrega: document.getElementById("fechaEntrega").value,
           producto: document.getElementById("selectFichaPedido").value
          };
          try {
            const res = await fetch("api/pedidos/registrar_pedido.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(datos)
            });
            const result = await res.json();
            if (result.success) {
              alert("Pedido registrado exitosamente.");
              formPedido.reset();
              cargarPedidos();
            } else {
              alert("Error: " + result.error);
            }
          } catch (err) {
            console.error("Error al registrar pedido:", err);
          }
        });
        formPedido.dataset.listener = "true";
      }
    }
  };

  mostrarSeccion("resumen"); // Cargar sección por defecto
});

function cargarPedidos() {
  fetch("api/pedidos/get_pedidos.php")
    .then(res => res.json())
    .then(data => {
      const tabla = document.querySelector("#tablaPedidos tbody");
      if (!tabla) return;
      tabla.innerHTML = "";

      if (!Array.isArray(data) || data.length === 0) {
        tabla.innerHTML = `<tr><td colspan="5" class="text-center">No hay pedidos registrados.</td></tr>`;
        return;
      }

      data.forEach(p => {
        const fila = document.createElement("tr");
        fila.innerHTML = `
          <td>${p.cliente}</td>
          <td>${p.producto}</td>
          <td>${p.cantidad}</td>
          <td>${p.fecha_registro}</td>
          <td>${p.fecha_entrega}</td>
        `;
        tabla.appendChild(fila);
      });
    })
    .catch(err => {
      console.error("Error al cargar pedidos:", err);
    });
}