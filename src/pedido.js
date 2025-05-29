// === SISTEMA COMPLETO DE PEDIDOS - VERSIÓN CORREGIDA ===
// Archivo: src/pedido.js

console.log("🚀 Cargando sistema de pedidos...");

// Variables globales
let productosDisponibles = [];
let pedidosData = [];

/**
 * Inicializar completamente el sistema de pedidos
 */
function inicializarSistemaPedidos() {
    console.log("🔧 Inicializando sistema completo de pedidos...");
    
    // Ejecutar inicialización con delay para asegurar DOM
    setTimeout(() => {
        cargarProductosParaFormulario();
        configurarFormularioPedidosCompleto();
        cargarTodosPedidos();
    }, 300);
}

/**
 * Cargar productos desde fichas técnicas - CORREGIDO
 */
async function cargarProductosParaFormulario() {
    console.log("📦 Cargando productos desde fichas técnicas...");
    
    const select = document.getElementById("selectFichaPedido");
    if (!select) {
        console.warn("⚠️ Select de productos no encontrado en DOM");
        return;
    }

    try {
        // Mostrar estado de carga
        select.innerHTML = '<option value="">🔄 Cargando productos...</option>';
        select.disabled = true;

        const response = await fetch("api/fichas/get_fichas_select.php");
        
        if (!response.ok) {
            throw new Error(`Error HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        console.log("📦 Respuesta de productos:", data);

        // Limpiar y poblar el select
        select.innerHTML = '<option value="">Seleccione un producto</option>';
        
        if (Array.isArray(data) && data.length > 0) {
            productosDisponibles = data;
            
            data.forEach(producto => {
                const option = document.createElement("option");
                option.value = producto.id; // ID de la ficha técnica
                option.textContent = producto.nombre; // Nombre del producto
                select.appendChild(option);
            });
            
            select.disabled = false;
            console.log(`✅ ${data.length} productos cargados correctamente`);
            
        } else {
            console.warn("⚠️ No hay productos disponibles");
            select.innerHTML = '<option value="">No hay productos disponibles</option>';
            
            // Mostrar mensaje informativo
            mostrarMensajeInfo("No hay productos disponibles. Registre fichas técnicas primero.");
        }

    } catch (error) {
        console.error("❌ Error cargando productos:", error);
        
        select.innerHTML = '<option value="">Error al cargar productos</option>';
        select.disabled = true;
        
        mostrarMensajeError("Error cargando productos: " + error.message);
    }
}

/**
 * Configurar completamente el formulario de pedidos - CORREGIDO
 */
function configurarFormularioPedidosCompleto() {
    console.log("⚙️ Configurando formulario completo de pedidos...");
    
    const form = document.getElementById("formPedido");
    if (!form) {
        console.error("❌ Formulario de pedidos no encontrado");
        return;
    }

    // Evitar reconfiguración
    if (form.dataset.sistemaConfigurado === "true") {
        console.log("ℹ️ Formulario ya configurado por el sistema");
        return;
    }

    // Configurar fechas por defecto
    establecerFechasPorDefecto();

    // Remover listeners anteriores si existen
    const nuevoForm = form.cloneNode(true);
    form.parentNode.replaceChild(nuevoForm, form);

    // Configurar nuevo event listener
    const formularioActualizado = document.getElementById("formPedido");
    if (formularioActualizado) {
        formularioActualizado.addEventListener("submit", procesarEnvioPedido);
        
        // Configurar validaciones en tiempo real
        configurarValidacionesFormulario(formularioActualizado);

        // Marcar como configurado
        formularioActualizado.dataset.sistemaConfigurado = "true";
    }
    
    console.log("✅ Formulario configurado completamente");
}

/**
 * Establecer fechas por defecto en el formulario
 */
function establecerFechasPorDefecto() {
    const hoy = new Date();
    const fechaEntrega = new Date();
    fechaEntrega.setDate(fechaEntrega.getDate() + 7); // 7 días después

    const fechaRegistroInput = document.getElementById("fechaRegistro");
    const fechaEntregaInput = document.getElementById("fechaEntrega");

    if (fechaRegistroInput && !fechaRegistroInput.value) {
        fechaRegistroInput.value = hoy.toISOString().split('T')[0];
    }
    
    if (fechaEntregaInput && !fechaEntregaInput.value) {
        fechaEntregaInput.value = fechaEntrega.toISOString().split('T')[0];
    }
}

/**
 * Configurar validaciones del formulario en tiempo real
 */
function configurarValidacionesFormulario(form) {
    // Validación de cliente
    const clienteInput = document.getElementById("cliente");
    if (clienteInput) {
        clienteInput.addEventListener("blur", () => {
            const valor = clienteInput.value.trim();
            if (valor.length > 0 && valor.length < 2) {
                mostrarErrorCampo(clienteInput, "El nombre debe tener al menos 2 caracteres");
            } else {
                limpiarErrorCampo(clienteInput);
            }
        });
    }

    // Validación de cantidad
    const cantidadInput = document.getElementById("cantidad");
    if (cantidadInput) {
        cantidadInput.addEventListener("blur", () => {
            const valor = parseInt(cantidadInput.value);
            if (isNaN(valor) || valor <= 0) {
                mostrarErrorCampo(cantidadInput, "La cantidad debe ser mayor a 0");
            } else if (valor > 100000) {
                mostrarErrorCampo(cantidadInput, "Cantidad muy alta, verifique el valor");
            } else {
                limpiarErrorCampo(cantidadInput);
            }
        });
    }

    // Validación de fechas
    const fechaRegistroInput = document.getElementById("fechaRegistro");
    const fechaEntregaInput = document.getElementById("fechaEntrega");
    
    if (fechaRegistroInput && fechaEntregaInput) {
        const validarFechas = () => {
            const fechaRegistro = new Date(fechaRegistroInput.value);
            const fechaEntrega = new Date(fechaEntregaInput.value);
            
            if (fechaEntrega <= fechaRegistro) {
                mostrarErrorCampo(fechaEntregaInput, "La fecha de entrega debe ser posterior al registro");
            } else {
                limpiarErrorCampo(fechaEntregaInput);
            }
        };
        
        fechaRegistroInput.addEventListener("change", validarFechas);
        fechaEntregaInput.addEventListener("change", validarFechas);
    }
}

/**
 * Procesar el envío del pedido - CORREGIDO
 */
async function procesarEnvioPedido(event) {
    event.preventDefault();
    console.log("📤 Procesando envío de pedido...");

    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    
    try {
        // Obtener y validar datos
        const datosPedido = obtenerDatosFormulario();
        
        if (!validarDatosPedido(datosPedido)) {
            return; // Las validaciones muestran sus propios errores
        }

        // Deshabilitar botón durante el envío
        const textoOriginal = submitBtn.textContent;
        submitBtn.textContent = "⏳ Registrando pedido...";
        submitBtn.disabled = true;

        console.log("📦 Enviando datos:", datosPedido);

        // Realizar petición usando la API correcta
        const response = await fetch("api/pedidos/registrar_pedido.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify(datosPedido)
        });

        console.log("📡 Respuesta del servidor:", response.status, response.statusText);

        // Verificar tipo de contenido
        const contentType = response.headers.get("content-type") || "";
        if (!contentType.includes("application/json")) {
            const textoRespuesta = await response.text();
            console.error("❌ Respuesta no es JSON:", textoRespuesta.substring(0, 500));
            throw new Error("El servidor no devolvió una respuesta JSON válida");
        }

        const resultado = await response.json();
        console.log("📦 Resultado procesado:", resultado);

        if (resultado.success) {
            // Éxito total
            mostrarNotificacionExito("✅ ¡Pedido registrado exitosamente!", resultado);
            limpiarFormularioCompleto(form);
            
            // Recargar lista de pedidos
            setTimeout(() => {
                cargarTodosPedidos();
            }, 800);
            
        } else {
            // Error del servidor
            console.error("❌ Error del servidor:", resultado.error);
            mostrarNotificacionError("Error registrando pedido: " + (resultado.error || "Error desconocido"));
        }

    } catch (error) {
        console.error("❌ Error en procesamiento:", error);
        mostrarNotificacionError("Error de conexión: " + error.message);
    } finally {
        // Restaurar botón siempre
        if (submitBtn) {
            submitBtn.textContent = "📝 Registrar Pedido";
            submitBtn.disabled = false;
        }
    }
}

/**
 * Obtener datos del formulario - CORREGIDO
 */
function obtenerDatosFormulario() {
    return {
        cliente: document.getElementById("cliente")?.value?.trim() || "",
        cantidad: parseInt(document.getElementById("cantidad")?.value) || 0,
        fecha_registro: document.getElementById("fechaRegistro")?.value || "",
        fecha_entrega: document.getElementById("fechaEntrega")?.value || "",
        producto: parseInt(document.getElementById("selectFichaPedido")?.value) || 0
    };
}

/**
 * Validar datos del pedido antes del envío
 */
function validarDatosPedido(datos) {
    console.log("🔍 Validando datos del pedido:", datos);

    if (!datos.cliente || datos.cliente.length < 2) {
        mostrarNotificacionError("❌ El nombre del cliente debe tener al menos 2 caracteres");
        document.getElementById("cliente")?.focus();
        return false;
    }

    if (datos.cantidad <= 0 || datos.cantidad > 100000) {
        mostrarNotificacionError("❌ La cantidad debe estar entre 1 y 100,000");
        document.getElementById("cantidad")?.focus();
        return false;
    }

    if (!datos.fecha_registro) {
        mostrarNotificacionError("❌ La fecha de registro es requerida");
        document.getElementById("fechaRegistro")?.focus();
        return false;
    }

    if (!datos.fecha_entrega) {
        mostrarNotificacionError("❌ La fecha de entrega es requerida");
        document.getElementById("fechaEntrega")?.focus();
        return false;
    }

    // Validar fechas
    const fechaRegistro = new Date(datos.fecha_registro);
    const fechaEntrega = new Date(datos.fecha_entrega);
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);

    if (fechaRegistro < hoy) {
        mostrarNotificacionError("❌ La fecha de registro no puede ser anterior a hoy");
        document.getElementById("fechaRegistro")?.focus();
        return false;
    }

    if (fechaEntrega <= fechaRegistro) {
        mostrarNotificacionError("❌ La fecha de entrega debe ser posterior a la fecha de registro");
        document.getElementById("fechaEntrega")?.focus();
        return false;
    }

    if (!datos.producto || datos.producto <= 0) {
        mostrarNotificacionError("❌ Debe seleccionar un producto válido");
        document.getElementById("selectFichaPedido")?.focus();
        return false;
    }

    console.log("✅ Datos válidos para envío");
    return true;
}

/**
 * Limpiar formulario después del envío exitoso
 */
function limpiarFormularioCompleto(form) {
    console.log("🧹 Limpiando formulario...");
    
    // Limpiar todos los campos
    form.reset();
    
    // Restaurar fechas por defecto
    establecerFechasPorDefecto();
    
    // Limpiar errores visuales
    const inputs = form.querySelectorAll('input, select');
    inputs.forEach(input => limpiarErrorCampo(input));
    
    console.log("✅ Formulario limpiado correctamente");
}

/**
 * Cargar todos los pedidos desde el servidor
 */
async function cargarTodosPedidos() {
    console.log("🔄 Cargando todos los pedidos...");
    
    const tabla = document.getElementById("tablaPedidos");
    const tbody = tabla?.querySelector("tbody");
    
    if (!tbody) {
        console.error("❌ Tabla de pedidos no encontrada");
        return;
    }

    try {
        // Mostrar indicador de carga
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-8">
                    <div class="animate-pulse">
                        <div class="text-4xl mb-2">🔄</div>
                        <p class="text-gray-600">Cargando pedidos...</p>
                    </div>
                </td>
            </tr>
        `;

        const response = await fetch("api/pedidos/listar_pedidos.php");
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const pedidos = await response.json();
        console.log("📦 Pedidos obtenidos:", pedidos);
        
        pedidosData = Array.isArray(pedidos) ? pedidos : [];
        
        // Limpiar tabla
        tbody.innerHTML = "";
        
        if (pedidosData.length > 0) {
            // Renderizar cada pedido
            pedidosData.forEach(pedido => {
                const fila = crearFilaPedido(pedido);
                tbody.appendChild(fila);
            });
            
            console.log(`✅ ${pedidosData.length} pedidos cargados en la tabla`);
            
        } else {
            // Mostrar mensaje cuando no hay pedidos
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-12">
                        <div class="text-gray-500">
                            <div class="text-6xl mb-4">📋</div>
                            <h3 class="text-lg font-semibold mb-2">No hay pedidos registrados</h3>
                            <p class="text-sm">Los pedidos aparecerán aquí una vez que sean registrados</p>
                        </div>
                    </td>
                </tr>
            `;
            console.log("ℹ️ No hay pedidos para mostrar");
        }
        
    } catch (error) {
        console.error("❌ Error cargando pedidos:", error);
        
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-8 text-red-500">
                    <div class="text-4xl mb-2">❌</div>
                    <div class="font-medium">Error cargando pedidos</div>
                    <div class="text-sm mt-2">${error.message}</div>
                    <button onclick="cargarTodosPedidos()" 
                            class="mt-4 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded transition-colors">
                        🔄 Reintentar
                    </button>
                </td>
            </tr>
        `;
    }
}

/**
 * Crear fila de la tabla para un pedido
 */
function crearFilaPedido(pedido) {
    const tr = document.createElement("tr");
    tr.className = "hover:bg-gray-50 border-b transition-colors";
    
    const estadoClass = obtenerClaseEstado(pedido.estado);
    const fechaRegistro = formatearFecha(pedido.fecha_registro);
    const fechaEntrega = formatearFecha(pedido.fecha_entrega);
    
    tr.innerHTML = `
        <td class="p-4 font-semibold text-blue-600">
            PED-${String(pedido.idpedi).padStart(6, '0')}
        </td>
        <td class="p-4">
            <div class="flex items-center">
                <i class="fas fa-user text-gray-400 mr-2"></i>
                ${pedido.nombrecliente}
            </div>
        </td>
        <td class="p-4">
            <div class="flex items-center">
                <i class="fas fa-tshirt text-gray-400 mr-2"></i>
                ${pedido.pro_nombre}
            </div>
        </td>
        <td class="p-4 text-center font-semibold">
            <span class="bg-blue-50 text-blue-700 px-2 py-1 rounded">
                ${pedido.cantidad.toLocaleString()}
            </span>
        </td>
        <td class="p-4 text-sm text-gray-600">${fechaRegistro}</td>
        <td class="p-4 text-sm text-gray-600">${fechaEntrega}</td>
        <td class="p-4">
            <span class="px-3 py-1 rounded-full text-xs font-medium ${estadoClass}">
                ${pedido.estado || 'Confirmado'}
            </span>
        </td>
        <td class="p-4">
            <div class="flex gap-1 justify-center">
                <button onclick="editarPedidoCompleto(${pedido.idpedi})" 
                        class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs transition-all transform hover:scale-105"
                        title="Editar pedido">
                    <i class="fas fa-edit mr-1"></i>Editar
                </button>
                
                <button onclick="eliminarPedidoCompleto(${pedido.idpedi}, '${pedido.nombrecliente.replace(/'/g, "\\'")}', '${pedido.pro_nombre.replace(/'/g, "\\'")}')" 
                        class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs transition-all transform hover:scale-105"
                        title="Eliminar pedido">
                    <i class="fas fa-trash mr-1"></i>Eliminar
                </button>
            </div>
        </td>
    `;
    
    return tr;
}

/**
 * Editar pedido completo - CORREGIDO
 */
function editarPedidoCompleto(pedidoId) {
    console.log("✏️ Editando pedido:", pedidoId);
    
    const pedido = pedidosData.find(p => p.idpedi === pedidoId);
    if (!pedido) {
        mostrarNotificacionError("❌ No se encontró la información del pedido");
        return;
    }
    
    mostrarModalEdicion(pedido);
}

/**
 * Mostrar modal de edición - CORREGIDO
 */
function mostrarModalEdicion(pedido) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold mb-4 flex items-center">
                <i class="fas fa-edit text-blue-500 mr-2"></i>
                Editar Pedido
            </h3>
            
            <div class="mb-4 p-3 bg-blue-50 rounded">
                <p class="text-sm"><strong>ID:</strong> PED-${String(pedido.idpedi).padStart(6, '0')}</p>
                <p class="text-sm"><strong>Producto:</strong> ${pedido.pro_nombre}</p>
            </div>
            
            <form id="formEditarPedido" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Cliente:</label>
                    <input type="text" id="editarCliente" value="${pedido.nombrecliente}" 
                           class="w-full p-3 border rounded focus:border-blue-500 focus:outline-none">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Cantidad:</label>
                    <input type="number" id="editarCantidad" value="${pedido.cantidad}" min="1"
                           class="w-full p-3 border rounded focus:border-blue-500 focus:outline-none">

                <div>
                    <label class="block text-sm font-medium mb-2">Fecha de Inicio:</label>
                    <input type="date" id="editarFechaInicio" value="${pedido.fecha_registro ? pedido.fecha_registro : new Date().toISOString().split('T')[0]}"
                           class="w-full p-3 border rounded focus:border-blue-500 focus:outline-none">
                </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Fecha de Entrega:</label>
                    <input type="date" id="editarFechaEntrega" value="${pedido.fecha_entrega}"
                           class="w-full p-3 border rounded focus:border-blue-500 focus:outline-none">
                </div>
                
                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <button type="button" onclick="cerrarModalEdicion()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition-colors">
                        <i class="fas fa-times mr-1"></i>Cancelar
                    </button>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition-colors">
                        <i class="fas fa-save mr-1"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Configurar formulario de edición
    const form = document.getElementById('formEditarPedido');
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        guardarCambiosPedidoCompleto(pedido.idpedi, modal);
    });
    
    // Función global para cerrar
    window.cerrarModalEdicion = () => {
        document.body.removeChild(modal);
        delete window.cerrarModalEdicion;
    };
}

