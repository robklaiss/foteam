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

// Get search parameters
$marathon_id = isset($_GET['marathon_id']) ? (int)$_GET['marathon_id'] : 0;
$runner_number = isset($_GET['runner_number']) ? trim($_GET['runner_number']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Validate marathon ID
if ($marathon_id <= 0) {
    set_flash_message('Debe seleccionar un maratón', 'warning');
    redirect('index.php');
}

// Get marathon details
$db = db_connect();
$stmt = $db->prepare("SELECT * FROM marathons WHERE id = :marathon_id");
$stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
$result = $stmt->execute();
$marathon = $result->fetchArray(SQLITE3_ASSOC);
$result->finalize();

if (!$marathon) {
    $db->close();
    set_flash_message('Maratón no encontrado', 'danger');
    redirect('index.php');
}

// Check if marathon is public or user is logged in
if (!$marathon['is_public'] && (!is_logged_in() || !is_admin())) {
    $db->close();
    set_flash_message('Este maratón no está disponible públicamente', 'warning');
    redirect('index.php');
}

// Search for images
$per_page = 12;
$params = [];
$where_clauses = ["marathon_id = :marathon_id"];
$params[':marathon_id'] = $marathon_id;

if (!empty($runner_number)) {
    $where_clauses[] = "detected_numbers LIKE :runner_number";
    $params[':runner_number'] = '%' . $runner_number . '%';
}

$where_clause = implode(' AND ', $where_clauses);

// Count total images matching search
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM images 
    WHERE $where_clause
");

foreach ($params as $param => $value) {
    $stmt->bindValue($param, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
}

$result = $stmt->execute();
$total = $result->fetchArray(SQLITE3_ASSOC)['total'];
$result->finalize();

$total_pages = ceil($total / $per_page);
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

// Get images for current page
$stmt = $db->prepare("
    SELECT i.*, u.username as photographer_name
    FROM images i
    JOIN users u ON i.user_id = u.id
    WHERE $where_clause
    ORDER BY i.upload_date DESC
    LIMIT :limit OFFSET :offset
");

foreach ($params as $param => $value) {
    $stmt->bindValue($param, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
}

$stmt->bindValue(':limit', $per_page, SQLITE3_INTEGER);
$stmt->bindValue(':offset', ($page - 1) * $per_page, SQLITE3_INTEGER);
$result = $stmt->execute();

$images = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    // Convert paths to URLs
    $row['url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $row['original_path']);
    $row['thumbnail_url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $row['thumbnail_path']);
    $row['watermarked_url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $row['watermarked_path'] ?? $row['thumbnail_path']);
    
    // Parse detected numbers
    if (!empty($row['detected_numbers'])) {
        $row['detected_numbers'] = explode(',', $row['detected_numbers']);
    } else {
        $row['detected_numbers'] = [];
    }
    
    $images[] = $row;
}

$result->finalize();
$db->close();

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="jumbotron bg-light p-5 rounded">
        <h1 class="display-4"><?php echo h($marathon['name']); ?></h1>
        <p class="lead">
            <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($marathon['event_date'])); ?> |
            <strong>Ubicación:</strong> <?php echo h($marathon['location']); ?>
        </p>
        <hr class="my-4">
        
        <!-- Search Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="search.php" method="GET" class="row g-3">
                    <input type="hidden" name="marathon_id" value="<?php echo $marathon_id; ?>">
                    
                    <div class="col-md-8">
                        <label for="runner_number" class="form-label">Número de Corredor</label>
                        <input type="text" class="form-control" id="runner_number" name="runner_number" placeholder="Ingrese el número de corredor" value="<?php echo h($runner_number); ?>">
                    </div>
                    
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Buscar Fotos</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Results -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <?php if (empty($runner_number)): ?>
                        Todas las fotos
                    <?php else: ?>
                        Fotos para el corredor #<?php echo h($runner_number); ?>
                    <?php endif; ?>
                </h5>
                <span class="badge bg-primary"><?php echo $total; ?> fotos encontradas</span>
            </div>
            <div class="card-body">
                <?php if (empty($images)): ?>
                    <div class="alert alert-info">
                        <?php if (empty($runner_number)): ?>
                            No hay fotos disponibles para este maratón.
                        <?php else: ?>
                            No se encontraron fotos para el corredor #<?php echo h($runner_number); ?>.
                            <a href="search.php?marathon_id=<?php echo $marathon_id; ?>">Ver todas las fotos</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($images as $image): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <div class="image-container position-relative">
                                        <img src="<?php echo $image['watermarked_url']; ?>" class="card-img-top" alt="Foto de maratón">
                                        <div class="position-absolute bottom-0 end-0 p-2">
                                            <span class="badge bg-primary"><?php echo format_money(PHOTO_PRICE); ?></span>
                                        </div>
                                        <?php if (!empty($image['detected_numbers'])): ?>
                                            <div class="position-absolute top-0 end-0 p-2">
                                                <span class="badge bg-success">
                                                    #<?php echo implode(', #', $image['detected_numbers']); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo h($image['filename']); ?></h5>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                Fotógrafo: <?php echo h($image['photographer_name']); ?><br>
                                                Maratón: <?php echo h($marathon['name']); ?><br>
                                                Fecha: <?php echo date('d/m/Y', strtotime($marathon['event_date'])); ?>
                                            </small>
                                        </p>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <a href="cart.php?action=add&image_id=<?php echo $image['id']; ?>" class="btn btn-success w-100">
                                            <i class="bi bi-cart-plus"></i> Añadir al Carrito
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Navegación de páginas">
                            <ul class="pagination justify-content-center mt-4">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?marathon_id=<?php echo $marathon_id; ?>&runner_number=<?php echo urlencode($runner_number); ?>&page=<?php echo $page-1; ?>" aria-label="Anterior">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?marathon_id=' . $marathon_id . '&runner_number=' . urlencode($runner_number) . '&page=1">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                    }
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="?marathon_id=' . $marathon_id . '&runner_number=' . urlencode($runner_number) . '&page=' . $i . '">' . $i . '</a></li>';
                                }
                                
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?marathon_id=' . $marathon_id . '&runner_number=' . urlencode($runner_number) . '&page=' . $total_pages . '">' . $total_pages . '</a></li>';
                                }
                                ?>
                                
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?marathon_id=<?php echo $marathon_id; ?>&runner_number=<?php echo urlencode($runner_number); ?>&page=<?php echo $page+1; ?>" aria-label="Siguiente">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
