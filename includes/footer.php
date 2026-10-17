<?php
/**
 * Shared Page Footer
 * Reddit-style clean platform footer
 */
?>
        </div><!-- /.container -->
    </main><!-- /.main-content -->

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="<?= url('public/index.php') ?>">Home</a>
                <a href="#">About</a>
                <a href="#">Help</a>
                <a href="#">Terms</a>
                <a href="#">Privacy Policy</a>
                <a href="#">Content Policy</a>
            </div>
            <div class="footer-copy">
                <span>ThredIt, Inc. &copy; <?= date('Y') ?>. All rights reserved.</span>
            </div>
        </div>
    </footer>

    <script src="<?= asset_url('js/main.js') ?>"></script>
</body>
</html>
