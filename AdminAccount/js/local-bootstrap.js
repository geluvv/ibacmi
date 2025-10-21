/* Minimal Bootstrap JavaScript - Modal functionality */
(function() {
    'use strict';

    // Modal class
    class Modal {
        constructor(element, options = {}) {
            this._element = element;
            this._isShown = false;
            this._backdrop = null;
            this._options = {
                backdrop: true,
                keyboard: true,
                focus: true,
                ...options
            };
            
            this._initializeBackDrop();
            this._initializeFocusTrap();
        }

        static getInstance(element) {
            return Modal._instances.get(element) || null;
        }

        show() {
            if (this._isShown) return;

            this._isShown = true;
            this._element.style.display = 'block';
            this._element.classList.add('show');
            
            if (this._options.backdrop) {
                this._showBackdrop();
            }
            
            document.body.classList.add('modal-open');
            this._element.scrollTop = 0;
            
            if (this._options.focus) {
                this._enforceFocus();
            }
        }

        hide() {
            if (!this._isShown) return;

            this._isShown = false;
            this._element.classList.remove('show');
            
            setTimeout(() => {
                this._element.style.display = 'none';
                document.body.classList.remove('modal-open');
                if (this._backdrop) {
                    this._backdrop.remove();
                    this._backdrop = null;
                }
            }, 150);
        }

        _showBackdrop() {
            this._backdrop = document.createElement('div');
            this._backdrop.className = 'modal-backdrop fade show';
            this._backdrop.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                z-index: 1050;
                width: 100vw;
                height: 100vh;
                background-color: #000;
                opacity: 0.5;
            `;
            
            if (this._options.backdrop === true) {
                this._backdrop.addEventListener('click', () => this.hide());
            }
            
            document.body.appendChild(this._backdrop);
        }

        _initializeBackDrop() {
            // Close button functionality
            const closeButtons = this._element.querySelectorAll('[data-bs-dismiss="modal"]');
            closeButtons.forEach(button => {
                button.addEventListener('click', () => this.hide());
            });
        }

        _initializeFocusTrap() {
            if (this._options.keyboard) {
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && this._isShown) {
                        this.hide();
                    }
                });
            }
        }

        _enforceFocus() {
            const focusableElements = this._element.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            
            if (focusableElements.length > 0) {
                focusableElements[0].focus();
            }
        }
    }

    // Static instances map
    Modal._instances = new Map();

    // Initialize modals
    window.bootstrap = {
        Modal: Modal
    };

    // Auto-initialize modals when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modalElement => {
            Modal._instances.set(modalElement, new Modal(modalElement));
        });
    });

    // Alert dismiss functionality
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-bs-dismiss="alert"]')) {
            const alert = e.target.closest('.alert');
            if (alert) {
                alert.classList.add('fade');
                setTimeout(() => alert.remove(), 150);
            }
        }
    });

})();