/**
 * Guardar cambios del pedido - FUNCIÓN CORREGIDA Y DEFINIDA
 */
async function guardarCambiosPedidoCompleto(pedidoId, modal) {
    console.log("💾 Guardando cambios del pedido:", pedidoId);

    try {
        const cliente = document.getElementById('editarCliente').value.trim();
        const cantidad = parseInt(document.getElementById('editarCantidad').value);
        const fechaEntrega = document.getElementById('editarFechaEntrega').value;
        const fechaInicio = document.getElementById('editarFechaInicio').value;

        const pedido = pedidosData.find(p => p.idpedi === pedidoId);
        const estado = pedido?.estado || 'Confirmado';
        const id_ficha = pedido?.id_ficha || 1;

        if (!cliente || cliente.length < 2) {
            mostrarNotificacionError("❌ El nombre del cliente debe tener al menos 2 caracteres");
            return;
        }

        if (!cantidad || cantidad <= 0) {
            mostrarNotificacionError("❌ La cantidad debe ser mayor a 0");
            return;
        }

        if (!fechaEntrega || !fechaInicio) {
            mostrarNotificacionError("❌ Las fechas de entrega e inicio son requeridas");
            return;
        }

        const response = await fetch("api/pedidos/actualizar_pedido.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                id: pedidoId,
                cliente: cliente,
                cantidad: cantidad,
                fecha_entrega: fechaEntrega,
                fecha_registro: fechaInicio,
                estado: estado,
                id_ficha: id_ficha
            })
        });

        const result = await response.json();

        if (result.success) {
            mostrarNotificacionExito("✅ Pedido actualizado correctamente");
            cerrarModalEdicion();
            cargarTodosPedidos();
        } else {
            mostrarNotificacionError("❌ Error: " + (result.error || "No se pudo actualizar el pedido"));
        }

    } catch (error) {
        console.error("❌ Error guardando cambios:", error);
        mostrarNotificacionError("Error guardando cambios: " + error.message);
    }
}

