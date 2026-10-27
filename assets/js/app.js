document.addEventListener('DOMContentLoaded', () => {
    const menu = document.getElementById('mobileMenu');
    const sidebar = document.getElementById('sidebar');
    const sidebarClose = document.getElementById('sidebarClose');

    if (menu && sidebar) {
        menu.addEventListener('click', () => {
            sidebar.classList.add('open');
        });
    }

    if (sidebarClose && sidebar) {
        sidebarClose.addEventListener('click', () => {
            sidebar.classList.remove('open');
        });
    }

    // Close the mobile sidebar after following any sidebar link.
    if (sidebar) {
        sidebar.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                sidebar.classList.remove('open');
            });
        });
    }

    // Generic modal open/close (used by every "+ Add / New ..." button)
    document.querySelectorAll('[data-open-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById(btn.dataset.openModal);
            if (!modal) return;
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            modal.querySelector('input, select, textarea')?.focus();
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-backdrop');
            if (!modal) return;
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        });
    });

    document.querySelectorAll('.modal-backdrop').forEach(modal => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.open').forEach(modal => {
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
            });
        }
    });

    // Live stock check against the Inventory team's API (New Sales Order form)
    const productCodeInput = document.getElementById('productCodeInput');
    const quantityInput = document.getElementById('quantityInput');
    const stockStatus = document.getElementById('stockStatus');
    const totalAmountInput = document.getElementById('totalAmountInput');

    if (productCodeInput && stockStatus) {
        let lastCheckedCode = '';
        let unitPrice = 0;
        let debounceTimer = null;

        const setStatus = (text, className) => {
            stockStatus.textContent = text;
            stockStatus.className = 'muted' + (className ? ' ' + className : '');
        };

        const recalcTotal = () => {
            if (!unitPrice || !totalAmountInput) return;
            const qty = parseInt(quantityInput.value, 10) || 1;
            totalAmountInput.value = (unitPrice * qty).toFixed(2);
        };

        const checkStock = async () => {
            const code = productCodeInput.value.trim();
            if (!code) {
                setStatus('');
                return;
            }

            setStatus('Checking stock with Inventory…');

            try {
                const res = await fetch(`api/check_availability.php?product_code=${encodeURIComponent(code)}`);
                const data = await res.json();

                if (!res.ok || !data.ok) {
                    setStatus(data.error || 'Could not check stock for this product.', 'error');
                    unitPrice = 0;
                    return;
                }

                lastCheckedCode = code;
                unitPrice = Number(data.unit_price) || 0;

                if (data.available) {
                    setStatus(`✓ In stock: ${data.quantity} available · ₱${unitPrice.toFixed(2)} each`, 'success');
                } else {
                    setStatus(`✗ Out of stock (0 available)`, 'error');
                }

                recalcTotal();
            } catch (err) {
                setStatus('Network error reaching Inventory service.', 'error');
                unitPrice = 0;
            }
        };

        productCodeInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(checkStock, 500); // wait for typing to pause
        });

        quantityInput?.addEventListener('input', recalcTotal);

        // Warn (but don't hard-block) if they submit for a code that
        // was never confirmed available, e.g. they typed and left it.
        productCodeInput.closest('form')?.addEventListener('submit', (event) => {
            const code = productCodeInput.value.trim();
            if (code && code !== lastCheckedCode) {
                if (!confirm('Stock for this product code was not confirmed. Submit anyway?')) {
                    event.preventDefault();
                }
            }
        });
    }

    // Table search
    document.querySelectorAll('[data-table-search]').forEach(input => {
        input.addEventListener('input', () => {
            const table = document.getElementById(input.dataset.tableSearch);
            if (!table) return;

            const term = input.value.toLowerCase().trim();

            table.querySelectorAll('tbody tr').forEach(row => {
                // Ignore action-button cells so words like "Edit" don't match every row
                const text = Array.from(row.children)
                    .filter(cell => !cell.hasAttribute('data-no-search'))
                    .map(cell => cell.innerText)
                    .join(' ')
                    .toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
    });
});

    // Profile dropdown
    const profileButton = document.getElementById('profileMenuButton');
    const profileDropdown = document.getElementById('profileDropdown');
    if (profileButton && profileDropdown) {
        profileButton.addEventListener('click', (event) => {
            event.stopPropagation();
            const open = profileDropdown.classList.toggle('open');
            profileButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', (event) => {
            if (!profileDropdown.contains(event.target) && !profileButton.contains(event.target)) {
                profileDropdown.classList.remove('open');
                profileButton.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Manage Profile modal
    const profileModal = document.getElementById('profileModal');
    const openProfileModal = document.getElementById('openProfileModal');
    const closeProfileModal = document.getElementById('closeProfileModal');
    const cancelProfileModal = document.getElementById('cancelProfileModal');

    const closeProfile = () => {
        if (!profileModal) return;
        profileModal.classList.remove('open');
        profileModal.setAttribute('aria-hidden', 'true');
    };

    if (profileModal && openProfileModal) {
        openProfileModal.addEventListener('click', () => {
            profileDropdown?.classList.remove('open');
            profileButton?.setAttribute('aria-expanded', 'false');
            profileModal.classList.add('open');
            profileModal.setAttribute('aria-hidden', 'false');
            profileModal.querySelector('input[name="name"]')?.focus();
        });

        closeProfileModal?.addEventListener('click', closeProfile);
        cancelProfileModal?.addEventListener('click', closeProfile);

        profileModal.addEventListener('click', (event) => {
            if (event.target === profileModal) closeProfile();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeProfile();
        });
<<<<<<< HEAD
    }
=======
    }
>>>>>>> origin/main
