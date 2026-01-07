<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plataforma de Clasificación de Facturas Aduaneras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-file-invoice"></i>
                Sistema de Clasificación Aduanera
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#que-es">¿Qué es?</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#como-funciona">¿Cómo funciona?</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#beneficios">Beneficios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contacto">Contacto</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-light ms-2" href="login.php">
                            <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <h1 class="hero-title">Clasificación Inteligente de Facturas Aduaneras</h1>
                    <p class="hero-subtitle">
                        Automatice el proceso de clasificación arancelaria con tecnología de inteligencia artificial.
                        Ahorre tiempo, reduzca errores y mejore la eficiencia de su operación aduanera.
                    </p>
                    <div class="mt-4">
                        <a href="register.php" class="btn btn-light btn-lg me-3">
                            <i class="fas fa-rocket"></i> Comenzar Ahora
                        </a>
                        <a href="#como-funciona" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-play-circle"></i> Ver Cómo Funciona
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="fas fa-robot" style="font-size: 15rem; color: rgba(255,255,255,0.2);"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Qué es Section -->
    <section id="que-es" class="section-padding">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="text-gradient">¿Qué es esta Plataforma?</h2>
                <p class="lead text-secondary">Una solución integral para la gestión y clasificación de facturas
                    aduaneras</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="fas fa-brain fa-3x" style="color: var(--primary-color);"></i>
                            </div>
                            <h4>Inteligencia Artificial</h4>
                            <p class="text-secondary">
                                Utiliza modelos de IA avanzados para sugerir códigos arancelarios precisos basados en
                                descripciones de productos.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="fas fa-file-alt fa-3x" style="color: var(--secondary-color);"></i>
                            </div>
                            <h4>OCR Automático</h4>
                            <p class="text-secondary">
                                Extrae automáticamente información de facturas en PDF o imágenes, eliminando la
                                digitación manual.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="fas fa-shield-alt fa-3x" style="color: var(--accent-color);"></i>
                            </div>
                            <h4>Auditoría Completa</h4>
                            <p class="text-secondary">
                                Registra todas las acciones del sistema con trazabilidad completa para cumplimiento
                                normativo.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Problema Section -->
    <section class="section-padding" style="background-color: var(--bg-secondary);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="mb-4">El Problema que Resolvemos</h2>
                    <div class="mb-3">
                        <i class="fas fa-times-circle text-danger me-2"></i>
                        <strong>Clasificación manual lenta y propensa a errores</strong>
                    </div>
                    <div class="mb-3">
                        <i class="fas fa-times-circle text-danger me-2"></i>
                        <strong>Digitación repetitiva de facturas</strong>
                    </div>
                    <div class="mb-3">
                        <i class="fas fa-times-circle text-danger me-2"></i>
                        <strong>Falta de trazabilidad en decisiones</strong>
                    </div>
                    <div class="mb-3">
                        <i class="fas fa-times-circle text-danger me-2"></i>
                        <strong>Códigos arancelarios incorrectos que generan multas</strong>
                    </div>
                    <div class="mb-3">
                        <i class="fas fa-times-circle text-danger me-2"></i>
                        <strong>Procesos aduaneros retrasados</strong>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body p-4">
                            <h3 class="text-success mb-4">Nuestra Solución</h3>
                            <p class="lead">
                                Automatizamos el 90% del proceso de clasificación arancelaria mediante:
                            </p>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> OCR inteligente
                                </li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Clasificación IA
                                </li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Validación humana
                                </li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Auditoría
                                    automática</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Cómo Funciona Section -->
    <section id="como-funciona" class="section-padding">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="text-gradient">¿Cómo Funciona la Clasificación Automática?</h2>
                <p class="lead text-secondary">Proceso simple en 3 pasos</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header text-center">
                            <h1 class="display-4 mb-0">1</h1>
                        </div>
                        <div class="card-body">
                            <h4><i class="fas fa-upload text-primary"></i> Carga y OCR</h4>
                            <p class="text-secondary">
                                Suba su factura en PDF o imagen. El sistema extrae automáticamente toda la información:
                                proveedor, ítems, cantidades, precios y descripciones.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header text-center">
                            <h1 class="display-4 mb-0">2</h1>
                        </div>
                        <div class="card-body">
                            <h4><i class="fas fa-robot text-success"></i> Clasificación IA</h4>
                            <p class="text-secondary">
                                La inteligencia artificial analiza cada descripción de producto y sugiere el código
                                arancelario correcto con explicación detallada y nivel de confianza.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header text-center">
                            <h1 class="display-4 mb-0">3</h1>
                        </div>
                        <div class="card-body">
                            <h4><i class="fas fa-check-double text-warning"></i> Revisión y Aprobación</h4>
                            <p class="text-secondary">
                                Revise las sugerencias, modifique si es necesario y apruebe. El sistema registra
                                todo en auditoría para trazabilidad completa.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Beneficios Section -->
    <section id="beneficios" class="section-padding" style="background-color: var(--bg-secondary);">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="text-gradient">Beneficios para Agencias Aduaneras</h2>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="text-center">
                        <div class="dashboard-card-icon mx-auto mb-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h4>Ahorro de Tiempo</h4>
                        <p class="text-secondary">Reduzca el tiempo de clasificación en un 80%</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="text-center">
                        <div class="dashboard-card-icon mx-auto mb-3"
                            style="background: linear-gradient(135deg, var(--success-color) 0%, var(--secondary-dark) 100%);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>Mayor Precisión</h4>
                        <p class="text-secondary">95% de precisión en clasificaciones</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="text-center">
                        <div class="dashboard-card-icon mx-auto mb-3"
                            style="background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%);">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h4>Reducción de Costos</h4>
                        <p class="text-secondary">Evite multas por clasificaciones incorrectas</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="text-center">
                        <div class="dashboard-card-icon mx-auto mb-3"
                            style="background: linear-gradient(135deg, var(--info-color) 0%, #2563eb 100%);">
                            <i class="fas fa-history"></i>
                        </div>
                        <h4>Trazabilidad Total</h4>
                        <p class="text-secondary">Auditoría completa de todas las operaciones</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Flujo del Sistema Section -->
    <section class="section-padding">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="text-gradient">Flujo General del Sistema</h2>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="badge bg-primary me-3"
                                    style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                                    1</div>
                                <div>
                                    <strong>Usuario carga factura</strong> → El sistema almacena el archivo
                                </div>
                            </div>

                            <div class="d-flex align-items-center mb-3">
                                <div class="badge bg-primary me-3"
                                    style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                                    2</div>
                                <div>
                                    <strong>OCR procesa documento</strong> → Extrae texto y datos estructurados
                                </div>
                            </div>

                            <div class="d-flex align-items-center mb-3">
                                <div class="badge bg-primary me-3"
                                    style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                                    3</div>
                                <div>
                                    <strong>Auto-digitación</strong> → Formulario se llena automáticamente
                                </div>
                            </div>

                            <div class="d-flex align-items-center mb-3">
                                <div class="badge bg-primary me-3"
                                    style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                                    4</div>
                                <div>
                                    <strong>IA clasifica ítems</strong> → Sugiere códigos arancelarios
                                </div>
                            </div>

                            <div class="d-flex align-items-center mb-3">
                                <div class="badge bg-primary me-3"
                                    style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                                    5</div>
                                <div>
                                    <strong>Usuario revisa y aprueba</strong> → Puede modificar si es necesario
                                </div>
                            </div>

                            <div class="d-flex align-items-center">
                                <div class="badge bg-success me-3"
                                    style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                                    ✓</div>
                                <div>
                                    <strong>Clasificación final guardada</strong> → Con auditoría completa
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contacto Section -->
    <section id="contacto" class="section-padding" style="background-color: var(--bg-secondary);">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="text-gradient">Contáctenos</h2>
                <p class="lead text-secondary">¿Tiene preguntas? Estamos aquí para ayudarle</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body p-4">
                            <form id="contactForm">
                                <div class="form-group mb-3">
                                    <label class="form-label">Nombre Completo</label>
                                    <input type="text" class="form-control" required>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" required>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label">Organización</label>
                                    <input type="text" class="form-control">
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label">Mensaje</label>
                                    <textarea class="form-control" rows="4" required></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-paper-plane"></i> Enviar Mensaje
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Plataforma de Clasificación de Facturas Aduaneras</h5>
                    <p>Solución profesional para automatización de procesos aduaneros</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2024 Sistema de Clasificación Aduanera. Todos los derechos reservados.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/app.js"></script>
    <script>
        // Smooth scroll para enlaces internos
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Manejo de formulario de contacto
        document.getElementById('contactForm').addEventListener('submit', function (e) {
            e.preventDefault();
            app.showAlert('Mensaje enviado exitosamente. Nos pondremos en contacto pronto.', 'success');
            this.reset();
        });
    </script>
</body>

</html>