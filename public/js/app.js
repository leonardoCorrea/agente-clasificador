/**
 * JavaScript Principal
 * Plataforma de Clasificación de Facturas Aduaneras
 */

// ============================================================
// UTILIDADES GENERALES
// ============================================================

/**
 * Mostrar spinner de carga
 */
function showSpinner(message = 'Procesando...') {
    const spinner = document.createElement('div');
    spinner.id = 'loading-spinner';
    spinner.className = 'spinner-overlay';
    spinner.innerHTML = `
        <div class="text-center">
            <div class="spinner"></div>
            <p class="text-white mt-3">${message}</p>
        </div>
    `;
    document.body.appendChild(spinner);
}

/**
 * Ocultar spinner de carga
 */
function hideSpinner() {
    const spinner = document.getElementById('loading-spinner');
    if (spinner) {
        spinner.remove();
    }
}

/**
 * Mostrar alerta
 */
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    const container = document.querySelector('.container');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);

        // Auto-cerrar después de 5 segundos
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
}

/**
 * Realizar petición AJAX
 */
async function ajaxRequest(url, method = 'GET', data = null) {
    try {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(url, options);
        const result = await response.json();

        return result;
    } catch (error) {
        console.error('Error en petición AJAX:', error);
        return { success: false, message: 'Error de conexión' };
    }
}

// ============================================================
// DRAG AND DROP PARA ARCHIVOS
// ============================================================

/**
 * Inicializar zona de drag and drop
 */
function initDropzone(elementId, callback) {
    const dropzone = document.getElementById(elementId);
    if (!dropzone) return;

    const fileInput = dropzone.querySelector('input[type="file"]');

    // Prevenir comportamiento por defecto
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Highlight al arrastrar
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.classList.remove('dragover');
        }, false);
    });

    // Manejar drop
    dropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            if (callback) callback(files[0]);
        }
    }, false);

    // Manejar click
    dropzone.addEventListener('click', () => {
        fileInput.click();
    });

    // Manejar cambio de archivo
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            if (callback) callback(e.target.files[0]);
        }
    });
}

/**
 * Validar archivo
 */
function validateFile(file, allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'], maxSize = 10485760) {
    const extension = file.name.split('.').pop().toLowerCase();

    if (!allowedExtensions.includes(extension)) {
        showAlert(`Tipo de archivo no permitido. Extensiones permitidas: ${allowedExtensions.join(', ')}`, 'danger');
        return false;
    }

    if (file.size > maxSize) {
        showAlert(`El archivo excede el tamaño máximo permitido (${(maxSize / 1048576).toFixed(2)} MB)`, 'danger');
        return false;
    }

    return true;
}

// ============================================================
// FORMULARIOS
// ============================================================

/**
 * Validar formulario
 */
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;

    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });

    return isValid;
}

/**
 * Limpiar formulario
 */
function clearForm(formId) {
    const form = document.getElementById(formId);
    if (form) {
        form.reset();
        form.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
    }
}

// ============================================================
// TABLAS DINÁMICAS
// ============================================================

/**
 * Agregar fila a tabla
 */
function addTableRow(tableId, rowData) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const row = tbody.insertRow();

    rowData.forEach(cellData => {
        const cell = row.insertCell();
        cell.innerHTML = cellData;
    });

    return row;
}

/**
 * Eliminar fila de tabla
 */
function removeTableRow(row) {
    if (row && row.parentNode) {
        row.parentNode.removeChild(row);
    }
}

// ============================================================
// FORMATEO
// ============================================================

/**
 * Formatear moneda
 */
function formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

/**
 * Formatear fecha
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('es-MX').format(date);
}

/**
 * Formatear porcentaje
 */
function formatPercentage(value) {
    return `${value.toFixed(2)}%`;
}

// ============================================================
// CLASIFICACIÓN IA
// ============================================================

/**
 * Procesar clasificación IA
 */
async function classifyItem(itemId) {
    showSpinner('Clasificando con IA...');

    try {
        const response = await ajaxRequest(`/agenteClasificador/api/classify.php?item_id=${itemId}`, 'POST');

        hideSpinner();

        if (response.success) {
            showAlert('Clasificación completada exitosamente', 'success');
            return response.resultado;
        } else {
            showAlert(response.message || 'Error al clasificar', 'danger');
            return null;
        }
    } catch (error) {
        hideSpinner();
        showAlert('Error de conexión con el servicio de IA', 'danger');
        return null;
    }
}

/**
 * Procesar OCR
 */
async function processOCR(facturaId) {
    showSpinner('Procesando OCR...');

    try {
        const response = await ajaxRequest(`/agenteClasificador/api/ocr.php?factura_id=${facturaId}`, 'POST');

        hideSpinner();

        if (response.success) {
            showAlert('OCR procesado exitosamente', 'success');
            return response;
        } else {
            showAlert(response.message || 'Error al procesar OCR', 'danger');
            return null;
        }
    } catch (error) {
        hideSpinner();
        showAlert('Error de conexión con el servicio OCR', 'danger');
        return null;
    }
}

// ============================================================
// CONFIRMACIONES
// ============================================================

/**
 * Confirmar acción
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// ============================================================
// INICIALIZACIÓN
// ============================================================

document.addEventListener('DOMContentLoaded', function () {
    // Inicializar tooltips de Bootstrap si existen
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Auto-cerrar alertas
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});

// ============================================================
// EXPORTAR FUNCIONES GLOBALES
// ============================================================

window.app = {
    showSpinner,
    hideSpinner,
    showAlert,
    ajaxRequest,
    initDropzone,
    validateFile,
    validateForm,
    clearForm,
    addTableRow,
    removeTableRow,
    formatCurrency,
    formatDate,
    formatPercentage,
    classifyItem,
    processOCR,
    confirmAction
};
