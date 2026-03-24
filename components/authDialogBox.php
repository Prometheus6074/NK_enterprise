<div class="connectContainer">
    <?php if (isset($result) && $result !== ''): ?>
    <span id="resultConnection" class="<?php echo htmlspecialchars($resultClass ?? ''); ?>">
        <?php echo htmlspecialchars($result); ?>
    </span>
    <?php else: ?>
    <span id="resultConnection"></span>
    <?php endif; ?>
</div>

<script>
const msg = document.getElementById("resultConnection");

if (msg && msg.textContent.trim() !== "") {
    setTimeout(() => {
        msg.style.opacity = "0";
        msg.style.transition = "opacity 0.5s ease";

        setTimeout(() => {
            if (msg.parentElement) msg.parentElement.style.display = "none";
        }, 500);
    }, 1000);
}
</script>
