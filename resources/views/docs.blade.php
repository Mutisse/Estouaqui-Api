<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EstouAqui API — Documentação</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Syne:wght@400;500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0c0c0f;
    --surface: #13131a;
    --surface2: #1c1c26;
    --border: rgba(255,255,255,0.07);
    --border-hover: rgba(255,255,255,0.14);
    --accent: #7c6ef7;
    --accent-glow: rgba(124,110,247,0.15);
    --text: #f0eff8;
    --text-muted: #8b8a9a;
    --text-dim: #4e4d5e;
    --get: #34d399;
    --get-bg: rgba(52,211,153,0.1);
    --post: #60a5fa;
    --post-bg: rgba(96,165,250,0.1);
    --put: #fbbf24;
    --put-bg: rgba(251,191,36,0.1);
    --del: #f87171;
    --del-bg: rgba(248,113,113,0.1);
    --sidebar-w: 260px;
    --header-h: 60px;
    --font-display: 'Syne', sans-serif;
    --font-body: 'Inter', sans-serif;
    --font-mono: 'IBM Plex Mono', monospace;
  }

  * { margin: 0; padding: 0; box-sizing: border-box; }

  html { scroll-behavior: smooth; }

  body {
    font-family: var(--font-body);
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    overflow-x: hidden;
  }

  /* ─── SCROLLBAR ─── */
  ::-webkit-scrollbar { width: 4px; height: 4px; }
  ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: var(--border-hover); border-radius: 2px; }

  /* ─── TOPBAR ─── */
  .topbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: var(--header-h);
    background: rgba(12,12,15,0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    padding: 0 24px;
    gap: 20px;
    z-index: 200;
  }

  .logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
  }

  .logo-mark {
    width: 32px;
    height: 32px;
    background: var(--accent);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .logo-mark svg {
    width: 18px;
    height: 18px;
    fill: white;
  }

  .logo-text {
    font-family: var(--font-display);
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
    letter-spacing: -0.02em;
  }

  .topbar-divider {
    width: 1px;
    height: 20px;
    background: var(--border);
  }

  .base-url {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--text-muted);
    background: var(--surface);
    padding: 4px 12px;
    border-radius: 6px;
    border: 1px solid var(--border);
  }

  .topbar-right {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .status-indicator {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    color: var(--text-muted);
  }

  .status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--text-dim);
    position: relative;
    transition: background 0.4s;
  }

  .status-dot.online {
    background: var(--get);
    box-shadow: 0 0 0 3px rgba(52,211,153,0.2);
    animation: pulse-dot 2s infinite;
  }

  .status-dot.offline { background: var(--del); }

  @keyframes pulse-dot {
    0%, 100% { box-shadow: 0 0 0 3px rgba(52,211,153,0.2); }
    50% { box-shadow: 0 0 0 6px rgba(52,211,153,0.05); }
  }

  .version-tag {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--accent);
    background: var(--accent-glow);
    padding: 3px 10px;
    border-radius: 20px;
    border: 1px solid rgba(124,110,247,0.25);
  }

  /* ─── LAYOUT ─── */
  .layout {
    display: flex;
    padding-top: var(--header-h);
    min-height: 100vh;
  }

  /* ─── SIDEBAR ─── */
  .sidebar {
    width: var(--sidebar-w);
    position: fixed;
    top: var(--header-h);
    left: 0;
    bottom: 0;
    overflow-y: auto;
    border-right: 1px solid var(--border);
    background: var(--surface);
    padding: 20px 0;
    flex-shrink: 0;
  }

  .sidebar-search {
    margin: 0 14px 16px;
    position: relative;
  }

  .sidebar-search input {
    width: 100%;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 8px 12px 8px 34px;
    font-size: 12px;
    font-family: var(--font-body);
    color: var(--text);
    outline: none;
    transition: border-color 0.2s;
  }

  .sidebar-search input::placeholder { color: var(--text-dim); }

  .sidebar-search input:focus { border-color: var(--accent); }

  .sidebar-search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-dim);
    pointer-events: none;
  }

  .sidebar-group-label {
    padding: 8px 16px 4px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-dim);
    font-family: var(--font-display);
  }

  .sidebar-item {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 16px;
    font-size: 13px;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.15s;
    border-left: 2px solid transparent;
    user-select: none;
  }

  .sidebar-item:hover {
    color: var(--text);
    background: rgba(255,255,255,0.03);
  }

  .sidebar-item.active {
    color: var(--accent);
    background: var(--accent-glow);
    border-left-color: var(--accent);
  }

  .sidebar-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
    opacity: 0.7;
  }

  /* ─── MAIN CONTENT ─── */
  .main {
    margin-left: var(--sidebar-w);
    flex: 1;
    padding: 40px 48px;
    max-width: calc(100% - var(--sidebar-w));
  }

  .content-section { display: none; }
  .content-section.active { display: block; }

  /* ─── PAGE HEADER ─── */
  .page-header { margin-bottom: 36px; }

  .page-eyebrow {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--accent);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 8px;
  }

  .page-title {
    font-family: var(--font-display);
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: var(--text);
    margin-bottom: 8px;
  }

  .page-desc {
    font-size: 14px;
    color: var(--text-muted);
    line-height: 1.6;
  }

  /* ─── INFO GRID ─── */
  .info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 40px;
  }

  .info-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 16px 18px;
    transition: border-color 0.2s;
  }

  .info-card:hover { border-color: var(--border-hover); }

  .info-card-label {
    font-size: 11px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    margin-bottom: 6px;
    font-family: var(--font-display);
    font-weight: 600;
  }

  .info-card-value {
    font-family: var(--font-mono);
    font-size: 13px;
    color: var(--text);
  }

  /* ─── SECTION BLOCK ─── */
  .section-block { margin-bottom: 36px; }

  .section-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-dim);
    margin-bottom: 12px;
    font-family: var(--font-display);
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
  }

  /* ─── AUTH NOTICE ─── */
  .auth-notice {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: rgba(251,191,36,0.06);
    border: 1px solid rgba(251,191,36,0.2);
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 13px;
    color: #e9c56a;
    margin-bottom: 24px;
    line-height: 1.5;
  }

  .auth-notice code {
    font-family: var(--font-mono);
    font-size: 11px;
    background: rgba(251,191,36,0.12);
    padding: 2px 7px;
    border-radius: 4px;
    color: #fbbf24;
  }

  /* ─── SEARCH BAR ─── */
  .ep-search {
    position: relative;
    margin-bottom: 20px;
  }

  .ep-search input {
    width: 100%;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 16px 10px 40px;
    font-size: 13px;
    font-family: var(--font-body);
    color: var(--text);
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
  }

  .ep-search input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
  }

  .ep-search input::placeholder { color: var(--text-dim); }

  .ep-search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-dim);
    pointer-events: none;
  }

  /* ─── ENDPOINT CARD ─── */
  .endpoint-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    margin-bottom: 8px;
    overflow: hidden;
    transition: border-color 0.2s;
  }

  .endpoint-card:hover { border-color: var(--border-hover); }

  .endpoint-card.open { border-color: var(--border-hover); }

  .endpoint-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 18px;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;
  }

  .endpoint-header:hover { background: rgba(255,255,255,0.02); }

  .method-badge {
    font-family: var(--font-mono);
    font-size: 10px;
    font-weight: 500;
    padding: 4px 10px;
    border-radius: 5px;
    min-width: 52px;
    text-align: center;
    flex-shrink: 0;
    letter-spacing: 0.03em;
  }

  .GET  { background: var(--get-bg);  color: var(--get);  border: 1px solid rgba(52,211,153,0.2); }
  .POST { background: var(--post-bg); color: var(--post); border: 1px solid rgba(96,165,250,0.2); }
  .PUT  { background: var(--put-bg);  color: var(--put);  border: 1px solid rgba(251,191,36,0.2); }
  .DELETE { background: var(--del-bg); color: var(--del); border: 1px solid rgba(248,113,113,0.2); }

  .endpoint-path {
    font-family: var(--font-mono);
    font-size: 13px;
    color: var(--text);
    flex: 1;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .endpoint-desc {
    font-size: 12px;
    color: var(--text-dim);
    flex-shrink: 0;
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .chevron {
    flex-shrink: 0;
    color: var(--text-dim);
    font-size: 10px;
    transition: transform 0.2s;
    margin-left: 4px;
  }

  .chevron.open { transform: rotate(180deg); }

  /* ─── ENDPOINT BODY ─── */
  .endpoint-body {
    display: none;
    border-top: 1px solid var(--border);
    padding: 20px 18px;
    animation: slideDown 0.2s ease;
  }

  .endpoint-body.open { display: block; }

  @keyframes slideDown {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .body-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    color: var(--text-dim);
    margin: 14px 0 8px;
    font-family: var(--font-display);
  }

  .body-label:first-child { margin-top: 0; }

  .code-block {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 14px 16px;
    font-family: var(--font-mono);
    font-size: 12px;
    color: #c8c7d8;
    overflow-x: auto;
    white-space: pre;
    line-height: 1.7;
  }

  .code-block .key { color: #a78bfa; }
  .code-block .str { color: #6ee7b7; }
  .code-block .num { color: #fbbf24; }
  .code-block .comment { color: var(--text-dim); font-style: italic; }

  .params-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--border);
  }

  .params-table th {
    padding: 8px 14px;
    background: rgba(255,255,255,0.03);
    text-align: left;
    font-weight: 500;
    color: var(--text-muted);
    font-family: var(--font-display);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  .params-table td {
    padding: 9px 14px;
    border-top: 1px solid var(--border);
    color: var(--text-muted);
  }

  .params-table td:first-child {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--accent);
  }

  .params-table td:nth-child(2) {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--put);
  }

  /* ─── STATUS TABLE ─── */
  .status-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
  }

  .status-table th {
    padding: 10px 16px;
    background: rgba(255,255,255,0.03);
    text-align: left;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--text-dim);
    font-family: var(--font-display);
    font-weight: 600;
  }

  .status-table td {
    padding: 10px 16px;
    border-top: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 13px;
  }

  .status-table td:first-child {
    font-family: var(--font-mono);
    font-size: 12px;
  }

  .code-2xx { color: var(--get); }
  .code-4xx { color: var(--del); }

  /* ─── RESPONSE FORMAT ─── */
  .response-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }

  .response-card {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 16px;
  }

  .response-card-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 10px;
    font-family: var(--font-display);
    font-weight: 600;
  }

  .response-card.success .response-card-label { color: var(--get); }
  .response-card.error   .response-card-label { color: var(--del); }

  /* ─── COPY BUTTON ─── */
  .code-wrapper { position: relative; }

  .copy-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    background: var(--surface2);
    border: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 10px;
    font-family: var(--font-mono);
    padding: 4px 10px;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.15s;
  }

  .copy-btn:hover {
    background: var(--border);
    color: var(--text);
  }

  .copy-btn.copied {
    color: var(--get);
    border-color: rgba(52,211,153,0.3);
    background: rgba(52,211,153,0.07);
  }

  /* ─── FADE-IN ANIMATION ─── */
  .content-section.active {
    animation: fadeIn 0.2s ease;
  }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  /* ─── RESPONSIVE ─── */
  @media (max-width: 900px) {
    .sidebar { display: none; }
    .main { margin-left: 0; max-width: 100%; padding: 24px; }
    .info-grid { grid-template-columns: repeat(2,1fr); }
    .response-grid { grid-template-columns: 1fr; }
    .endpoint-desc { display: none; }
  }
