// === GESTIÓN DE INVENTARIO - VERSIÓN DEFINITIVA CORREGIDA ===

let alertasCriticasInterval = null;

// Cargar inventario desde la API con manejo robusto de errores
function cargarInventario() {
    console.log("🔄 Iniciando carga de inventario...");
    
    const tbody = document.getElementById("tablaInventarioBody");
    if (!tbody) {
        console.error("❌ Elemento tablaInventarioBody no encontrado");
        return;
    }

    // Mostrar indicador de carga
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4">🔄 Cargando inventario...</td></tr>';

    fetch("api/inventario/get_inventario.php")
        .then(res => {
            console.log("📡 Respuesta recibida:", res.status, res.statusText);
            
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            }
            
            return res.text(); // Primero obtener como texto para debugging
        })
        .then(text => {
            console.log("📄 Respuesta raw:", text.substring(0, 200) + "...");
            
            try {
                return JSON.parse(text);
            } catch (parseError) {
                console.error("❌ Error parsing JSON:", parseError);
                throw new Error("Respuesta del servidor no es JSON válido: " + text.substring(0, 100));
            }
        })
        .then(data => {
            console.log("📦 Datos parseados:", data);
            tbody.innerHTML = "";

            if (!data || typeof data !== 'object') {
                throw new Error("Respuesta del servidor inválida");
            }

            if (!data.success) {
                throw new Error(data.error || "Error desconocido del servidor");
            }

            if (!Array.isArray(data.data)) {
                console.warn("⚠️ data.data no es un array:", data.data);
                tbody.innerHTML = '<tr><td colspan="6" class="p-4 text-center text-gray-500">No hay datos de inventario disponibles</td></tr>';
                return;
            }

            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="p-4 text-center text-gray-500">No hay materiales en inventario</td></tr>';
                return;
            }

            // Procesar cada material con validación robusta
            data.data.forEach((item, index) => {
                try {
                    if (!item || typeof item !== 'object') {
                        console.warn(`⚠️ Item ${index} inválido:`, item);
                        return;
                    }

                    const tr = document.createElement("tr");
                    tr.className = "border-t hover:bg-gray-50";
                    
                    // Validar y limpiar datos del item
                    const id = parseInt(item.id) || 0;
                    const material = String(item.Material || 'Material sin nombre');
                    const cantidadDisp = parseInt(item.cantidad_disp) || 0;
                    const cantidadMin = parseInt(item.cantidad_min) || 0;
                    const estado = String(item.estado || 'Desconocido');
                    
                    // Obtener información del estado de forma segura
                    const estadoInfo = getEstadoInfoSeguro(cantidadDisp, cantidadMin, estado);
                    
                    tr.innerHTML = `
                        <td class="p-2 border">${id}</td>
                        <td class="p-2 border">
                            <div class="flex items-center gap-2">
                                ${estadoInfo.icon}
                                <span>${material}</span>
                            </div>
                        </td>
                        <td class="p-2 border text-center">
                            <span class="font-semibold ${estadoInfo.cantidadClass}">
                                ${cantidadDisp}
                            </span>
                        </td>
                        <td class="p-2 border text-center text-gray-600">${cantidadMin}</td>
                        <td class="p-2 border">
                            <span class="px-2 py-1 rounded text-xs font-medium ${estadoInfo.estadoClass}">
                                ${estadoInfo.estadoTexto}
                            </span>
                        </td>
                        <td class="p-2 border">
                            <div class="flex gap-1 flex-wrap">
                                <button onclick="mostrarReabastecimiento(${id}, '${material.replace(/'/g, "\\'")}', ${cantidadDisp}, ${cantidadMin})" 
                                        class="bg-green-600 text-white px-2 py-1 rounded text-xs hover:bg-green-700 mb-1">
                                    📦 Reabastecer
                                </button>
                                <button onclick="editarCantidadMinima(${id}, '${material.replace(/'/g, "\\'")}', ${cantidadMin})" 
                                        class="bg-blue-500 text-white px-2 py-1 rounded text-xs hover:bg-blue-600 mb-1">
                                    ⚙️ Min
                                </button>
                                <button onclick="eliminarMaterial(${id})" 
                                        class="bg-red-600 text-white px-2 py-1 rounded text-xs hover:bg-red-700 mb-1">
                                    🗑️
                                </button>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(tr);
                } catch (itemError) {
                    console.error(`❌ Error procesando item ${index}:`, itemError, item);
                }
            });

            console.log(`✅ ${data.data.length} materiales cargados correctamente`);

            // Verificar alertas críticas de forma segura
            try {
                verificarAlertasSeguro(data.data);
            } catch (alertError) {
                console.error("❌ Error verificando alertas:", alertError);
            }
        })
        .catch(err => {
            console.error("❌ Error cargando inventario:", err);
            
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="p-4 text-center text-red-500">
                            <div class="mb-2">❌ Error al cargar inventario</div>
                            <div class="text-sm">${err.message}</div>
                            <button onclick="cargarInventario()" 
                                    class="mt-2 bg-red-500 text-white px-3 py-1 rounded text-xs hover:bg-red-600">
                                🔄 Reintentar
                            </button>
                        </td>
                    </tr>
                `;
            }
            
            // Mostrar notificación de error
            mostrarNotificacionError("Error cargando inventario: " + err.message);
        });
}

// Función getEstadoInfo mejorada con validación robusta
function getEstadoInfoSeguro(cantidadActual, cantidadMinima, estadoDB) {
    try {
        const cantidad = Math.max(0, parseInt(cantidadActual) || 0);
        const minimo = Math.max(1, parseInt(cantidadMinima) || 1);
        const estado = String(estadoDB || '').toLowerCase().trim();
        
        // Casos específicos por estado de BD
        switch (estado) {
            case 'agotado':
                return {
                    icon: '🚨',
                    cantidadClass: 'text-red-700',
                    estadoClass: 'bg-red-100 text-red-800 border border-red-300',
                    estadoTexto: 'AGOTADO',
                    nivel: 'AGOTADO'
                };
            case 'crítico':
            case 'critico':
                return {
                    icon: '⚠️',
                    cantidadClass: 'text-orange-700',
                    estadoClass: 'bg-orange-100 text-orange-800 border border-orange-300',
                    estadoTexto: 'CRÍTICO',
                    nivel: 'CRÍTICO'
                };
            case 'bajo':
                return {
                    icon: '🟨',
                    cantidadClass: 'text-yellow-700',
                    estadoClass: 'bg-yellow-100 text-yellow-800 border border-yellow-300',
                    estadoTexto: 'BAJO',
                    nivel: 'BAJO'
                };
            case 'disponible':
                return {
                    icon: '✅',
                    cantidadClass: 'text-green-700',
                    estadoClass: 'bg-green-100 text-green-800 border border-green-300',
                    estadoTexto: 'DISPONIBLE',
                    nivel: 'DISPONIBLE'
                };
        }
        
        // Cálculo por cantidades si no hay estado específico en BD
        if (cantidad === 0) {
            return {
                icon: '🚨',
                cantidadClass: 'text-red-700',
                estadoClass: 'bg-red-100 text-red-800 border border-red-300',
                estadoTexto: 'AGOTADO',
                nivel: 'AGOTADO'
            };
        } else if (cantidad <= minimo) {
            return {
                icon: '⚠️',
                cantidadClass: 'text-orange-700',
                estadoClass: 'bg-orange-100 text-orange-800 border border-orange-300',
                estadoTexto: 'CRÍTICO',
                nivel: 'CRÍTICO'
            };
        } else if (cantidad <= (minimo * 1.5)) {
            return {
                icon: '🟨',
                cantidadClass: 'text-yellow-700',
                estadoClass: 'bg-yellow-100 text-yellow-800 border border-yellow-300',
                estadoTexto: 'BAJO',
                nivel: 'BAJO'
            };
        } else {
            return {
                icon: '✅',
                cantidadClass: 'text-green-700',
                estadoClass: 'bg-green-100 text-green-800 border border-green-300',
                estadoTexto: 'DISPONIBLE',
                nivel: 'DISPONIBLE'
            };
        }
    } catch (error) {
        console.error("❌ Error en getEstadoInfoSeguro:", error);
        // Estado por defecto en caso de error
        return {
            icon: '❓',
            cantidadClass: 'text-gray-700',
            estadoClass: 'bg-gray-100 text-gray-800',
            estadoTexto: 'DESCONOCIDO',
            nivel: 'DESCONOCIDO'
        };
    }
}

