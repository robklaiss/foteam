<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Redirect to login if not logged in
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = '/fotografos/';
    set_flash_message('Debe iniciar sesión como fotógrafo para acceder a esta sección', 'warning');
    redirect('../login.php');
}

// Check if user is a photographer
$db = db_connect();
$stmt = $db->prepare("SELECT is_photographer FROM users WHERE id = :user_id");
$stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);
$result->finalize();
$db->close();

// Redirect if not a photographer
if (!isset($user['is_photographer']) || $user['is_photographer'] != 1) {
    set_flash_message('No tiene permisos para acceder a esta sección', 'danger');
    redirect('../index.php');
}

// Get photographer's marathons
$marathons = get_user_marathons($_SESSION['user_id']);

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="jumbotron bg-light p-5 rounded">
        <h1 class="display-4">Área de Fotógrafos</h1>
        <p class="lead">Sube y administra tus fotografías de maratones</p>
        <hr class="my-4">
        
        <!-- Upload Photos Section -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Subir Nuevas Fotos</h5>
                <p>Selecciona un maratón y sube tus fotografías. Las imágenes serán procesadas para detectar números de corredores.</p>
                <a href="upload.php" class="btn btn-primary">Subir Fotos</a>
            </div>
        </div>
        
        <!-- Marathons Section -->
        <h2 class="mb-4">Tus Maratones</h2>
        <div class="row">
            <?php if (!empty($marathons)): ?>
                <?php foreach ($marathons as $marathon): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo h($marathon['name']); ?></h5>
                                <p class="card-text">
                                    <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($marathon['event_date'])); ?><br>
                                    <strong>Ubicación:</strong> <?php echo h($marathon['location']); ?><br>
                                    <strong>Estado:</strong> <?php echo $marathon['is_public'] ? 'Público' : 'Privado'; ?>
                                </p>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="marathon_photos.php?id=<?php echo $marathon['id']; ?>" class="btn btn-primary">Ver Fotos</a>
                                <a href="upload.php?marathon_id=<?php echo $marathon['id']; ?>" class="btn btn-outline-primary">Subir Fotos</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        No tienes maratones asignados. Contacta al administrador para que te asigne maratones.
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Statistics Section -->
        <h2 class="mb-4 mt-5">Estadísticas</h2>
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo count_user_images($_SESSION['user_id']); ?></h3>
                        <p class="card-text">Fotos Subidas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo count_user_sold_images($_SESSION['user_id']); ?></h3>
                        <p class="card-text">Fotos Vendidas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo count_user_marathons($_SESSION['user_id']); ?></h3>
                        <p class="card-text">Maratones Asignados</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
