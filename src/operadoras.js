// src/operadoras.js - Funcionalidades específicas para operadoras

class OperadoraManager {
    constructor() {
        this.nombreUsuario = localStorage.getItem("nombreUsuario") || "Operadora";
        this.datosOperadora = {};
        this.tareaActual = null;
        this.cronometroInterval = null;
        this.tiempoInicio = null;
        this.tiempoAcumulado = 0;
        this.notificacionesInterval = null;
        
        this.init();
    }

    init() {
        console.log('🚀 Inicializando OperadoraManager para:', this.nombreUsuario);
        
        // Cargar datos iniciales
        this.cargarPerfilCompleto();
        
        // Inicializar cronómetro si hay tiempo guardado
        this.recuperarTiempoGuardado();
        
        // Configurar actualizaciones automáticas
        this.configurarActualizacionesAutomaticas();
        
        // Inicializar notificaciones
        this.inicializarNotificaciones();
    }

    // === GESTIÓN DE PERFIL ===
    
    async cargarPerfilCompleto() {
        try {
            const response = await fetch(`api/operadoras/get_perfil.php?nombre=${encodeURIComponent(this.nombreUsuario)}`);
            const data = await response.json();
            
            if (data.success) {
                this.datosOperadora = data.data;
                this.actualizarInterfazPerfil();
                console.log('✅ Perfil cargado:', this.datosOperadora);
            } else {
                console.warn('⚠️ No se pudo cargar el perfil:', data.error);
                this.mostrarNotificacion('Error cargando perfil', 'error');
            }
        } catch (error) {
            console.error('❌ Error cargando perfil:', error);
            this.mostrarNotificacion('Error de conexión al cargar perfil', 'error');
        }
    }

    actualizarInterfazPerfil() {
        // Actualizar elementos de la interfaz con datos del perfil
        const elementos = {
            'nombreOperaria': this.datosOperadora.nombre,
            'especialidadOperaria': this.datosOperadora.especialidad,
            'tareasCompletadas': this.datosOperadora.piezas_hoy || 0,
            'productividadHoy': (this.datosOperadora.eficiencia_hoy || 0) + '%',
            'horasHoy': (this.datosOperadora.horas_hoy || 0) + 'h'
        };

        Object.entries(elementos).forEach(([id, valor]) => {
            const elemento = document.getElementById(id);
            if (elemento) {
                elemento.textContent = valor;
            }
        });

        // Actualizar foto de perfil
        this.actualizarFotoPerfil(this.datosOperadora.foto_perfil);
        
        // Actualizar indicador de estado
        this.actualizarEstadoIndicator(this.datosOperadora.estado);
    }

    actualizarFotoPerfil(urlFoto) {
        const fotos = ['fotoPerfil', 'fotoPerfilGrande'];
        const iconos = ['iconoPerfil', 'iconoPerfilGrande'];
        
        fotos.forEach((fotoId, index) => {
            const foto = document.getElementById(fotoId);
            const icono = document.getElementById(iconos[index]);
            
            if (foto && icono) {
                if (urlFoto && urlFoto !== '') {
                    foto.src = urlFoto;
                    foto.classList.remove('hidden');
                    icono.classList.add('hidden');
                } else {
                    foto.classList.add('hidden');
                    icono.classList.remove('hidden');
                }
            }
        });
    }

    actualizarEstadoIndicator(estado) {
        const indicator = document.getElementById('statusIndicator');
        if (indicator) {
            const clases = {
                'disponible': 'status-available',
                'ocupado': 'status-working',
                'descanso': 'status-break',
                'ausente': 'status-absent'
            };
            
            indicator.className = `absolute -bottom-1 -right-1 w-6 h-6 rounded-full border-2 border-white ${clases[estado] || clases.disponible}`;
            
            if (estado === 'ocupado') {
                indicator.classList.add('pulse');
            } else {
                indicator.classList.remove('pulse');
            }
        }
    }

    // === GESTIÓN DE TAREAS ===

    async cargarTareaActual() {
        try {
            const response = await fetch(`api/tareas/get_tarea_operaria.php?nombre=${encodeURIComponent(this.nombreUsuario)}`);
            const data = await response.json();
            
            if (data.success && data.tarea) {
                this.tareaActual = data;
                return data;
            } else {
                this.tareaActual = null;
                return null;
            }
        } catch (error) {
            console.error('❌ Error cargando tarea actual:', error);
            return null;
        }
    }