// Función para verificar alertas de forma segura
function verificarAlertasSeguro(inventario) {
    try {
        if (!Array.isArray(inventario) || inventario.length === 0) {
            console.log("ℹ️ No hay inventario para verificar alertas");
            return;
        }

        const materialesCriticos = inventario.filter(item => {
            try {
                if (!item || typeof item !== 'object') return false;
                
                const cantidad = parseInt(item.cantidad_disp) || 0;
                const minimo = parseInt(item.cantidad_min) || 1;
                const estado = String(item.estado || '').toLowerCase().trim();
                
                return cantidad === 0 || 
                       cantidad <= minimo || 
                       estado === 'crítico' || 
                       estado === 'critico' ||
                       estado === 'agotado';
            } catch (itemError) {
                console.error("❌ Error verificando item crítico:", itemError, item);
                return false;
            }
        });

        if (materialesCriticos.length > 0) {
            console.log(`⚠️ Encontrados ${materialesCriticos.length} materiales críticos`);
            mostrarAlertaCriticaSegura(materialesCriticos);
        } else {
            console.log("✅ No hay materiales críticos");
        }
    } catch (error) {
        console.error("❌ Error en verificarAlertasSeguro:", error);
    }
}

// Mostrar alerta crítica de forma segura
function mostrarAlertaCriticaSegura(materialesCriticos) {
    try {
        // Solo mostrar si no hay una alerta activa
        if (document.getElementById('alerta-critica')) {
            console.log("ℹ️ Ya hay una alerta crítica activa");
            return;
        }

        if (!Array.isArray(materialesCriticos) || materialesCriticos.length === 0) {
            console.log("ℹ️ No hay materiales críticos para mostrar alerta");
            return;
        }

        const alerta = document.createElement('div');
        alerta.id = 'alerta-critica';
        alerta.className = 'fixed top-4 right-4 bg-red-500 text-white p-4 rounded-lg shadow-lg z-50 max-w-sm';
        
        const agotados = materialesCriticos.filter(m => {
            try {
                return parseInt(m.cantidad_disp) === 0 || 
                       String(m.estado || '').toLowerCase() === 'agotado';
            } catch {
                return false;
            }
        });
        
        const criticos = materialesCriticos.filter(m => {
            try {
                const cantidad = parseInt(m.cantidad_disp) || 0;
                const minimo = parseInt(m.cantidad_min) || 1;
                const estado = String(m.estado || '').toLowerCase();
                return cantidad > 0 && 
                       (cantidad <= minimo || estado === 'crítico' || estado === 'critico') && 
                       !agotados.includes(m);
            } catch {
                return false;
            }
        });
        
        alerta.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="text-2xl">🚨</div>
                <div class="flex-1">
                    <h4 class="font-bold mb-1">¡Alerta de Inventario!</h4>
                    <p class="text-sm mb-2">
                        ${agotados.length > 0 ? `${agotados.length} materiales agotados` : ''}
                        ${agotados.length > 0 && criticos.length > 0 ? ' y ' : ''}
                        ${criticos.length > 0 ? `${criticos.length} materiales críticos` : ''}
                    </p>
                    <div class="flex gap-2">
                        <button onclick="mostrarPanelCriticosSeguro()" 
                                class="bg-white text-red-600 px-2 py-1 rounded text-xs font-medium hover:bg-gray-100">
                            Ver Detalles
                        </button>
                        <button onclick="cerrarAlerta()" 
                                class="bg-red-400 text-white px-2 py-1 rounded text-xs hover:bg-red-300">
                            ✕
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(alerta);
        
        // Auto-cerrar después de 15 segundos
        setTimeout(() => {
            const alertaExistente = document.getElementById('alerta-critica');
            if (alertaExistente) {
                cerrarAlerta();
            }
        }, 15000);
        
        console.log("✅ Alerta crítica mostrada correctamente");
    } catch (error) {
        console.error("❌ Error mostrando alerta crítica:", error);
    }
}

