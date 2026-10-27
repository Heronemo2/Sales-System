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

    // Table search
    document.querySelectorAll('[data-table-search]').forEach(input => {
        input.addEventListener('input', () => {
            const table = document.getElementById(input.dataset.tableSearch);
            if (!table) return;

            const term = input.value.toLowerCase().trim();

            table.querySelectorAll('tbody tr').forEach(row => {
                row.style.display =
                    row.innerText.toLowerCase().includes(term) ? '' : 'none';
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
    }
