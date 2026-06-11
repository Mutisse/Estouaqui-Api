<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>EstouAqui — Documentação da API</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<style>
/* ─── TOKENS ─────────────────────────────────────────────────────────────── */
:root {
  --a:       #5B4BF5;
  --a2:      #9F7AEA;
  --a-l:     rgba(91,75,245,0.08);
  --ink:     #0A0A0F;
  --ink2:    #3D3D55;
  --muted:   #9898B0;
  --line:    rgba(0,0,0,0.07);
  --sur:     #FFFFFF;
  --bg:      #F4F4F8;
  --sidebar: #16163A;
  --r:       8px;
  --rl:      14px;
  --green:   #10B981;
  --gold:    #F59E0B;
  --red:     #EF4444;
  --blue:    #3B82F6;
}
*{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--ink);display:flex;min-height:100vh;font-size:15px;line-height:1.6;}

/* ─── SIDEBAR ─────────────────────────────────────────────────────────────── */
.sidebar{
  width:260px;flex-shrink:0;background:var(--sidebar);
  height:100vh;position:sticky;top:0;overflow-y:auto;
  display:flex;flex-direction:column;
  scrollbar-width:thin;scrollbar-color:rgba(255,255,255,0.1) transparent;
}
.sidebar::-webkit-scrollbar{width:4px;}
.sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.1);border-radius:2px;}

.sb-brand{padding:24px 20px 18px;border-bottom:1px solid rgba(255,255,255,0.07);flex-shrink:0;}
.sb-logo{display:flex;align-items:center;gap:10px;}
.sb-icon{width:34px;height:34px;border-radius:9px;background:var(--a);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:13px;font-weight:800;color:#fff;flex-shrink:0;}
.sb-name{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;color:#fff;}
.sb-tag{font-size:10px;color:rgba(255,255,255,0.4);margin-top:1px;}

.sb-search{padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.07);}
.sb-search input{width:100%;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);border-radius:var(--r);padding:8px 12px;font-size:12px;color:rgba(255,255,255,0.8);font-family:'DM Sans',sans-serif;outline:none;}
.sb-search input::placeholder{color:rgba(255,255,255,0.3);}
.sb-search input:focus{border-color:rgba(91,75,245,0.5);background:rgba(91,75,245,0.1);}

.sb-nav{flex:1;padding:12px 0;}
.sb-group{margin-bottom:4px;}
.sb-group-label{font-size:10px;color:rgba(255,255,255,0.28);letter-spacing:.1em;text-transform:uppercase;padding:10px 20px 5px;}
.sb-link{display:flex;align-items:center;justify-content:space-between;padding:7px 20px;font-size:12.5px;color:rgba(255,255,255,0.5);cursor:pointer;transition:all .15s;text-decoration:none;border-left:2px solid transparent;}
.sb-link:hover{background:rgba(255,255,255,0.05);color:rgba(255,255,255,.85);}
.sb-link.active{background:rgba(91,75,245,0.15);color:#fff;border-left-color:var(--a);}
.sb-count{background:rgba(255,255,255,0.1);color:rgba(255,255,255,0.6);font-size:10px;font-weight:600;padding:1px 6px;border-radius:10px;}

.sb-footer{padding:14px 16px;border-top:1px solid rgba(255,255,255,0.07);flex-shrink:0;}
.sb-version{font-size:11px;color:rgba(255,255,255,0.3);text-align:center;}

/* ─── MAIN ────────────────────────────────────────────────────────────────── */
.main{flex:1;min-width:0;display:flex;flex-direction:column;}

/* TOPBAR */
.topbar{
  background:var(--sur);border-bottom:1px solid var(--line);
  padding:0 32px;height:56px;display:flex;align-items:center;
  justify-content:space-between;position:sticky;top:0;z-index:50;flex-shrink:0;
}
.tb-breadcrumb{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);}
.tb-breadcrumb span{color:var(--ink);font-weight:500;}
.tb-pills{display:flex;align-items:center;gap:8px;}
.tb-pill{font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;border:1px solid var(--line);}
.tp-v{background:rgba(16,185,129,.1);color:darken(#10B981,10%);color:#065F46;border-color:rgba(16,185,129,.2);}
.tp-s{background:var(--a-l);color:var(--a);border-color:rgba(91,75,245,.2);}

/* CONTENT */
.content{flex:1;padding:40px 48px 80px;max-width:900px;}

/* HERO */
.hero{margin-bottom:48px;}
.hero-eyebrow{display:inline-flex;align-items:center;gap:8px;background:var(--a-l);border:1px solid rgba(91,75,245,.2);color:var(--a);font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;margin-bottom:16px;letter-spacing:.04em;text-transform:uppercase;}
.hero-dot{width:6px;height:6px;border-radius:50%;background:var(--green);box-shadow:0 0 6px var(--green);}
.hero h1{font-family:'Syne',sans-serif;font-size:36px;font-weight:800;color:var(--ink);line-height:1.15;letter-spacing:-.03em;margin-bottom:10px;}
.hero h1 span{color:var(--a);}
.hero p{font-size:15px;color:var(--ink2);line-height:1.65;max-width:580px;margin-bottom:24px;}
.hero-meta{display:flex;align-items:center;gap:16px;flex-wrap:wrap;}
.meta-item{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);}
.meta-item code{background:var(--bg);border:1px solid var(--line);padding:2px 8px;border-radius:4px;font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--ink2);}

/* STATS ROW */
.stats-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:40px;}
.stat-card{background:var(--sur);border:1px solid var(--line);border-radius:var(--rl);padding:16px;}
.stat-card__val{font-family:'Syne',sans-serif;font-size:28px;font-weight:700;color:var(--ink);line-height:1;}
.stat-card__lbl{font-size:11px;color:var(--muted);margin-top:4px;}
.stat-card--a .stat-card__val{color:var(--a);}
.stat-card--g .stat-card__val{color:var(--green);}
.stat-card--o .stat-card__val{color:var(--gold);}
.stat-card--r .stat-card__val{color:var(--red);}

