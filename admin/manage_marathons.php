<?php\n// Include bootstrap first to configure environment and settings\nrequire_once dirname(__DIR__) . '/includes/bootstrap.php';\n\n// Include config and functions\nrequire_once dirname(__DIR__) . '/includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Redirect to login if not logged in or not admin
if (!is_logged_in() || !is_admin()) {
    $_SESSION['redirect_after_login'] = '/admin/manage_marathons.php';
    set_flash_message('Debe iniciar sesión como administrador para acceder a esta sección', 'warning');
    redirect('../login.php');
}

// Get all marathons
$db = db_connect();
$stmt = $db->prepare("
    SELECT m.*, u.username as creator_name,
    (SELECT COUNT(*) FROM images WHERE marathon_id = m.id) as image_count,
    (SELECT COUNT(DISTINCT user_id) FROM photographer_marathons WHERE marathon_id = m.id) as photographer_count
    FROM marathons m
    LEFT JOIN users u ON m.creator_id = u.id
    ORDER BY m.event_date DESC
");
$result = $stmt->execute();

$marathons = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $marathons[] = $row;
}

$result->finalize();
$db->close();

// Include header
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="container">
    <div class="jumbotron bg-light p-5 rounded">
        <h1 class="display-4">Gestionar Maratones</h1>
        <p class="lead">Administre los maratones y asigne fotógrafos</p>
        <hr class="my-4">
        
        <!-- Actions -->
        <div class="d-flex justify-content-between mb-4">
            <a href="index.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Volver al Panel
            </a>
            <a href="create_marathon.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Crear Nuevo Maratón
            </a>
        </div>
        
        <!-- Marathons List -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($marathons)): ?>
                    <div class="alert alert-info">
                        No hay maratones registrados. Cree un nuevo maratón para comenzar.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Fecha</th>
                                    <th>Ubicación</th>
                                    <th>Estado</th>
                                    <th>Fotógrafos</th>
                                    <th>Fotos</th>
                                    <th>Creado por</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($marathons as $marathon): ?>
                                    <tr>
                                        <td><?php echo $marathon['id']; ?></td>
                                        <td><?php echo h($marathon['name']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($marathon['event_date'])); ?></td>
                                        <td><?php echo h($marathon['location']); ?></td>
                                        <td>
                                            <?php if ($marathon['is_public']): ?>
                                                <span class="badge bg-success">Público</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Privado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $marathon['photographer_count']; ?></td>
                                        <td><?php echo $marathon['image_count']; ?></td>
                                        <td><?php echo h($marathon['creator_name']); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="edit_marathon.php?id=<?php echo $marathon['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    Editar
                                                </a>
                                                <a href="view_marathon.php?id=<?php echo $marathon['id']; ?>" class="btn btn-sm btn-outline-info">
                                                    Ver Detalles
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#toggleStatusModal" data-marathon-id="<?php echo $marathon['id']; ?>" data-marathon-name="<?php echo h($marathon['name']); ?>" data-is-public="<?php echo $marathon['is_public']; ?>">
                                                    <?php echo $marathon['is_public'] ? 'Hacer Privado' : 'Hacer Público'; ?>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Toggle Status Modal -->
<div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-labelledby="toggleStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="toggleStatusModalLabel">Cambiar Estado del Maratón</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="marathon_actions.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="marathon_id" id="toggle_marathon_id">
                    <input type="hidden" name="is_public" id="toggle_is_public">
                    
                    <p id="toggle_confirmation_message"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for Modals -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle Status Modal
    const toggleStatusModal = document.getElementById('toggleStatusModal');
    if (toggleStatusModal) {
        toggleStatusModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const marathonId = button.getAttribute('data-marathon-id');
            const marathonName = button.getAttribute('data-marathon-name');
            const isPublic = button.getAttribute('data-is-public') === '1';
            
            document.getElementById('toggle_marathon_id').value = marathonId;
            document.getElementById('toggle_is_public').value = isPublic ? '0' : '1';
            
            const message = isPublic 
                ? `¿Está seguro de que desea hacer privado el maratón "${marathonName}"? Los usuarios no podrán ver este maratón en la página principal.` 
                : `¿Está seguro de que desea hacer público el maratón "${marathonName}"? Los usuarios podrán ver este maratón en la página principal.`;
            
            document.getElementById('toggle_confirmation_message').textContent = message;
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
