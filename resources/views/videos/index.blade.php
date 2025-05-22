<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Buscador de Subtítulos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 0.8em;
            padding: 0.4em 0.6em;
        }
        .status-pending { background-color: #ffc107; }
        .status-processing { background-color: #0dcaf0; }
        .status-completed { background-color: #198754; }
        .status-failed { background-color: #dc3545; }
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
        }
        .loading-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
        }
        .pagination {
            margin-bottom: 0;
        }
        .pagination .page-link {
            padding: 0.375rem 0.75rem;
            font-size: 0.9rem;
        }
        .pagination .page-item.disabled .page-link {
            background-color: #e9ecef;
            border-color: #dee2e6;
        }
        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .sortable {
            cursor: pointer;
            position: relative;
            padding-right: 20px;
        }
        .sortable:after {
            content: '↕';
            position: absolute;
            right: 5px;
            color: #999;
        }
        .sortable.asc:after {
            content: '↑';
            color: #0d6efd;
        }
        .sortable.desc:after {
            content: '↓';
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Buscador de Subtítulos</h1>

        <div id="alert-container"></div>

        <div class="card mb-4">
            <div class="card-body">
                <form id="scan-form">
                    @csrf
                    <div class="mb-3">
                        <label for="path" class="form-label">Ruta del directorio</label>
                        <input type="text" class="form-control" id="path" name="path" required>
                        <div class="form-text">Ingrese la ruta completa del directorio que desea escanear.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">Escanear directorio</button>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="file_name">Nombre del archivo</th>
                        <th class="sortable" data-sort="language">Idioma</th>
                        <th class="sortable" data-sort="status">Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="videos-table-body">
                    <!-- Los videos se cargarán aquí dinámicamente -->
                </tbody>
            </table>
        </div>

        <div id="pagination" class="mt-4">
            <!-- La paginación se cargará aquí dinámicamente -->
        </div>
    </div>

    <div id="loading" class="loading">
        <div class="loading-content">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-2" id="loading-message">Procesando...</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuración global para las peticiones Ajax
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const headers = {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        };

        // Variables globales para el ordenamiento
        let currentSort = 'file_name';
        let currentDirection = 'asc';

        // Funciones de utilidad
        function showLoading(message = 'Procesando...') {
            document.getElementById('loading-message').textContent = message;
            document.getElementById('loading').style.display = 'block';
        }

        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
        }

        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            alertContainer.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        function getStatusBadge(status) {
            const statusText = {
                'pending': 'Pendiente',
                'processing': 'Procesando',
                'completed': 'Completado',
                'failed': 'Fallido'
            };
            return `<span class="badge status-badge status-${status}">${statusText[status]}</span>`;
        }

        function getStatusBadgeClass(status) {
            const statusClass = {
                'pending': 'bg-warning',
                'processing': 'bg-info',
                'completed': 'bg-success',
                'failed': 'bg-danger'
            };
            return statusClass[status] || 'bg-secondary';
        }

        function getStatusText(status) {
            const statusText = {
                'pending': 'Pendiente',
                'processing': 'Procesando',
                'completed': 'Completado',
                'failed': 'Fallido'
            };
            return statusText[status] || 'Desconocido';
        }

        // Cargar videos
        function loadVideos(page = 1) {
            showLoading('Cargando videos...');
            fetch(`/videos?page=${page}&sort=${currentSort}&direction=${currentDirection}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const tbody = document.getElementById('videos-table-body');
                        tbody.innerHTML = '';

                        data.data.data.forEach(video => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${video.file_name}</td>
                                <td>
                                    <span class="badge ${video.language ? 'bg-success' : 'bg-warning'}"
                                          data-bs-toggle="tooltip"
                                          title="${video.language ? 'Idioma detectado: ' + video.language : 'No se detectó idioma'}">
                                        ${video.language || 'No detectado'}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge ${getStatusBadgeClass(video.status)}">
                                        ${getStatusText(video.status)}
                                    </span>
                                </td>
                                <td>
                                    ${getActionButton(video)}
                                </td>
                            `;
                            tbody.appendChild(tr);
                        });

                        // Actualizar paginación
                        updatePagination(data.data);

                        // Reinicializar tooltips después de actualizar el contenido
                        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                            return new bootstrap.Tooltip(tooltipTriggerEl);
                        });
                    }
                })
                .catch(error => {
                    showAlert('Error al cargar los videos: ' + error.message, 'danger');
                })
                .finally(() => {
                    hideLoading();
                });
        }

        function getActionButton(video) {
            if (video.status === 'pending') {
                return `<button onclick="processVideo(${video.id})" class="btn btn-sm btn-primary">Buscar subtítulos</button>`;
            } else if (video.status === 'completed') {
                return `<span class="badge bg-success">Subtítulos descargados</span>`;
            } else if (video.status === 'failed') {
                return `<button onclick="processVideo(${video.id})" class="btn btn-sm btn-warning">Reintentar</button>`;
            }
            return '';
        }

        function updatePagination(data) {
            const pagination = document.getElementById('pagination');
            let html = '<nav><ul class="pagination justify-content-center">';

            // Botón anterior
            html += `
                <li class="page-item ${data.current_page === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadVideos(${data.current_page - 1})">Anterior</a>
                </li>
            `;

            // Números de página
            for (let i = 1; i <= data.last_page; i++) {
                html += `
                    <li class="page-item ${data.current_page === i ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="loadVideos(${i})">${i}</a>
                    </li>
                `;
            }

            // Botón siguiente
            html += `
                <li class="page-item ${data.current_page === data.last_page ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadVideos(${data.current_page + 1})">Siguiente</a>
                </li>
            `;

            html += '</ul></nav>';
            pagination.innerHTML = html;
        }

        // Configurar ordenamiento
        document.querySelectorAll('.sortable').forEach(header => {
            header.addEventListener('click', function() {
                const sortColumn = this.dataset.sort;

                // Actualizar dirección de ordenamiento
                if (currentSort === sortColumn) {
                    currentDirection = currentDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSort = sortColumn;
                    currentDirection = 'asc';
                }

                // Actualizar clases visuales
                document.querySelectorAll('.sortable').forEach(h => {
                    h.classList.remove('asc', 'desc');
                });
                this.classList.add(currentDirection);

                // Recargar videos con el nuevo ordenamiento
                loadVideos();
            });
        });

        // Escanear directorio
        document.getElementById('scan-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const path = document.getElementById('path').value;

            showLoading('Escaneando directorio...');
            fetch('/scan', {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({ path })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message);
                    loadVideos();
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Error al escanear el directorio: ' + error.message, 'danger');
            })
            .finally(() => {
                hideLoading();
            });
        });

        // Procesar video
        function processVideo(videoId) {
            showLoading('Buscando subtítulos...');
            fetch(`/videos/${videoId}/process`, {
                method: 'POST',
                headers: headers
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message);
                } else {
                    showAlert(data.message, 'danger');
                }
                loadVideos();
            })
            .catch(error => {
                showAlert('Error al procesar el video: ' + error.message, 'danger');
            })
            .finally(() => {
                hideLoading();
            });
        }

        // Cargar videos al iniciar
        document.addEventListener('DOMContentLoaded', function() {
            loadVideos();
            // Inicializar todos los tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