</style>
</head>
<body>

<!-- TOP BAR -->
<header class="topbar">
  <a class="logo" href="#">
    <div class="logo-mark">
      <svg viewBox="0 0 18 18"><path d="M9 1L1.5 5.25v7.5L9 17l7.5-4.25v-7.5L9 1zm0 2.4L14.6 6.7v6.6L9 14.6 3.4 13.3V6.7L9 3.4z"/></svg>
    </div>
    <span class="logo-text">EstouAqui</span>
  </a>
  <div class="topbar-divider"></div>
  <span class="base-url">estouaqui-api.onrender.com/api</span>
  <div class="topbar-right">
    <div class="status-indicator">
      <div class="status-dot" id="statusDot"></div>
      <span id="statusText">Verificando...</span>
    </div>
    <span class="version-tag">v1.0.0</span>
  </div>
</header>

<div class="layout">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-search">
      <span class="sidebar-search-icon">
        <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><circle cx="6.5" cy="6.5" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M10.5 10.5L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      </span>
      <input type="text" placeholder="Buscar seção..." id="sidebarSearch">
    </div>

    <div class="sidebar-group-label">Overview</div>
    <div class="sidebar-item active" data-section="overview" onclick="showSection('overview', this)">
      <span class="sidebar-dot" style="background:#7c6ef7"></span> Informações gerais
    </div>
    <div class="sidebar-item" data-section="auth" onclick="showSection('auth', this)">
      <span class="sidebar-dot" style="background:#60a5fa"></span> Autenticação
    </div>

    <div class="sidebar-group-label" style="margin-top:8px">Público</div>
    <div class="sidebar-item" data-section="public" onclick="showSection('public', this)">
      <span class="sidebar-dot" style="background:#34d399"></span> Endpoints públicos
    </div>

    <div class="sidebar-group-label" style="margin-top:8px">Autenticado</div>
    <div class="sidebar-item" data-section="prestador" onclick="showSection('prestador', this)">
      <span class="sidebar-dot" style="background:#a78bfa"></span> Prestador
    </div>
    <div class="sidebar-item" data-section="cliente" onclick="showSection('cliente', this)">
      <span class="sidebar-dot" style="background:#f472b6"></span> Cliente
    </div>
    <div class="sidebar-item" data-section="usuario" onclick="showSection('usuario', this)">
      <span class="sidebar-dot" style="background:#67e8f9"></span> Usuário
    </div>
    <div class="sidebar-item" data-section="admin" onclick="showSection('admin', this)">
      <span class="sidebar-dot" style="background:#fbbf24"></span> Admin
    </div>

    <div class="sidebar-group-label" style="margin-top:8px">Sistema</div>
    <div class="sidebar-item" data-section="sistema" onclick="showSection('sistema', this)">
      <span class="sidebar-dot" style="background:#34d399"></span> Health & Métricas
    </div>
    <div class="sidebar-item" data-section="auxiliar" onclick="showSection('auxiliar', this)">
      <span class="sidebar-dot" style="background:#94a3b8"></span> Dados auxiliares
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">

    <!-- ═══ OVERVIEW ═══ -->
    <section id="sec-overview" class="content-section active">
      <div class="page-header">
        <div class="page-eyebrow">Documentação oficial</div>
        <div class="page-title">EstouAqui API</div>
        <div class="page-desc">Plataforma de conexão entre clientes e prestadores de serviços em Moçambique. API RESTful com autenticação Bearer Token via Laravel Sanctum.</div>
      </div>

      <div class="info-grid">
        <div class="info-card"><div class="info-card-label">Base URL</div><div class="info-card-value" style="font-size:11px">estouaqui-api.onrender.com/api</div></div>
        <div class="info-card"><div class="info-card-label">Formato</div><div class="info-card-value">JSON</div></div>
        <div class="info-card"><div class="info-card-label">Autenticação</div><div class="info-card-value">Bearer Token</div></div>
        <div class="info-card"><div class="info-card-label">Rate Limit</div><div class="info-card-value">60 req / min</div></div>
        <div class="info-card"><div class="info-card-label">CORS</div><div class="info-card-value">Habilitado</div></div>
        <div class="info-card"><div class="info-card-label">Versão</div><div class="info-card-value">1.0.0</div></div>
      </div>

      <div class="section-block">
        <div class="section-label">Formato de resposta</div>
        <div class="response-grid">
          <div class="response-card success">
            <div class="response-card-label">Sucesso</div>
            <div class="code-block">{\n  <span class="key">"success"</span>: <span class="num">true</span>,\n  <span class="key">"data"</span>: { ... }\n}</div>
          </div>
          <div class="response-card error">
            <div class="response-card-label">Erro</div>
            <div class="code-block">{\n  <span class="key">"success"</span>: <span class="num">false</span>,\n  <span class="key">"error"</span>: <span class="str">"Mensagem de erro"</span>\n}</div>
          </div>
        </div>
      </div>

      <div class="section-block">
        <div class="section-label">Códigos HTTP</div>
        <table class="status-table">
          <tr><th>Código</th><th>Significado</th></tr>
          <tr><td class="code-2xx">200</td><td>Sucesso</td></tr>
          <tr><td class="code-2xx">201</td><td>Criado com sucesso</td></tr>
          <tr><td class="code-4xx">401</td><td>Não autorizado — token inválido ou ausente</td></tr>
          <tr><td class="code-4xx">403</td><td>Proibido — sem permissão</td></tr>
          <tr><td class="code-4xx">404</td><td>Recurso não encontrado</td></tr>
          <tr><td class="code-4xx">422</td><td>Erro de validação</td></tr>
          <tr><td class="code-4xx">429</td><td>Rate limit excedido</td></tr>
        </table>
      </div>
    </section>

    <!-- ═══ AUTH ═══ -->
    <section id="sec-auth" class="content-section">
      <div class="page-header">
        <div class="page-eyebrow">Segurança</div>
        <div class="page-title">Autenticação</div>
        <div class="page-desc">Bearer token via Laravel Sanctum. Inclua o token no header de todas as requisições autenticadas.</div>
      </div>
      <div id="ep-auth"></div>
    </section>

    <!-- ═══ PUBLIC ═══ -->
    <section id="sec-public" class="content-section">
      <div class="page-header">
        <div class="page-eyebrow">Acesso público</div>
        <div class="page-title">Endpoints públicos</div>
        <div class="page-desc">Não requerem autenticação.</div>
      </div>
      <div class="ep-search">
        <span class="ep-search-icon"><svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="6.5" cy="6.5" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M10.5 10.5L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
        <input type="text" placeholder="Filtrar endpoints..." oninput="filterEp('ep-public', this.value)">
      </div>
      <div id="ep-public"></div>
    </section>

    <!-- ═══ PRESTADOR ═══ -->
    <section id="sec-prestador" class="content-section">
      <div class="page-header">
        <div class="page-eyebrow">Autenticado</div>
        <div class="page-title">Prestador</div>
        <div class="page-desc">Gerenciamento completo de serviços, disponibilidade, propostas e ganhos.</div>
      </div>
      <div class="auth-notice">🔒 Requer <code>Authorization: Bearer {token}</code> em todos os requests.</div>
      <div class="ep-search">
        <span class="ep-search-icon"><svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="6.5" cy="6.5" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M10.5 10.5L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
        <input type="text" placeholder="Filtrar endpoints..." oninput="filterEp('ep-prestador', this.value)">
      </div>
      <div id="ep-prestador"></div>
    </section>

    <!-- ═══ CLIENTE ═══ -->
    <section id="sec-cliente" class="content-section">
      <div class="page-header">
        <div class="page-eyebrow">Autenticado</div>
        <div class="page-title">Cliente</div>
        <div class="page-desc">Pedidos, favoritos, avaliações e propostas do lado do cliente.</div>
      </div>
      <div class="auth-notice">🔒 Requer <code>Authorization: Bearer {token}</code> em todos os requests.</div>
      <div id="ep-cliente"></div>
    </section>

    <!-- ═══ USUARIO ═══ -->
    <section id="sec-usuario" class="content-section">
      <div class="page-header">
        <div class="page-eyebrow">Autenticado</div>
        <div class="page-title">Usuário</div>
        <div class="page-desc">Perfil, senha, avatar, dashboard e notificações.</div>
      </div>
      <div class="auth-notice">🔒 Requer <code>Authorization: Bearer {token}</code> em todos os requests.</div>
      <div id="ep-usuario"></div>
    </section>

    <!-- ═══ ADMIN ═══ -->
    <section id="sec-admin" class="content-section">
      <div class="page-header">
        <div class="page-eyebrow">Administração</div>
        <div class="page-title">Admin</div>
        <div class="page-desc">Gestão de usuários, prestadores e categorias. Requer token de administrador.</div>
      </div>
      <div class="auth-notice">🔒 Requer token de administrador.</div>
      <div id="ep-admin"></div>
    </section>

    <!-- ═══ SISTEMA ═══ -->
    <section id="sec-sistema" class="content-section">
      <div class="page-header">
        <div class="page-eyebrow">Infraestrutura</div>
        <div class="page-title">Health & Métricas</div>
        <div class="page-desc">Verificação de status e monitoramento do sistema.</div>
      </div>
      <div id="ep-sistema"></div>
    </section>

    <!-- ═══ AUXILIAR ═══ -->
    <section id="sec-auxiliar" class="content-section">
      <div class="page-header">
        <div class="page-eyebrow">Dados estáticos</div>
        <div class="page-title">Dados auxiliares</div>
        <div class="page-desc">Listas de referência usadas em formulários e filtros.</div>
      </div>
      <div id="ep-auxiliar"></div>
    </section>

  </main>
