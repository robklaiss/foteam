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

// Get query parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$marathon_id = isset($_GET['marathon_id']) ? $_GET['marathon_id'] : '';
$search_numbers = isset($_GET['search_numbers']) ? $_GET['search_numbers'] : '';

// Get all marathons for filter dropdown
$marathons = get_public_marathons();
if (is_logged_in()) {
    $user_marathons = get_user_marathons($_SESSION['user_id']);
    $marathons = array_merge($marathons, $user_marathons);
}

// Get images based on filters
$db = db_connect();
$per_page = 12;

// Build query
$query = "SELECT i.* FROM images i";
$where_conditions = [];
$params = [];

if (!empty($marathon_id)) {
    $where_conditions[] = "i.marathon_id = :marathon_id";
    $params[':marathon_id'] = $marathon_id;
} else {
    // If no specific marathon, show public marathons and user's own images
    if (is_logged_in()) {
        $query .= " LEFT JOIN marathons m ON i.marathon_id = m.id";
        $where_conditions[] = "(m.is_public = 1 OR i.user_id = :user_id)";
        $params[':user_id'] = $_SESSION['user_id'];
    } else {
        $query .= " LEFT JOIN marathons m ON i.marathon_id = m.id";
        $where_conditions[] = "m.is_public = 1";
    }
}

// Add number search if provided
if (!empty($search_numbers)) {
    $where_conditions[] = "i.detected_numbers LIKE :numbers";
    $params[':numbers'] = "%$search_numbers%";
}

// Add WHERE clause if we have conditions
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

// Count total images
$count_query = str_replace("SELECT i.*", "SELECT COUNT(*) as total", $query);
$count_stmt = $db->prepare($count_query);

// Bind parameters for count query
foreach ($params as $param_name => $param_value) {
    $param_type = is_int($param_value) ? SQLITE3_INTEGER : SQLITE3_TEXT;
    $count_stmt->bindValue($param_name, $param_value, $param_type);
}

$count_result = $count_stmt->execute();
$total_row = $count_result->fetchArray(SQLITE3_ASSOC);
$total = $total_row['total'];
$total_pages = ceil($total / $per_page);
$count_result->finalize();

// Get images for current page
$query .= " ORDER BY i.upload_date DESC LIMIT :limit OFFSET :offset";
$params[':limit'] = $per_page;
$params[':offset'] = ($page - 1) * $per_page;

$stmt = $db->prepare($query);

// Bind parameters for main query
foreach ($params as $param_name => $param_value) {
    $param_type = is_int($param_value) ? SQLITE3_INTEGER : SQLITE3_TEXT;
    $stmt->bindValue($param_name, $param_value, $param_type);
}

$result = $stmt->execute();

