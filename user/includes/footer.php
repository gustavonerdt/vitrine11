<?php
// user/includes/footer.php
?>
            </div>
        </main>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- User Footer -->
    <footer class="user-footer">
        <div class="footer-content">
            <p>Desenvolvido com 🧡 por Gustavo A. Félix</p>
                <p class="footer-version">Vitrine Independente</p>
        </div>
    </footer>

    <!-- Activation Banner Modal -->
    <?php if (isset($activationBanner) && $activationBanner): ?>
    <div id="activationBannerModal" class="banner-modal-overlay">
        <div class="banner-modal-container">
            <button class="banner-modal-close" onclick="closeActivationBannerModal()" aria-label="Fechar">
                <i class="fas fa-times"></i>
            </button>
            <div class="banner-modal-content">
                <?php if (!empty($activationBanner['link_url'])): ?>
                <a href="<?php echo htmlspecialchars($activationBanner['link_url']); ?>" target="_blank" class="banner-modal-link">
                <?php endif; ?>
                <img src="<?php echo htmlspecialchars($activationBanner['image_url']); ?>" 
                     alt="Ativar Escritório" 
                     class="banner-modal-image"
                     style="<?php 
                        if (!empty($activationBanner['width'])) echo 'width: ' . htmlspecialchars($activationBanner['width']) . ';';
                        if (!empty($activationBanner['height'])) echo 'height: ' . htmlspecialchars($activationBanner['height']) . ';';
                        if (!empty($activationBanner['background_color'])) echo 'background-color: ' . htmlspecialchars($activationBanner['background_color']) . ';';
                     ?>">
                <?php if (!empty($activationBanner['link_url'])): ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    const sidebar = document.querySelector('.user-sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const menuBtn = document.querySelector('.mobile-menu-btn');
    
    function toggleBrandSubmenu() {
        const submenu = document.getElementById('brandSubmenu');
        const parent = document.getElementById('productsSubmenu');
        if (submenu && parent) {
            parent.classList.toggle('active');
            if (parent.classList.contains('active')) {
                submenu.style.maxHeight = '500px';
            } else {
                submenu.style.maxHeight = '0';
            }
        }
    }
    
    // Inicializar submenu fechado
    document.addEventListener('DOMContentLoaded', function() {
        const submenu = document.getElementById('brandSubmenu');
        if (submenu) {
            submenu.style.maxHeight = '0';
        }
    });
    
    function toggleSidebar() {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
        document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
    }

    // Close sidebar when clicking overlay
    overlay.addEventListener('click', () => {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    });

    // Close sidebar when clicking a link on mobile
    document.querySelectorAll('.nav-item').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 1024) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    });

    // Close sidebar on ESC key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    });

    // Handle window resize
    window.addEventListener('resize', () => {
        if (window.innerWidth > 1024) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    });

    // Activation Banner Modal Functions
    function openActivationBannerModal() {
        const modal = document.getElementById('activationBannerModal');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeActivationBannerModal() {
        const modal = document.getElementById('activationBannerModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    // Close modal when clicking overlay
    const bannerModal = document.getElementById('activationBannerModal');
    if (bannerModal) {
        bannerModal.addEventListener('click', (e) => {
            if (e.target === bannerModal) {
                closeActivationBannerModal();
            }
        });

        // Close on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && bannerModal.classList.contains('show')) {
                closeActivationBannerModal();
            }
        });
    }
    </script>
</body>
</html>