/* SECTION */
.doc-section{margin-bottom:48px;}
.doc-section h2{font-family:'Syne',sans-serif;font-size:22px;font-weight:700;color:var(--ink);margin-bottom:6px;display:flex;align-items:center;gap:10px;}
.sec-num{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:7px;background:var(--a);font-size:12px;font-weight:700;color:#fff;flex-shrink:0;}
.doc-section .sec-desc{font-size:13.5px;color:var(--ink2);margin-bottom:18px;padding-left:38px;}
.doc-section h3{font-size:14px;font-weight:600;color:var(--ink);margin:20px 0 10px;padding-left:38px;display:flex;align-items:center;gap:8px;}
.doc-section h3::before{content:'';display:inline-block;width:3px;height:14px;background:var(--a);border-radius:2px;flex-shrink:0;}

/* ROUTE TABLE */
.route-table{width:100%;border-collapse:collapse;border:1px solid var(--line);border-radius:var(--rl);overflow:hidden;margin-bottom:8px;}
.route-table thead th{background:var(--sidebar);color:rgba(255,255,255,0.7);font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;padding:10px 14px;text-align:left;}
.route-table tbody tr{border-bottom:1px solid var(--line);transition:background .12s;}
.route-table tbody tr:last-child{border-bottom:none;}
.route-table tbody tr:hover{background:var(--a-l);}
.route-table td{padding:9px 14px;vertical-align:middle;}
.rt-method{display:inline-flex;align-items:center;justify-content:center;font-family:'JetBrains Mono',monospace;font-size:10px;font-weight:500;padding:3px 8px;border-radius:4px;min-width:58px;letter-spacing:.05em;}
.m-get   {background:#DBEAFE;color:#1E40AF;}
.m-post  {background:#D1FAE5;color:#065F46;}
.m-put   {background:#FEF3C7;color:#92400E;}
.m-patch {background:#FEF3C7;color:#92400E;}
.m-delete{background:#FEE2E2;color:#991B1B;}
.rt-path{font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--ink2);}
.rt-path .param{color:var(--a);}
.rt-desc{font-size:13px;color:var(--ink2);}
.rt-auth{display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:600;padding:2px 7px;border-radius:10px;}
.auth-pub  {background:rgba(16,185,129,.1);color:#065F46;}
.auth-priv {background:var(--a-l);color:var(--a);}
.auth-admin{background:rgba(239,68,68,.1);color:#991B1B;}

/* INFO CARD */
.info-card{background:var(--sur);border:1px solid var(--line);border-radius:var(--rl);overflow:hidden;margin-bottom:16px;}
.info-row{display:flex;align-items:baseline;border-bottom:1px solid var(--line);padding:10px 16px;gap:16px;}
.info-row:last-child{border-bottom:none;}
.info-key{font-size:12px;font-weight:600;color:var(--muted);min-width:140px;flex-shrink:0;}
.info-val{font-size:13px;color:var(--ink);}
.info-val code{background:var(--bg);border:1px solid var(--line);padding:1px 7px;border-radius:4px;font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--a);}

/* CODE BLOCK */
.code-block{background:var(--sidebar);border-radius:var(--rl);padding:16px 20px;margin:10px 0 16px;overflow-x:auto;}
.code-block pre{font-family:'JetBrains Mono',monospace;font-size:12px;color:rgba(255,255,255,0.8);line-height:1.6;white-space:pre;}
.code-block .c-key{color:#A78BFA;}
.code-block .c-str{color:#6EE7B7;}
.code-block .c-num{color:#FCD34D;}
.code-block .c-bool{color:#F9A8D4;}

/* HTTP CODES TABLE */
.codes-grid{display:grid;grid-template-columns:80px 200px 1fr;gap:0;border:1px solid var(--line);border-radius:var(--rl);overflow:hidden;}
.codes-grid .hdr{background:var(--sidebar);color:rgba(255,255,255,.6);font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;padding:10px 14px;}
.codes-grid .row-code,.codes-grid .row-status,.codes-grid .row-desc{padding:9px 14px;font-size:13px;border-top:1px solid var(--line);}
.codes-grid .row-code{font-family:'JetBrains Mono',monospace;font-weight:600;color:var(--a);}
.codes-grid .row-status{color:var(--ink2);}
.codes-grid .row-desc{color:var(--muted);}
.codes-grid .row-code:hover,.codes-grid .row-status:hover,.codes-grid .row-desc:hover{background:var(--a-l);}

/* LEGEND */
.legend{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.legend-item{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--ink2);}

/* DIVIDER */
.section-divider{height:1px;background:var(--line);margin:40px 0;}

/* SCROLL TOP */
.scroll-top{position:fixed;bottom:32px;right:32px;width:40px;height:40px;border-radius:50%;background:var(--a);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;opacity:0;transition:opacity .2s;pointer-events:none;z-index:99;}
.scroll-top.visible{opacity:1;pointer-events:auto;}

/* RESPONSIVE */
@media(max-width:900px){
  .sidebar{display:none;}
  .content{padding:24px 20px 60px;}
  .stats-row{grid-template-columns:repeat(2,1fr);}
  .hero h1{font-size:26px;}
}
</style>
</head>
<body>

<!-- ═══ SIDEBAR ════════════════════════════════════════════════════════════ -->
<aside class="sidebar">
  <div class="sb-brand">
    <div class="sb-logo">
      <div class="sb-icon">EA</div>
      <div>
        <div class="sb-name">EstouAqui</div>
        <div class="sb-tag">API Reference</div>
      </div>
    </div>
  </div>

  <div class="sb-search">
    <input type="text" id="searchInput" placeholder="🔍  Pesquisar endpoint..." oninput="filterRoutes(this.value)"/>
  </div>

  <nav class="sb-nav">
    <div class="sb-group">
      <div class="sb-group-label">Geral</div>
      <a class="sb-link active" href="#intro">Introdução</a>
      <a class="sb-link" href="#autenticacao">Autenticação</a>
      <a class="sb-link" href="#codigos">Códigos HTTP</a>
    </div>
    <div class="sb-group">
      <div class="sb-group-label">Públicas</div>
      <a class="sb-link" href="#publicas">Rotas Públicas <span class="sb-count">15</span></a>
    </div>
    <div class="sb-group">
      <div class="sb-group-label">Cliente</div>
      <a class="sb-link" href="#cliente-auth">Perfil &amp; Auth <span class="sb-count">15</span></a>
      <a class="sb-link" href="#cliente-pedidos">Pedidos <span class="sb-count">6</span></a>
      <a class="sb-link" href="#cliente-notif">Notificações <span class="sb-count">5</span></a>
      <a class="sb-link" href="#favoritos">Favoritos <span class="sb-count">6</span></a>
      <a class="sb-link" href="#chat">Chat <span class="sb-count">6</span></a>
      <a class="sb-link" href="#avaliacoes">Avaliações <span class="sb-count">6</span></a>
    </div>
    <div class="sb-group">
      <div class="sb-group-label">Prestador</div>
      <a class="sb-link" href="#prest-dash">Dashboard <span class="sb-count">4</span></a>
      <a class="sb-link" href="#prest-pedidos">Solicitações <span class="sb-count">11</span></a>
      <a class="sb-link" href="#prest-servicos">Serviços <span class="sb-count">5</span></a>
      <a class="sb-link" href="#prest-agenda">Agenda <span class="sb-count">4</span></a>
      <a class="sb-link" href="#prest-ganhos">Ganhos <span class="sb-count">4</span></a>
      <a class="sb-link" href="#prest-perfil">Perfil <span class="sb-count">13</span></a>
    </div>
    <div class="sb-group">
      <div class="sb-group-label">Administração</div>
      <a class="sb-link" href="#admin-dash">Dashboard <span class="sb-count">5</span></a>
      <a class="sb-link" href="#admin-util">Utilizadores <span class="sb-count">8</span></a>
      <a class="sb-link" href="#admin-prest">Prestadores <span class="sb-count">8</span></a>
      <a class="sb-link" href="#admin-cat">Categorias <span class="sb-count">7</span></a>
      <a class="sb-link" href="#admin-pedidos">Pedidos <span class="sb-count">9</span></a>
      <a class="sb-link" href="#admin-fin">Financeiro <span class="sb-count">11</span></a>
      <a class="sb-link" href="#admin-promo">Promoções <span class="sb-count">7</span></a>
      <a class="sb-link" href="#admin-notif">Notificações <span class="sb-count">8</span></a>
      <a class="sb-link" href="#admin-sup">Suporte <span class="sb-count">11</span></a>
      <a class="sb-link" href="#admin-rel">Relatórios <span class="sb-count">5</span></a>
      <a class="sb-link" href="#admin-sys">Sistema <span class="sb-count">14</span></a>
      <a class="sb-link" href="#admin-config">Configurações <span class="sb-count">11</span></a>
    </div>
  </nav>

  <div class="sb-footer">
    <div class="sb-version">v1.0 — Laravel Sanctum — JSON</div>
  </div>
</aside>

<!-- ═══ MAIN ════════════════════════════════════════════════════════════════ -->
<div class="main">
  <header class="topbar">
    <div class="tb-breadcrumb">API / <span>Documentação de Referência</span></div>
    <div class="tb-pills">
      <span class="tb-pill tp-v">v1.0</span>
      <span class="tb-pill tp-s">Sanctum</span>
    </div>
  </header>

  <div class="content" id="mainContent">

    <!-- HERO -->
    <div class="hero" id="intro">
      <div class="hero-eyebrow"><span class="hero-dot"></span> Documentação Activa</div>
      <h1>API <span>EstouAqui</span><br>Referência Completa</h1>
      <p>Documentação de todas as rotas REST da plataforma EstouAqui. Autenticação via Laravel Sanctum, respostas em JSON. Base URL: <code style="background:var(--bg);border:1px solid var(--line);padding:2px 8px;border-radius:4px;font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--a);">https://api.estouaqui.co.mz/api</code></p>
      <div class="hero-meta">
        <div class="meta-item"><span>🔐</span> <code>Bearer Token</code></div>
        <div class="meta-item"><span>📦</span> <code>application/json</code></div>
        <div class="meta-item"><span>⚡</span> <code>REST</code></div>
      </div>
    </div>

    <!-- STATS -->
    <div class="stats-row">
      <div class="stat-card stat-card--a"><div class="stat-card__val">160+</div><div class="stat-card__lbl">Total de Rotas</div></div>
      <div class="stat-card stat-card--g"><div class="stat-card__val">15</div><div class="stat-card__lbl">Rotas Públicas</div></div>
      <div class="stat-card stat-card--o"><div class="stat-card__val">60+</div><div class="stat-card__lbl">Rotas Autenticadas</div></div>
      <div class="stat-card stat-card--r"><div class="stat-card__val">85+</div><div class="stat-card__lbl">Rotas Admin</div></div>
    </div>

    <!-- LEGEND -->
    <div class="legend">
      <div class="legend-item"><span class="rt-method m-get">GET</span> Consulta</div>
      <div class="legend-item"><span class="rt-method m-post">POST</span> Criação</div>
      <div class="legend-item"><span class="rt-method m-put">PUT</span> Actualização</div>
      <div class="legend-item"><span class="rt-method m-patch">PATCH</span> Actualização parcial</div>
      <div class="legend-item"><span class="rt-method m-delete">DELETE</span> Eliminação</div>
    </div>

    <!-- ── AUTENTICAÇÃO ─────────────────────────────────────────────── -->
    <div class="doc-section" id="autenticacao">
      <h2><span class="sec-num">1</span> Autenticação</h2>
      <p class="sec-desc">A API usa Laravel Sanctum. Após login, inclua o token em todas as requisições autenticadas.</p>

      <div class="info-card" style="margin-left:38px;">
        <div class="info-row"><div class="info-key">Cabeçalho</div><div class="info-val"><code>Authorization: Bearer {token}</code></div></div>
        <div class="info-row"><div class="info-key">Content-Type</div><div class="info-val"><code>application/json</code></div></div>
        <div class="info-row"><div class="info-key">Accept</div><div class="info-val"><code>application/json</code></div></div>
        <div class="info-row"><div class="info-key">Base URL</div><div class="info-val"><code>https://api.estouaqui.co.mz/api</code></div></div>
      </div>

      <h3 style="margin-left:0">Exemplo de Resposta — Login</h3>
      <div class="code-block" style="margin-left:38px;">
        <pre><span class="c-key">"success"</span>: <span class="c-bool">true</span>,
<span class="c-key">"data"</span>: {
  <span class="c-key">"token"</span>: <span class="c-str">"1|abcdefgh1234567890..."</span>,
  <span class="c-key">"user"</span>: {
    <span class="c-key">"id"</span>: <span class="c-num">42</span>,
    <span class="c-key">"nome"</span>: <span class="c-str">"Maria Armando"</span>,
    <span class="c-key">"tipo"</span>: <span class="c-str">"cliente"</span>
  }
},
<span class="c-key">"message"</span>: <span class="c-str">"Login bem-sucedido"</span></pre>
      </div>
    </div>

    <div class="section-divider"></div>

    <!-- ── PÚBLICAS ────────────────────────────────────────────────── -->
    <div class="doc-section" id="publicas">
      <h2><span class="sec-num">2</span> Rotas Públicas</h2>
      <p class="sec-desc">Não requerem autenticação. Acessíveis por qualquer cliente.</p>
      <div style="padding-left:38px;">
        <table class="route-table" id="table-publicas">
          <thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead>
          <tbody>
            <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/auth/login</td><td class="rt-desc">Autenticação de utilizador</td></tr>
            <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/auth/register</td><td class="rt-desc">Registo de novo utilizador</td></tr>
            <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/auth/forgot-password</td><td class="rt-desc">Solicitar recuperação de senha</td></tr>
            <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/auth/reset-password/<span class="param">{token}</span></td><td class="rt-desc">Redefinir senha com token</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/categorias</td><td class="rt-desc">Listar todas as categorias</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/categorias/<span class="param">{id}</span></td><td class="rt-desc">Detalhe de uma categoria</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestadores</td><td class="rt-desc">Listar prestadores</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestadores/<span class="param">{id}</span></td><td class="rt-desc">Detalhe de um prestador</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestadores/proximos</td><td class="rt-desc">Prestadores próximos por geolocalização</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestadores/destaque</td><td class="rt-desc">Prestadores em destaque</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestadores/top</td><td class="rt-desc">Top prestadores melhor avaliados</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestadores/disponiveis</td><td class="rt-desc">Prestadores disponíveis agora</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestadores/categoria/<span class="param">{id}</span></td><td class="rt-desc">Prestadores por categoria</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/promocoes</td><td class="rt-desc">Listar promoções activas</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/health</td><td class="rt-desc">Estado da API (health check)</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="section-divider"></div>

    <!-- ── CLIENTE ─────────────────────────────────────────────────── -->
    <div class="doc-section" id="cliente-auth">
      <h2><span class="sec-num">3</span> Rotas do Cliente</h2>
      <p class="sec-desc">Requerem autenticação Bearer Token. Prefixo: <code>/api/</code></p>
      <h3>Perfil &amp; Autenticação</h3>
      <div style="padding-left:38px;">
        <table class="route-table">
          <thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead>
          <tbody>
            <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/auth/logout</td><td class="rt-desc">Terminar sessão</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/auth/verify</td><td class="rt-desc">Verificar token activo</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/auth/user</td><td class="rt-desc">Dados do utilizador autenticado</td></tr>
            <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/auth/user</td><td class="rt-desc">Actualizar perfil do utilizador</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/perfil</td><td class="rt-desc">Obter perfil do cliente</td></tr>
            <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/perfil</td><td class="rt-desc">Actualizar perfil do cliente</td></tr>
            <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/perfil/foto</td><td class="rt-desc">Upload de foto do cliente</td></tr>
            <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/perfil/foto</td><td class="rt-desc">Remover foto do cliente</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/perfil/enderecos</td><td class="rt-desc">Listar endereços do cliente</td></tr>
            <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/perfil/enderecos</td><td class="rt-desc">Adicionar endereço</td></tr>
            <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/perfil/enderecos/<span class="param">{id}</span></td><td class="rt-desc">Actualizar endereço</td></tr>
            <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/perfil/enderecos/<span class="param">{id}</span>/principal</td><td class="rt-desc">Definir endereço principal</td></tr>
            <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/perfil/enderecos/<span class="param">{id}</span></td><td class="rt-desc">Remover endereço</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/perfil/configuracoes</td><td class="rt-desc">Configurações do perfil</td></tr>
            <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/perfil/configuracoes</td><td class="rt-desc">Actualizar configurações do perfil</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div id="cliente-pedidos" style="padding-left:38px;margin-bottom:28px;">
      <h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:var(--a);border-radius:2px;"></span>Pedidos de Serviço</h3>
      <table class="route-table">
        <thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead>
        <tbody>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/cliente/pedidos</td><td class="rt-desc">Listar pedidos do cliente</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/cliente/pedidos/<span class="param">{id}</span></td><td class="rt-desc">Detalhe de um pedido</td></tr>
          <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/cliente/pedidos</td><td class="rt-desc">Criar novo pedido de serviço</td></tr>
          <tr><td><span class="rt-method m-patch">PATCH</span></td><td class="rt-path">/cliente/pedidos/<span class="param">{id}</span>/status</td><td class="rt-desc">Actualizar estado do pedido</td></tr>
          <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/cliente/pedidos/<span class="param">{id}</span></td><td class="rt-desc">Eliminar pedido</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/pedidos/<span class="param">{id}</span>/historico</td><td class="rt-desc">Histórico de um pedido</td></tr>
        </tbody>
      </table>
    </div>

    <div id="cliente-notif" style="padding-left:38px;margin-bottom:28px;">
      <h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:var(--a);border-radius:2px;"></span>Notificações</h3>
      <table class="route-table">
        <thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead>
        <tbody>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/cliente/notificacoes</td><td class="rt-desc">Listar notificações</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/cliente/notificacoes/nao-lidas</td><td class="rt-desc">Notificações não lidas</td></tr>
          <tr><td><span class="rt-method m-patch">PATCH</span></td><td class="rt-path">/cliente/notificacoes/<span class="param">{id}</span>/ler</td><td class="rt-desc">Marcar como lida</td></tr>
          <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/cliente/notificacoes/marcar-todas-lidas</td><td class="rt-desc">Marcar todas como lidas</td></tr>
          <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/cliente/notificacoes/<span class="param">{id}</span></td><td class="rt-desc">Eliminar notificação</td></tr>
        </tbody>
      </table>
    </div>

    <div id="favoritos" style="padding-left:38px;margin-bottom:28px;">
      <h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:var(--a);border-radius:2px;"></span>Favoritos</h3>
      <table class="route-table">
        <thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead>
        <tbody>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/favoritos</td><td class="rt-desc">Listar favoritos</td></tr>
          <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/favoritos</td><td class="rt-desc">Adicionar favorito</td></tr>
          <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/favoritos/<span class="param">{prestadorId}</span></td><td class="rt-desc">Remover favorito</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/favoritos/check/<span class="param">{prestadorId}</span></td><td class="rt-desc">Verificar se é favorito</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/favoritos/prestadores</td><td class="rt-desc">Prestadores favoritos</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/favoritos/count</td><td class="rt-desc">Contagem de favoritos</td></tr>
        </tbody>
      </table>
    </div>

    <div id="chat" style="padding-left:38px;margin-bottom:28px;">
      <h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:var(--a);border-radius:2px;"></span>Chat</h3>
      <table class="route-table">
        <thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead>
        <tbody>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/chat/chats</td><td class="rt-desc">Listar conversas</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/chat/mensagens/<span class="param">{prestadorId}</span></td><td class="rt-desc">Mensagens com um prestador</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/chat/mensagens/<span class="param">{prestadorId}</span>/novas</td><td class="rt-desc">Novas mensagens</td></tr>
          <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/chat/enviar/<span class="param">{prestadorId}</span></td><td class="rt-desc">Enviar mensagem</td></tr>
          <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/chat/marcar-lidas/<span class="param">{prestadorId}</span></td><td class="rt-desc">Marcar mensagens como lidas</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/chat/nao-lidas</td><td class="rt-desc">Total de mensagens não lidas</td></tr>
        </tbody>
      </table>
    </div>

    <div id="avaliacoes" style="padding-left:38px;margin-bottom:8px;">
      <h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:var(--a);border-radius:2px;"></span>Avaliações</h3>
      <table class="route-table">
        <thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead>
        <tbody>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/pedidos/<span class="param">{pedidoId}</span>/avaliacao</td><td class="rt-desc">Avaliação de um pedido</td></tr>
          <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/pedidos/<span class="param">{pedidoId}</span>/avaliar</td><td class="rt-desc">Criar avaliação</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestadores/<span class="param">{prestadorId}</span>/avaliacoes</td><td class="rt-desc">Avaliações de um prestador</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/clientes/<span class="param">{clienteId}</span>/avaliacoes</td><td class="rt-desc">Avaliações de um cliente</td></tr>
          <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/avaliacoes/<span class="param">{id}</span></td><td class="rt-desc">Actualizar avaliação</td></tr>
          <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/avaliacoes/<span class="param">{id}</span></td><td class="rt-desc">Eliminar avaliação</td></tr>
        </tbody>
      </table>
    </div>

    <div class="section-divider"></div>

    <!-- ── PRESTADOR ───────────────────────────────────────────────── -->
    <div class="doc-section" id="prest-dash">
      <h2><span class="sec-num">4</span> Rotas do Prestador</h2>
      <p class="sec-desc">Requerem autenticação Bearer Token. Prefixo: <code>/api/prestador/</code></p>
      <h3>Dashboard</h3>
      <div style="padding-left:38px;">
        <table class="route-table">
          <thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead>
          <tbody>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/dashboard/stats</td><td class="rt-desc">Estatísticas do dashboard</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/dashboard/ganhos</td><td class="rt-desc">Ganhos do prestador</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/dashboard/proximos-servicos</td><td class="rt-desc">Próximos serviços agendados</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/dashboard/avaliacoes-recentes</td><td class="rt-desc">Avaliações recentes</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div id="prest-pedidos" style="padding-left:38px;margin-bottom:28px;">
      <h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:var(--a);border-radius:2px;"></span>Solicitações e Pedidos</h3>
      <table class="route-table">
        <thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead>
        <tbody>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/solicitacoes</td><td class="rt-desc">Listar solicitações recebidas</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/solicitacoes/<span class="param">{id}</span></td><td class="rt-desc">Detalhe de uma solicitação</td></tr>
          <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/prestador/solicitacoes/<span class="param">{id}</span>/aceitar</td><td class="rt-desc">Aceitar solicitação</td></tr>
          <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/prestador/solicitacoes/<span class="param">{id}</span>/recusar</td><td class="rt-desc">Recusar solicitação</td></tr>
          <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/prestador/solicitacoes/<span class="param">{id}</span>/iniciar</td><td class="rt-desc">Iniciar serviço</td></tr>
          <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/prestador/solicitacoes/<span class="param">{id}</span>/concluir</td><td class="rt-desc">Concluir serviço</td></tr>
          <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/prestador/solicitacoes/<span class="param">{id}</span>/cancelar</td><td class="rt-desc">Cancelar serviço</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/pedidos-disponiveis</td><td class="rt-desc">Pedidos disponíveis no mercado</td></tr>
          <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/prestador/propostas</td><td class="rt-desc">Enviar proposta a um pedido</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/pedidos</td><td class="rt-desc">Histórico de pedidos</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/avaliacoes</td><td class="rt-desc">Avaliações recebidas</td></tr>
        </tbody>
      </table>
    </div>

    <div id="prest-servicos" style="padding-left:38px;margin-bottom:28px;">
      <h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:var(--a);border-radius:2px;"></span>Serviços</h3>
      <table class="route-table">
        <thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead>
        <tbody>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/servicos</td><td class="rt-desc">Listar serviços do prestador</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/servicos/<span class="param">{id}</span></td><td class="rt-desc">Detalhe de um serviço</td></tr>
          <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/prestador/servicos</td><td class="rt-desc">Criar novo serviço</td></tr>
          <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/prestador/servicos/<span class="param">{id}</span></td><td class="rt-desc">Actualizar serviço</td></tr>
          <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/prestador/servicos/<span class="param">{id}</span></td><td class="rt-desc">Eliminar serviço</td></tr>
        </tbody>
      </table>
    </div>

    <div id="prest-agenda" style="padding-left:38px;margin-bottom:28px;">
      <h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:var(--a);border-radius:2px;"></span>Agenda</h3>
      <table class="route-table">
        <thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead>
        <tbody>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/agenda</td><td class="rt-desc">Ver disponibilidade geral</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/agenda/<span class="param">{data}</span></td><td class="rt-desc">Disponibilidade numa data</td></tr>
          <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/prestador/agenda</td><td class="rt-desc">Actualizar disponibilidade</td></tr>
          <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/prestador/agenda/bloquear</td><td class="rt-desc">Bloquear horário específico</td></tr>
        </tbody>
      </table>
    </div>

    <div id="prest-ganhos" style="padding-left:38px;margin-bottom:28px;">
      <h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:var(--a);border-radius:2px;"></span>Ganhos e Saques</h3>
      <table class="route-table">
        <thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead>
        <tbody>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/ganhos</td><td class="rt-desc">Resumo de ganhos</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/ganhos/extrato</td><td class="rt-desc">Extrato detalhado</td></tr>
          <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/prestador/saques</td><td class="rt-desc">Solicitar saque</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/saques/historico</td><td class="rt-desc">Histórico de saques</td></tr>
        </tbody>
      </table>
    </div>

    <div id="prest-perfil" style="padding-left:38px;margin-bottom:8px;">
      <h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:var(--a);border-radius:2px;"></span>Perfil do Prestador</h3>
      <table class="route-table">
        <thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead>
        <tbody>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/perfil</td><td class="rt-desc">Obter perfil do prestador</td></tr>
          <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/prestador/perfil</td><td class="rt-desc">Actualizar perfil</td></tr>
          <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/prestador/perfil/foto</td><td class="rt-desc">Upload de foto</td></tr>
          <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/prestador/perfil/foto</td><td class="rt-desc">Remover foto</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/perfil/categorias</td><td class="rt-desc">Categorias do prestador</td></tr>
          <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/prestador/perfil/categorias</td><td class="rt-desc">Adicionar categoria</td></tr>
          <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/prestador/perfil/categorias/<span class="param">{id}</span></td><td class="rt-desc">Remover categoria</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/perfil/disponibilidade</td><td class="rt-desc">Ver disponibilidade</td></tr>
          <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/prestador/perfil/disponibilidade</td><td class="rt-desc">Actualizar disponibilidade</td></tr>
          <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/prestador/perfil/portfolio</td><td class="rt-desc">Adicionar item ao portfólio</td></tr>
          <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/prestador/perfil/portfolio</td><td class="rt-desc">Remover item do portfólio</td></tr>
          <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/prestador/perfil/stats</td><td class="rt-desc">Estatísticas do perfil</td></tr>
          <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/prestador/perfil/conta</td><td class="rt-desc">Excluir conta do prestador</td></tr>
        </tbody>
      </table>
    </div>

    <div class="section-divider"></div>

    <!-- ── ADMIN ───────────────────────────────────────────────────── -->
    <div class="doc-section" id="admin-dash">
      <h2><span class="sec-num" style="background:#EF4444">5</span> Administração</h2>
      <p class="sec-desc">Requerem token Bearer + papel admin/root. Prefixo: <code>/api/admin/</code></p>
      <h3>Dashboard</h3>
      <div style="padding-left:38px;">
        <table class="route-table">
          <thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead>
          <tbody>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/dashboard</td><td class="rt-desc">Dashboard principal</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/dashboard/estatisticas</td><td class="rt-desc">Estatísticas gerais</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/dashboard/atividade</td><td class="rt-desc">Actividade dos últimos 7 dias</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/dashboard/ultimos-utilizadores</td><td class="rt-desc">Últimos utilizadores registados</td></tr>
            <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/dashboard/servicos-recentes</td><td class="rt-desc">Serviços recentes</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Admin sub-sections as compact tables -->
    <div id="admin-util" style="padding-left:38px;margin-bottom:28px;"><h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:#EF4444;border-radius:2px;"></span>Utilizadores</h3>
      <table class="route-table"><thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead><tbody>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/utilizadores</td><td class="rt-desc">Listar utilizadores</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/utilizadores/<span class="param">{id}</span></td><td class="rt-desc">Detalhe de um utilizador</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/utilizadores</td><td class="rt-desc">Criar utilizador</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/utilizadores/<span class="param">{id}</span></td><td class="rt-desc">Actualizar utilizador</td></tr>
        <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/admin/utilizadores/<span class="param">{id}</span></td><td class="rt-desc">Eliminar utilizador</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/utilizadores/<span class="param">{id}</span>/verificar</td><td class="rt-desc">Verificar conta</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/utilizadores/<span class="param">{id}</span>/bloquear</td><td class="rt-desc">Bloquear utilizador</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/utilizadores/<span class="param">{id}</span>/desbloquear</td><td class="rt-desc">Desbloquear utilizador</td></tr>
      </tbody></table>
    </div>

    <div id="admin-prest" style="padding-left:38px;margin-bottom:28px;"><h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:#EF4444;border-radius:2px;"></span>Prestadores</h3>
      <table class="route-table"><thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead><tbody>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/prestadores</td><td class="rt-desc">Listar prestadores</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/prestadores/<span class="param">{id}</span></td><td class="rt-desc">Detalhe de um prestador</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/prestadores</td><td class="rt-desc">Criar prestador</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/prestadores/<span class="param">{id}</span></td><td class="rt-desc">Actualizar prestador</td></tr>
        <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/admin/prestadores/<span class="param">{id}</span></td><td class="rt-desc">Eliminar prestador</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/prestadores/<span class="param">{id}</span>/verificar</td><td class="rt-desc">Verificar prestador</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/prestadores/<span class="param">{id}</span>/ativar</td><td class="rt-desc">Activar conta</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/prestadores/<span class="param">{id}</span>/desativar</td><td class="rt-desc">Desactivar conta</td></tr>
      </tbody></table>
    </div>

    <div id="admin-cat" style="padding-left:38px;margin-bottom:28px;"><h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:#EF4444;border-radius:2px;"></span>Categorias</h3>
      <table class="route-table"><thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead><tbody>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/categorias</td><td class="rt-desc">Listar categorias</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/categorias</td><td class="rt-desc">Criar categoria</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/categorias/<span class="param">{id}</span></td><td class="rt-desc">Actualizar categoria</td></tr>
        <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/admin/categorias/<span class="param">{id}</span></td><td class="rt-desc">Eliminar categoria</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/categorias/<span class="param">{id}</span>/status</td><td class="rt-desc">Alternar estado</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/categorias/reordenar</td><td class="rt-desc">Reordenar categorias</td></tr>
      </tbody></table>
    </div>

    <div id="admin-pedidos" style="padding-left:38px;margin-bottom:28px;"><h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:#EF4444;border-radius:2px;"></span>Pedidos</h3>
      <table class="route-table"><thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead><tbody>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/pedidos</td><td class="rt-desc">Listar todos os pedidos</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/pedidos/<span class="param">{id}</span></td><td class="rt-desc">Detalhe de um pedido</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/pedidos/<span class="param">{id}</span>/status</td><td class="rt-desc">Actualizar estado</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/pedidos/<span class="param">{id}</span>/cancelar</td><td class="rt-desc">Cancelar pedido</td></tr>
        <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/admin/pedidos/<span class="param">{id}</span></td><td class="rt-desc">Eliminar pedido</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/pedidos/<span class="param">{id}</span>/propostas</td><td class="rt-desc">Propostas de um pedido</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/propostas/<span class="param">{id}</span>/aceitar</td><td class="rt-desc">Aceitar proposta</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/propostas/<span class="param">{id}</span>/recusar</td><td class="rt-desc">Recusar proposta</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/pedidos/estatisticas</td><td class="rt-desc">Estatísticas de pedidos</td></tr>
      </tbody></table>
    </div>

    <div id="admin-fin" style="padding-left:38px;margin-bottom:28px;"><h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:#EF4444;border-radius:2px;"></span>Financeiro e Saques</h3>
      <table class="route-table"><thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead><tbody>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/financeiro</td><td class="rt-desc">Visão geral financeira</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/financeiro/resumo</td><td class="rt-desc">Resumo financeiro</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/financeiro/transacoes</td><td class="rt-desc">Listar transacções</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/financeiro/transacoes</td><td class="rt-desc">Registar transacção</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/financeiro/transacoes/<span class="param">{id}</span>/status</td><td class="rt-desc">Actualizar estado de transacção</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/financeiro/ganhos-por-mes</td><td class="rt-desc">Ganhos mensais</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/financeiro/saques/pendentes</td><td class="rt-desc">Saques pendentes</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/financeiro/saques/<span class="param">{id}</span>/aprovar</td><td class="rt-desc">Aprovar saque</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/financeiro/saques/<span class="param">{id}</span>/concluir</td><td class="rt-desc">Concluir saque</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/financeiro/saques/<span class="param">{id}</span>/recusar</td><td class="rt-desc">Recusar saque</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/financeiro/exportar</td><td class="rt-desc">Exportar dados financeiros</td></tr>
      </tbody></table>
    </div>

    <div id="admin-promo" style="padding-left:38px;margin-bottom:28px;"><h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:#EF4444;border-radius:2px;"></span>Promoções</h3>
      <table class="route-table"><thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead><tbody>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/promocoes</td><td class="rt-desc">Listar promoções</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/promocoes/<span class="param">{id}</span></td><td class="rt-desc">Detalhe de uma promoção</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/promocoes</td><td class="rt-desc">Criar promoção</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/promocoes/<span class="param">{id}</span></td><td class="rt-desc">Actualizar promoção</td></tr>
        <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/admin/promocoes/<span class="param">{id}</span></td><td class="rt-desc">Eliminar promoção</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/promocoes/<span class="param">{id}</span>/status</td><td class="rt-desc">Alternar estado</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/promocoes/estatisticas</td><td class="rt-desc">Estatísticas de promoções</td></tr>
      </tbody></table>
    </div>

    <div id="admin-notif" style="padding-left:38px;margin-bottom:28px;"><h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:#EF4444;border-radius:2px;"></span>Notificações</h3>
      <table class="route-table"><thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead><tbody>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/notificacoes</td><td class="rt-desc">Listar notificações</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/notificacoes/<span class="param">{id}</span></td><td class="rt-desc">Detalhe de uma notificação</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/notificacoes/enviar</td><td class="rt-desc">Enviar notificação</td></tr>
        <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/admin/notificacoes/<span class="param">{id}</span></td><td class="rt-desc">Eliminar notificação</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/notificacoes/<span class="param">{id}</span>/marcar-lida</td><td class="rt-desc">Marcar como lida</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/notificacoes/marcar-todas-lidas</td><td class="rt-desc">Marcar todas como lidas</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/notificacoes/templates</td><td class="rt-desc">Templates de notificações</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/notificacoes/estatisticas</td><td class="rt-desc">Estatísticas de notificações</td></tr>
      </tbody></table>
    </div>

    <div id="admin-sup" style="padding-left:38px;margin-bottom:28px;"><h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:#EF4444;border-radius:2px;"></span>Suporte</h3>
      <table class="route-table"><thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead><tbody>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/suporte/tickets</td><td class="rt-desc">Listar tickets de suporte</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/suporte/tickets/<span class="param">{id}</span></td><td class="rt-desc">Detalhe de um ticket</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/suporte/tickets/<span class="param">{id}</span>/status</td><td class="rt-desc">Actualizar estado do ticket</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/suporte/tickets/<span class="param">{id}</span>/prioridade</td><td class="rt-desc">Actualizar prioridade</td></tr>
        <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/admin/suporte/tickets/<span class="param">{id}</span></td><td class="rt-desc">Eliminar ticket</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/suporte/tickets/<span class="param">{id}</span>/mensagens</td><td class="rt-desc">Mensagens de um ticket</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/suporte/tickets/<span class="param">{id}</span>/mensagens</td><td class="rt-desc">Enviar mensagem no ticket</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/suporte/tickets/<span class="param">{id}</span>/atribuir</td><td class="rt-desc">Atribuir ticket a admin</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/suporte/estatisticas</td><td class="rt-desc">Estatísticas de suporte</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/suporte/chat/tickets</td><td class="rt-desc">Tickets de chat</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/suporte/chat/tickets/<span class="param">{id}</span>/enviar</td><td class="rt-desc">Enviar mensagem de chat</td></tr>
      </tbody></table>
    </div>

    <div id="admin-rel" style="padding-left:38px;margin-bottom:28px;"><h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:#EF4444;border-radius:2px;"></span>Relatórios</h3>
      <table class="route-table"><thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead><tbody>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/relatorios/pedidos</td><td class="rt-desc">Relatório de pedidos</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/relatorios/financeiro</td><td class="rt-desc">Relatório financeiro</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/relatorios/prestadores</td><td class="rt-desc">Relatório de prestadores</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/relatorios/clientes</td><td class="rt-desc">Relatório de clientes</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/relatorios/<span class="param">{tipo}</span>/exportar</td><td class="rt-desc">Exportar relatório</td></tr>
      </tbody></table>
    </div>

    <div id="admin-sys" style="padding-left:38px;margin-bottom:28px;"><h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:#EF4444;border-radius:2px;"></span>Logs, Performance e Monitoramento</h3>
      <table class="route-table"><thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead><tbody>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/logs</td><td class="rt-desc">Listar logs do sistema</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/logs/estatisticas</td><td class="rt-desc">Estatísticas dos logs</td></tr>
        <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/admin/logs/limpar</td><td class="rt-desc">Limpar logs</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/logs/exportar</td><td class="rt-desc">Exportar logs</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/performance</td><td class="rt-desc">Dados de performance</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/performance/realtime</td><td class="rt-desc">Performance em tempo real</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/performance/historico</td><td class="rt-desc">Histórico de performance</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/monitoramento/status</td><td class="rt-desc">Estado dos serviços</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/monitoramento/alertas</td><td class="rt-desc">Alertas activos</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/monitoramento/metricas</td><td class="rt-desc">Métricas do sistema</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/backups</td><td class="rt-desc">Listar backups</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/backups</td><td class="rt-desc">Criar backup</td></tr>
        <tr><td><span class="rt-method m-post">POST</span></td><td class="rt-path">/admin/backups/<span class="param">{id}</span>/restaurar</td><td class="rt-desc">Restaurar backup</td></tr>
        <tr><td><span class="rt-method m-delete">DELETE</span></td><td class="rt-path">/admin/backups/<span class="param">{id}</span></td><td class="rt-desc">Eliminar backup</td></tr>
      </tbody></table>
    </div>

    <div id="admin-config" style="padding-left:38px;margin-bottom:8px;"><h3 style="padding-left:0;margin-bottom:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px;"><span style="display:inline-block;width:3px;height:14px;background:#EF4444;border-radius:2px;"></span>Configurações e Permissões</h3>
      <table class="route-table"><thead><tr><th style="width:90px">Método</th><th>Endpoint</th><th>Descrição</th></tr></thead><tbody>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/configuracoes/todas</td><td class="rt-desc">Todas as configurações</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/configuracoes/gerais</td><td class="rt-desc">Configurações gerais</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/configuracoes/gerais</td><td class="rt-desc">Actualizar configurações gerais</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/configuracoes/prestador</td><td class="rt-desc">Configurações de prestadores</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/configuracoes/prestador</td><td class="rt-desc">Actualizar configurações de prestadores</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/configuracoes/pagamento</td><td class="rt-desc">Configurações de pagamento</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/configuracoes/pagamento</td><td class="rt-desc">Actualizar configurações de pagamento</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/permissoes</td><td class="rt-desc">Listar permissões</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/permissoes/<span class="param">{id}</span></td><td class="rt-desc">Actualizar permissão</td></tr>
        <tr><td><span class="rt-method m-get">GET</span></td><td class="rt-path">/admin/roles</td><td class="rt-desc">Listar roles/papéis</td></tr>
        <tr><td><span class="rt-method m-put">PUT</span></td><td class="rt-path">/admin/roles/<span class="param">{id}</span></td><td class="rt-desc">Actualizar role</td></tr>
      </tbody></table>
    </div>

    <div class="section-divider"></div>

    <!-- ── HTTP CODES ──────────────────────────────────────────────── -->
    <div class="doc-section" id="codigos">
      <h2><span class="sec-num">6</span> Códigos de Resposta</h2>
      <p class="sec-desc">Códigos HTTP retornados pela API e o seu significado.</p>
      <div style="padding-left:38px;">
        <div class="codes-grid">
          <div class="hdr">Código</div><div class="hdr">Estado</div><div class="hdr">Significado</div>
          <div class="row-code">200</div><div class="row-status">OK</div><div class="row-desc">Pedido bem sucedido</div>
          <div class="row-code">201</div><div class="row-status">Created</div><div class="row-desc">Recurso criado com sucesso</div>
          <div class="row-code">204</div><div class="row-status">No Content</div><div class="row-desc">Sucesso sem corpo de resposta</div>
          <div class="row-code">400</div><div class="row-status">Bad Request</div><div class="row-desc">Parâmetros inválidos ou em falta</div>
          <div class="row-code">401</div><div class="row-status">Unauthorized</div><div class="row-desc">Token ausente, inválido ou expirado</div>
          <div class="row-code">403</div><div class="row-status">Forbidden</div><div class="row-desc">Sem permissão para aceder ao recurso</div>
          <div class="row-code">404</div><div class="row-status">Not Found</div><div class="row-desc">Recurso não encontrado</div>
          <div class="row-code">422</div><div class="row-status">Unprocessable</div><div class="row-desc">Erros de validação dos dados enviados</div>
          <div class="row-code">429</div><div class="row-status">Too Many Requests</div><div class="row-desc">Rate limit excedido — 60 req/min por IP</div>
          <div class="row-code">500</div><div class="row-status">Server Error</div><div class="row-desc">Erro interno do servidor</div>
        </div>
      </div>
    </div>

    <!-- FOOTER -->
    <div style="margin-top:48px;padding-top:20px;border-top:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      <div style="font-size:12px;color:var(--muted);">EstouAqui API Reference v1.0 — Maputo, Moçambique</div>
      <div style="font-size:12px;color:var(--muted);">Laravel Sanctum · JSON · REST</div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<button class="scroll-top" id="scrollTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Voltar ao topo">↑</button>

<script>
// Active sidebar link on scroll
const links = document.querySelectorAll('.sb-link[href^="#"]');
const observer = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      links.forEach(l => l.classList.remove('active'));
      const a = document.querySelector(`.sb-link[href="#${e.target.id}"]`);
      if (a) a.classList.add('active');
    }
  });
}, { rootMargin: '-20% 0px -75% 0px' });

document.querySelectorAll('[id]').forEach(el => observer.observe(el));

// Scroll top button
const scrollBtn = document.getElementById('scrollTop');
window.addEventListener('scroll', () => {
  scrollBtn.classList.toggle('visible', window.scrollY > 400);
});

// Search filter
function filterRoutes(q) {
  const term = q.toLowerCase().trim();
  document.querySelectorAll('.route-table tbody tr').forEach(row => {
    const txt = row.textContent.toLowerCase();
    row.style.display = (!term || txt.includes(term)) ? '' : 'none';
  });
}
</script>
</body>
</html>
