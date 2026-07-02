<?php
// index.php
$paypal_client_id = getenv('PAYPAL_CLIENT_ID');
$TELEGRAM_LINK = getenv('TELEGRAM_LINK') ?: "https://t.me/";
include "verifica_login_opcional.php";
include "conexao.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$usuarioLogado = $_SESSION['usuario'] ?? null;
$id_perfil     = $usuarioLogado['idperfil'] ?? null;
$idUsuario     = $usuarioLogado['id_usuario'] ?? null;




// ── Registrar visualização (POST AJAX) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_visualizacao'])) {
    header('Content-Type: application/json');
    $idVideo = intval($_POST['id_video']);
    $ip      = $_SERVER['REMOTE_ADDR'];

    if ($idUsuario) {
        $stmt = $conexao->prepare("SELECT id_download FROM video_download_previa WHERE id_video = ? AND id_usuario = ?");
        $stmt->execute([$idVideo, $idUsuario]);
    } else {
        $stmt = $conexao->prepare("SELECT id_download FROM video_download_previa WHERE id_video = ? AND ip_address = ? AND id_usuario IS NULL");
        $stmt->execute([$idVideo, $ip]);
    }

    if ($stmt->rowCount() == 0) {
        $conexao->prepare("INSERT INTO video_download_previa (id_video, id_usuario, ip_address) VALUES (?, ?, ?)")
                ->execute([$idVideo, $idUsuario, $ip]);

        $conexao->prepare("UPDATE video SET visualizacoes = visualizacoes + 1 WHERE id_video = ?")
                ->execute([$idVideo]);

        $stmtCount = $conexao->prepare("SELECT visualizacoes FROM video WHERE id_video = ?");
        $stmtCount->execute([$idVideo]);
        echo json_encode(['success' => true, 'visualizacoes' => $stmtCount->fetchColumn()]);
    } else {
        $stmtCount = $conexao->prepare("SELECT visualizacoes FROM video WHERE id_video = ?");
        $stmtCount->execute([$idVideo]);
        echo json_encode(['success' => true, 'already_viewed' => true, 'visualizacoes' => $stmtCount->fetchColumn()]);
    }
    exit;
}

// ── Categorias para filtro ──
$lista_categorias = $conexao->query("SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria")->fetchAll();

// ── Montar query base com filtros ──
$filtros  = [];
$sql_base = "FROM video v
             LEFT JOIN video_imagem vi ON v.id_video = vi.id_video AND vi.imagem_principal = true
             WHERE v.ativo = true";

if (!empty($_GET['categoria'])) {
    $sql_base .= " AND EXISTS (SELECT 1 FROM video_categoria vc WHERE vc.id_video = v.id_video AND vc.id_categoria = ?)";
    $filtros[] = $_GET['categoria'];
}
if (!empty($_GET['busca'])) {
    $busca     = "%" . trim($_GET['busca']) . "%";
    $sql_base .= " AND (v.nome_video ILIKE ? OR v.descricao ILIKE ?)";
    $filtros[] = $busca;
    $filtros[] = $busca;
}
if (!empty($_GET['duracao_min'])) {
    $sql_base .= " AND EXTRACT(EPOCH FROM v.duracao::interval) >= ?";
    $filtros[] = intval($_GET['duracao_min']) * 60;
}
if (!empty($_GET['duracao_max'])) {
    $sql_base .= " AND EXTRACT(EPOCH FROM v.duracao::interval) <= ?";
    $filtros[] = intval($_GET['duracao_max']) * 60;
}
if (!empty($_GET['preco_min'])) {
    $sql_base .= " AND v.preco >= ?";
    $filtros[] = floatval($_GET['preco_min']);
}
if (!empty($_GET['preco_max'])) {
    $sql_base .= " AND v.preco <= ?";
    $filtros[] = floatval($_GET['preco_max']);
}

// // ── Paginação ──
// $limite       = 12;
// $pagina_atual = max(1, (int)($_GET['pagina'] ?? 1));
// $offset       = ($pagina_atual - 1) * $limite;