</div>

<script>
const BASE = 'https://estouaqui-api.onrender.com/api';

const endpoints = {
  auth: [
    { method:'POST', path:'/login', desc:'Login do usuário',
      body:`{\n  "email": "usuario@email.com",\n  "password": "senha123"\n}`,
      response:`{\n  "success": true,\n  "data": {\n    "user": {\n      "id": 1,\n      "nome": "Usuário",\n      "email": "usuario@email.com",\n      "tipo": "prestador"\n    },\n    "token": "1|xxxxxxxxxxxxxxxx"\n  }\n}` },
    { method:'POST', path:'/auth/logout', desc:'Revogar token atual',
      response:`{\n  "success": true,\n  "message": "Logout realizado com sucesso"\n}` },
  ],
  public: [
    { method:'GET',  path:'/test',                    desc:'Verificar status da API' },
    { method:'POST', path:'/register/cliente',         desc:'Registrar novo cliente',
      body:`{\n  "nome": "João Silva",\n  "email": "joao@email.com",\n  "telefone": "841234567",\n  "password": "senha123",\n  "endereco": "Maputo"\n}` },
    { method:'POST', path:'/register/prestador',       desc:'Registrar novo prestador',
      body:`{\n  "nome": "Maria Santos",\n  "email": "maria@email.com",\n  "telefone": "841234568",\n  "password": "senha123",\n  "categorias": [1,2,3],\n  "latitude": -25.969248,\n  "longitude": 32.573174\n}` },
    { method:'GET',  path:'/prestadores',              desc:'Listar prestadores',
      params:[['categoria','int','Filtrar por categoria'],['busca','string','Buscar por nome']] },
    { method:'GET',  path:'/prestadores/proximos',     desc:'Prestadores próximos',
      params:[['latitude','float','Latitude do usuário (obrigatório)'],['longitude','float','Longitude do usuário (obrigatório)'],['radius','int','Raio em km (padrão: 10)']] },
    { method:'GET',  path:'/prestadores/{id}',         desc:'Detalhes de um prestador' },
    { method:'GET',  path:'/prestadores/categorias',   desc:'Categorias públicas' },
    { method:'GET',  path:'/categorias',               desc:'Listar todas as categorias' },
  ],
  prestador: [
    { method:'GET',    path:'/prestador/stats',                       desc:'Estatísticas do prestador',
      response:`{\n  "success": true,\n  "data": {\n    "pedidos_pendentes": 3,\n    "servicos_hoje": 2,\n    "avaliacao_media": 4.8,\n    "ganhos_mes": 12500,\n    "ticket_medio": 2500\n  }\n}` },
    { method:'GET',    path:'/prestador/ganhos',                      desc:'Resumo de ganhos',
      response:`{\n  "success": true,\n  "data": {\n    "total": 35000,\n    "mes": 12500,\n    "semana": 3000,\n    "pendente": 5000\n  }\n}` },
    { method:'GET',    path:'/prestador/servicos',                    desc:'Listar serviços' },
    { method:'POST',   path:'/prestador/servicos',                    desc:'Criar novo serviço',
      body:`{\n  "nome": "Instalação Elétrica",\n  "categoria_id": 1,\n  "preco": 3500,\n  "duracao": 120,\n  "descricao": "Instalação completa"\n}` },
    { method:'GET',    path:'/prestador/categorias',                  desc:'Minhas categorias' },
    { method:'POST',   path:'/prestador/categorias/{categoriaId}',    desc:'Adicionar categoria' },
    { method:'DELETE', path:'/prestador/categorias/{categoriaId}',    desc:'Remover categoria' },
    { method:'GET',    path:'/prestador/disponibilidade',             desc:'Ver disponibilidade' },
    { method:'PUT',    path:'/prestador/disponibilidade',             desc:'Atualizar disponibilidade',
      body:`{\n  "horarios_padrao": {\n    "segunda": ["08:00","09:00","10:00"],\n    "terca":   ["08:00","09:00","10:00"]\n  }\n}` },
    { method:'GET',    path:'/prestador/solicitacoes',                desc:'Minhas solicitações' },
    { method:'PUT',    path:'/prestador/solicitacoes/{id}/aceitar',   desc:'Aceitar solicitação' },
    { method:'PUT',    path:'/prestador/solicitacoes/{id}/recusar',   desc:'Recusar solicitação' },
    { method:'GET',    path:'/prestador/proximos-servicos',           desc:'Próximos serviços agendados' },
    { method:'GET',    path:'/prestador/avaliacoes/recentes',         desc:'Avaliações recentes' },
    { method:'GET',    path:'/prestador/saques',                      desc:'Histórico de saques' },
    { method:'POST',   path:'/prestador/saques',                      desc:'Solicitar saque',
      body:`{\n  "valor": 5000,\n  "metodo": "mpesa",\n  "conta": "841234567"\n}` },
    { method:'GET',    path:'/prestador/intervalos',                  desc:'Meus intervalos' },
    { method:'POST',   path:'/prestador/intervalos',                  desc:'Criar intervalo de pausa',
      body:`{\n  "dias": ["segunda","terca"],\n  "inicio": "12:00",\n  "fim": "13:00",\n  "descricao": "Horário de almoço"\n}` },
    { method:'GET',    path:'/prestador/pedidos-disponiveis',         desc:'Pedidos disponíveis na área' },
    { method:'POST',   path:'/prestador/propostas',                   desc:'Enviar proposta',
      body:`{\n  "pedido_id": 1,\n  "valor": 3500,\n  "mensagem": "Posso realizar o serviço amanhã"\n}` },
    { method:'GET',    path:'/prestador/propostas',                   desc:'Minhas propostas enviadas' },
    { method:'POST',   path:'/prestador/clear-cache',                 desc:'Limpar cache do prestador' },
  ],
  cliente: [
    { method:'POST',   path:'/cliente/pedidos',                       desc:'Criar novo pedido',
      body:`{\n  "servico_id": 1,\n  "prestador_id": 280546,\n  "data": "2026-05-10",\n  "endereco": "Av. 24 de Julho, 123"\n}` },
    { method:'GET',    path:'/cliente/pedidos/meus-pedidos',          desc:'Listar meus pedidos' },
    { method:'PUT',    path:'/cliente/pedidos/{id}/cancelar',         desc:'Cancelar pedido' },
    { method:'POST',   path:'/cliente/avaliacoes',                    desc:'Avaliar serviço recebido',
      body:`{\n  "pedido_id": 1,\n  "nota": 5,\n  "comentario": "Excelente serviço!"\n}` },
    { method:'GET',    path:'/cliente/favoritos',                     desc:'Meus prestadores favoritos' },
    { method:'POST',   path:'/cliente/favoritos/{prestadorId}',       desc:'Adicionar favorito' },
    { method:'DELETE', path:'/cliente/favoritos/{prestadorId}',       desc:'Remover favorito' },
    { method:'GET',    path:'/cliente/propostas',                     desc:'Propostas recebidas' },
    { method:'PUT',    path:'/cliente/propostas/{id}/aceitar',        desc:'Aceitar proposta' },
    { method:'PUT',    path:'/cliente/propostas/{id}/recusar',        desc:'Recusar proposta' },
  ],
  usuario: [
    { method:'GET',  path:'/me',                desc:'Ver meu perfil' },
    { method:'PUT',  path:'/me',                desc:'Atualizar perfil',
      body:`{\n  "nome": "Novo Nome",\n  "telefone": "841234567"\n}` },
    { method:'POST', path:'/avatar',            desc:'Atualizar foto de perfil',
      body:`// multipart/form-data\nfoto: <arquivo de imagem>` },
    { method:'PUT',  path:'/password',          desc:'Alterar senha',
      body:`{\n  "current_password": "senha_antiga",\n  "new_password": "nova_senha",\n  "confirm_password": "nova_senha"\n}` },
    { method:'GET',  path:'/dashboard',         desc:'Dashboard do usuário' },
    { method:'GET',  path:'/notifications',     desc:'Minhas notificações' },
    { method:'GET',  path:'/activities/recent', desc:'Atividades recentes' },
  ],
  admin: [
    { method:'GET', path:'/admin/dashboard',              desc:'Dashboard administrativo' },
    { method:'GET', path:'/admin/users',                  desc:'Listar todos os usuários' },
    { method:'GET', path:'/admin/prestadores',            desc:'Listar todos os prestadores' },
    { method:'GET', path:'/admin/prestadores/pendentes',  desc:'Prestadores aguardando aprovação' },
    { method:'PUT', path:'/admin/prestadores/{id}/aprovar', desc:'Aprovar prestador' },
    { method:'GET', path:'/admin/categorias',             desc:'Listar categorias (admin)' },
    { method:'POST',path:'/admin/categorias',             desc:'Criar categoria',
      body:`{\n  "nome": "Eletricista",\n  "descricao": "Serviços elétricos",\n  "icone": "bolt",\n  "cor": "primary"\n}` },
    { method:'PUT', path:'/admin/categorias/{id}',        desc:'Atualizar categoria',
      body:`{\n  "nome": "Eletricista Profissional"\n}` },
  ],
  sistema: [
    { method:'GET', path:'/system/health',  desc:'Health check da API',
      response:`{\n  "success": true,\n  "message": "API funcionando",\n  "timestamp": "2026-05-02T00:00:00.000000Z",\n  "cache_driver": "redis"\n}` },
    { method:'GET', path:'/system/metrics', desc:'Métricas do sistema (admin)' },
  ],
  auxiliar: [
    { method:'GET', path:'/auxiliar/dias-semana',     desc:'Lista de dias da semana' },
    { method:'GET', path:'/auxiliar/horarios-padrao', desc:'Horários padrão disponíveis' },
    { method:'GET', path:'/public/servico-tipos',     desc:'Tipos de serviço' },
    { method:'GET', path:'/public/raio-opcoes',       desc:'Opções de raio de busca' },
  ],
};

