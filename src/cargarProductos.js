async function cargarProductos() {
  try {
    console.log("🔄 Iniciando carga de productos...");
    
    const res = await fetch("api/fichas/get_fichas_select.php");
    
    console.log("📡 Respuesta recibida:", res.status);
    
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    }
    
    const productos = await res.json();
    console.log("📦 Productos recibidos:", productos);

    const select = document.getElementById("selectFichaPedido");
    if (!select) {
      console.warn("❌ No se encontró el select con id 'selectFichaPedido'");
      return;
    }

    // Limpiar el select antes de agregar nuevas opciones
    select.innerHTML = '<option value="">Seleccione un producto</option>';

    if (Array.isArray(productos) && productos.length > 0) {
      productos.forEach(p => {
        const option = document.createElement("option");
        option.value = p.id;
        option.textContent = p.nombre;
        select.appendChild(option);
      });
      
      console.log(`✅ ${productos.length} productos cargados exitosamente`);
    } else {
      console.warn("⚠️ No se encontraron productos en la base de datos.");
      select.innerHTML = '<option value="">No hay productos disponibles</option>';
    }

  } catch (err) {
    console.error("❌ Error al cargar productos desde FichaTec:", err);
    
    const select = document.getElementById("selectFichaPedido");
    if (select) {
      select.innerHTML = '<option value="">Error al cargar productos</option>';
    }
  }
}

// Hacer la función disponible globalmente
window.cargarProductos = cargarProductos;

// Ejecutar automáticamente cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
  // Solo cargar si estamos en la sección de pedidos
  if (document.getElementById('selectFichaPedido')) {
    cargarProductos();
  }
});