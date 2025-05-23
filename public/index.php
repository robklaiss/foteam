<?php
// Include bootstrap first to configure environment and settings
if (!defined('BOOTSTRAP_LOADED')) {
    require_once __DIR__ . '/../includes/bootstrap.php';
}

// Include config
if (!defined('CONFIG_LOADED')) {
    require_once __DIR__ . '/../includes/config.php';
}

// Include functions
require_once __DIR__ . '/../includes/functions.php';

// Get recent public marathons
$db = db_connect();

$stmt = $db->prepare("
    SELECT m.*, 
           (SELECT COUNT(*) FROM images WHERE marathon_id = m.id) as image_count
    FROM marathons m
    WHERE m.is_public = 1 
    ORDER BY m.event_date DESC 
    LIMIT 6
");
$result = $stmt->execute();

$recent_marathons = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $recent_marathons[] = $row;
}

$result->finalize();
$db->close();

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="jumbotron bg-light p-5 rounded">
        <h1 class="display-4">Encuentra tus Fotos de Maratón</h1>
        <p class="lead">Busca, visualiza y compra fotos de alta calidad de tus eventos deportivos favoritos.</p>
        <hr class="my-4">
        
        <!-- Marathon Search Box -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Buscar Fotos</h5>
                <form action="search.php" method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label for="marathon_id" class="form-label">Selecciona un Maratón</label>
                        <select name="marathon_id" id="marathon_id" class="form-select" required>
                            <option value="">Seleccione un maratón...</option>
                            <?php
                            $marathons = get_public_marathons();
                            foreach ($marathons as $marathon) {
                                echo '<option value="' . $marathon['id'] . '">' . h($marathon['name']) . ' (' . date('d/m/Y', strtotime($marathon['event_date'])) . ')</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="runner_number" class="form-label">Número de Corredor (opcional)</label>
                        <input type="text" class="form-control" id="runner_number" name="runner_number" placeholder="Ej: 123">
                        <div class="form-text">Deja en blanco para ver todas las fotos</div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Buscar</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Recent Marathons -->
        <h2 class="mb-4">Maratones Recientes</h2>
        <div class="row">
            <?php
            if (!empty($recent_marathons)):
                foreach ($recent_marathons as $marathon):
            ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo h($marathon['name']); ?></h5>
                        <p class="card-text">
                            <i class="bi bi-calendar-event"></i> <?php echo date('d/m/Y', strtotime($marathon['event_date'])); ?><br>
                            <i class="bi bi-geo-alt"></i> <?php echo h($marathon['location']); ?><br>
                            <i class="bi bi-camera"></i> <?php echo $marathon['image_count']; ?> fotos disponibles
                        </p>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="search.php?marathon_id=<?php echo $marathon['id']; ?>" class="btn btn-primary w-100">Ver Fotos</a>
                    </div>
                </div>
            </div>
            <?php
                endforeach;
            else:
            ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No hay maratones públicos disponibles actualmente.
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="mt-4 text-center">
            <?php if (!is_logged_in()): ?>
            <p class="mb-3">¿Eres fotógrafo o administrador? <a href="login.php">Iniciar Sesión</a></p>
            <?php endif; ?>
            
            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title">¿Cómo funciona?</h5>
                    <div class="row">
                        <div class="col-md-4 text-center mb-3 mb-md-0">
                            <div class="p-3">
                                <i class="bi bi-search fs-1 text-primary"></i>
                                <h5 class="mt-3">1. Busca tus fotos</h5>
                                <p>Selecciona un maratón e ingresa tu número de corredor</p>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-3 mb-md-0">
                            <div class="p-3">
                                <i class="bi bi-cart-plus fs-1 text-primary"></i>
                                <h5 class="mt-3">2. Añade al carrito</h5>
                                <p>Selecciona las fotos que quieras comprar</p>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="p-3">
                                <i class="bi bi-download fs-1 text-primary"></i>
                                <h5 class="mt-3">3. Descarga</h5>
                                <p>Completa el pago y descarga tus fotos en alta resolución</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