// // ── Contagem total ──
// $stmt_count = $conexao->prepare("SELECT COUNT(*) " . $sql_base);
// $stmt_count->execute($filtros);
// $total_registros = (int) $stmt_count->fetchColumn();
// $total_paginas   = ceil($total_registros / $limite);

// ── Buscar vídeos ──
$stmt = $conexao->prepare("SELECT v.*, vi.caminho_imagem " . $sql_base . " ORDER BY v.data_cadastro DESC");
$stmt->execute($filtros);
$videos            = $stmt->fetchAll();
$total_encontrados = count($videos);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DarkVelvetClub — Premium Video Store</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="css/basico.css">
<?php /* PayPal temporariamente desativado até obter credenciais
<script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars($paypal_client_id) ?>&currency=USD"></script>
*/ ?>
<!-- SVG favicon: faceted diamond on dark background -->
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='13' fill='%230a0a0f'/%3E%3Cpath d='M32 8 L56 26 L46 56 L18 56 L8 26 Z' fill='none' stroke='%23d4a843' stroke-width='2' stroke-linejoin='round' opacity='0.5'/%3E%3Cpath d='M32 8 L56 26 L32 38 L8 26 Z' fill='%23d4a843' opacity='0.9'/%3E%3Cpath d='M32 38 L56 26 L46 56 Z' fill='%23b8861e' opacity='0.75'/%3E%3Cpath d='M32 38 L8 26 L18 56 Z' fill='%23c49430' opacity='0.6'/%3E%3Cpath d='M32 38 L46 56 L18 56 Z' fill='%238a6010' opacity='0.5'/%3E%3Cellipse cx='32' cy='28' rx='5' ry='3' fill='white' opacity='0.15' transform='rotate(-15 32 28)'/%3E%3C/svg%3E">

