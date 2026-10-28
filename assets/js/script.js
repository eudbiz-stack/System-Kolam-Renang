// assets/js/script.js

// Global functions
window.KolamRenang = {
    // Format currency
    formatCurrency: function(amount) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR'
        }).format(amount);
    },
    
    // Format date
    formatDate: function(date) {
        return new Date(date).toLocaleDateString('id-ID');
    },
    
    // Show loading
    showLoading: function(element) {
        const spinner = '<div class="spinner-border spinner-border-sm me-2" role="status"></div>';
        element.innerHTML = spinner + element.innerHTML;
        element.disabled = true;
    },
    
    // Hide loading
    hideLoading: function(element, originalText) {
        element.innerHTML = originalText;
        element.disabled = false;
    },
    
    // Show toast notification
    showToast: function(message, type = 'info') {
        const toastContainer = document.getElementById('toast-container') || this.createToastContainer();
        const toast = this.createToast(message, type);
        toastContainer.appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Remove toast after hidden
        toast.addEventListener('hidden.bs.toast', function() {
            toast.remove();
        });
    },
    
    // Create toast container
    createToastContainer: function() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1055';
        document.body.appendChild(container);
        return container;
    },
    
    // Create toast element
    createToast: function(message, type) {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.setAttribute('role', 'alert');
        
        const icons = {
            success: 'check-circle',
            error: 'exclamation-triangle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        
        const colors = {
            success: 'text-success',
            error: 'text-danger',
            warning: 'text-warning',
            info: 'text-info'
        };
        
        toast.innerHTML = `
            <div class="toast-header">
                <i class="fas fa-${icons[type]} ${colors[type]} me-2"></i>
                <strong class="me-auto">Notifikasi</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        `;
        
        return toast;
    },
    
    // Confirm dialog
    confirm: function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    },
    
    // AJAX helper
    ajax: function(url, options = {}) {
        const defaults = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        const config = { ...defaults, ...options };
        
        return fetch(url, config)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .catch(error => {
                console.error('Ajax error:', error);
                this.showToast('Terjadi kesalahan: ' + error.message, 'error');
                throw error;
            });
    }
};

// Document ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Auto-resize textareas
    document.querySelectorAll('textarea').forEach(function(textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });
    
    // Number input formatting
    document.querySelectorAll('input[type="number"]').forEach(function(input) {
        input.addEventListener('input', function() {
            if (this.value < 0) this.value = 0;
        });
    });
    
    // Phone number formatting
    document.querySelectorAll('input[name="no_telepon"]').forEach(function(input) {
        input.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.startsWith('62')) {
                value = '0' + value.substring(2);
            }
            this.value = value;
        });
    });
    
    // Date input minimum today
    document.querySelectorAll('input[type="date"]').forEach(function(input) {
        if (input.hasAttribute('data-min-today')) {
            const today = new Date().toISOString().split('T')[0];
            input.setAttribute('min', today);
        }
    });
    
    // Time slot validation
    const dateInput = document.querySelector('input[name="tanggal_kunjungan"]');
    const timeInput = document.querySelector('input[name="jam_kunjungan"]');
    
    if (dateInput && timeInput) {
        function validateTimeSlot() {
            const date = dateInput.value;
            const time = timeInput.value;
            
            if (date && time) {
                // Check if selected time is in the future
                const selectedDateTime = new Date(date + 'T' + time);
                const now = new Date();
                
                if (selectedDateTime <= now) {
                    KolamRenang.showToast('Waktu kunjungan harus di masa depan', 'warning');
                    timeInput.value = '';
                    return false;
                }
            }
            return true;
        }
        
        dateInput.addEventListener('change', validateTimeSlot);
        timeInput.addEventListener('change', validateTimeSlot);
    }
    
    // Form submission with loading
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                const originalText = submitBtn.innerHTML;
                KolamRenang.showLoading(submitBtn);
                
                // Re-enable button after 5 seconds as fallback
                setTimeout(function() {
                    KolamRenang.hideLoading(submitBtn, originalText);
                }, 5000);
            }
        });
    });
    
    // Table row click to select
    document.querySelectorAll('.table-selectable tbody tr').forEach(function(row) {
        row.addEventListener('click', function() {
            // Remove active class from other rows
            document.querySelectorAll('.table-selectable tbody tr').forEach(function(r) {
                r.classList.remove('table-active');
            });
            // Add active class to clicked row
            this.classList.add('table-active');
        });
    });
    
    // Auto-refresh for real-time data
    if (document.querySelector('[data-auto-refresh]')) {
        setInterval(function() {
            const elements = document.querySelectorAll('[data-auto-refresh]');
            elements.forEach(function(el) {
                const url = el.getAttribute('data-refresh-url');
                if (url) {
                    KolamRenang.ajax(url)
                        .then(data => {
                            if (data.html) {
                                el.innerHTML = data.html;
                            }
                        })
                        .catch(error => {
                            console.error('Auto-refresh failed:', error);
                        });
                }
            });
        }, 30000); // Refresh every 30 seconds
    }
    
    // Sidebar toggle for mobile
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
        
        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });
    }
    
    // Print functionality
    document.querySelectorAll('.btn-print').forEach(function(btn) {
        btn.addEventListener('click', function() {
            window.print();
        });
    });
    
    // Export functionality
    document.querySelectorAll('.btn-export').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.getAttribute('href');
            const format = this.getAttribute('data-format') || 'excel';
            
            KolamRenang.showToast(`Mengunduh laporan dalam format ${format}...`, 'info');
            
            // Create temporary link to download
            const link = document.createElement('a');
            link.href = url;
            link.download = `laporan_${Date.now()}.${format === 'excel' ? 'xlsx' : 'pdf'}`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    });
});

// Utility functions for specific pages
window.BookingUtils = {
    calculateTotal: function(price, quantity) {
        return price * quantity;
    },
    
    updateTotalPrice: function() {
        const priceElement = document.querySelector('[data-price]');
        const quantityInput = document.querySelector('input[name="jumlah_tiket"]');
        const totalElement = document.querySelector('[data-total]');
        
        if (priceElement && quantityInput && totalElement) {
            const price = parseInt(priceElement.getAttribute('data-price'));
            const quantity = parseInt(quantityInput.value) || 1;
            const total = this.calculateTotal(price, quantity);
            
            totalElement.textContent = KolamRenang.formatCurrency(total);
        }
    }
};

// Dashboard utils
window.DashboardUtils = {
    updateStats: function() {
        const statsElements = document.querySelectorAll('[data-stat]');
        
        statsElements.forEach(function(el) {
            const statType = el.getAttribute('data-stat');
            const url = '/api/stats/' + statType;
            
            KolamRenang.ajax(url)
                .then(data => {
                    el.textContent = data.value;
                })
                .catch(error => {
                    console.error('Failed to update stat:', statType, error);
                });
        });
    },
    
    initCharts: function() {
        // Chart initialization will be added when needed
        console.log('Charts initialized');
    }
};