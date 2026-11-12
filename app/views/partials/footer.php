<?php

declare(strict_types=1);

$councilName = (string) appConfig('council_name');
?>
</main>
<footer class="site-footer border-top bg-white py-4 mt-5 no-print">
    <div class="container portal-container d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 small text-secondary">
        <div>&copy; <?= date('Y') ?> <?= e($councilName) ?>. All rights reserved.</div>
        <div>Need assistance? Contact the Council Secretariat.</div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>

