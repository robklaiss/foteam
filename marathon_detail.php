<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get marathon ID from URL
$marathon_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($marathon_id <= 0) {
    set_flash_message('ID de maratón inválido', 'danger');
    redirect('index.php');
}

// Get marathon details
$marathon = get_marathon($marathon_id);

if (!$marathon) {
    set_flash_message('Maratón no encontrado', 'danger');
    redirect('index.php');
}

// Check if marathon is public or belongs to the current user
if (!$marathon['is_public'] && (!is_logged_in() || $marathon['user_id'] != $_SESSION['user_id'])) {
    set_flash_message('No tiene permiso para ver este maratón', 'danger');
    redirect('index.php');
}

// Get marathon images
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$result = get_marathon_images($marathon_id, $page);
$images = $result['images'];
$total_pages = $result['pages'];

// Include header
include 'includes/header.php';
?>

<div class="container">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo h($marathon['name']); ?></li>
        </ol>
    </nav>

    <div class="card mb-4">
        <div class="card-body">
            <h1 class="card-title"><?php echo h($marathon['name']); ?></h1>
            <p class="card-text">
                <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($marathon['event_date'])); ?>
                <?php if (!empty($marathon['location'])): ?>
                <br><strong>Ubicación:</strong> <?php echo h($marathon['location']); ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <h2>Fotos del Maratón</h2>
    
    <?php if (empty($images)): ?>
    <div class="alert alert-info">
        No hay fotos disponibles para este maratón.
    </div>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4">
        <?php foreach ($images as $image): ?>
        <div class="col">
            <div class="card h-100">
                <div class="image-container">
                    <img src="<?php echo h($image['url']); ?>" class="card-img-top view-image" alt="Foto de Maratón" 
                         data-image-url="<?php echo h($image['url']); ?>"
                         data-numbers="<?php echo !empty($image['detected_numbers']) ? 'Números de Corredor: ' . h($image['detected_numbers']) : 'No se detectaron números'; ?>">
                </div>
                <div class="card-body">
                    <p class="card-text">
                        <?php if (!empty($image['detected_numbers'])): ?>
                        <strong>Números de Corredor:</strong> <?php echo h($image['detected_numbers']); ?>
                        <?php else: ?>
                        <em>No se detectaron números</em>
                        <?php endif; ?>
                    </p>
                    <?php if (is_logged_in()): ?>
                    <button type="button" class="btn btn-success w-100 add-to-cart" data-image-id="<?php echo $image['id']; ?>">
                        Añadir al Carrito
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <li class="page-item <?php echo ($p == $page) ? 'active' : ''; ?>">
                <a class="page-link" href="marathon_detail.php?id=<?php echo $marathon_id; ?>&page=<?php echo $p; ?>">
                    <?php echo $p; ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Image Modal -->
    <div class="modal fade image-modal" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <img src="" class="modal-image" alt="Foto de Maratón">
                </div>
                <div class="modal-footer">
                    <p class="image-numbers mb-0"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container for notifications -->
<div class="toast-container position-fixed top-0 end-0 p-3"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image modal functionality
    const viewImages = document.querySelectorAll('.view-image');
    const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
    
    viewImages.forEach(img => {
        img.addEventListener('click', function() {
            const modalImg = document.querySelector('.modal-image');
            const numbersText = document.querySelector('.image-numbers');
            
            modalImg.src = this.getAttribute('data-image-url');
            numbersText.textContent = this.getAttribute('data-numbers');
            
            imageModal.show();
        });
    });
    
    // Add to cart functionality
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    
    function createToast(message, type = 'success') {
        const toastContainer = document.querySelector('.toast-container');
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-white bg-${type} border-0`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        toastContainer.appendChild(toastEl);
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
        
        // Remove toast after it's hidden
        toastEl.addEventListener('hidden.bs.toast', function() {
            toastEl.remove();
        });
    }
    
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const imageId = this.getAttribute('data-image-id');
            
            fetch('cart_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=add&image_id=${imageId}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    createToast(data.message);
                    
                    // Update cart count in navbar
                    const cartBadge = document.querySelector('.cart-badge');
                    if (cartBadge) {
                        const currentCount = parseInt(cartBadge.textContent);
                        cartBadge.textContent = currentCount + 1;
                    } else {
                        const cartLink = document.querySelector('a[href="cart.php"]');
                        if (cartLink) {
                            const badge = document.createElement('span');
                            badge.className = 'cart-badge';
                            badge.textContent = '1';
                            cartLink.appendChild(badge);
                        }
                    }
                } else {
                    createToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                createToast('Error al añadir al carrito', 'danger');
            });
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
