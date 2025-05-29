// Función para reabastecer material usando tu estructura de BD
async function reabastecerMaterial(datos) {
    try {
        // Crear FormData con los datos
        const formData = new FormData();
        formData.append('id_material', datos.id_material);
        formData.append('cantidad', datos.cantidad);
        
        // Campos opcionales
        if (datos.comentario) {
            formData.append('comentario', datos.comentario);
        }
        if (datos.usuario) {
            formData.append('usuario', datos.usuario);
        }

        // Hacer la petición POST
        const response = await fetch('api/inventario/reabastecer_material.php', {
            method: 'POST',
            body: formData
        });

        // Obtener la respuesta JSON
        const result = await response.json();

        if (result.success) {
            console.log('✅ Reabastecimiento exitoso', result);
            return result;
        } else {
            console.error('❌ Error en reabastecimiento', result);
            throw new Error(result.error || 'Error desconocido');
        }

    } catch (error) {
        console.error('❌ Error de conexión:', error);
        throw error;
    }
}

// Ejemplo de uso con tu estructura
async function ejemplo() {
    try {
        const resultado = await reabastecerMaterial({
            id_material: 1,
            cantidad: 50,
            comentario: 'Reabastecimiento programado - Proveedor XYZ',
            usuario: 'Admin'
        });
        
        alert('✅ Reabastecimiento registrado correctamente');
        console.log('Datos guardados:', resultado.data);
        
        // Mostrar información del resultado
        console.log(`Material: ${resultado.data.material}`);
        console.log(`Cantidad anterior: ${resultado.data.cantidad_anterior}`);
        console.log(`Cantidad agregada: ${resultado.data.cantidad_agregada}`);
        console.log(`Cantidad nueva: ${resultado.data.cantidad_nueva}`);
        console.log(`Estado cambió de: ${resultado.data.estado_anterior} → ${resultado.data.estado_nuevo}`);
        
    } catch (error) {
        alert('❌ Error: ' + error.message);
    }
}

// Función para obtener el historial de reabastecimientos
async function obtenerHistorialReabastecimientos(materialId = null) {
    try {
        let url = 'api/inventario/historial_reabastecimientos.php';
        if (materialId) {
            url += `?material_id=${materialId}`;
        }

        const response = await fetch(url);
        const result = await response.json();

        if (result.success) {
            return result.data;
        } else {
            throw new Error(result.error || 'Error al obtener historial');
        }
    } catch (error) {
        console.error('❌ Error obteniendo historial:', error);
        throw error;
    }
}

// Función para obtener estadísticas de reabastecimiento
async function obtenerEstadisticasReabastecimiento() {
    try {
        const response = await fetch('api/inventario/estadisticas_reabastecimiento.php');
        const result = await response.json();

        if (result.success) {
            return result.data;
        } else {
            throw new Error(result.error || 'Error al obtener estadísticas');
        }
    } catch (error) {
        console.error('❌ Error obteniendo estadísticas:', error);
        throw error;
    }
}

// Función para obtener materiales que necesitan reabastecimiento
async function obtenerMaterialesCriticos() {
    try {
        const response = await fetch('api/inventario/materiales_criticos.php');
        const result = await response.json();

        if (result.success) {
            return result.data;
        } else {
            throw new Error(result.error || 'Error al obtener materiales críticos');
        }
    } catch (error) {
        console.error('❌ Error obteniendo materiales críticos:', error);
        throw error;
    }
}

// También con jQuery si lo prefieres
function reabastecerMaterialJQuery(datos) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: 'api/inventario/reabastecer_material.php',
            type: 'POST',
            data: {
                id_material: datos.id_material,
                cantidad: datos.cantidad,
                comentario: datos.comentario || 'Reabastecimiento via sistema',
                usuario: datos.usuario || 'Usuario'
            },
            dataType: 'json',
            success: function(result) {
                if (result.success) {
                    resolve(result);
                } else {
                    reject(new Error(result.error || 'Error desconocido'));
                }
            },
            error: function(xhr, status, error) {
                reject(new Error('Error de conexión: ' + error));
            }
        });
    });
}

// Ejemplo de integración en tu sistema existente
function integrarConFormulario() {
    // Si tienes un formulario HTML para reabastecimiento
    document.getElementById('form-reabastecer')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const datos = {
            id_material: formData.get('id_material'),
            cantidad: formData.get('cantidad'),
            comentario: formData.get('comentario'),
            usuario: formData.get('usuario')
        };

        try {
            const resultado = await reabastecerMaterial(datos);
            
            // Actualizar UI
            mostrarMensajeExito('Reabastecimiento registrado correctamente');
            actualizarTablaInventario();
            limpiarFormulario();
            
        } catch (error) {
            mostrarMensajeError('Error: ' + error.message);
        }
    });
}

// Funciones auxiliares para UI
function mostrarMensajeExito(mensaje) {
    // Implementa según tu sistema de notificaciones
    console.log('✅ ' + mensaje);
}

function mostrarMensajeError(mensaje) {
    // Implementa según tu sistema de notificaciones
    console.error('❌ ' + mensaje);
}

function actualizarTablaInventario() {
    // Recargar tabla de inventario
    location.reload(); // O implementa actualización AJAX
}

function limpiarFormulario() {
    document.getElementById('form-reabastecer')?.reset();
}