/**
 * Cambiar estado del pedido
 */
function cambiarEstadoPedidoCompleto(pedidoId, estadoActual, cliente) {
    console.log("🔄 Cambiando estado del pedido:", pedidoId);
    
    const estados = [
        'Confirmado',
        'En Proceso', 
        'En Producción',
        'Completado',
        'Entregado',
        'Cancelado'
    ];
    
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-md">
            <h3 class="text-xl font-semibold mb-4 flex items-center">
                <i class="fas fa-sync-alt text-green-500 mr-2"></i>
                Cambiar Estado
            </h3>
            
            <div class="mb-4 p-4 bg-gray-50 rounded">
                <p class="text-sm mb-1"><strong>Cliente:</strong> ${cliente}</p>
                <p class="text-sm mb-1"><strong>Pedido:</strong> PED-${String(pedidoId).padStart(6, '0')}</p>
                <p class="text-sm">
                    <strong>Estado actual:</strong> 
                    <span class="px-2 py-1 rounded text-xs ${obtenerClaseEstado(estadoActual)}">${estadoActual}</span>
                </p>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">Nuevo Estado:</label>
                <select id="nuevoEstadoPedido" class="w-full p-3 border rounded focus:border-green-500 focus:outline-none">
                    ${estados.map(estado => 
                        `<option value="${estado}" ${estado === estadoActual ? 'selected' : ''}>${estado}</option>`
                    ).join('')}
                </select>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button onclick="cerrarModalEstado()" 
                        class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition-colors">
                    <i class="fas fa-times mr-1"></i>Cancelar
                </button>
                <button onclick="confirmarCambioEstadoCompleto(${pedidoId})" 
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition-colors">
                    <i class="fas fa-check mr-1"></i>Cambiar Estado
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Funciones globales para el modal
    window.cerrarModalEstado = () => {
        document.body.removeChild(modal);
        delete window.cerrarModalEstado;
        delete window.confirmarCambioEstadoCompleto;
    };
    
    window.confirmarCambioEstadoCompleto = async (id) => {
        const nuevoEstado = document.getElementById('nuevoEstadoPedido').value;
        
        if (nuevoEstado === estadoActual) {
            mostrarNotificacionInfo("ℹ️ El estado seleccionado es el mismo que el actual");
            return;
        }
        
        try {
            const response = await fetch("api/pedidos/actualizar_estado_pedido.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    pedido: id,
                    estado: nuevoEstado
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                mostrarNotificacionExito(`✅ Estado actualizado correctamente`, {
                    cliente: cliente,
                    estado_anterior: estadoActual,
                    estado_nuevo: nuevoEstado
                });
                cerrarModalEstado();
                cargarTodosPedidos();
            } else {
                mostrarNotificacionError("❌ Error: " + (result.error || "No se pudo actualizar el estado"));
            }
        } catch (error) {
            console.error("❌ Error cambiando estado:", error);
            mostrarNotificacionError("❌ Error de conexión al cambiar estado");
        }
    };
}

