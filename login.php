<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in | Team Sales</title>
    <style>
        html, body { height: 100%; margin: 0; }
        body { font-family: Arial, sans-serif; background: #f3f4f6; }
        iframe { display: block; width: 100%; height: 100%; border: 0; }
        .notice {
            position: fixed;
            z-index: 1;
            top: 16px;
            left: 50%;
            width: min(520px, calc(100% - 48px));
            transform: translateX(-50%);
            padding: 14px 18px;
            border: 1px solid #e1b5b5;
            border-radius: 8px;
            background: #fff;
            color: #7f1d1d;
            box-shadow: 0 4px 18px rgba(0, 0, 0, .12);
        }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
    <div class="notice" id="loginNotice" role="alert" hidden></div>
    <iframe
        id="companyLogin"
        src="../ITPROFEL/root/index.php"
        title="Company sign in">
    </iframe>
    <script>
        (() => {
            const frame = document.getElementById('companyLogin');
            const notice = document.getElementById('loginNotice');
            let checking = false;
            let redirecting = false;
            let accessDenied = false;

            function showFormError(form, message) {
                const error = form.querySelector('.error-text');
                if (error) {
                    error.textContent = message;
                    error.classList.toggle('is-visible', Boolean(message));
                }
            }

            function attachSalesLoginAdapter() {
                const form = frame.contentDocument.getElementById('loginForm');
                if (!form || form.dataset.salesAdapterAttached === 'true') return;
                form.dataset.salesAdapterAttached = 'true';
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    event.stopImmediatePropagation();

                    const identifier = form.querySelector('[name="identifier"], [name="username"]')?.value.trim() || '';
                    const password = form.querySelector('[name="password"]')?.value || '';
                    const submitButton = form.querySelector('[type="submit"]');
                    showFormError(form, '');
                    if (!identifier || !password) {
                        showFormError(form, 'Enter your username and password.');
                        return;
                    }

                    if (submitButton) submitButton.disabled = true;
                    try {
                        const response = await fetch('auth_bridge.php?action=login', {
                            method: 'POST',
                            credentials: 'same-origin',
                            cache: 'no-store',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ identifier, password })
                        });
                        const result = await response.json();
                        if (!response.ok || !result.ok) {
                            throw new Error(result.message || 'Sign-in failed. Please try again.');
                        }
                        redirecting = true;
                        window.location.replace('dashboard.php');
                    } catch (error) {
                        showFormError(form, error.message || 'Sign-in failed. Please try again.');
                    } finally {
                        if (submitButton) submitButton.disabled = false;
                    }
                }, true);
            }

            async function checkSalesSession() {
                if (checking || redirecting || accessDenied) return;
                checking = true;
                try {
                    const response = await fetch('auth_bridge.php?action=session', {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: { Accept: 'application/json' }
                    });
                    if (!response.ok) {
                        throw new Error('The company sign-in service is unavailable.');
                    }

                    const result = await response.json();
                    if (!result.ok) {
                        throw new Error(result.message || 'The company sign-in service could not verify your session.');
                    }

                    const user = result.user;
                    if (!user) return;

                    const role = String(user.role || '');
                    const department = String(user.department || '').trim().toLowerCase();
                    if (department === 'sales' && ['Sales Admin', 'Sales Staff'].includes(role)) {
                        redirecting = true;
                        window.location.replace('dashboard.php');
                        return;
                    }

                    accessDenied = true;
                    frame.hidden = true;
                    notice.textContent = 'This account does not have Sales access. Sign in with an active Sales Admin or Sales Staff account.';
                    const signOutLink = document.createElement('a');
                    signOutLink.href = 'logout.php';
                    signOutLink.target = '_blank';
                    signOutLink.rel = 'noopener';
                    signOutLink.textContent = ' Sign out of this account, then reload Sales sign-in.';
                    notice.appendChild(signOutLink);
                    notice.hidden = false;
                } catch (error) {
                    console.error('Could not verify the shared sign-in session:', error);
                    notice.textContent = error.message || 'Could not verify the company sign-in session. Please refresh and try again.';
                    notice.hidden = false;
                } finally {
                    checking = false;
                }
            }

            frame.addEventListener('load', () => {
                attachSalesLoginAdapter();
                checkSalesSession();
            });
            checkSalesSession();
        })();
    </script>
</body>
</html>