function buildEp(list, containerId) {
  const c = document.getElementById(containerId);
  if (!c) return;
  c.innerHTML = '';
  list.forEach(ep => {
    const card = document.createElement('div');
    card.className = 'endpoint-card';
    card.dataset.search = (ep.method + ep.path + ep.desc).toLowerCase();

    const curlPath = ep.path.replace(/{[^}]+}/g, '1');
    const isAuth = ['auth','prestador','cliente','usuario','admin'].some(s => containerId.includes(s));
    const authHeader = isAuth && ep.path !== '/login' ? ` \\\n  -H "Authorization: Bearer {seu_token}"` : '';
    const curl = `curl -X ${ep.method} "${BASE}${curlPath}"${authHeader}${ep.method !== 'GET' ? ` \\\n  -H "Content-Type: application/json"` : ''}${ep.body && !ep.body.startsWith('//') ? ` \\\n  -d '${ep.body.replace(/\n/g,'').replace(/  /g,' ')}'` : ''}`;

    let bodyHTML = '';
    if (ep.params) {
      bodyHTML += `<div class="body-label">Parâmetros de query</div>
      <table class="params-table">
        <tr><th>Parâmetro</th><th>Tipo</th><th>Descrição</th></tr>
        ${ep.params.map(p => `<tr><td>${p[0]}</td><td>${p[1]}</td><td>${p[2]}</td></tr>`).join('')}
      </table>`;
    }
    if (ep.body) {
      bodyHTML += `<div class="body-label">Request body</div>
      <div class="code-wrapper">
        <div class="code-block">${ep.body}</div>
        <button class="copy-btn" onclick="copyCode(this, \`${ep.body.replace(/`/g,'\\`')}\`)">copiar</button>
      </div>`;
    }
    if (ep.response) {
      bodyHTML += `<div class="body-label">Resposta de sucesso</div>
      <div class="code-wrapper">
        <div class="code-block">${ep.response}</div>
        <button class="copy-btn" onclick="copyCode(this, \`${ep.response.replace(/`/g,'\\`')}\`)">copiar</button>
      </div>`;
    }
    bodyHTML += `<div class="body-label">Exemplo cURL</div>
    <div class="code-wrapper">
      <div class="code-block">${curl}</div>
      <button class="copy-btn" onclick="copyCode(this, \`${curl.replace(/`/g,'\\`')}\`)">copiar</button>
    </div>`;

    card.innerHTML = `
      <div class="endpoint-header" onclick="toggleCard(this)">
        <span class="method-badge ${ep.method}">${ep.method}</span>
        <span class="endpoint-path">${ep.path}</span>
        <span class="endpoint-desc">${ep.desc}</span>
        <svg class="chevron" width="10" height="10" viewBox="0 0 10 10" fill="none">
          <path d="M2 3.5L5 6.5L8 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </div>
      <div class="endpoint-body">${bodyHTML}</div>
    `;
    c.appendChild(card);
  });
}