/**
 * Eliminar pedido completo
 */
async function eliminarPedidoCompleto(pedidoId, cliente, producto) {
    console.log("🗑️ Eliminando pedido:", pedidoId);
    
    const confirmacion = confirm(
        `⚠️ ¿Está seguro de eliminar este pedido?\n\n` +
        `Cliente: ${cliente}\n` +
        `Producto: ${producto}\n` +
        `ID: PED-${String(pedidoId).padStart(6, '0')}\n\n` +
        `Esta acción no se puede deshacer.`
    );
    
    if (!confirmacion) {
        console.log("ℹ️ Eliminación cancelada por el usuario");
        return;
    }

    try {
        const response = await fetch("api/pedidos/delete_pedido.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: pedidoId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            mostrarNotificacionExito("✅ Pedido eliminado correctamente", {
                cliente: cliente,
                producto: producto,
                id: pedidoId
            });
            cargarTodosPedidos();
        } else {
            mostrarNotificacionError("❌ Error: " + (result.error || "No se pudo eliminar el pedido"));
        }
    } catch (error) {
        console.error("❌ Error eliminando pedido:", error);
        mostrarNotificacionError("❌ Error de conexión al eliminar pedido");
    }
}

// === FUNCIONES AUXILIARES ===

/**
 * Formatear fecha para visualización
 */
