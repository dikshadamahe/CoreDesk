<?php
// =====================================================================
// includes/footer.php
// Standardized Enterprise Footer & JS Bootstrapper
// =====================================================================

declare(strict_types=1);
?>

<?php if (empty($isAuthPage)): ?>
        </main>
        
        <footer style="padding: 18px 32px; border-top: 1px solid var(--jira-border-subtle); background: #FFFFFF; font-size: 12px; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center; flex-wrap: gap; gap: 12px;">
            <div>
                <strong>CoreDesk Service Management</strong> &middot; Engineered in Pure Core PHP 8.x, Vanilla JS &amp; MySQL.
            </div>
            <div style="display: flex; gap: 16px; align-items: center;">
                <span>Role: <strong><?= e($currentUser['role'] ?? 'Guest') ?></strong></span>
                <span>&bull;</span>
                <a href="https://github.com/dikshadamahe/CoreDesk" target="_blank" rel="noopener" style="color: var(--jira-blue);">GitHub Repository</a>
            </div>
        </footer>
    </div> <!-- /.app-main -->
</div> <!-- /.app-shell -->
<?php endif; ?>

<!-- Toast Alert Notifications Container -->
<div id="toast-container"></div>

<!-- Native Vanilla JavaScript Client Engine -->
<script src="assets/js/app.js"></script>
</body>
</html>