<style>
/* ── RESET & BASE ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg: #050508;
    --surface: rgba(18, 18, 28, 0.82);
    --surface2: rgba(255,255,255,0.06);
    --border: rgba(255,255,255,0.10);
    --gold: #f5c15d;
    --gold2: #ff8f3c;
    --pink: #ff3d81;
    --purple: #7c3cff;
    --teal: #2AABEE;
    --text: #f5f5fb;
    --muted: #a6a6bd;
    --radius: 22px;
    --shadow: 0 30px 80px rgba(0,0,0,0.55);
}

body {
    background:
        radial-gradient(circle at top left, rgba(124,60,255,0.25), transparent 32%),
        radial-gradient(circle at top right, rgba(255,61,129,0.18), transparent 34%),
        radial-gradient(circle at bottom, rgba(245,193,93,0.08), transparent 38%),
        var(--bg);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
}

.topbar {
    background: rgba(5,5,8,0.72);
    backdrop-filter: blur(18px);
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    z-index: 100;
}

.topbar .container {
    max-width: 1440px;
    margin: 0 auto;
    height: 76px;
    padding: 0 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo-text .l-velvet {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 1.75rem;
    letter-spacing: 4px;
    background: linear-gradient(135deg, var(--gold), var(--pink));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.nav-links {
    display: flex;
    gap: 8px;
}

.nav-links a {
    color: var(--muted);
    text-decoration: none;
    padding: 9px 18px;
    border-radius: 999px;
    transition: 0.25s ease;
}

.nav-links a:hover {
    color: var(--text);
    background: rgba(255,255,255,0.08);
}

.hero {
    position: relative;
    overflow: hidden;
    padding: 86px 24px 74px;
    text-align: center;
}

.hero::before {
    content: '';
    position: absolute;
    inset: 12px;
    border-radius: 36px;
    background:
        linear-gradient(135deg, rgba(255,255,255,0.10), rgba(255,255,255,0.03)),
        radial-gradient(circle at 50% 0%, rgba(245,193,93,0.20), transparent 45%);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    z-index: -1;
}

.hero-eyebrow {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    padding: 8px 18px;
    border-radius: 999px;
    color: var(--gold);
    border: 1px solid rgba(245,193,93,0.32);
    background: rgba(245,193,93,0.09);
    font-weight: 700;
    font-size: 0.78rem;
    letter-spacing: 1.8px;
    text-transform: uppercase;
    margin-bottom: 22px;
}

.hero h1 {
    font-family: 'Bebas Neue', sans-serif;
    font-size: clamp(3.2rem, 8vw, 7.4rem);
    line-height: 0.88;
    letter-spacing: 4px;
    margin-bottom: 20px;
}

.hero h1 em {
    font-style: normal;
    background: linear-gradient(135deg, var(--gold), var(--pink), var(--purple));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.hero p {
    max-width: 640px;
    margin: 0 auto 30px;
    color: var(--muted);
    font-size: 1.08rem;
}

.hero-trust {
    display: flex;
    justify-content: center;
    gap: 14px;
    flex-wrap: wrap;
}

.trust-pill {
    padding: 10px 16px;
    border-radius: 999px;
    background: rgba(255,255,255,0.07);
    border: 1px solid var(--border);
    color: var(--text);
    font-size: 0.86rem;
}

.trust-pill i {
    color: var(--gold);
    margin-right: 6px;
}

.main-container {
    max-width: 1440px;
    margin: 0 auto;
    padding: 36px 24px 80px;
}

.filter-toggle-btn,
.filters,
.video-card,
#infoToast,
.modal-content {
    background: var(--surface);
    border: 1px solid var(--border);
    backdrop-filter: blur(18px);
}

.filter-toggle-btn {
    color: var(--text);
    padding: 13px 22px;
    border-radius: 999px;
    cursor: pointer;
    margin-bottom: 18px;
}

.filters {
    border-radius: var(--radius);
    padding: 28px;
    margin-bottom: 30px;
    display: none;
}

.filters.show {
    display: block;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
}

.filter-group label {
    color: var(--muted);
    font-size: 0.76rem;
    text-transform: uppercase;
    font-weight: 800;
    letter-spacing: 1px;
    margin-bottom: 8px;
    display: block;
}

.filter-group input,
.filter-group select {
    width: 100%;
    border: 1px solid var(--border);
    background: rgba(255,255,255,0.06);
    color: var(--text);
    padding: 12px 14px;
    border-radius: 14px;
    outline: none;
}

.btn-filter {
    border: 0;
    padding: 12px 24px;
    border-radius: 999px;
    cursor: pointer;
    font-weight: 800;
}

.btn-primary {
    background: linear-gradient(135deg, var(--gold), var(--gold2));
    color: #111;
}

.btn-secondary {
    background: rgba(255,255,255,0.08);
    color: var(--text);
}

.count-bar {
    display: flex;
    justify-content: space-between;
    margin-bottom: 24px;
}

.count {
    color: var(--muted);
}

.count strong {
    color: var(--gold);
}

.videos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 26px;
}

.video-card {
    border-radius: 26px;
    overflow: hidden;
    transition: 0.28s ease;
    position: relative;
}

.video-card:hover {
    transform: translateY(-8px) scale(1.01);
    border-color: rgba(245,193,93,0.40);
    box-shadow: 0 28px 70px rgba(0,0,0,0.50);
}

.video-thumbnail-wrapper {
    position: relative;
    aspect-ratio: 16 / 10;
    overflow: hidden;
    background: #111;
}

.video-thumbnail {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: 0.35s ease;
}

.video-card:hover .video-thumbnail {
    transform: scale(1.08);
}

.price-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: linear-gradient(135deg, var(--gold), var(--gold2));
    color: #111;
    font-weight: 900;
    padding: 7px 13px;
    border-radius: 999px;
}

.duration-badge {
    position: absolute;
    left: 12px;
    bottom: 12px;
    background: rgba(0,0,0,0.72);
    color: white;
    padding: 6px 12px;
    border-radius: 999px;
}

.video-info {
    padding: 20px;
}

.video-title {
    font-size: 1.02rem;
    font-weight: 800;
    margin-bottom: 14px;
}

.video-stats {
    display: flex;
    justify-content: space-between;
    color: var(--muted);
    font-size: 0.8rem;
    margin-bottom: 16px;
}

.online-badge {
    color: #4ade80;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.action-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.action-btn {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    border-radius: 15px;
    padding: 12px 14px;
    text-decoration: none;
    border: 0;
    cursor: pointer;
    font-weight: 900;
    transition: 0.22s ease;
}

.btn-preview {
    color: var(--text);
    background: rgba(255,255,255,0.08);
    border: 1px solid var(--border);
}

.btn-telegram {
    color: white;
    background: linear-gradient(135deg, #2AABEE, #177fb5);
}

.btn-buy {
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--pink));
    box-shadow: 0 14px 30px rgba(255,61,129,0.22);
}

.action-btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.1);
}

.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.86);
    backdrop-filter: blur(14px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal[style*="display: block"] {
    display: flex !important;
}

.modal-content {
    width: min(92vw, 940px);
    border-radius: 26px;
    overflow: hidden;
}

.video-player {
    width: 100%;
    background: #000;
}

.close-modal {
    position: absolute;
    right: 18px;
    top: 12px;
    font-size: 2rem;
    cursor: pointer;
    color: white;
    z-index: 10;
}

#infoToast {
    position: fixed;
    right: 22px;
    bottom: 22px;
    z-index: 9999;
    max-width: 330px;
    border-radius: 22px;
    padding: 18px;
    box-shadow: var(--shadow);
}

.toast-title {
    color: var(--gold);
    font-weight: 900;
}

.toast-close {
    background: transparent;
    border: 0;
    color: var(--muted);
    cursor: pointer;
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 90px 20px;
    color: var(--muted);
}

@media (max-width: 700px) {
    .topbar .container {
        height: auto;
        padding: 14px 18px;
        flex-direction: column;
        gap: 12px;
    }

    .hero {
        padding: 52px 16px 48px;
    }

    .videos-grid {
        grid-template-columns: 1fr;
    }

    .action-row {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<!-- ── TOAST ── -->
<div id="infoToast">
    <div class="toast-header">
        <span class="toast-title">
            <i class="fas fa-circle-info"></i> How it works
        </span>
        <button class="toast-close" onclick="dismissToast()" title="Dismiss">
            <i class="fas fa-xmark"></i>
        </button>
    </div>
    <div class="toast-row">
        <i class="fas fa-user-clock"></i>
        <span>
            <strong>Access</strong>
            Login is optional. You can browse and buy without an account.
        </span>
    </div>
</div>

<!-- ── TOPBAR ── -->
<div class="topbar">
    <div class="container">
        <a href="index.php" class="logo">
            <!-- Faceted diamond SVG icon -->
            <svg class="logo-icon" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
                <rect width="64" height="64" rx="13" fill="#0a0a0f"/>
                <path d="M32 8 L56 26 L46 56 L18 56 L8 26 Z"
                      fill="none" stroke="#d4a843" stroke-width="2"
                      stroke-linejoin="round" opacity="0.45"/>
                <path d="M32 8 L56 26 L32 38 L8 26 Z" fill="#d4a843" opacity="0.92"/>
                <path d="M32 38 L56 26 L46 56 Z"      fill="#b8861e" opacity="0.78"/>
                <path d="M32 38 L8 26 L18 56 Z"       fill="#c49430" opacity="0.62"/>
                <path d="M32 38 L46 56 L18 56 Z"      fill="#8a6010" opacity="0.52"/>
                <ellipse cx="32" cy="26" rx="4.5" ry="2.5" fill="white"
                         opacity="0.18" transform="rotate(-18 32 26)"/>
            </svg>
            <div class="logo-text">
                <span class="l-dark">DARK</span>
                <span class="l-velvet">VELVET</span>
                <span class="l-club">CLUB</span>
            </div>
        </a>
        <div class="nav-links">
            <a href="index.php"><i class="fas fa-house"></i> Home</a>
            <a href="#">Videos</a>
            <?php if ($usuarioLogado): ?>
                <a href="logout.php"><i class="fas fa-arrow-right-from-bracket"></i> Sign Out</a>
            <?php else: ?>
                <a href="login.php"><i class="fas fa-user"></i> Login</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── HERO ── -->
<div class="hero">
    <div class="hero-eyebrow"><i class="fas fa-gem"></i> Members-Only Collection</div>
    <h1>Dark<em>Velvet</em>Club</h1>
    <p>An exclusive vault of premium videos. Browse freely, buy instantly — no membership required.</p>
    <div class="hero-trust">
        <span class="trust-pill"><i class="fas fa-shield-halved"></i> Secure Purchase</span>
        <span class="trust-pill"><i class="fab fa-telegram"></i> Fast Delivery</span>
        <span class="trust-pill"><i class="fas fa-user-slash"></i> No Account Needed</span>
    </div>
</div>

<!-- ── MAIN ── -->
<div class="main-container">

    <button class="filter-toggle-btn" id="filterToggle">
        <i class="fas fa-sliders"></i>
        <span>Show Filters</span>
        <i class="fas fa-chevron-down"></i>
    </button>

    <div class="filters" id="filtersContainer">
        <h2><i class="fas fa-filter"></i> Search Filters</h2>
        <form method="get">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>Search Video</label>
                    <input type="text" name="busca" placeholder="Video name..."
                           value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label>Category</label>
                    <select name="categoria">
                        <option value="">All Categories</option>
                        <?php foreach ($lista_categorias as $cat): ?>
                            <option value="<?= $cat['id_categoria'] ?>"
                                <?= isset($_GET['categoria']) && $_GET['categoria'] == $cat['id_categoria'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nome_categoria']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Min Duration (min)</label>
                    <input type="number" name="duracao_min" placeholder="e.g. 5" min="0"
                           value="<?= htmlspecialchars($_GET['duracao_min'] ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label>Max Duration (min)</label>
                    <input type="number" name="duracao_max" placeholder="e.g. 60" min="0"
                           value="<?= htmlspecialchars($_GET['duracao_max'] ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label>Min Price ($)</label>
                    <input type="number" name="preco_min" placeholder="e.g. 10" min="0" step="0.01"
                           value="<?= htmlspecialchars($_GET['preco_min'] ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label>Max Price ($)</label>
                    <input type="number" name="preco_max" placeholder="e.g. 100" min="0" step="0.01"
                           value="<?= htmlspecialchars($_GET['preco_max'] ?? '') ?>">
                </div>
            </div>
            <div class="filter-buttons">
                <button type="submit" class="btn-filter btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <button type="button" onclick="window.location='index.php'" class="btn-filter btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </form>
    </div>

    <div class="count-bar">
        <div class="count">
            <i class="fas fa-video"></i>
            <strong><?= $total_encontrados ?></strong> video<?= $total_encontrados !== 1 ? 's' : '' ?> found
        </div>
    </div>

    <div class="videos-grid">
        <?php if (empty($videos)): ?>
            <div class="empty-state">
                <i class="fas fa-film"></i>
                <h3>No videos found</h3>
                <p>Try adjusting your filters or clear the search.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($videos as $v):
            $mensagem_telegram = urlencode(
                "Hello! I'm interested in purchasing:\n\n" .
                "🎬 Video: " . $v['nome_video'] . "\n" .
                "💰 Price: $" . number_format($v['preco'], 2) . "\n" .
                "⏱ Duration: " . ($v['duracao'] ?? 'N/A') . "\n\n" .
                "How do I proceed with payment?"
            );
            $link_telegram = $TELEGRAM_LINK . "?text=" . $mensagem_telegram;
        ?>
            <div class="video-card">
                <div class="video-thumbnail-wrapper">
                    <?php if (!empty($v['caminho_imagem'])): ?>
                        <img src="<?= htmlspecialchars($v['caminho_imagem']) ?>"
                             class="video-thumbnail"
                             alt="<?= htmlspecialchars($v['nome_video']) ?>"
                             loading="lazy">
                    <?php else: ?>
                        <div class="video-thumbnail" style="background:linear-gradient(135deg,#1a1a26 0%,#12121a 100%);display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-film" style="font-size:3rem;color:rgba(212,168,67,0.2);"></i>
                        </div>
                    <?php endif; ?>

                    <div class="price-badge">$<?= number_format($v['preco'], 2) ?></div>
                    <?php if (!empty($v['duracao'])): ?>
                        <div class="duration-badge">
                            <i class="far fa-clock"></i> <?= htmlspecialchars($v['duracao']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="video-info">
                    <h3 class="video-title"><?= htmlspecialchars($v['nome_video']) ?></h3>

                    <div class="video-stats">
                        <span><i class="fas fa-eye"></i> <?= number_format($v['visualizacoes']) ?> views</span>
                        <span class="online-badge"><i class="fas fa-circle"></i> Available</span>
                    </div>

                    <div class="action-buttons">
                        <div class="action-row">
                            <button onclick="abrirPreview(<?= $v['id_video'] ?>, '<?= addslashes($v['caminho_previa']) ?>')"
                                    class="action-btn btn-preview">
                                <i class="far fa-play-circle"></i> Preview
                            </button>
                            <a href="<?= $link_telegram ?>" target="_blank" rel="noopener"
                               class="action-btn btn-telegram">
                                <i class="fab fa-telegram"></i> Telegram
                            </a>
                        </div>
<a href="<?= $link_telegram ?>" target="_blank" rel="noopener"
   class="action-btn btn-buy">
    <i class="fas fa-paper-plane"></i> Send Message — $<?= number_format($v['preco'], 2) ?>
</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>



</div>

<!-- ── MODAL PREVIEW ── -->
<div id="modalPreview" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="fecharPreview()">&times;</span>
        <video id="videoPreview" class="video-player" controls playsinline>
            <source id="videoSource" src="" type="video/mp4">
        </video>
    </div>
</div>

<!-- PayPal temporariamente desativado

< <div id="paypalModal">
    <div class="paypal-modal-box">
        <h3 id="pm-title">Complete your purchase</h3>
        <div class="pm-video-name" id="pm-video-name"></div>
        <div class="pm-price" id="pm-price"></div>

        <div style="background:rgba(212,168,67,0.07);border:1px solid rgba(212,168,67,0.18);border-radius:10px;padding:12px 14px;margin-bottom:20px;font-size:0.82rem;color:var(--muted);line-height:1.6;">
            <div style="font-weight:700;color:var(--gold);margin-bottom:8px;font-size:0.85rem;">
                <i class="fas fa-circle-info" style="margin-right:5px;"></i> How does it work?
            </div>
            <div style="display:flex;flex-direction:column;gap:7px;">
                <span><i class="fab fa-paypal" style="color:var(--gold);width:16px;"></i> Click the PayPal button and complete your payment securely.</span>
                <span><i class="fab fa-telegram" style="color:var(--gold);width:16px;"></i> After payment, you'll be automatically redirected to our Telegram.</span>
                <span><i class="fas fa-bolt" style="color:var(--gold);width:16px;"></i> Share your transaction ID and receive your video access instantly.</span>
            </div>
        </div>

        <div id="paypal-button-container"></div>
        <div id="paypal-error-msg">
            <i class="fas fa-circle-exclamation"></i>
            Payment not completed. Please try again.
        </div>
        <div class="pm-cancel" onclick="fecharPayPal()">
            <i class="fas fa-xmark"></i> Cancel and go back
        </div>
    </div>
</div> -->

<script>

  /*  let paypalButtons = null;

function abrirPayPal(idVideo, nomeVideo, preco) {
    document.getElementById('pm-video-name').textContent = nomeVideo;
    document.getElementById('pm-price').textContent      = '$' + parseFloat(preco).toFixed(2);
    document.getElementById('paypal-error-msg').style.display = 'none';
    document.getElementById('paypalModal').classList.add('open');
    document.body.style.overflow = 'hidden';

    // Limpar botões anteriores se existirem
    document.getElementById('paypal-button-container').innerHTML = '';
    if (paypalButtons) {
        paypalButtons.close();
        paypalButtons = null;
    }

    paypalButtons = paypal.Buttons({
        // 1. Criar ordem no backend
        createOrder: async function () {
            try {
                const res = await fetch('/paypal/create-order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_video: idVideo }),
                });
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                return data.id; // order ID do PayPal
            } catch (err) {
                console.error('createOrder error:', err);
                mostrarErroPayPal();
            }
        },

        // 2. Após aprovação do utilizador — capturar
        onApprove: async function (data) {
            try {
                // Redireciona para o capture, que trata de tudo e redireciona para Telegram
                window.location.href = '/paypal/capture-order.php?token=' + data.orderID;
            } catch (err) {
                console.error('onApprove error:', err);
                mostrarErroPayPal();
            }
        },

        // 3. Cancelamento
        onCancel: function () {
            fecharPayPal();
        },

        // 4. Erro no widget PayPal
        onError: function (err) {
            console.error('PayPal error:', err);
            mostrarErroPayPal();
        },

        // Estilo do botão
        style: {
            layout: 'vertical',
            color:  'gold',
            shape:  'rect',
            label:  'paypal',
        },
    });

    paypalButtons.render('#paypal-button-container');
}

function fecharPayPal() {
    document.getElementById('paypalModal').classList.remove('open');
    document.body.style.overflow = 'auto';
    document.getElementById('paypal-button-container').innerHTML = '';
    if (paypalButtons) {
        paypalButtons.close();
        paypalButtons = null;
    }
}

function mostrarErroPayPal() {
    document.getElementById('paypal-error-msg').style.display = 'block';
}
    */

