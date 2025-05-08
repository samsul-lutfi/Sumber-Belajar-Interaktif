/**
 * Main JavaScript file for Sumber Belajar Interaktif
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all tooltips
    var tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltips.map(function(tooltip) {
        return new bootstrap.Tooltip(tooltip);
    });

    // Initialize all popovers
    var popovers = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popovers.map(function(popover) {
        return new bootstrap.Popover(popover);
    });

    // Password visibility toggle
    const passwordToggles = document.querySelectorAll('.password-toggle-icon');
    passwordToggles.forEach(icon => {
        icon.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            
            // Toggle icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    });

    // Handle form submission with loading state
    const forms = document.querySelectorAll('form.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                const submitBtn = this.querySelector('[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="loading-spinner me-2"></span>Memproses...';
                    
                    // Re-enable after timeout (in case of network issues)
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }, 10000);
                }
            }
            
            this.classList.add('was-validated');
        }, false);
    });

    // Password strength meter
    const passwordInputs = document.querySelectorAll('.password-with-strength');
    passwordInputs.forEach(input => {
        input.addEventListener('input', function() {
            const password = this.value;
            const meter = document.querySelector('.password-strength-meter');
            const text = document.querySelector('.password-strength-text');
            
            if (!meter || !text) return;
            
            // Remove all strength classes
            meter.classList.remove('strength-weak', 'strength-medium', 'strength-good', 'strength-strong');
            
            if (password.length === 0) {
                text.textContent = '';
                return;
            }
            
            const strength = calculatePasswordStrength(password);
            
            if (strength < 25) {
                meter.classList.add('strength-weak');
                text.textContent = 'Sangat Lemah';
                text.style.color = '#e74c3c';
            } else if (strength < 50) {
                meter.classList.add('strength-medium');
                text.textContent = 'Lemah';
                text.style.color = '#f39c12';
            } else if (strength < 75) {
                meter.classList.add('strength-good');
                text.textContent = 'Baik';
                text.style.color = '#3498db';
            } else {
                meter.classList.add('strength-strong');
                text.textContent = 'Kuat';
                text.style.color = '#2ecc71';
            }
        });
    });

    // Initialize dropdown select with search
    const selects = document.querySelectorAll('select.searchable');
    if (typeof choices !== 'undefined') {
        selects.forEach(select => {
            new Choices(select, {
                searchEnabled: true,
                searchPlaceholderValue: 'Cari...',
                itemSelectText: 'Pilih',
                removeItemButton: true
            });
        });
    }

    // Quiz handling
    setupQuizFunctionality();
    
    // Forum post character counter
    const forumTextareas = document.querySelectorAll('.forum-textarea');
    forumTextareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            const counter = document.querySelector('.character-counter');
            if (counter) {
                const remaining = 1000 - this.value.length;
                counter.textContent = `${remaining} karakter tersisa`;
                
                if (remaining < 100) {
                    counter.style.color = '#e74c3c';
                } else {
                    counter.style.color = '#6c757d';
                }
            }
        });
    });
});

/**
 * Calculate password strength score (0-100)
 * @param {string} password - The password to check
 * @return {number} - Strength score from 0-100
 */
function calculatePasswordStrength(password) {
    let score = 0;
    
    // Length contribution (up to 40 points)
    score += Math.min(password.length * 4, 40);
    
    // Character variety contribution
    if (/[a-z]/.test(password)) score += 10; // lowercase
    if (/[A-Z]/.test(password)) score += 10; // uppercase
    if (/[0-9]/.test(password)) score += 10; // numbers
    if (/[^a-zA-Z0-9]/.test(password)) score += 15; // special characters
    
    // Penalize repetition and sequential patterns
    if (/(.)\\1{2,}/.test(password)) score -= 10; // character repetition
    if (/(?:012|123|234|345|456|567|678|789|987|876|765|654|543|432|321|210)/.test(password)) 
        score -= 10; // sequential numbers
    
    return Math.max(0, Math.min(score, 100));
}

/**
 * Setup quiz functionality
 */
function setupQuizFunctionality() {
    const quizContainer = document.querySelector('.quiz-container');
    if (!quizContainer) return;
    
    // Option selection
    const options = document.querySelectorAll('.quiz-option');
    options.forEach(option => {
        option.addEventListener('click', function() {
            // Single choice questions (radio)
            if (this.querySelector('input[type="radio"]')) {
                const name = this.querySelector('input').getAttribute('name');
                document.querySelectorAll(`.quiz-option input[name="${name}"]`).forEach(input => {
                    input.closest('.quiz-option').classList.remove('selected');
                });
                
                this.classList.add('selected');
                this.querySelector('input').checked = true;
            } 
            // Multiple choice questions (checkbox)
            else if (this.querySelector('input[type="checkbox"]')) {
                this.classList.toggle('selected');
                const checkbox = this.querySelector('input');
                checkbox.checked = !checkbox.checked;
            }
        });
    });
    
    // Timer functionality for timed quizzes
    const timerElement = document.querySelector('.quiz-timer');
    if (timerElement) {
        const duration = parseInt(timerElement.dataset.duration || 0, 10);
        if (duration > 0) {
            startQuizTimer(duration, timerElement);
        }
    }
    
    // Quiz submission confirmation
    const quizForm = document.querySelector('.quiz-form');
    if (quizForm) {
        quizForm.addEventListener('submit', function(e) {
            const confirmed = confirm('Apakah Anda yakin ingin menyelesaikan kuis ini?');
            if (!confirmed) {
                e.preventDefault();
            }
        });
    }
}

/**
 * Start quiz timer countdown
 * @param {number} duration - Duration in seconds
 * @param {HTMLElement} timerElement - Element to update with time
 */
function startQuizTimer(duration, timerElement) {
    let timer = duration;
    const interval = setInterval(() => {
        const minutes = Math.floor(timer / 60);
        const seconds = timer % 60;
        
        timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        if (--timer < 0) {
            clearInterval(interval);
            // Auto-submit the form when time runs out
            document.querySelector('.quiz-form').submit();
        }
        
        // Warning when less than 1 minute remains
        if (timer < 60) {
            timerElement.classList.add('text-danger');
            timerElement.classList.add('fw-bold');
        }
    }, 1000);
}

/**
 * Handle material filter selection
 * @param {string} category - Category to filter by
 */
function filterMaterials(category) {
    const materials = document.querySelectorAll('.materi-item');
    
    materials.forEach(item => {
        if (category === 'all' || item.dataset.category === category) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
    
    // Update active filter button
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`.filter-btn[data-category="${category}"]`).classList.add('active');
}