function formatearFecha(fecha) {
    if (!fecha) return 'N/D';
    
    try {
        const fechaObj = new Date(fecha + 'T00:00:00');
        return fechaObj.toLocaleDateString('es-MX', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    } catch {
        return fecha;
    }
}

/**
 * Obtener clase CSS para el estado
 */
function obtenerClaseEstado(estado) {
    switch (estado?.toLowerCase()) {
        case 'completado':
        case 'entregado':
            return 'bg-green-100 text-green-800 border border-green-300';
        case 'en proceso':
        case 'en producción':
            return 'bg-blue-100 text-blue-800 border border-blue-300';
        case 'confirmado':
            return 'bg-yellow-100 text-yellow-800 border border-yellow-300';
        case 'cancelado':
            return 'bg-red-100 text-red-800 border border-red-300';
        default:
            return 'bg-gray-100 text-gray-800 border border-gray-300';
    }
}

/**
 * Mostrar error en campo específico
 */
function mostrarErrorCampo(input, mensaje) {
    limpiarErrorCampo(input);
    
    input.classList.add('border-red-500', 'bg-red-50');
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'text-red-600 text-xs mt-1 error-mensaje';
    errorDiv.textContent = mensaje;
    
    input.parentNode.appendChild(errorDiv);
}

/**
 * Limpiar error de campo
 */
function limpiarErrorCampo(input) {
    input.classList.remove('border-red-500', 'bg-red-50');
    
    const errorExistente = input.parentNode.querySelector('.error-mensaje');
    if (errorExistente) {
        errorExistente.remove();
    }
}

/**
 * Mostrar notificación de éxito
 */
function mostrarNotificacionExito(mensaje, datos = null) {
    console.log("✅", mensaje, datos);
    mostrarNotificacion(mensaje, 'success');
}

/**
 * Mostrar notificación de error
 */
function mostrarNotificacionError(mensaje) {
    console.error("❌", mensaje);
    mostrarNotificacion(mensaje, 'error');
}

/**
 * Mostrar notificación informativa
 */
function mostrarNotificacionInfo(mensaje) {
    console.log("ℹ️", mensaje);
    mostrarNotificacion(mensaje, 'info');
}

/**
 * Mostrar mensaje informativo
 */
function mostrarMensajeInfo(mensaje) {
    const div = document.createElement('div');
    div.className = 'fixed bottom-24 right-4 bg-blue-500 text-white p-4 rounded-lg shadow-lg z-50 max-w-sm';
    div.innerHTML = `
        <div class="flex items-start">
            <i class="fas fa-info-circle mr-2 mt-1"></i>
            <div>${mensaje}</div>
        </div>
    `;
    
    document.body.appendChild(div);
    
    setTimeout(() => {
        if (div.parentElement) {
            div.remove();
        }
    }, 6000);
}

/**
 * Mostrar mensaje de error
 */
function mostrarMensajeError(mensaje) {
    const div = document.createElement('div');
    div.className = 'fixed bottom-24 right-4 bg-red-500 text-white p-4 rounded-lg shadow-lg z-50 max-w-sm';
    div.innerHTML = `
        <div class="flex items-start">
            <i class="fas fa-exclamation-circle mr-2 mt-1"></i>
            <div>${mensaje}</div>
        </div>
    `;
    
    document.body.appendChild(div);
    
    setTimeout(() => {
        if (div.parentElement) {
            div.remove();
        }
    }, 8000);
}

/**
 * Mostrar notificación genérica - CORREGIDA PARA COMPATIBILIDAD
 */
function mostrarNotificacion(mensaje, tipo = 'info') {
    // Primero intentar usar el sistema de notificaciones del test.html
    const notif = document.getElementById("notificacion");
    if (notif) {
        notif.textContent = mensaje;
        notif.className = `fixed bottom-20 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-lg shadow-lg text-sm z-50 ${
            tipo === 'success' ? 'bg-green-600 text-white' : 
            tipo === 'error' ? 'bg-red-600 text-white' : 
            'bg-blue-600 text-white'
        }`;
        notif.classList.remove("hidden");
        setTimeout(() => notif.classList.add("hidden"), 5000);
    } else {
        // Fallback si no existe el elemento de notificación
        const alert = document.createElement('div');
        alert.className = `fixed bottom-20 right-4 px-4 py-3 rounded-lg shadow-lg text-sm z-50 max-w-sm ${
            tipo === 'success' ? 'bg-green-600 text-white' : 
            tipo === 'error' ? 'bg-red-600 text-white' : 
            'bg-blue-600 text-white'
        }`;
        alert.textContent = mensaje;
        document.body.appendChild(alert);
        
        setTimeout(() => {
            if (alert.parentElement) {
                alert.remove();
            }
        }, 5000);
    }
}

// === FUNCIONES GLOBALES PARA COMPATIBILIDAD ===

// Exponer todas las funciones necesarias globalmente
window.inicializarSistemaPedidos = inicializarSistemaPedidos;
window.cargarProductosParaFormulario = cargarProductosParaFormulario;
window.configurarFormularioPedidosCompleto = configurarFormularioPedidosCompleto;
window.cargarTodosPedidos = cargarTodosPedidos;
window.editarPedidoCompleto = editarPedidoCompleto;
window.cambiarEstadoPedidoCompleto = cambiarEstadoPedidoCompleto;
window.eliminarPedidoCompleto = eliminarPedidoCompleto;
window.guardarCambiosPedidoCompleto = guardarCambiosPedidoCompleto; // ← FUNCIÓN QUE FALTABA

// Para compatibilidad con el sistema existente
window.cargarPedidos = cargarTodosPedidos;
window.cargarProductosParaSelect = cargarProductosParaFormulario;
window.configurarFormularioPedidos = configurarFormularioPedidosCompleto;
window.editarPedido = editarPedidoCompleto;
window.cambiarEstadoPedido = cambiarEstadoPedidoCompleto;
window.eliminarPedido = eliminarPedidoCompleto;

// === INICIALIZACIÓN AUTOMÁTICA ===
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 DOM cargado - Verificando si debe inicializar pedidos');
    
    // Solo inicializar si estamos en la sección de pedidos
    setTimeout(() => {
        const formPedido = document.getElementById('formPedido');
        const tablaPedidos = document.getElementById('tablaPedidos');
        
        if (formPedido || tablaPedidos) {
            console.log('📋 Elementos de pedidos encontrados - Inicializando sistema');
            inicializarSistemaPedidos();
        }
    }, 500);
});

console.log("✅ Sistema completo de pedidos cargado y listo");

// === FUNCIONES DE INTEGRACIÓN CON TEST.HTML ===

/**
 * Función específica para cargar productos en el select del test.html
 */
function cargarProductos() {
    return cargarProductosParaFormulario();
}

/**
 * Función específica para configurar el formulario del test.html
 */
function configurarFormularioPedidos() {
    return configurarFormularioPedidosCompleto();
}

// Exponer funciones adicionales
window.cargarProductos = cargarProductos;
window.configurarFormularioPedidos = configurarFormularioPedidos;