</main>

<footer class="text-center text-xs text-slate-500 py-5">
    © <?= date('Y') + 543 ?> <?= h(APP_NAME) ?> · <?= h(APP_SUBTITLE) ?> · v<?= APP_VERSION ?>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/common.js?v=<?= APP_VERSION ?>"></script>
<?php foreach (($pageScripts ?? []) as $s): ?>
<script src="<?= h($s) ?>?v=<?= APP_VERSION ?>"></script>
<?php endforeach; ?>
</body>
</html>