// Mensagem de feedback após retorno
window.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const payment = params.get('payment');
    if (payment === 'cancelled') {
        alert('Payment cancelled. You can try again at any time.');
    } else if (payment === 'failed') {
        alert('Payment failed. Please try again or contact support.');
    } else if (payment === 'error') {
        alert('A technical error occurred. Please try again.');
    }
});
// ── Toast ──
function dismissToast() {
    const toast = document.getElementById('infoToast');
    toast.style.transition = 'opacity 0.3s, transform 0.3s';
    toast.style.opacity    = '0';
    toast.style.transform  = 'translateY(24px)';
    setTimeout(() => toast.classList.add('hidden'), 300);
    sessionStorage.setItem('toastDismissed', '1');
}
window.addEventListener('DOMContentLoaded', () => {
    const toast = document.getElementById('infoToast');
    if (sessionStorage.getItem('toastDismissed')) {
        toast.classList.add('hidden');
    } else {
        toast.style.display = 'none';
        setTimeout(() => { toast.style.display = 'flex'; }, 1800);
    }
});

// ── Filter toggle ──
const filterToggle     = document.getElementById('filterToggle');
const filtersContainer = document.getElementById('filtersContainer');
filterToggle.addEventListener('click', function () {
    filtersContainer.classList.toggle('show');
    filterToggle.classList.toggle('active');
    const span = filterToggle.querySelector('span');
    span.textContent = filtersContainer.classList.contains('show') ? 'Hide Filters' : 'Show Filters';
});

// ── Preview modal ──
function abrirPreview(idVideo, caminho) {
    document.getElementById('modalPreview').style.display = 'block';
    document.getElementById('videoSource').src = caminho;
    document.getElementById('videoPreview').load();
    document.body.style.overflow = 'hidden';

    const fd = new FormData();
    fd.append('registrar_visualizacao', '1');
    fd.append('id_video', idVideo);
    fetch(window.location.href, { method: 'POST', body: fd })
        .catch(err => console.error('View register error:', err));
}
function fecharPreview() {
    document.getElementById('modalPreview').style.display = 'none';
    const player = document.getElementById('videoPreview');
    player.pause();
    player.currentTime = 0;
    document.body.style.overflow = 'auto';
}
window.onclick = function (e) {
    if (e.target === document.getElementById('modalPreview')) fecharPreview();
};
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') fecharPreview();
});
</script>

</body>
</html>