// Función mejorada para mostrar panel de críticos
async function mostrarPanelCriticosSeguro() {
    console.log("🔄 Cargando panel de materiales críticos de forma segura...");
    
    try {
        // Mostrar indicador de carga
        const modalCarga = crearModalCarga();
        document.body.appendChild(modalCarga);
        
        const response = await fetch('api/inventario/get_inventario.php');
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const text = await response.text();
        let data;
        
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            throw new Error("Respuesta del servidor no es JSON válido");
        }

        // Remover modal de carga
        if (modalCarga.parentElement) {
            modalCarga.remove();
        }

        if (!data || !data.success) {
            throw new Error(data?.error || "Error desconocido del servidor");
        }

        if (!Array.isArray(data.data)) {
            throw new Error("Datos de inventario inválidos");
        }

        // Filtrar materiales críticos de forma segura
        const materialesCriticos = data.data.filter(item => {
            try {
                if (!item || typeof item !== 'object') return false;
                
                const cantidad = parseInt(item.cantidad_disp) || 0;
                const minimo = parseInt(item.cantidad_min) || 1;
                const estado = String(item.estado || '').toLowerCase().trim();
                
                return cantidad === 0 || 
                       cantidad <= minimo || 
                       cantidad <= (minimo * 1.5) ||
                       estado === 'crítico' || 
                       estado === 'critico' ||
                       estado === 'agotado';
            } catch {
                return false;
            }
        });

        const agotados = materialesCriticos.filter(m => {
            try {
                return parseInt(m.cantidad_disp) === 0 || 
                       String(m.estado || '').toLowerCase() === 'agotado';
            } catch {
                return false;
            }
        });
        
        const criticos = materialesCriticos.filter(m => {
            try {
                const cantidad = parseInt(m.cantidad_disp) || 0;
                const minimo = parseInt(m.cantidad_min) || 1;
                const estado = String(m.estado || '').toLowerCase();
                return cantidad > 0 && 
                       (cantidad <= minimo || estado === 'crítico' || estado === 'critico') && 
                       !agotados.includes(m);
            } catch {
                return false;
            }
        });
        
        const bajos = materialesCriticos.filter(m => {
            try {
                const cantidad = parseInt(m.cantidad_disp) || 0;
                const minimo = parseInt(m.cantidad_min) || 1;
                return cantidad > 0 && 
                       cantidad > minimo && 
                       cantidad <= (minimo * 1.5) && 
                       !agotados.includes(m) && 
                       !criticos.includes(m);
            } catch {
                return false;
            }
        });

        console.log(`📊 Críticos encontrados - Agotados: ${agotados.length}, Críticos: ${criticos.length}, Bajos: ${bajos.length}`);

        const modal = crearModalSeguro('🚨 Materiales Críticos');
        const content = modal.querySelector('.modal-content .p-6');
        
        content.innerHTML = `
            <div class="mb-4">
                <h3 class="text-xl font-semibold mb-2">🚨 Materiales que Requieren Atención</h3>
                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div class="bg-red-50 border border-red-200 p-3 rounded text-center">
                        <div class="text-2xl font-bold text-red-600">${agotados.length}</div>
                        <div class="text-sm text-red-700">Agotados</div>
                    </div>
                    <div class="bg-orange-50 border border-orange-200 p-3 rounded text-center">
                        <div class="text-2xl font-bold text-orange-600">${criticos.length}</div>
                        <div class="text-sm text-orange-700">Críticos</div>
                    </div>
                    <div class="bg-yellow-50 border border-yellow-200 p-3 rounded text-center">
                        <div class="text-2xl font-bold text-yellow-600">${bajos.length}</div>
                        <div class="text-sm text-yellow-700">Bajos</div>
                    </div>
                </div>
            </div>
            
            <div class="max-h-96 overflow-y-auto">
                ${materialesCriticos.length > 0 ? `
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="p-2 text-left">Material</th>
                                <th class="p-2 text-center">Actual</th>
                                <th class="p-2 text-center">Mín</th>
                                <th class="p-2 text-center">Estado</th>
                                <th class="p-2 text-center">Sugerencia</th>
                                <th class="p-2 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${materialesCriticos.map(material => {
                                try {
                                    const cantidadDisp = parseInt(material.cantidad_disp) || 0;
                                    const cantidadMin = parseInt(material.cantidad_min) || 1;
                                    const nombreMaterial = String(material.Material || 'Sin nombre');
                                    const estadoInfo = getEstadoInfoSeguro(cantidadDisp, cantidadMin, material.estado);
                                    const sugerencia = calcularSugerenciaSegura(cantidadDisp, cantidadMin);
                                    
                                    return `
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="p-2">
                                                <div class="flex items-center gap-2">
                                                    ${estadoInfo.icon}
                                                    <span class="font-medium">${nombreMaterial}</span>
                                                </div>
                                            </td>
                                            <td class="p-2 text-center font-semibold ${estadoInfo.cantidadClass}">
                                                ${cantidadDisp}
                                            </td>
                                            <td class="p-2 text-center text-gray-600">${cantidadMin}</td>
                                            <td class="p-2 text-center">
                                                <span class="px-2 py-1 rounded text-xs ${estadoInfo.estadoClass}">
                                                    ${estadoInfo.estadoTexto}
                                                </span>
                                            </td>
                                            <td class="p-2 text-center font-semibold text-green-600">
                                                +${sugerencia}
                                            </td>
                                            <td class="p-2 text-center">
                                                <button onclick="cerrarModalSeguro(); mostrarReabastecimiento(${material.id || 0}, '${nombreMaterial.replace(/'/g, "\\'")}', ${cantidadDisp}, ${cantidadMin})" 
                                                        class="bg-green-600 text-white px-2 py-1 rounded text-xs hover:bg-green-700">
                                                    📦 Reabastecer
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                } catch (itemError) {
                                    console.error("❌ Error procesando material crítico:", itemError, material);
                                    return '';
                                }
                            }).join('')}
                        </tbody>
                    </table>
                ` : `
                    <div class="text-center py-8 text-green-600">
                        <div class="text-4xl mb-2">✅</div>
                        <p class="font-medium">¡Excelente!</p>
                        <p class="text-sm">Todos los materiales tienen niveles adecuados</p>
                    </div>
                `}
            </div>
            
            <div class="flex justify-end items-center mt-6">
                <button onclick="cerrarModalSeguro()" 
                        class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                    Cerrar
                </button>
            </div>
        `;

        document.body.appendChild(modal);
        cerrarAlerta(); // Cerrar la alerta flotante
        
        console.log("✅ Panel de críticos mostrado correctamente");

    } catch (error) {
        console.error('❌ Error cargando materiales críticos:', error);
        
        // Remover modal de carga si existe
        const modalCarga = document.getElementById('modal-carga');
        if (modalCarga) {
            modalCarga.remove();
        }
        
        mostrarNotificacionError('Error al cargar materiales críticos: ' + error.message);
    }
}

// === FUNCIONES AUXILIARES SEGURAS ===

function calcularSugerenciaSegura(cantidadActual, cantidadMinima) {
    try {
        const cantidad = Math.max(0, parseInt(cantidadActual) || 0);
        const minimo = Math.max(1, parseInt(cantidadMinima) || 1);
        
        if (cantidad === 0) {
            return minimo * 3;
        } else if (cantidad <= minimo) {
            return Math.max(minimo * 2 - cantidad, minimo);
        } else {
            return minimo;
        }
    } catch (error) {
        console.error("❌ Error calculando sugerencia:", error);
        return 10; // Valor por defecto
    }
}

function crearModalSeguro(titulo) {
    try {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
                <div class="p-6 overflow-y-auto max-h-[90vh]">
                    <h2 class="text-xl font-bold mb-4">${titulo || 'Modal'}</h2>
                    <!-- Contenido se insertará aquí -->
                </div>
            </div>
        `;
        return modal;
    } catch (error) {
        console.error("❌ Error creando modal:", error);
        return document.createElement('div');
    }
}

