document.addEventListener("DOMContentLoaded", function () {
    console.log("JavaScript cargado correctamente");

    // Obtener elementos del DOM
    const roleSelection = document.getElementById("roleSelection");
    const loginForm = document.getElementById("loginForm");
    const adminBtn = document.getElementById("adminBtn");
    const operatorBtn = document.getElementById("operatorBtn");

    // Función para mostrar el formulario y ocultar los botones de selección
    function showLoginForm() {
        roleSelection.style.display = "none";  // Oculta la selección de rol
        loginForm.style.display = "block";    // Muestra el formulario de inicio de sesión
    }

    // Agregar eventos a los botones
    if (adminBtn) {
        adminBtn.addEventListener("click", showLoginForm);
    }
    if (operatorBtn) {
        operatorBtn.addEventListener("click", showLoginForm);
    }

    // Función de inicio de sesión
    function login(event) {
        event.preventDefault();  // Evita la recarga de la página por defecto

        const username = document.getElementById("username").value;
        const password = document.getElementById("password").value;

        if (username && password) {
            fetch("api/usuarios/login.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ username, password })
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta de la base de datos');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.jerarquia !== undefined) {
                        const jerarquia = parseInt(data.jerarquia, 10);
                        localStorage.setItem("jerarquiaUsuario", jerarquia);
                        localStorage.setItem("nombreUsuario", data.nombre);

                        if (jerarquia === 1) {
                            console.log("Redirigiendo a test.html");
                            window.location.href = "test.html";
                        } else if (jerarquia === 2) {
                            console.log("Redirigiendo a operador.html");
                            window.location.href = "operador.html";
                        } else {
                            alert("Rol de usuario no reconocido.");
                        }
                    } else {
                        alert(data.message || "Error en el inicio de sesión.");
                    }
                })
                .catch(error => {
                    console.error("Error al intentar iniciar sesión:", error);
                    alert("Hubo un problema con la conexión a la base de datos.");
                });
        } else {
            alert("Por favor ingrese el usuario y la contraseña");
        }
    }

    // Enlazar la función de login al formulario
    const loginFormElement = document.getElementById("loginForm");
    if (loginFormElement) {
        loginFormElement.addEventListener("submit", login);
    }

    // Botón de depuración para login simulado
    const debugButton = document.getElementById("debugLogin");
    if (debugButton) {
        debugButton.addEventListener("click", function () {
            console.log("Botón de depuración clickeado");
            window.location.href = "operador.html";
        });
    }
});