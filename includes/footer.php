    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-content">
                <p>&copy; <?php echo date('Y'); ?> College of Computer Studies. All rights reserved.</p>
                <p class="motto">INCEPTUM. INNOVATIO. NUMERIS.</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript Files -->
    <script src="assets/js/main.js"></script>
    
    <?php if(isset($extraJS)): ?>
        <script src="assets/js/<?php echo $extraJS; ?>.js"></script>
    <?php endif; ?>
</body>
</html>