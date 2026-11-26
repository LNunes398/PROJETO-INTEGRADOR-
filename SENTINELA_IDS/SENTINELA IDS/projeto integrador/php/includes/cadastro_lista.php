<?php
function cm_h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function cm_lista(array $maquinas, array $locais): void
{
    if (!$maquinas) {
        echo '<div class="empty-state">Nenhum computador encontrado.<br>Cadastre manualmente ou use o cadastro automático.</div>';
        return;
    }
    $grupoAtual = false;
    echo '<table class="cm-table"><thead><tr><th>Nome</th><th>IP</th><th>Hostname</th><th>MAC</th><th>SO</th><th>Status</th><th>Local</th><th></th></tr></thead><tbody>';
    foreach ($maquinas as $m) {
        $grupo = $m['location_id'] === null ? 0 : (int) $m['location_id'];
        if ($grupo !== $grupoAtual) {
            $grupoAtual = $grupo;
            $total = 0;
            foreach ($maquinas as $x) { if (((int) ($x['location_id'] ?? 0)) === $grupo) $total++; }
            echo '<tr class="cm-group"><td colspan="8">'
               . ($grupo === 0 ? '⏳ Local pendente / não definido' : '📍 ' . cm_h($m['location_name']))
               . ' <span class="cm-count">' . $total . '</span></td></tr>';
        }
        echo '<tr>';
        echo '<td><b>' . cm_h($m['name']) . '</b></td>';
        echo '<td class="cm-mono">' . cm_h($m['ip']) . '</td>';
        echo '<td class="cm-mono">' . cm_h($m['hostname'] ?? '—') . '</td>';
        echo '<td class="cm-mono">' . cm_h($m['mac'] ?? '—') . '</td>';
        echo '<td>' . cm_h($m['so'] ?? '—') . '</td>';
        echo '<td><span class="dot ' . cm_h($m['status']) . ' cm-dot"></span>' . cm_h($m['status']) . '</td>';
        echo '<td><select class="cm-sel sel-local" data-id="' . (int) $m['id'] . '">';
        echo '<option value="">— Não definido —</option>';
        foreach ($locais as $l) {
            echo '<option value="' . (int) $l['id'] . '"' . ((string) $m['location_id'] === (string) $l['id'] ? ' selected' : '') . '>' . cm_h($l['name']) . '</option>';
        }
        echo '</select></td>';
        echo '<td style="white-space:nowrap"><a class="icon-btn" href="maquina_form.php?id=' . (int) $m['id'] . '">✏️ Editar</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
