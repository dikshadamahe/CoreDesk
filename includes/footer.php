<?php
// =====================================================================
// includes/footer.php
// Clean Standard Footer for Zendesk Agent Workspace
// =====================================================================

declare(strict_types=1);
?>

<?php if (empty($isAuthPage)): ?>
        </main>
    </div> <!-- /.zd-main-pane -->
</div> <!-- /.zd-app-layout -->
<?php endif; ?>

<!-- Toast Notifications -->
<div id="toast-container"></div>

<!-- Vanilla JS Client Logic -->
<script src="assets/js/app.js"></script>
</body>
</html>
