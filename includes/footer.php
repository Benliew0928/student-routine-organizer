    </main>
    <footer class="footer">
        <p>&copy; <?= date('Y'); ?> <?= APP_NAME; ?>. UCCD3243 assignment project.</p>
    </footer>
    <script src="<?= BASE_URL; ?>/assets/js/app.js"></script>
    <?php foreach (($pageScripts ?? []) as $pageScript): ?>
        <script src="<?= escapeOutput($pageScript); ?>"></script>
    <?php endforeach; ?>
</body>
</html>

