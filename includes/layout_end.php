    </div>
  </div>
</div>
<?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
<div class="toast toast--<?= h($f['tone']) ?> no-print" id="flashToast">
  <div>
    <div class="toast__title"><?= h($f['title']) ?></div>
    <?php if (!empty($f['desc'])): ?><div class="toast__desc"><?= h($f['desc']) ?></div><?php endif; ?>
  </div>
</div>
<script>setTimeout(function(){var t=document.getElementById('flashToast'); if(t) t.style.display='none';}, 5000);</script>
<?php endif; ?>
</body>
</html>