    async registrarProgreso(cantidad, calidad = 'buena', observaciones = '') {
        if (!cantidad || cantidad <= 0) {
            this.mostrarNotificacion('Cantidad inválida', 'error');
            return false;
        }

        try {
            const response = await fetch('api/operadoras/registrar_progreso.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nombre: this.nombreUsuario,
                    cantidad: parseInt(cantidad),
                    calidad: calidad,
                    observaciones: observaciones,
                    fecha: new Date().toISOString()
                })
            });

            const data = await response.json();

            if (data.success) {
                this.mostrarNotificacion(`✅ Progreso registrado: ${cantidad} piezas`, 'success');
                
                // Actualizar datos locales
                if (this.tareaActual) {
                    this.tareaActual.progreso_actual = data.data.progreso_nuevo;
                    this.tareaActual.porcentaje_completado = data.data.porcentaje_completado;
                }
                
                // Recargar datos
                setTimeout(() => {
                    this.cargarTareaActual();
                    this.cargarPerfilCompleto();
                }, 1000);
                
                return true;
            } else {
                this.mostrarNotificacion('❌ ' + (data.error || 'Error al registrar progreso'), 'error');
                return false;
            }
        } catch (error) {
            console.error('❌ Error registrando progreso:', error);
            this.mostrarNotificacion('❌ Error de conexión', 'error');
            return false;
        }
    }

    // === CRONÓMETRO Y TIEMPO ===

    recuperarTiempoGuardado() {
        const tiempoGuardado = localStorage.getItem(`tiempo_${this.nombreUsuario}`);
        const estadoCronometro = localStorage.getItem(`cronometro_activo_${this.nombreUsuario}`);
        
        if (tiempoGuardado) {
            this.tiempoAcumulado = parseInt(tiempoGuardado);
            this.actualizarCronometroDisplay();
            
            // Si el cronómetro estaba activo, continuar
            if (estadoCronometro === 'true') {
                const tiempoInicio = localStorage.getItem(`tiempo_inicio_${this.nombreUsuario}`);
                if (tiempoInicio) {
                    this.tiempoInicio = parseInt(tiempoInicio);
                    this.iniciarCronometro(false); // No mostrar notificación
                }
            }
        }
    }

    iniciarCronometro(mostrarNotificacion = true) {
        if (!this.cronometroInterval) {
            if (!this.tiempoInicio) {
                this.tiempoInicio = Date.now() - this.tiempoAcumulado;
                localStorage.setItem(`tiempo_inicio_${this.nombreUsuario}`, this.tiempoInicio.toString());
            }
            
            this.cronometroInterval = setInterval(() => this.actualizarCronometro(), 1000);
            localStorage.setItem(`cronometro_activo_${this.nombreUsuario}`, 'true');
            
            if (mostrarNotificacion) {
                this.mostrarNotificacion('⏰ Cronómetro iniciado', 'success');
            }
        }
    }

    pausarCronometro() {
        if (this.cronometroInterval) {
            clearInterval(this.cronometroInterval);
            this.cronometroInterval = null;
            
            localStorage.setItem(`tiempo_${this.nombreUsuario}`, this.tiempoAcumulado.toString());
            localStorage.setItem(`cronometro_activo_${this.nombreUsuario}`, 'false');
            
            this.mostrarNotificacion('⏸️ Cronómetro pausado', 'info');
        }
    }

    async detenerCronometro() {
        if (this.cronometroInterval) {
            clearInterval(this.cronometroInterval);
            this.cronometroInterval = null;
        }

        // Guardar tiempo trabajado
        if (this.tiempoAcumulado > 0) {
            await this.guardarTiempoTrabajado(this.tiempoAcumulado);
        }

        // Resetear cronómetro
        this.tiempoAcumulado = 0;
        this.tiempoInicio = null;
        
        // Limpiar localStorage
        localStorage.removeItem(`tiempo_${this.nombreUsuario}`);
        localStorage.removeItem(`cronometro_activo_${this.nombreUsuario}`);
        localStorage.removeItem(`tiempo_inicio_${this.nombreUsuario}`);
        
        this.actualizarCronometroDisplay();
        this.mostrarNotificacion('✅ Tiempo de trabajo registrado', 'success');
    }

    actualizarCronometro() {
        if (this.tiempoInicio) {
            this.tiempoAcumulado = Date.now() - this.tiempoInicio;
            this.actualizarCronometroDisplay();
            
            // Guardar progreso cada minuto
            if (this.tiempoAcumulado % 60000 < 1000) {
                localStorage.setItem(`tiempo_${this.nombreUsuario}`, this.tiempoAcumulado.toString());
            }
        }
    }

    actualizarCronometroDisplay() {
        const horas = Math.floor(this.tiempoAcumulado / 3600000);
        const minutos = Math.floor((this.tiempoAcumulado % 3600000) / 60000);
        const segundos = Math.floor((this.tiempoAcumulado % 60000) / 1000);

        const tiempoFormateado = `${horas.toString().padStart(2, '0')}:${minutos.toString().padStart(2, '0')}:${segundos.toString().padStart(2, '0')}`;

        const cronometroEl = document.getElementById('cronometro');
        if (cronometroEl) {
            cronometroEl.textContent = tiempoFormateado;
        }

        // Actualizar también en el header si existe
        const tiempoHeader = document.getElementById('tiempoTrabajado');
        if (tiempoHeader) {
            tiempoHeader.textContent = `${horas}h ${minutos}m`;
        }
    }

    async guardarTiempoTrabajado(tiempo) {
        try {
            await fetch('api/operadoras/guardar_tiempo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nombre: this.nombreUsuario,
                    tiempo_ms: tiempo,
                    fecha: new Date().toISOString().split('T')[0]
                })
            });
        } catch (error) {
            console.error('❌ Error guardando tiempo:', error);
        }
    }

    // === CAMBIO DE ESTADO ===

    async cambiarEstado(nuevoEstado) {
        try {
            const response = await fetch('api/operadoras/cambiar_estado.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nombre: this.nombreUsuario,
                    estado: nuevoEstado
                })
            });

            const data = await response.json();

            if (data.success) {
                this.actualizarEstadoIndicator(nuevoEstado);
                this.datosOperadora.estado = nuevoEstado;
                
                // Manejar cronómetro según el estado
                if (nuevoEstado === 'descanso' || nuevoEstado === 'ausente') {
                    this.pausarCronometro();
                } else if (nuevoEstado === 'ocupado' && this.tareaActual) {
                    this.iniciarCronometro();
                }
                
                this.mostrarNotificacion(`✅ ${data.message}`, 'success');
                return true;
            } else {
                this.mostrarNotificacion('❌ ' + (data.error || 'Error al cambiar estado'), 'error');
                return false;
            }
        } catch (error) {
            console.error('❌ Error cambiando estado:', error);
            this.mostrarNotificacion('❌ Error de conexión', 'error');
            return false;
        }
    }

    // === HISTORIAL ===

    async cargarHistorial(limite = 10, fechaDesde = null, fechaHasta = null) {
        try {
            let url = `api/operadoras/get_historial.php?nombre=${encodeURIComponent(this.nombreUsuario)}&limite=${limite}`;
            
            if (fechaDesde) url += `&fecha_desde=${fechaDesde}`;
            if (fechaHasta) url += `&fecha_hasta=${fechaHasta}`;

            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                return data.data;
            } else {
                console.warn('⚠️ Error cargando historial:', data.error);
                return null;
            }
        } catch (error) {
            console.error('❌ Error cargando historial:', error);
            return null;
        }
    }

    // === NOTIFICACIONES ===

    inicializarNotificaciones() {
        // Verificar notificaciones cada 30 segundos
        this.notificacionesInterval = setInterval(() => {
            this.verificarNotificaciones();
        }, 30000);
    }

    async verificarNotificaciones() {
        try {
            const response = await fetch(`api/operadoras/get_notificaciones.php?nombre=${encodeURIComponent(this.nombreUsuario)}`);
            const data = await response.json();

            if (data.success && data.notificaciones.length > 0) {
                data.notificaciones.forEach(notif => {
                    if (!notif.leida) {
                        this.mostrarNotificacionPersistente(notif.titulo, notif.mensaje, notif.tipo);
                    }
                });
            }
        } catch (error) {
            console.debug('Error verificando notificaciones:', error);
        }
    }

    mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
        const contenedor = document.getElementById('notificaciones');
        if (!contenedor) return;

        const notif = document.createElement('div');
        const colores = {
            info: 'bg-blue-500',
            success: 'bg-green-500',
            warning: 'bg-yellow-500',
            error: 'bg-red-500'
        };

        const iconos = {
            info: 'fas fa-info-circle',
            success: 'fas fa-check-circle',
            warning: 'fas fa-exclamation-triangle',
            error: 'fas fa-times-circle'
        };

        notif.className = `notification ${colores[tipo]} text-white p-4 rounded-lg shadow-lg max-w-sm mb-2`;
        notif.innerHTML = `
            <div class="flex items-start">
                <i class="${iconos[tipo]} mr-3 mt-1"></i>
                <div class="flex-1">
                    <span>${mensaje}</span>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" 
                        class="ml-2 text-white hover:text-gray-200 focus:outline-none">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        contenedor.appendChild(notif);

        // Auto-eliminar
        setTimeout(() => {
            if (notif.parentElement) {
                notif.remove();
            }
        }, duracion);
    }

    mostrarNotificacionPersistente(titulo, mensaje, tipo = 'info') {
        const contenedor = document.getElementById('notificaciones');
        if (!contenedor) return;

        const notif = document.createElement('div');
        const colores = {
            info: 'bg-blue-500',
            success: 'bg-green-500',
            warning: 'bg-yellow-500',
            error: 'bg-red-500'
        };

        notif.className = `notification ${colores[tipo]} text-white p-4 rounded-lg shadow-lg max-w-sm mb-2`;
        notif.innerHTML = `
            <div class="flex items-start">
                <i class="fas fa-bell mr-3 mt-1"></i>
                <div class="flex-1">
                    <div class="font-semibold">${titulo}</div>
                    <div class="text-sm">${mensaje}</div>
                    <div class="text-xs mt-1 opacity-75">${new Date().toLocaleTimeString()}</div>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" 
                        class="ml-2 text-white hover:text-gray-200 focus:outline-none">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        contenedor.appendChild(notif);
    }

    // === CONFIGURACIÓN Y LIMPIEZA ===

    configurarActualizacionesAutomaticas() {
        // Actualizar datos cada 2 minutos
        setInterval(() => {
            this.cargarPerfilCompleto();
        }, 120000);

        // Actualizar tarea actual cada minuto
        setInterval(() => {
            if (this.datosOperadora.estado === 'ocupado') {
                this.cargarTareaActual();
            }
        }, 60000);
    }

    destruir() {
        // Limpiar intervalos
        if (this.cronometroInterval) {
            clearInterval(this.cronometroInterval);
        }
        if (this.notificacionesInterval) {
            clearInterval(this.notificacionesInterval);
        }

        // Guardar estado actual
        if (this.tiempoAcumulado > 0) {
            localStorage.setItem(`tiempo_${this.nombreUsuario}`, this.tiempoAcumulado.toString());
            localStorage.setItem(`cronometro_activo_${this.nombreUsuario}`, this.cronometroInterval ? 'true' : 'false');
            if (this.tiempoInicio) {
                localStorage.setItem(`tiempo_inicio_${this.nombreUsuario}`, this.tiempoInicio.toString());
            }
        }
    }

    // === MÉTODOS PÚBLICOS PARA LA INTERFAZ ===

    async manejarRegistroProgreso() {
        const cantidad = document.getElementById('cantidadProgreso')?.value;
        const calidad = document.getElementById('calidadProgreso')?.value || 'buena';
        const observaciones = document.getElementById('observacionesProgreso')?.value || '';

        if (await this.registrarProgreso(cantidad, calidad, observaciones)) {
            // Limpiar formulario
            const campos = ['cantidadProgreso', 'observacionesProgreso'];
            campos.forEach(campo => {
                const elemento = document.getElementById(campo);
                if (elemento) elemento.value = '';
            });
        }
    }

    manejarIniciarCronometro() {
        this.iniciarCronometro();
    }

    manejarPausarCronometro() {
        this.pausarCronometro();
    }

    async manejarDetenerCronometro() {
        await this.detenerCronometro();
    }

    async manejarCambiarEstado(estado) {
        return await this.cambiarEstado(estado);
    }
}

