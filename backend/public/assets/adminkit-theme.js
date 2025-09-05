/**
 * AdminKit Theme JavaScript
 * Handles theme-specific functionality and enhancements
 */

(function() {
    'use strict';

    // Theme manager object
    const AdminKitTheme = {
        // Initialize the theme
        init() {
            this.setupMobileNavigation();
            this.setupTooltips();
            this.setupFormEnhancements();
            this.setupTableEnhancements();
            this.setupCardAnimations();
            this.initializeTheme();
        },

        // Mobile navigation handling
        setupMobileNavigation() {
            const toggleBtn = document.querySelector('[data-bs-toggle="offcanvas"]');
            const sidebar = document.querySelector('.kt-aside');
            const backdrop = document.querySelector('.kt-aside-backdrop');
            const body = document.body;

            if (!toggleBtn || !sidebar) return;

            // Create backdrop if it doesn't exist
            if (!backdrop) {
                const newBackdrop = document.createElement('div');
                newBackdrop.className = 'kt-aside-backdrop';
                document.body.appendChild(newBackdrop);
            }

            const backdropEl = document.querySelector('.kt-aside-backdrop');

            // Toggle sidebar
            toggleBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleSidebar(sidebar, backdropEl, body);
            });

            // Close on backdrop click
            if (backdropEl) {
                backdropEl.addEventListener('click', () => {
                    this.closeSidebar(sidebar, backdropEl, body);
                });
            }

            // Close on escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && sidebar.classList.contains('show')) {
                    this.closeSidebar(sidebar, backdropEl, body);
                }
            });

            // Close sidebar when clicking on nav links on mobile
            const navLinks = sidebar.querySelectorAll('.kt-nav a');
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 992) {
                        this.closeSidebar(sidebar, backdropEl, body);
                    }
                });
            });
        },

        toggleSidebar(sidebar, backdrop, body) {
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('show');
            body.classList.toggle('offcanvas-open');
        },

        closeSidebar(sidebar, backdrop, body) {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
            body.classList.remove('offcanvas-open');
        },

        // Setup tooltips for info icons
        setupTooltips() {
            const infoIcons = document.querySelectorAll('.info-icon[title], .info-icon[data-bs-title]');
            
            infoIcons.forEach(icon => {
                // Add Bootstrap tooltip if available
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    new bootstrap.Tooltip(icon, {
                        placement: 'top',
                        trigger: 'hover focus'
                    });
                }
            });
        },

        // Form enhancements
        setupFormEnhancements() {
            // Add floating label effect
            const formControls = document.querySelectorAll('.form-control, .form-select');
            
            formControls.forEach(control => {
                // Add focus/blur classes for styling
                control.addEventListener('focus', function() {
                    this.classList.add('focused');
                });

                control.addEventListener('blur', function() {
                    this.classList.remove('focused');
                    if (this.value.trim() !== '') {
                        this.classList.add('has-value');
                    } else {
                        this.classList.remove('has-value');
                    }
                });

                // Check initial state
                if (control.value.trim() !== '') {
                    control.classList.add('has-value');
                }
            });

            // Form validation styling
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Add was-validated class for Bootstrap validation styling
                    if (!this.checkValidity()) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    this.classList.add('was-validated');
                });
            });
        },

        // Table enhancements
        setupTableEnhancements() {
            const tables = document.querySelectorAll('.table');
            
            tables.forEach(table => {
                // Add hover effect to table rows
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    // Skip rows that already have click handlers
                    if (!row.hasAttribute('data-enhanced')) {
                        row.setAttribute('data-enhanced', 'true');
                        
                        // Add subtle hover animation
                        row.addEventListener('mouseenter', function() {
                            this.style.transform = 'translateX(2px)';
                            this.style.transition = 'transform 0.15s ease';
                        });

                        row.addEventListener('mouseleave', function() {
                            this.style.transform = 'translateX(0)';
                        });
                    }
                });

                // Make table responsive if not already wrapped
                if (!table.parentElement.classList.contains('table-responsive')) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'table-responsive';
                    table.parentNode.insertBefore(wrapper, table);
                    wrapper.appendChild(table);
                }
            });
        },

        // Card animations
        setupCardAnimations() {
            const cards = document.querySelectorAll('.card');
            
            // Add stagger animation to cards on page load
            cards.forEach((card, index) => {
                if (!card.hasAttribute('data-animated')) {
                    card.setAttribute('data-animated', 'true');
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, index * 50);
                }
            });
        },

        // Initialize theme-specific features
        initializeTheme() {
            // Add theme class to body
            document.body.classList.add('adminkit-theme');

            // Setup custom scrollbar for webkit browsers
            this.setupCustomScrollbar();

            // Setup loading states
            this.setupLoadingStates();

            // Auto-hide alerts after delay
            this.setupAutoHideAlerts();
        },

        // Custom scrollbar setup
        setupCustomScrollbar() {
            // Add custom scrollbar class to scrollable elements
            const scrollableElements = document.querySelectorAll('.overflow-auto, .overflow-y-auto');
            scrollableElements.forEach(el => {
                el.classList.add('adminkit-scrollbar');
            });
        },

        // Loading states
        setupLoadingStates() {
            // Add loading animation to buttons when clicked
            const submitButtons = document.querySelectorAll('button[type="submit"], .btn-submit');
            
            submitButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    if (this.form && this.form.checkValidity()) {
                        this.classList.add('loading');
                        this.disabled = true;
                        
                        const originalText = this.innerHTML;
                        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Laden...';
                        
                        // Reset after 5 seconds (fallback)
                        setTimeout(() => {
                            this.classList.remove('loading');
                            this.disabled = false;
                            this.innerHTML = originalText;
                        }, 5000);
                    }
                });
            });
        },

        // Auto-hide alerts
        setupAutoHideAlerts() {
            const autoHideAlerts = document.querySelectorAll('.alert[data-auto-hide]');
            
            autoHideAlerts.forEach(alert => {
                const delay = parseInt(alert.dataset.autoHide) || 5000;
                
                setTimeout(() => {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    } else {
                        alert.style.opacity = '0';
                        alert.style.transform = 'translateY(-100%)';
                        setTimeout(() => alert.remove(), 300);
                    }
                }, delay);
            });
        },

        // Utility functions
        utils: {
            // Smooth scroll to element
            scrollTo(element, offset = 0) {
                const targetElement = typeof element === 'string' 
                    ? document.querySelector(element) 
                    : element;
                
                if (targetElement) {
                    const elementPosition = targetElement.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - offset;
                    
                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            },

            // Create skeleton loader
            createSkeleton(width = '100%', height = '1rem') {
                const skeleton = document.createElement('div');
                skeleton.className = 'skeleton';
                skeleton.style.width = width;
                skeleton.style.height = height;
                return skeleton;
            },

            // Show loading state
            showLoading(element) {
                const loadingSpinner = document.createElement('div');
                loadingSpinner.className = 'text-center py-4';
                loadingSpinner.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Laden...</span></div>';
                
                element.innerHTML = '';
                element.appendChild(loadingSpinner);
            },

            // Debounce function
            debounce(func, wait, immediate) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        timeout = null;
                        if (!immediate) func(...args);
                    };
                    const callNow = immediate && !timeout;
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                    if (callNow) func(...args);
                };
            }
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => AdminKitTheme.init());
    } else {
        AdminKitTheme.init();
    }

    // Make AdminKitTheme globally available
    window.AdminKitTheme = AdminKitTheme;

    // Auto-refresh theme on dynamic content changes
    const observer = new MutationObserver((mutations) => {
        let shouldRefresh = false;
        
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1 && (
                        node.classList.contains('card') ||
                        node.classList.contains('table') ||
                        node.querySelector('.card, .table')
                    )) {
                        shouldRefresh = true;
                    }
                });
            }
        });
        
        if (shouldRefresh) {
            // Debounce the refresh
            clearTimeout(window.adminKitRefreshTimeout);
            window.adminKitRefreshTimeout = setTimeout(() => {
                AdminKitTheme.setupTableEnhancements();
                AdminKitTheme.setupCardAnimations();
                AdminKitTheme.setupFormEnhancements();
            }, 100);
        }
    });

    // Start observing
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

})();
