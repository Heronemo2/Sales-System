<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signing out | Team Sales</title>
</head>
<body>
    <p>Signing out of the company account...</p>
    <iframe id="companyLogout" title="Company sign-out" style="position:absolute;width:1px;height:1px;left:-10000px;border:0"></iframe>
    <script>
        const companyLogout = document.getElementById('companyLogout');
        companyLogout.addEventListener('load', () => {
            if (companyLogout.contentWindow.location.href === 'about:blank') return;
            window.location.replace('login.php');
        });
        companyLogout.src = '../ITPROFEL/root/index.php?logout=1';
    </script>
</body>
</html>
