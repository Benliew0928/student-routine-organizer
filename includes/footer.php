    </main>
    <footer class="footer">
        <p>&copy; <?= date('Y'); ?> <?= APP_NAME; ?>. UCCD3243 assignment project.</p>
    </footer>
    <script src="<?= BASE_URL; ?>/assets/js/app.js?v=20260812-password-controls-v2"></script>
    <?php if (!empty($pageScripts)): ?>
        <?php foreach ($pageScripts as $scriptUrl): ?>
            <script src="<?= escapeOutput($scriptUrl); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

