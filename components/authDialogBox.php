<div class="connectContainer">
    <span id="resultConnection" class="<?php echo $resultClass; ?>">
        <?php echo $result; ?>
    </span>
</div>

<script>
const msg = document.getElementById("resultConnection");

if (msg.textContent !== "") {
    setTimeout(() => {
        msg.style.opacity = "0";
        msg.style.transition = "opacity 0.5s ease";

        setTimeout(() => {
            msg.parentElement.style.display = "none";
        }, 500);
    }, 1000);
}
</script>