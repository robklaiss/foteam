    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white mt-5 py-3">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> FoTeam - Fotografía de Maratones</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- CSRF Token JS -->
    <script>
        // Make CSRF token available for AJAX requests
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    </script>
</body>
</html>