function toggleCard(header) {
  const body = header.nextElementSibling;
  const chevron = header.querySelector('.chevron');
  const card = header.parentElement;
  const isOpen = body.classList.contains('open');
  body.classList.toggle('open', !isOpen);
  chevron.classList.toggle('open', !isOpen);
  card.classList.toggle('open', !isOpen);
}

function filterEp(containerId, q) {
  const lower = q.toLowerCase();
  document.querySelectorAll(`#${containerId} .endpoint-card`).forEach(card => {
    card.style.display = card.dataset.search.includes(lower) ? '' : 'none';
  });
}

function showSection(name, el) {
  document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.sidebar-item').forEach(s => s.classList.remove('active'));
  const sec = document.getElementById('sec-' + name);
  if (sec) sec.classList.add('active');
  if (el) el.classList.add('active');
}

function copyCode(btn, text) {
  navigator.clipboard.writeText(text).then(() => {
    btn.textContent = 'copiado!';
    btn.classList.add('copied');
    setTimeout(() => { btn.textContent = 'copiar'; btn.classList.remove('copied'); }, 1800);
  });
}

// Sidebar search
document.getElementById('sidebarSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.sidebar-item').forEach(item => {
    item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
  document.querySelectorAll('.sidebar-group-label').forEach(l => l.style.display = q ? 'none' : '');
});

// Build all
Object.entries(endpoints).forEach(([key, list]) => buildEp(list, `ep-${key}`));

// Status check
fetch(BASE + '/test')
  .then(r => r.json())
  .then(() => {
    document.getElementById('statusDot').classList.add('online');
    document.getElementById('statusText').textContent = 'Online';
  })
  .catch(() => {
    document.getElementById('statusDot').classList.add('offline');
    document.getElementById('statusText').textContent = 'Offline';
  });
</script>
</body>
</html>