$images = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    // Convert paths to URLs - Fixed for root deployment
    $row['url'] = !empty($row['original_path']) ? '/uploads/' . basename($row['original_path']) : '';
    $row['thumbnail_url'] = !empty($row['thumbnail_path']) ? '/uploads/thumbnails/' . basename($row['thumbnail_path']) : '';
    $row['watermarked_url'] = !empty($row['watermarked_path']) ? '/uploads/watermarked/' . basename($row['watermarked_path']) : '';
    
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Galería de Fotos</h2>
        <?php if (is_logged_in()): ?>
        <form action="reset_gallery.php" method="POST" onsubmit="return confirm('¿Estás seguro de que deseas reiniciar tu galería? Esto eliminará todas tus fotos.');">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-trash"></i> Reiniciar Galería
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Search and Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="gallery.php" class="row g-3">
                <div class="col-md-4">
                    <label for="marathon_id" class="form-label">Maratón</label>
                    <select name="marathon_id" id="marathon_id" class="form-select">
                        <option value="">Todos los Maratones</option>
                        <?php foreach ($marathons as $marathon): ?>
                        <option value="<?php echo $marathon['id']; ?>" <?php echo ($marathon_id == $marathon['id']) ? 'selected' : ''; ?>>
                            <?php echo h($marathon['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search_numbers" class="form-label">Buscar Números de Corredor</label>
                    <input type="text" class="form-control" id="search_numbers" name="search_numbers"
                        value="<?php echo h($search_numbers); ?>" placeholder="Ingrese números (ej., 123, 456)">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Photo Grid -->
    <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4">
        <?php foreach ($images as $image): ?>
        <div class="col">
            <div class="card h-100">
                <div class="image-container">
                    <img src="<?php echo h($image['thumbnail_url']); ?>" class="card-img-top view-image" alt="Foto de Maratón" 
                         data-image-url="<?php echo h($image['watermarked_url']); ?>"
                         data-numbers="<?php echo !empty($image['detected_numbers']) ? 'Números de Corredor: ' . h(implode(', ', $image['detected_numbers'])) : 'No se detectaron números'; ?>">
                </div>
                <div class="card-body">
                    <p class="card-text">
                        <?php if (!empty($image['detected_numbers'])): ?>
                        <strong>Números de Corredor:</strong> <?php echo h(implode(', ', $image['detected_numbers'])); ?>
                        <?php else: ?>
                        <em>No se detectaron números</em>
                        <?php endif; ?>
                    </p>
                    <?php if (is_logged_in()): ?>
                    <button type="button" class="btn btn-success w-100 add-to-cart" data-image-id="<?php echo $image['id']; ?>">
                        <i class="bi bi-cart-plus"></i> Añadir al Carrito
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($images)): ?>
    <div class="alert alert-info mt-4">
        No se encontraron imágenes con los criterios de búsqueda actuales.
    </div>
    <?php endif; ?>

    <!-- Image Modal -->
    <div class="modal fade image-modal" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="lightbox-container">
                        <img src="" class="modal-image" alt="Foto de Maratón">
                    </div>
                </div>
                <div class="modal-footer">
                    <p class="image-numbers mb-0"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <li class="page-item <?php echo ($p == $page) ? 'active' : ''; ?>">
                <a class="page-link" href="gallery.php?page=<?php echo $p; ?>&marathon_id=<?php echo h($marathon_id); ?>&search_numbers=<?php echo h($search_numbers); ?>">
                    <?php echo $p; ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- Toast Container for notifications -->
<div class="toast-container position-fixed end-0 p-3" style="top: 80px; z-index: 1090;"></div>

<script>
// Store CSRF token for AJAX requests
const csrfToken = '<?php echo $csrf_token; ?>';

document.addEventListener('DOMContentLoaded', function() {
    // Image modal functionality
    const viewImages = document.querySelectorAll('.view-image');
    const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
    
    viewImages.forEach(img => {
        img.addEventListener('click', function() {
            const modalImg = document.querySelector('.modal-image');
            const numbersText = document.querySelector('.image-numbers');
            const imageUrl = this.getAttribute('data-image-url');
            
            // Convert server path to URL if needed
            let displayUrl = imageUrl;
            if (imageUrl.startsWith('/')) {
                // It's already a URL path
                displayUrl = imageUrl;
            } else if (imageUrl.includes('/uploads/')) {
                // It's a server path, convert to URL
                displayUrl = imageUrl.substring(imageUrl.indexOf('/uploads/'));
            }
            
            modalImg.src = displayUrl;
            numbersText.textContent = this.getAttribute('data-numbers');
            
            // Reset any previous styles
            modalImg.style.width = '';
            modalImg.style.height = '';
            
            // Set up image load handler to determine orientation
            modalImg.onload = function() {
                const isLandscape = this.naturalWidth > this.naturalHeight;
                if (isLandscape) {
                    this.style.width = '600px';
                    this.style.height = 'auto';
                } else {
                    this.style.height = '600px';
                    this.style.width = 'auto';
                }
            };
            
            imageModal.show();
        });
    });
    
    // Add to cart functionality
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    function createToast(message, type = 'success') {
        const toastContainer = document.querySelector('.toast-container');
        const toastId = 'toast-' + Date.now();
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.role = 'alert';
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.id = toastId;
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Remove toast from DOM after it's hidden
        toast.addEventListener('hidden.bs.toast', function() {
            toast.remove();
        });
    }
    
    // Function to update cart badge
    function updateCartBadge(count) {
        console.log('Updating cart badge with count:', count);
        // Find the cart link by text content since the href might contain BASE_URL
        const cartLinks = Array.from(document.querySelectorAll('a'));
        const cartLink = cartLinks.find(link => 
            link.textContent.includes('Carrito') || 
            link.href.endsWith('/cart.php') || 
            link.href.endsWith('cart.php')
        );
        
        if (!cartLink) {
            console.error('Cart link not found!');
            return;
        }
        
        console.log('Found cart link:', cartLink);
        
        // Remove existing badge if it exists
        const existingBadge = cartLink.querySelector('.cart-badge');
        if (existingBadge) {
            existingBadge.remove();
        }
        
        // Only add badge if there are items in cart
        if (count > 0) {
            console.log('Adding badge with count:', count);
            const badge = document.createElement('span');
            badge.className = 'cart-badge badge bg-danger';
            badge.textContent = count;
            
            // Make sure the cart link has position relative for absolute positioning of badge
            cartLink.style.position = 'relative';
            
            // Style the badge
            badge.style.position = 'absolute';
            badge.style.top = '10px';  // Position 10px from top
            badge.style.right = '0';
            badge.style.transform = 'translate(50%, 0)';
            badge.style.borderRadius = '50%';
            badge.style.padding = '0.25em 0.5em';
            badge.style.fontSize = '0.75rem';
            badge.style.minWidth = '20px';
            badge.style.textAlign = 'center';
            badge.style.lineHeight = '1';
            
            // Add the badge to the cart link
            cartLink.appendChild(badge);
            
            console.log('Badge added to cart link:', cartLink);
        } else {
            console.log('No items in cart, not showing badge');
        }
    }
    
    // Initialize cart badge on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Get initial cart count from server
        fetch('cart_actions.php?action=get_count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateCartBadge(data.cart_count);
                }
            });
    });
    
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const imageId = this.getAttribute('data-image-id');
            const currentCsrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('image_id', imageId);
            formData.append('csrf_token', currentCsrfToken);
            
            // Disable button to prevent multiple clicks
            const button = this;
            const originalText = this.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Añadiendo...';
            
            fetch('cart_actions.php', {
                method: 'POST',
                body: new URLSearchParams(formData).toString(),
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                console.log('Add to cart response:', data);
                if (data.success) {
                    createToast(data.message);
                    // Update cart badge with the new count
                    if (typeof data.cart_count !== 'undefined') {
                        console.log('Updating cart count from response:', data.cart_count);
                        updateCartBadge(data.cart_count);
                    } else {
                        console.log('No cart_count in response, fetching count...');
                        // If cart_count is not in response, fetch it
                        fetch('cart_actions.php?action=get_count')
                            .then(response => response.json())
                            .then(countData => {
                                console.log('Fetched cart count:', countData);
                                if (countData.success) {
                                    updateCartBadge(countData.cart_count);
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching cart count:', error);
                            });
                    }
                } else {
                    createToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                createToast('Error al añadir al carrito', 'danger');
                // Re-enable button on success or error
                button.disabled = false;
                button.innerHTML = originalText;
            });
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
