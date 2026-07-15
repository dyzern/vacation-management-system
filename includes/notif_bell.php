<?php
// Requer que $notifNaoLidas e $notificacoes estejam definidos na página que inclui este ficheiro
?>
<div class="notif-wrapper" id="notifWrapper">
    <button class="notif-btn" type="button" id="notifBtn" onclick="toggleNotificacoes()">
        <i class="fa-regular fa-bell"></i>
        <?php if ($notifNaoLidas > 0): ?>
            <span class="notif-count" id="notifCount"><?= e((string) $notifNaoLidas) ?></span>
        <?php endif; ?>
    </button>

    <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-dropdown-header">
            <span>Notificações</span>
            <?php if ($notifNaoLidas > 0): ?>
                <span class="notif-new-badge" id="notifNewBadge">
                    <?= e((string) $notifNaoLidas) ?> nova<?= $notifNaoLidas > 1 ? 's' : '' ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="notif-list">
            <?php if ($notificacoes === []): ?>
                <div class="notif-empty">Sem notificações de momento.</div>
            <?php else: ?>
                <?php foreach ($notificacoes as $notif): ?>
                    <div class="notif-item <?= ((int) $notif['lida']) === 0 ? 'nao-lida' : '' ?>">
                        <div class="notif-msg"><?= e($notif['mensagem']) ?></div>
                        <?php if (!empty($notif['data_inicio'])): ?>
                            <div class="notif-periodo">
                                <?= e(formatarDataInterface($notif['data_inicio'])) ?>
                                a
                                <?= e(formatarDataInterface($notif['data_fim'])) ?>
                            </div>
                        <?php endif; ?>
                        <div class="notif-time"><?= e(formatarDataHoraInterface($notif['criado_em'])) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    var jaAbriu = false;

    window.toggleNotificacoes = function () {
        var dropdown = document.getElementById('notifDropdown');
        var isOpen = dropdown.classList.toggle('active');

        if (isOpen && !jaAbriu) {
            jaAbriu = true;

            fetch('../api/notificacoes_lidas.php', { method: 'POST' })
                .then(function () {
                    var badge = document.getElementById('notifCount');
                    if (badge) badge.remove();
                    var newBadge = document.getElementById('notifNewBadge');
                    if (newBadge) newBadge.remove();
                    var items = document.querySelectorAll('.notif-item.nao-lida');
                    items.forEach(function (el) { el.classList.remove('nao-lida'); });
                });

            document.addEventListener('click', fecharForaDoDropdown);
        } else if (!isOpen) {
            document.removeEventListener('click', fecharForaDoDropdown);
        }
    };

    function fecharForaDoDropdown(e) {
        var wrapper = document.getElementById('notifWrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            document.getElementById('notifDropdown').classList.remove('active');
            document.removeEventListener('click', fecharForaDoDropdown);
        }
    }
})();
</script>
