<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/photographer_functions.php';

// Redirect to login if not logged in or not a photographer
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = '/fotografos/marathon_photos.php';
    set_flash_message('Debe iniciar sesión como fotógrafo para acceder a esta sección', 'warning');
    redirect('../login.php');
}

// Check if user is a photographer
if (!is_photographer()) {
    set_flash_message('No tiene permisos para acceder a esta sección', 'danger');
    redirect('../index.php');
}

// Get marathon ID from query string
$marathon_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($marathon_id <= 0) {
    set_flash_message('Maratón no válido', 'danger');
    redirect('/fotografos/');
}

// Check if marathon is assigned to this photographer
$photographer_marathons = get_photographer_marathons($_SESSION['user_id']);
$marathon_assigned = false;
$current_marathon = null;

foreach ($photographer_marathons as $marathon) {
    if ($marathon['id'] == $marathon_id) {
        $marathon_assigned = true;
        $current_marathon = $marathon;
        break;
    }
}

if (!$marathon_assigned) {
    set_flash_message('No tiene permiso para ver las fotos de este maratón', 'danger');
    redirect('/fotografos/');
}

// Get page number from query string
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Get images for this marathon uploaded by this photographer
$db = db_connect();
$per_page = 12;

// Count total images
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM images 
    WHERE marathon_id = :marathon_id AND user_id = :user_id
");
$stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
$stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$total = $result->fetchArray(SQLITE3_ASSOC)['total'];
$result->finalize();

$total_pages = ceil($total / $per_page);
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

// Get images for current page
$stmt = $db->prepare("
    SELECT * FROM images 
    WHERE marathon_id = :marathon_id AND user_id = :user_id
    ORDER BY upload_date DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
$stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
$stmt->bindValue(':limit', $per_page, SQLITE3_INTEGER);
$stmt->bindValue(':offset', ($page - 1) * $per_page, SQLITE3_INTEGER);
$result = $stmt->execute();

$images = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    // Convert paths to URLs
    $row['url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $row['original_path']);
    $row['thumbnail_url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $row['thumbnail_path']);
    
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
        <h1 class="display-4"><?php echo h($current_marathon['name']); ?></h1>
        <p class="lead">
            <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($current_marathon['event_date'])); ?> |
            <strong>Ubicación:</strong> <?php echo h($current_marathon['location']); ?> |
            <strong>Estado:</strong> <?php echo $current_marathon['is_public'] ? 'Público' : 'Privado'; ?>
        </p>
        <hr class="my-4">
        
        <!-- Actions -->
        <div class="d-flex justify-content-between mb-4">
            <a href="/fotografos/" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <a href="upload.php?marathon_id=<?php echo $marathon_id; ?>" class="btn btn-primary">
                <i class="bi bi-cloud-upload"></i> Subir Más Fotos
            </a>
        </div>
        
        <!-- Photos Stats -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <h3><?php echo $total; ?></h3>
                        <p>Fotos Subidas</p>
                    </div>
                    <div class="col-md-4">
                        <?php
                        // Count detected numbers
                        $detected_count = 0;
                        foreach ($images as $image) {
                            $detected_count += count($image['detected_numbers']);
                        }
                        ?>
                        <h3><?php echo $detected_count; ?></h3>
                        <p>Números Detectados</p>
                    </div>
                    <div class="col-md-4">
                        <?php
                        // Count sold photos
                        $db = db_connect();
                        $stmt = $db->prepare("
                            SELECT COUNT(DISTINCT oi.image_id) as count 
                            FROM order_items oi
                            JOIN images i ON oi.image_id = i.id
                            JOIN orders o ON oi.order_id = o.id
                            WHERE i.user_id = :user_id AND i.marathon_id = :marathon_id AND o.status = 'completed'
                        ");
                        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                        $stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
                        $result = $stmt->execute();
                        $sold_count = $result->fetchArray(SQLITE3_ASSOC)['count'];
                        $result->finalize();
                        $db->close();
                        ?>
                        <h3><?php echo $sold_count; ?></h3>
                        <p>Fotos Vendidas</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Photos Grid -->
        <?php if (empty($images)): ?>
            <div class="alert alert-info">
                No hay fotos para este maratón. <a href="upload.php?marathon_id=<?php echo $marathon_id; ?>">Subir fotos</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($images as $image): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="image-container">
                                <img src="<?php echo $image['thumbnail_url']; ?>" class="card-img-top" alt="Foto de maratón">
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo h($image['filename']); ?></h5>
                                <?php if (!empty($image['detected_numbers'])): ?>
                                    <p class="card-text">
                                        <strong>Números detectados:</strong> 
                                        <?php echo implode(', ', $image['detected_numbers']); ?>
                                    </p>
                                <?php else: ?>
                                    <p class="card-text text-muted">No se detectaron números</p>
                                <?php endif; ?>
                                <p class="card-text">
                                    <small class="text-muted">
                                        Subida el <?php echo date('d/m/Y H:i', strtotime($image['upload_date'])); ?>
                                    </small>
                                </p>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="<?php echo $image['url']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">Ver Original</a>
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
                            <a class="page-link" href="?id=<?php echo $marathon_id; ?>&page=<?php echo $page-1; ?>" aria-label="Anterior">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?id=' . $marathon_id . '&page=1">1</a></li>';
                            if ($start_page > 2) {
                                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="?id=' . $marathon_id . '&page=' . $i . '">' . $i . '</a></li>';
                        }
                        
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?id=' . $marathon_id . '&page=' . $total_pages . '">' . $total_pages . '</a></li>';
                        }
                        ?>
                        
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?id=<?php echo $marathon_id; ?>&page=<?php echo $page+1; ?>" aria-label="Siguiente">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
