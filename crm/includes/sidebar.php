<?php
// Sidebar navigation
?>
<aside class="crm-sidebar" id="crmSidebar">
    <div class="sidebar-inner">
        <nav class="sidebar-nav">
            <div class="sidebar-section-label"><?= e(t('dashboard')) ?></div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link<?= isActive('/index.php') ?>" href="<?= APP_URL ?>/index.php">
                        <i class="bi bi-speedometer2"></i>
                        <span><?= e(t('dashboard')) ?></span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-section-label"><?= e(t('contacts')) ?></div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link<?= isActive('/contacts/') ?>" href="<?= APP_URL ?>/contacts/index.php">
                        <i class="bi bi-person-lines-fill"></i>
                        <span><?= e(t('contacts')) ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= isActive('/companies/') ?>" href="<?= APP_URL ?>/companies/index.php">
                        <i class="bi bi-building"></i>
                        <span><?= e(t('companies')) ?></span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-section-label"><?= e(t('products')) ?></div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link<?= isActive('/products/') ?>" href="<?= APP_URL ?>/products/index.php">
                        <i class="bi bi-box-seam"></i>
                        <span><?= e(t('products')) ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= isActive('/offers/') ?>" href="<?= APP_URL ?>/offers/index.php">
                        <i class="bi bi-file-earmark-text"></i>
                        <span><?= e(t('offers')) ?></span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-section-label"><?= e(t('settings')) ?></div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link<?= isActive('/custom_fields/') ?>" href="<?= APP_URL ?>/custom_fields/index.php">
                        <i class="bi bi-sliders"></i>
                        <span><?= e(t('custom_fields')) ?></span>
                    </a>
                </li>
                <?php if (isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link<?= isActive('/users/') ?>" href="<?= APP_URL ?>/users/index.php">
                        <i class="bi bi-people"></i>
                        <span><?= e(t('users')) ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= isActive('/settings/') ?>" href="<?= APP_URL ?>/settings/index.php">
                        <i class="bi bi-gear"></i>
                        <span><?= e(t('settings')) ?></span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <a href="<?= APP_URL ?>/logout.php" class="btn btn-sm btn-outline-secondary w-100">
                <i class="bi bi-box-arrow-right me-1"></i><?= e(t('logout')) ?>
            </a>
        </div>
    </div>
</aside>