// === INSTANCIA GLOBAL ===
let operadoraManager = null;

// === FUNCIONES GLOBALES PARA LA INTERFAZ ===

function inicializarOperadoraManager() {
    if (!operadoraManager) {
        operadoraManager = new OperadoraManager();
    }
    return operadoraManager;
}

function registrarProgreso() {
    if (operadoraManager) {
        operadoraManager.manejarRegistroProgreso();
    }
}

function iniciarCronometro() {
    if (operadoraManager) {
        operadoraManager.manejarIniciarCronometro();
    }
}

function pausarCronometro() {
    if (operadoraManager) {
        operadoraManager.manejarPausarCronometro();
    }
}

function detenerCronometro() {
    if (operadoraManager) {
        operadoraManager.manejarDetenerCronometro();
    }
}

function cambiarEstadoOperadora(estado) {
    if (operadoraManager) {
        operadoraManager.manejarCambiarEstado(estado);
    }
}

// === INICIALIZACIÓN AUTOMÁTICA ===
document.addEventListener('DOMContentLoaded', () => {
    // Solo inicializar si estamos en la página de operadoras
    if (document.getElementById('nombreOperaria')) {
        console.log('🚀 Inicializando OperadoraManager automáticamente');
        inicializarOperadoraManager();
    }
});

// === LIMPIEZA AL SALIR ===
window.addEventListener('beforeunload', () => {
    if (operadoraManager) {
        operadoraManager.destruir();
    }
});

// === EXPORTAR PARA USO MODULAR ===
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { OperadoraManager, inicializarOperadoraManager };
}