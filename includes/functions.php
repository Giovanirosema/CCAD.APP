<?php
/**
 * Fonctions utilitaires partagées par toutes les pages.
 */
require_once __DIR__ . '/../config/db.php';

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/** Formate un montant façon « 2 500 HTG » (séparateur de milliers = espace). */
function fmt_money($n, ?string $devise = null): string
{
    $s = number_format((float) $n, 0, ',', ' ');
    return $devise ? $s . ' ' . $devise : $s;
}

function fmt_date_fr(?string $ymd): string
{
    if (!$ymd) {
        return '—';
    }
    $t = strtotime($ymd);
    return $t ? date('d/m/Y', $t) : '—';
}

function fmt_datetime_fr(?string $ymdhms): string
{
    if (!$ymdhms) {
        return '—';
    }
    $t = strtotime($ymdhms);
    return $t ? date('d/m/Y H:i', $t) : '—';
}

function age_years(?string $date_naissance): ?int
{
    if (!$date_naissance) {
        return null;
    }
    $naiss = new DateTime($date_naissance);
    $aujourdhui = new DateTime();
    return $naiss->diff($aujourdhui)->y;
}

/** Mois écoulés entre la date d'adhésion et aujourd'hui (mois cotisés). */
function mois_cotises(string $date_adhesion): int
{
    $adh = new DateTime($date_adhesion);
    $aujourdhui = new DateTime();
    $diff = $adh->diff($aujourdhui);
    return $diff->y * 12 + $diff->m;
}

/** Ton visuel associé à un statut (badges, puces de couleur). */
function badge_tone(string $statut): string
{
    static $tones = [
        'Active' => 'success',
        'En retard' => 'danger',
        'En attente' => 'warning',
        'Suspendue' => 'neutral',
        'Résiliée' => 'neutral',
        'Encaissé' => 'success',
        'À valider' => 'warning',
        'Rejeté' => 'danger',
        'Versé' => 'success',
        'En cours' => 'warning',
        'Archivé' => 'neutral',
    ];
    return $tones[$statut] ?? 'neutral';
}

function badge_html(string $statut, ?string $tone = null): string
{
    $tone = $tone ?? badge_tone($statut);
    return '<span class="badge badge--' . h($tone) . '"><i class="badge__dot"></i>' . h($statut) . '</span>';
}

/**
 * Génère la prochaine référence séquentielle pour une table/colonne/préfixe donnés,
 * à partir du plus grand suffixe numérique existant (et non du dernier id inséré,
 * car des références historiques peuvent avoir été importées dans le désordre).
 */
function next_reference(string $table, string $colonne, string $prefixe, int $largeur = 4): string
{
    $stmt = db()->prepare(
        "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX($colonne,'-',-1) AS UNSIGNED)),0) FROM $table WHERE $colonne LIKE ?"
    );
    $stmt->execute([$prefixe . '-%']);
    $seq = ((int) $stmt->fetchColumn()) + 1;
    return $prefixe . '-' . str_pad((string) $seq, $largeur, '0', STR_PAD_LEFT);
}

function next_police_number(): string
{
    $annee = date('Y');
    $stmt = db()->prepare(
        "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero,'-',-1) AS UNSIGNED)),1000) FROM polices WHERE numero LIKE ?"
    );
    $stmt->execute(["P-$annee-%"]);
    $seq = max(1001, ((int) $stmt->fetchColumn()) + 1);
    return "P-$annee-$seq";
}

/** Consigne une opération dans le journal d'audit (non modifiable depuis l'application). */
function log_audit(string $type, string $cible, string $detail): void
{
    $user = current_user();
    $stmt = db()->prepare(
        'INSERT INTO audit_log (utilisateur_id, zone, type_operation, cible, detail) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['id'] ?? null,
        $user['zone'] ?? 'Système',
        $type,
        $cible,
        $detail,
    ]);
}

/** Règles métier actives (éligibilité, retard, suspension, échéance). */
function regles(): array
{
    static $r = null;
    if ($r === null) {
        $r = db()->query('SELECT * FROM reglages_regles WHERE id = 1')->fetch() ?: [
            'eligibilite_mois' => 24, 'retard_jours' => 30, 'suspension_jours' => 60, 'echeance_jour' => 1,
        ];
    }
    return $r;
}

/** Enregistre un message flash (toast) affiché après une redirection. */
function flash(string $tone, string $title, ?string $desc = null): void
{
    $_SESSION['flash'] = ['tone' => $tone, 'title' => $title, 'desc' => $desc];
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function organisation(): array
{
    static $o = null;
    if ($o === null) {
        $o = db()->query('SELECT * FROM reglages_organisation WHERE id = 1')->fetch() ?: [];
    }
    return $o;
}

/** Carte { département => [communes] } pour les listes déroulantes en cascade (rendue en JSON). */
function haiti_communes_map(): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        $rows = db()->query('SELECT d.nom AS dept, c.nom AS commune FROM communes c JOIN departements d ON d.id = c.departement_id ORDER BY d.id, c.id')->fetchAll();
        foreach ($rows as $row) {
            $map[$row['dept']][] = $row['commune'];
        }
    }
    return $map;
}

function icon(string $name, int $size = 16): string
{
    // Icônes en ligne (Lucide) — un sous-ensemble suffisant pour l'interface CCAD.
    static $paths = [
        'chart-column' => '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'file-text' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>',
        'credit-card' => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
        'calendar-clock' => '<path d="M21 7.5V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4.3"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><circle cx="17" cy="17" r="5"/><path d="M17 15.5v1.7l1.2.7"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'folder-open' => '<path d="m6 14 1.5-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.54 6a2 2 0 0 1-1.95 1.5H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H18a2 2 0 0 1 2 2v2"/>',
        'shield-check' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'user-plus' => '<path d="M2 21a8 8 0 0 1 13.292-6"/><circle cx="10" cy="8" r="5"/><path d="M19 16v6"/><path d="M22 19h-6"/>',
        'triangle-alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'printer' => '<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"/><rect width="12" height="8" x="6" y="14"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
        'trash-2' => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>',
        'plus' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>',
    ];
    $p = $paths[$name] ?? $paths['file-text'];
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon" aria-hidden="true">' . $p . '</svg>';
}
