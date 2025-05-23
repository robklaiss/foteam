<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Redirect to login if not logged in or not admin
if (!is_logged_in() || !is_admin()) {
    $_SESSION['redirect_after_login'] = '/admin/edit_marathon.php';
    set_flash_message('Debe iniciar sesión como administrador para acceder a esta sección', 'warning');
    redirect('../login.php');
}

// Get marathon ID from query string
$marathon_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($marathon_id <= 0) {
    set_flash_message('Maratón no válido', 'danger');
    redirect('manage_marathons.php');
}

// Get marathon details
$db = db_connect();
$stmt = $db->prepare("
    SELECT * FROM marathons
    WHERE id = :marathon_id
");
$stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
$result = $stmt->execute();

$marathon = $result->fetchArray(SQLITE3_ASSOC);
$result->finalize();

if (!$marathon) {
    $db->close();
    set_flash_message('Maratón no encontrado', 'danger');
    redirect('manage_marathons.php');
}

// Get photographers assigned to this marathon
$stmt = $db->prepare("
    SELECT u.id, u.username, u.email
    FROM users u
    JOIN photographer_marathons pm ON u.id = pm.user_id
    WHERE pm.marathon_id = :marathon_id
    ORDER BY u.username ASC
");
$stmt->bindValue(':marathon_id', $marathon_id, SQLITE3_INTEGER);
$result = $stmt->execute();

$assigned_photographers = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $assigned_photographers[] = $row;
}
$result->finalize();

// Get all photographers for assignment (using username pattern matching for now)
$stmt = $db->prepare("
    SELECT id, username, email
    FROM users
    WHERE username LIKE '%fotografo%' OR username LIKE '%photographer%'
    ORDER BY username ASC
");
$result = $stmt->execute();

$all_photographers = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    // Check if already assigned
    $already_assigned = false;
    foreach ($assigned_photographers as $assigned) {
        if ($assigned['id'] == $row['id']) {
            $already_assigned = true;
            break;
        }
    }
    
    if (!$already_assigned) {
        $all_photographers[] = $row;
    }
}
$result->finalize();
$db->close();

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Editar Maratón</h4>
                </div>
                <div class="card-body">
                    <form action="marathon_actions.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="update_marathon">
                        <input type="hidden" name="marathon_id" value="<?php echo $marathon['id']; ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre del Maratón</label>
                            <input type="text" class="form-control" id="name" name="name" required value="<?php echo h($marathon['name']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="event_date" class="form-label">Fecha del Evento</label>
                            <input type="date" class="form-control" id="event_date" name="event_date" required value="<?php echo h($marathon['event_date']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="location" class="form-label">Ubicación</label>
                            <input type="text" class="form-control" id="location" name="location" value="<?php echo h($marathon['location']); ?>">
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_public" name="is_public" <?php echo $marathon['is_public'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_public">Maratón Público</label>
                            <div class="form-text">Si está marcado, el maratón será visible para todos los usuarios.</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Actualizar Maratón</button>
                            <a href="manage_marathons.php" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Assigned Photographers -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0">Fotógrafos Asignados</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($assigned_photographers)): ?>
                        <div class="alert alert-info">
                            No hay fotógrafos asignados a este maratón.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Usuario</th>
                                        <th>Email</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assigned_photographers as $photographer): ?>
                                        <tr>
                                            <td><?php echo $photographer['id']; ?></td>
                                            <td><?php echo h($photographer['username']); ?></td>
                                            <td><?php echo h($photographer['email']); ?></td>
                                            <td>
                                                <form action="marathon_photographer_actions.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="remove_photographer">
                                                    <input type="hidden" name="marathon_id" value="<?php echo $marathon['id']; ?>">
                                                    <input type="hidden" name="photographer_id" value="<?php echo $photographer['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro de que desea eliminar a este fotógrafo del maratón?')">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Assign New Photographer -->
                    <?php if (!empty($all_photographers)): ?>
                        <hr>
                        <h5>Asignar Nuevo Fotógrafo</h5>
                        <form action="marathon_photographer_actions.php" method="POST" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="add_photographer">
                            <input type="hidden" name="marathon_id" value="<?php echo $marathon['id']; ?>">
                            
                            <div class="col-md-8">
                                <select class="form-select" name="photographer_id" required>
                                    <option value="">Seleccione un fotógrafo...</option>
                                    <?php foreach ($all_photographers as $photographer): ?>
                                        <option value="<?php echo $photographer['id']; ?>">
                                            <?php echo h($photographer['username']); ?> (<?php echo h($photographer['email']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">Asignar Fotógrafo</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Danger Zone -->
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0">Zona de Peligro</h4>
                </div>
                <div class="card-body">
                    <p class="card-text">Las siguientes acciones son peligrosas y no se pueden deshacer. Tenga cuidado.</p>
                    
                    <form action="marathon_actions.php" method="POST" onsubmit="return confirm('¿Está seguro de que desea eliminar este maratón? Esta acción no se puede deshacer.')">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="delete_marathon">
                        <input type="hidden" name="marathon_id" value="<?php echo $marathon['id']; ?>">
                        
                        <button type="submit" class="btn btn-danger">Eliminar Maratón</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