function crearModalCarga() {
    const modal = document.createElement('div');
    modal.id = 'modal-carga';
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-xl">
            <div class="flex items-center gap-3">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <span>Cargando materiales críticos...</span>
            </div>
        </div>
    `;
    return modal;
}

function cerrarModalSeguro() {
    try {
        const modales = document.querySelectorAll('.fixed.inset-0');
        modales.forEach(modal => {
            if (modal.id !== 'formInventarioModal') {
                modal.remove();
            }
        });
    } catch (error) {
        console.error("❌ Error cerrando modal:", error);
    }
}

function cerrarAlerta() {
    try {
        const alerta = document.getElementById('alerta-critica');
        if (alerta && alerta.parentElement) {
            alerta.remove();
        }
    } catch (error) {
        console.error("❌ Error cerrando alerta:", error);
    }
}

function mostrarNotificacionError(mensaje) {
    try {
        const notif = document.createElement('div');
        notif.className = 'fixed bottom-4 right-4 bg-red-500 text-white p-4 rounded-lg shadow-lg z-50 max-w-sm';
        notif.innerHTML = `
            <div class="flex justify-between items-start">
                <span>❌ ${mensaje}</span>
                <button onclick="this.parentElement.parentElement.remove()" 
                        class="ml-2 text-white hover:text-gray-200 focus:outline-none">
                    ✕
                </button>
            </div>
        `;
        document.body.appendChild(notif);

        setTimeout(() => {
            if (notif.parentElement) {
                notif.remove();
            }
        }, 8000);
    } catch (error) {
        console.error("❌ Error mostrando notificación:", error);
    }
}

// === FUNCIONES GLOBALES PARA COMPATIBILIDAD ===

// Hacer funciones disponibles globalmente
window.cargarInventario = cargarInventario;
window.mostrarPanelCriticos = mostrarPanelCriticosSeguro;
window.mostrarPanelCriticosSeguro = mostrarPanelCriticosSeguro;
window.cerrarAlerta = cerrarAlerta;
window.cerrarModal = cerrarModalSeguro;

// === INICIALIZACIÓN ===
document.addEventListener("DOMContentLoaded", () => {
    console.log("🚀 Inicializando sistema de inventario seguro...");
    
    try {
        // Cargar inventario inicial
        setTimeout(() => {
            cargarInventario();
        }, 500);

        // Verificar alertas cada 5 minutos
        if (!alertasCriticasInterval) {
            alertasCriticasInterval = setInterval(() => {
                console.log("🔄 Verificación automática de inventario...");
                cargarInventario();
            }, 5 * 60 * 1000);
        }
    } catch (initError) {
        console.error("❌ Error en inicialización:", initError);
    }
});

// === FUNCIONES DE INVENTARIO RESTANTES (COMPATIBILIDAD) ===

// Mostrar formulario de inventario
function mostrarFormularioInventario(modo, id = null) {
    try {
        const modal = document.getElementById("formInventarioModal");
        const titulo = document.getElementById("tituloFormInventario");
        const modoInput = document.getElementById("modoInventario");
        const idInput = document.getElementById("idMaterial");

        if (!modal || !titulo || !modoInput || !idInput) {
            console.error("❌ Elementos del modal no encontrados");
            mostrarNotificacionError("Error: Modal de inventario no encontrado");
            return;
        }

        modal.classList.remove("hidden");
        modal.classList.add("flex");

        titulo.textContent = modo === "agregar" ? "Agregar Material" : "Editar Material";
        modoInput.value = modo;
        idInput.value = id || "";

        limpiarFormularioInventario();

        if (modo === "editar" && id) {
            console.log("📝 Modo edición para ID:", id);
        }
    } catch (error) {
        console.error("❌ Error mostrando formulario:", error);
        mostrarNotificacionError("Error mostrando formulario de inventario");
    }
}

function limpiarFormularioInventario() {
    try {
        const campos = ["nombreMaterial", "cantidadDisponible", "cantidadMinima", "estadoMaterial"];
        campos.forEach(campo => {
            const elemento = document.getElementById(campo);
            if (elemento) {
                elemento.value = "";
            }
        });
    } catch (error) {
        console.error("❌ Error limpiando formulario:", error);
    }
}

function cerrarModalInventario() {
    try {
        const modal = document.getElementById("formInventarioModal");
        if (modal) {
            modal.classList.add("hidden");
            modal.classList.remove("flex");
        }
    } catch (error) {
        console.error("❌ Error cerrando modal:", error);
    }
}

function guardarMaterial() {
    console.log("💾 Iniciando proceso de guardado de material...");
    
    try {
        const modo = document.getElementById("modoInventario")?.value;
        const id = document.getElementById("idMaterial")?.value;
        const nombre = document.getElementById("nombreMaterial")?.value?.trim();
        const cantidad = document.getElementById("cantidadDisponible")?.value?.trim();
        const minima = document.getElementById("cantidadMinima")?.value?.trim();
        const estado = document.getElementById("estadoMaterial")?.value?.trim();

        console.log("📦 Datos del formulario:", { modo, id, nombre, cantidad, minima, estado });

        // Validaciones mejoradas
        if (!nombre || nombre.length < 2) {
            alert("El nombre del material debe tener al menos 2 caracteres.");
            return;
        }

        if (!cantidad || isNaN(cantidad) || parseInt(cantidad) < 0) {
            alert("La cantidad disponible debe ser un número válido mayor o igual a 0.");
            return;
        }

        if (!minima || isNaN(minima) || parseInt(minima) < 0) {
            alert("La cantidad mínima debe ser un número válido mayor o igual a 0.");
            return;
        }

        if (!estado) {
            alert("Por favor seleccione un estado para el material.");
            return;
        }

        // Preparar payload
        const payload = {
            accion: modo || "agregar",
            Material: nombre,
            cantidad_disp: parseInt(cantidad),
            cantidad_min: parseInt(minima),
            estado: estado
        };

        if (modo === "editar" && id) {
            payload.id = parseInt(id);
        }

        console.log("📤 Enviando payload:", payload);

        // Deshabilitar botón para evitar dobles envíos
        const btnGuardar = document.getElementById("btnGuardarInventario");
        const textoOriginal = btnGuardar ? btnGuardar.textContent : "Guardar";
        
        if (btnGuardar) {
            btnGuardar.textContent = "Guardando...";
            btnGuardar.disabled = true;
        }

        // Realizar petición
        fetch("api/inventario/update_material.php", {
            method: "POST",
            headers: { 
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify(payload)
        })
        .then(response => {
            console.log("📡 Respuesta del servidor:", response.status, response.statusText);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return response.text(); // Primero como texto para debugging
        })
        .then(text => {
            console.log("📄 Respuesta raw:", text);
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseError) {
                console.error("❌ Error parsing JSON:", parseError);
                throw new Error("Respuesta del servidor no es JSON válido: " + text.substring(0, 100));
            }
            
            console.log("📦 Datos parseados:", data);
            
            if (data && data.success) {
                console.log("✅ Material guardado exitosamente");
                
                // Mostrar mensaje de éxito
                const mensaje = data.message || "Material guardado correctamente";
                alert("✅ " + mensaje);
                
                // Cerrar modal
                cerrarModalInventario();
                
                // Recargar inventario después de un pequeño delay
                setTimeout(() => {
                    console.log("🔄 Recargando inventario...");
                    cargarInventario();
                }, 500);
                
            } else {
                console.error("❌ Error del servidor:", data?.error);
                alert("❌ Error: " + (data?.error || "Error desconocido del servidor"));
            }
        })
        .catch(err => {
            console.error("❌ Error en la petición:", err);
            alert("❌ Error de conexión: " + err.message);
        })
        .finally(() => {
            // Restaurar botón
            if (btnGuardar) {
                btnGuardar.textContent = textoOriginal;
                btnGuardar.disabled = false;
            }
        });

    } catch (error) {
        console.error("❌ Error en guardarMaterial:", error);
        alert("❌ Error procesando el formulario: " + error.message);
    }
}

// AGREGAR también estas funciones auxiliares mejoradas:

function cerrarModalInventario() {
    console.log("🚪 Cerrando modal de inventario...");
    
    try {
        const modal = document.getElementById("formInventarioModal");
        if (modal) {
            modal.classList.add("hidden");
            modal.classList.remove("flex");
            
            // Limpiar formulario
            limpiarFormularioInventario();
        }
    } catch (error) {
        console.error("❌ Error cerrando modal:", error);
    }
}

function limpiarFormularioInventario() {
    console.log("🧹 Limpiando formulario de inventario...");
    
    try {
        const campos = ["nombreMaterial", "cantidadDisponible", "cantidadMinima", "estadoMaterial"];
        campos.forEach(campo => {
            const elemento = document.getElementById(campo);
            if (elemento) {
                elemento.value = "";
            }
        });
        
        // Limpiar campos ocultos
        const modoElement = document.getElementById("modoInventario");
        const idElement = document.getElementById("idMaterial");
        
        if (modoElement) modoElement.value = "";
        if (idElement) idElement.value = "";
        
    } catch (error) {
        console.error("❌ Error limpiando formulario:", error);
    }
}

// MEJORAR también la función mostrarFormularioInventario
function mostrarFormularioInventario(modo, id = null) {
    console.log("📝 Mostrando formulario de inventario:", modo, id);
    
    try {
        const modal = document.getElementById("formInventarioModal");
        const titulo = document.getElementById("tituloFormInventario");
        const modoInput = document.getElementById("modoInventario");
        const idInput = document.getElementById("idMaterial");

        if (!modal || !titulo || !modoInput || !idInput) {
            console.error("❌ Elementos del modal no encontrados");
            mostrarNotificacionError("Error: Modal de inventario no encontrado");
            return;
        }

        // Mostrar modal
        modal.classList.remove("hidden");
        modal.classList.add("flex");

        // Configurar título y modo
        titulo.textContent = modo === "agregar" ? "Agregar Material" : "Editar Material";
        modoInput.value = modo;
        idInput.value = id || "";

        // Limpiar formulario
        limpiarFormularioInventario();
        
        // Si es modo editar, cargar datos
        if (modo === "editar" && id) {
            console.log("📝 Modo edición para ID:", id);
            cargarDatosMaterialParaEdicion(id);
        }
        
        // Configurar event listeners si no están configurados
        configurarEventListenersModal();
        
    } catch (error) {
        console.error("❌ Error mostrando formulario:", error);
        mostrarNotificacionError("Error mostrando formulario de inventario");
    }
}

function configurarEventListenersModal() {
    // Configurar botón guardar si no está configurado
    const btnGuardar = document.getElementById("btnGuardarInventario");
    if (btnGuardar && !btnGuardar.dataset.configured) {
        btnGuardar.addEventListener("click", guardarMaterial);
        btnGuardar.dataset.configured = "true";
    }
    
    // Configurar botón cancelar si no está configurado
    const btnCancelar = document.getElementById("btnCancelarInventario");
    if (btnCancelar && !btnCancelar.dataset.configured) {
        btnCancelar.addEventListener("click", cerrarModalInventario);
        btnCancelar.dataset.configured = "true";
    }
}

// Función para cargar datos en modo edición
async function cargarDatosMaterialParaEdicion(id) {
    try {
        console.log("📖 Cargando datos para edición del material ID:", id);
        
        const response = await fetch("api/inventario/get_inventario.php");
        const data = await response.json();
        
        if (data.success && Array.isArray(data.data)) {
            const material = data.data.find(item => item.id == id);
            
            if (material) {
                document.getElementById("nombreMaterial").value = material.Material || "";
                document.getElementById("cantidadDisponible").value = material.cantidad_disp || 0;
                document.getElementById("cantidadMinima").value = material.cantidad_min || 0;
                document.getElementById("estadoMaterial").value = material.estado || "Disponible";
                
                console.log("✅ Datos cargados para edición:", material);
            } else {
                console.warn("⚠️ Material no encontrado para edición");
                alert("Material no encontrado para edición");
                cerrarModalInventario();
            }
        }
    } catch (error) {
        console.error("❌ Error cargando datos para edición:", error);
        alert("Error cargando datos del material");
        cerrarModalInventario();
    }
}

function eliminarMaterial(id) {
    try {
        if (!confirm("¿Está seguro de eliminar este material?")) {
            return;
        }

        fetch("api/inventario/update_material.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                accion: "eliminar",
                id: parseInt(id)
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    alert(data.message || "Material eliminado correctamente");
                    cargarInventario();
                } else {
                    alert("Error: " + (data?.error || "Error desconocido"));
                }
            })
            .catch(err => {
                console.error("❌ Error eliminando material:", err);
                alert("Error de conexión con el servidor");
            });
    } catch (error) {
        console.error("❌ Error en eliminarMaterial:", error);
        alert("Error eliminando material");
    }
}

// Mostrar modal de reabastecimiento
function mostrarReabastecimiento(id, nombreMaterial, cantidadActual, cantidadMinima) {
    try {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        
        const sugerencia = calcularSugerenciaSegura(cantidadActual, cantidadMinima);
        
        modal.innerHTML = `
            <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="text-center mb-4">
                    <h3 class="text-xl font-semibold mb-2">📦 Reabastecer Material</h3>
                    <div class="bg-blue-50 p-3 rounded">
                        <p class="font-medium">${nombreMaterial}</p>
                        <p class="text-sm text-gray-600">Cantidad actual: <span class="font-semibold">${cantidadActual}</span></p>
                        <p class="text-sm text-gray-600">Cantidad mínima: <span class="font-semibold">${cantidadMinima}</span></p>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Cantidad a Agregar:</label>
                        <input type="number" id="cantidadAgregar" 
                               value="${sugerencia}" 
                               min="1" max="50000"
                               class="w-full p-3 border rounded focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <p class="text-xs text-gray-500 mt-1">Sugerencia: ${sugerencia} unidades</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Comentario (Opcional):</label>
                        <textarea id="comentarioReabastecimiento" 
                                  placeholder="Ej: Proveedor XYZ, Lote #123, etc."
                                  class="w-full p-3 border rounded h-20 resize-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                    </div>
                    
                    <div class="bg-green-50 p-3 rounded">
                        <p class="text-sm">
                            <strong>Resultado:</strong> 
                            <span id="cantidadFinal">${parseInt(cantidadActual) + sugerencia}</span> unidades totales
                        </p>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button onclick="cerrarModalReabastecimiento()" 
                            class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition-colors">
                        Cancelar
                    </button>
                    <button onclick="confirmarReabastecimiento(${id}, '${nombreMaterial.replace(/'/g, "\\'")}', ${cantidadActual})" 
                            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition-colors">
                        📦 Reabastecer
                    </button>
                </div>
            </div>
        `;

        // Agregar funcionalidad para actualizar cantidad final en tiempo real
        modal.addEventListener('input', function(e) {
            if (e.target.id === 'cantidadAgregar') {
                const cantidad = parseInt(e.target.value) || 0;
                const spanFinal = modal.querySelector('#cantidadFinal');
                if (spanFinal) {
                    spanFinal.textContent = parseInt(cantidadActual) + cantidad;
                }
            }
        });

        document.body.appendChild(modal);
        
        setTimeout(() => {
            const input = modal.querySelector('#cantidadAgregar');
            if (input) {
                input.focus();
                input.select();
            }
        }, 100);
    } catch (error) {
        console.error("❌ Error mostrando reabastecimiento:", error);
        mostrarNotificacionError("Error mostrando formulario de reabastecimiento");
    }
}

function cerrarModalReabastecimiento() {
    try {
        const modales = document.querySelectorAll('.fixed.inset-0');
        modales.forEach(modal => {
            if (modal.querySelector('h3')?.textContent?.includes('Reabastecer Material')) {
                modal.remove();
            }
        });
    } catch (error) {
        console.error("❌ Error cerrando modal reabastecimiento:", error);
    }
}

async function confirmarReabastecimiento(id, nombreMaterial, cantidadActual) {
    try {
        const cantidadInput = document.getElementById('cantidadAgregar');
        const comentarioInput = document.getElementById('comentarioReabastecimiento');
        
        if (!cantidadInput || !comentarioInput) {
            alert('Error: Elementos del formulario no encontrados');
            return;
        }

        const cantidad = parseInt(cantidadInput.value);
        const comentario = comentarioInput.value.trim() || 'Reabastecimiento via sistema';

        if (!cantidad || cantidad <= 0) {
            alert('Por favor ingrese una cantidad válida');
            return;
        }

        if (cantidad > 50000) {
            alert('La cantidad máxima por reabastecimiento es 50,000');
            return;
        }

        const btnConfirmar = document.querySelector('button[onclick*="confirmarReabastecimiento"]');
        if (btnConfirmar) {
            const textoOriginal = btnConfirmar.textContent;
            btnConfirmar.textContent = 'Reabasteciendo...';
            btnConfirmar.disabled = true;

            try {
                const formData = new FormData();
                formData.append('id_material', id);
                formData.append('cantidad', cantidad);
                formData.append('comentario', comentario);
                formData.append('usuario', 'Administrador');

                const response = await fetch('api/inventario/reabastecer_material.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data && data.success) {
                    alert(`✅ Reabastecimiento exitoso: ${nombreMaterial}\nCantidad agregada: +${cantidad}`);
                    cerrarModalReabastecimiento();
                    setTimeout(() => {
                        cargarInventario();
                    }, 500);
                } else {
                    alert('Error: ' + (data?.error || 'Error desconocido'));
                }

            } finally {
                btnConfirmar.textContent = textoOriginal;
                btnConfirmar.disabled = false;
            }
        }

    } catch (error) {
        console.error('❌ Error en reabastecimiento:', error);
        alert('Error de conexión con el servidor: ' + error.message);
    }
}

function editarCantidadMinima(id, nombreMaterial, cantidadMinActual) {
    try {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-md">
                <div class="text-center mb-4">
                    <h3 class="text-xl font-semibold mb-2">⚙️ Cantidad Mínima</h3>
                    <div class="bg-blue-50 p-3 rounded">
                        <p class="font-medium">${nombreMaterial}</p>
                        <p class="text-sm text-gray-600">Cantidad mínima actual: <span class="font-semibold">${cantidadMinActual}</span></p>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Nueva Cantidad Mínima:</label>
                        <input type="number" id="nuevaCantidadMin" 
                               value="${cantidadMinActual}" 
                               min="0" max="10000"
                               class="w-full p-3 border rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Esta cantidad determinará las alertas de reabastecimiento</p>
                    </div>
                    
                    <div class="bg-yellow-50 border border-yellow-200 p-3 rounded">
                        <p class="text-sm text-yellow-800">
                            <strong>💡 Consejo:</strong> Establezca la cantidad mínima considerando el tiempo de reabastecimiento 
                            y el consumo promedio del material.
                        </p>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button onclick="cerrarModalCantidadMinima()" 
                            class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition-colors">
                        Cancelar
                    </button>
                    <button onclick="confirmarCantidadMinima(${id}, '${nombreMaterial.replace(/'/g, "\\'")}', ${cantidadMinActual})" 
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                        ⚙️ Actualizar
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        
        setTimeout(() => {
            const input = modal.querySelector('#nuevaCantidadMin');
            if (input) {
                input.focus();
                input.select();
            }
        }, 100);
    } catch (error) {
        console.error("❌ Error mostrando editor cantidad mínima:", error);
        mostrarNotificacionError("Error mostrando editor de cantidad mínima");
    }
}

function cerrarModalCantidadMinima() {
    try {
        const modales = document.querySelectorAll('.fixed.inset-0');
        modales.forEach(modal => {
            if (modal.querySelector('h3')?.textContent?.includes('Cantidad Mínima')) {
                modal.remove();
            }
        });
    } catch (error) {
        console.error("❌ Error cerrando modal cantidad mínima:", error);
    }
}

async function confirmarCantidadMinima(id, nombreMaterial, cantidadMinAnterior) {
    try {
        const input = document.getElementById('nuevaCantidadMin');
        if (!input) {
            alert('Error: Campo de cantidad no encontrado');
            return;
        }

        const nuevaCantidad = parseInt(input.value);

        if (isNaN(nuevaCantidad) || nuevaCantidad < 0) {
            alert('Por favor ingrese una cantidad válida');
            return;
        }

        const btnConfirmar = document.querySelector('button[onclick*="confirmarCantidadMinima"]');
        if (btnConfirmar) {
            const textoOriginal = btnConfirmar.textContent;
            btnConfirmar.textContent = 'Actualizando...';
            btnConfirmar.disabled = true;

            try {
                // Primero obtener datos actuales del material
                const responseGet = await fetch("api/inventario/get_inventario.php");
                const dataGet = await responseGet.json();
                
                if (!dataGet || !dataGet.success) {
                    throw new Error("Error al obtener datos del inventario");
                }

                const material = dataGet.data.find(item => item.id === parseInt(id));
                if (!material) {
                    throw new Error("Material no encontrado");
                }

                // Enviar actualización
                const response = await fetch("api/inventario/update_material.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        accion: "editar",
                        id: parseInt(id),
                        Material: material.Material,
                        cantidad_disp: material.cantidad_disp,
                        cantidad_min: nuevaCantidad,
                        estado: material.estado,
                        ord_idord: material.ord_idord
                    })
                });

                const data = await response.json();

                if (data && data.success) {
                    alert(`✅ Cantidad mínima actualizada para ${nombreMaterial}\nAnterior: ${cantidadMinAnterior} → Nueva: ${nuevaCantidad}`);
                    cerrarModalCantidadMinima();
                    setTimeout(() => {
                        cargarInventario();
                    }, 500);
                } else {
                    alert('Error: ' + (data?.error || 'Error desconocido'));
                }

            } finally {
                btnConfirmar.textContent = textoOriginal;
                btnConfirmar.disabled = false;
            }
        }

    } catch (error) {
        console.error('❌ Error actualizando cantidad mínima:', error);
        alert('Error de conexión con el servidor: ' + error.message);
    }
}

// Generar reportes PDF (funciones simplificadas)
function generarReportePDF() {
    try {
        if (!window.jspdf) {
            alert('Error: Librería PDF no disponible');
            return;
        }
        
        fetch("api/inventario/get_inventario.php")
            .then(res => res.json())
            .then(data => {
                if (!data || !data.success) {
                    throw new Error('Error obteniendo datos');
                }
                
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                
                doc.text("REPORTE DE INVENTARIO", 20, 20);
                doc.text("Fecha: " + new Date().toLocaleDateString(), 20, 30);
                
                const tableData = data.data.map(item => [
                    String(item.id),
                    String(item.Material),
                    String(item.cantidad_disp),
                    String(item.cantidad_min),
                    String(item.estado)
                ]);

                if (doc.autoTable) {
                    doc.autoTable({
                        head: [['ID', 'Material', 'Cantidad', 'Mínimo', 'Estado']],
                        body: tableData,
                        startY: 40
                    });
                }
                
                doc.save(`inventario_${new Date().toISOString().split('T')[0]}.pdf`);
            })
            .catch(err => {
                console.error('❌ Error generando PDF:', err);
                alert('Error generando reporte PDF');
            });
    } catch (error) {
        console.error('❌ Error en generarReportePDF:', error);
        alert('Error generando reporte PDF');
    }
}

// === CORRECCIÓN ESPECÍFICA PARA REPORTE PDF DE CRÍTICOS ===
// Reemplaza SOLO la función generarReporteCriticos en tu archivo inventario.js

// === CORRECCIÓN ESPECÍFICA PARA REPORTE PDF DE CRÍTICOS ===
// Reemplaza SOLO la función generarReporteCriticos en tu archivo inventario.js

function generarReporteCriticos() {
    console.log("📄 Generando reporte PDF de materiales críticos...");
    
    fetch('api/inventario/get_inventario.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success || !window.jspdf) {
                alert('Error: No se pueden generar reportes PDF');
                return;
            }

            try {
                // Filtrar materiales críticos
                const materialesCriticos = data.data.filter(item => {
                    const cantidad = parseInt(item.cantidad_disp) || 0;
                    const minimo = parseInt(item.cantidad_min) || 1;
                    const estado = (item.estado || '').toLowerCase();
                    
                    return cantidad === 0 || 
                           cantidad <= minimo || 
                           cantidad <= (minimo * 1.5) ||
                           estado === 'crítico' || 
                           estado === 'critico' ||
                           estado === 'agotado';
                });

                if (materialesCriticos.length === 0) {
                    alert('✅ ¡Excelente! No hay materiales críticos para reportar');
                    return;
                }

                // Clasificar materiales
                const agotados = materialesCriticos.filter(m => 
                    parseInt(m.cantidad_disp) === 0 || 
                    (m.estado && m.estado.toLowerCase() === 'agotado')
                );
                
                const criticos = materialesCriticos.filter(m => {
                    const cantidad = parseInt(m.cantidad_disp) || 0;
                    const minimo = parseInt(m.cantidad_min) || 1;
                    const estado = (m.estado || '').toLowerCase();
                    return cantidad > 0 && 
                           (cantidad <= minimo || estado === 'crítico' || estado === 'critico') && 
                           !agotados.includes(m);
                });
                
                const bajos = materialesCriticos.filter(m => {
                    const cantidad = parseInt(m.cantidad_disp) || 0;
                    const minimo = parseInt(m.cantidad_min) || 1;
                    return cantidad > minimo && 
                           cantidad <= (minimo * 1.5) && 
                           !agotados.includes(m) && 
                           !criticos.includes(m);
                });

                // Crear PDF
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                
                // Configurar fuente
                doc.setFont("helvetica");
                
                // Título
                doc.setFontSize(18);
                doc.setTextColor(220, 53, 69); // Rojo
                doc.text('🚨 REPORTE DE MATERIALES CRÍTICOS', 20, 20);
                
                // Fecha y hora
                doc.setFontSize(10);
                doc.setTextColor(0, 0, 0); // Negro
                doc.text(`Fecha: ${new Date().toLocaleDateString('es-MX')}`, 20, 35);
                doc.text(`Hora: ${new Date().toLocaleTimeString('es-MX')}`, 120, 35);
                
                // Resumen ejecutivo
                doc.setFontSize(14);
                doc.setTextColor(220, 53, 69);
                doc.text('RESUMEN EJECUTIVO:', 20, 50);
                
                doc.setFontSize(11);
                doc.setTextColor(0, 0, 0);
                doc.text(`• Total de materiales con problemas: ${materialesCriticos.length}`, 25, 60);
                doc.text(`• Materiales AGOTADOS (prioridad máxima): ${agotados.length}`, 25, 68);
                doc.text(`• Materiales CRÍTICOS (reabastecer urgente): ${criticos.length}`, 25, 76);
                doc.text(`• Materiales BAJOS (monitorear): ${bajos.length}`, 25, 84);
                
                // Preparar datos para la tabla
                const tableData = materialesCriticos.map(material => {
                    const cantidad = parseInt(material.cantidad_disp) || 0;
                    const minimo = parseInt(material.cantidad_min) || 1;
                    
                    // Calcular sugerencia
                    let sugerencia = 0;
                    if (cantidad === 0) {
                        sugerencia = minimo * 3;
                    } else if (cantidad <= minimo) {
                        sugerencia = Math.max(minimo * 2 - cantidad, minimo);
                    } else {
                        sugerencia = minimo;
                    }
                    
                    // Determinar estado y urgencia
                    let estadoTexto = 'Normal';
                    let urgencia = 'Baja';
                    
                    if (cantidad === 0 || (material.estado && material.estado.toLowerCase() === 'agotado')) {
                        estadoTexto = 'AGOTADO';
                        urgencia = 'CRÍTICA';
                    } else if (cantidad <= minimo || (material.estado && material.estado.toLowerCase().includes('crítico'))) {
                        estadoTexto = 'CRÍTICO';
                        urgencia = 'ALTA';
                    } else if (cantidad <= (minimo * 1.5)) {
                        estadoTexto = 'BAJO';
                        urgencia = 'MEDIA';
                    }
                    
                    return [
                        material.Material || 'Sin nombre',
                        cantidad.toString(),
                        minimo.toString(),
                        estadoTexto,
                        urgencia,
                        sugerencia.toString()
                    ];
                });

                // Crear tabla con autoTable
                if (doc.autoTable) {
                    doc.autoTable({
                        head: [['Material', 'Stock', 'Mínimo', 'Estado', 'Urgencia', 'Sugerencia']],
                        body: tableData,
                        startY: 95,
                        theme: 'striped',
                        headStyles: { 
                            fillColor: [220, 53, 69], // Rojo
                            textColor: 255,
                            fontSize: 10,
                            fontStyle: 'bold'
                        },
                        bodyStyles: { 
                            fontSize: 9,
                            cellPadding: 3
                        },
                        columnStyles: {
                            0: { cellWidth: 50 }, // Material
                            1: { cellWidth: 20, halign: 'center' }, // Stock
                            2: { cellWidth: 20, halign: 'center' }, // Mínimo
                            3: { cellWidth: 25, halign: 'center' }, // Estado
                            4: { cellWidth: 25, halign: 'center' }, // Urgencia
                            5: { cellWidth: 25, halign: 'center' }  // Sugerencia
                        },
                        // Colorear filas según urgencia
                        didParseCell: function(data) {
                            if (data.section === 'body') {
                                const urgencia = data.row.raw[4]; // Columna urgencia
                                if (urgencia === 'CRÍTICA') {
                                    data.cell.styles.fillColor = [255, 235, 235]; // Rojo claro
                                } else if (urgencia === 'ALTA') {
                                    data.cell.styles.fillColor = [255, 245, 235]; // Naranja claro
                                } else if (urgencia === 'MEDIA') {
                                    data.cell.styles.fillColor = [255, 255, 235]; // Amarillo claro
                                }
                            }
                        }
                    });
                }
                
                // Recomendaciones al final
                const finalY = doc.lastAutoTable ? doc.lastAutoTable.finalY + 15 : 200;
                
                doc.setFontSize(12);
                doc.setTextColor(220, 53, 69);
                doc.text('RECOMENDACIONES:', 20, finalY);
                
                doc.setFontSize(10);
                doc.setTextColor(0, 0, 0);
                
                let recomendacionesY = finalY + 10;
                const recomendaciones = [
                    '1. Procesar INMEDIATAMENTE pedidos para materiales AGOTADOS',
                    '2. Reabastecer materiales CRÍTICOS en las próximas 24-48 horas',
                    '3. Monitorear materiales BAJOS y programar reabastecimiento',
                    '4. Revisar proveedores alternativos para materiales recurrentemente críticos',
                    '5. Considerar ajustar cantidades mínimas según consumo real'
                ];
                
                recomendaciones.forEach(rec => {
                    doc.text(rec, 20, recomendacionesY);
                    recomendacionesY += 8;
                });
                
                // Pie de página en todas las páginas
                const pageCount = doc.internal.getNumberOfPages();
                for (let i = 1; i <= pageCount; i++) {
                    doc.setPage(i);
                    
                    // Línea de separación
                    doc.setDrawColor(200, 200, 200);
                    doc.line(20, doc.internal.pageSize.height - 20, doc.internal.pageSize.width - 20, doc.internal.pageSize.height - 20);
                    
                    // Información del pie
                    doc.setFontSize(8);
                    doc.setTextColor(128, 128, 128);
                    doc.text(`Página ${i} de ${pageCount}`, doc.internal.pageSize.width - 40, doc.internal.pageSize.height - 10);
                    doc.text('Sistema de Gestión - Taller de Confección', 20, doc.internal.pageSize.height - 10);
                    doc.text(`Generado: ${new Date().toLocaleString('es-MX')}`, 20, doc.internal.pageSize.height - 5);
                }
                
                // Guardar archivo
                const fileName = `materiales_criticos_${new Date().toISOString().split('T')[0]}.pdf`;
                doc.save(fileName);
                
                console.log(`✅ Reporte PDF generado: ${fileName}`);
                
                // Mostrar notificación verde (con fallback a alert si no existe la función)
                if (typeof mostrarNotificacionExito === 'function') {
                    mostrarNotificacionExito(
                        `✅ Reporte PDF generado exitosamente`,
                        `<div class="text-sm mt-2 space-y-1">
                            <p><strong>📊 Resumen:</strong></p>
                            <p>• ${materialesCriticos.length} materiales requieren atención</p>
                            <p>• ${agotados.length} agotados</p>
                            <p>• ${criticos.length} críticos</p>
                            <p>• ${bajos.length} bajos</p>
                            <p><strong>📄 Archivo:</strong> ${fileName}</p>
                        </div>`
                    );
                } else {
                    // Crear notificación verde simple si la función no existe
                    const notif = document.createElement('div');
                    notif.className = 'fixed bottom-4 right-4 bg-green-500 text-white p-4 rounded-lg shadow-lg z-50 max-w-sm';
                    notif.innerHTML = `
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-semibold mb-1">✅ Reporte PDF generado exitosamente</div>
                                <div class="text-sm">
                                    <p><strong>📊 Resumen:</strong></p>
                                    <p>• ${materialesCriticos.length} materiales requieren atención</p>
                                    <p>• ${agotados.length} agotados, ${criticos.length} críticos, ${bajos.length} bajos</p>
                                    <p><strong>📄 Archivo:</strong> ${fileName}</p>
                                </div>
                            </div>
                            <button onclick="this.parentElement.parentElement.remove()" 
                                    class="ml-2 text-white hover:text-gray-200 focus:outline-none">
                                ✕
                            </button>
                        </div>
                    `;
                    document.body.appendChild(notif);
                    
                    // Auto-eliminar después de 8 segundos
                    setTimeout(() => {
                        if (notif.parentElement) {
                            notif.remove();
                        }
                    }, 8000);
                }
                
            } catch (error) {
                console.error('❌ Error generando reporte:', error);
                alert('❌ Error al generar el reporte PDF: ' + error.message);
            }
        })
        .catch(err => {
            console.error('❌ Error obteniendo datos:', err);
            alert('❌ Error al obtener datos del inventario: ' + err.message);
        });
}


// === COMPATIBILIDAD GLOBAL ===

// Asegurar que las funciones estén disponibles globalmente
window.mostrarFormularioInventario = mostrarFormularioInventario;
window.guardarMaterial = guardarMaterial;
window.eliminarMaterial = eliminarMaterial;
window.mostrarReabastecimiento = mostrarReabastecimiento;
window.confirmarReabastecimiento = confirmarReabastecimiento;
window.cerrarModalReabastecimiento = cerrarModalReabastecimiento;
window.editarCantidadMinima = editarCantidadMinima;
window.confirmarCantidadMinima = confirmarCantidadMinima;
window.cerrarModalCantidadMinima = cerrarModalCantidadMinima;
window.generarReportePDF = generarReportePDF;
window.generarReporteCriticos = generarReporteCriticos;

console.log("✅ Sistema de inventario inicializado correctamente");