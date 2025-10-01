<!doctype html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Panel de Cargas</title>

  {{-- Bootstrap 5 + Icons (CDN) --}}
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  @livewireStyles

  <style>
    :root{
      --sidebar-width: 260px;
    }
    body{ overflow-x:hidden; }
    .layout{
      display:grid;
      grid-template-columns: var(--sidebar-width) 1fr;
      min-height: 100dvh;
      background: #111;
    }
    .sidebar{
      background: #0d0f12;
      border-right: 1px solid rgba(255,255,255,.08);
      position: sticky; top:0; height: 100dvh;
      padding: 1rem .75rem;
    }
    .brand{
      display:flex; align-items:center; gap:.6rem; padding:.4rem .6rem;
      margin-bottom: .75rem; border-radius:.66rem; background:#14171c;
    }
    .brand i{ font-size:1.3rem; }
    .nav-sidebar .nav-link{
      color:#cbd3df; border-radius:.6rem; padding:.55rem .7rem; display:flex; align-items:center; gap:.6rem;
    }
    .nav-sidebar .nav-link:hover{ background:#1b2028; color:#fff; }
    .nav-sidebar .nav-link.active{ background:#2a3340; color:#fff; }
    .content{
      padding: 1rem 1rem 2rem 1rem;
    }
    .topbar{
      position: sticky; top:0; z-index: 10;
      background: #0e1116; border-bottom:1px solid rgba(255,255,255,.08);
      padding:.6rem .9rem; margin: -1rem -1rem 1rem -1rem;
      display:flex; align-items:center; justify-content:space-between;
    }
    .card{ background:#12161c; border-color: rgba(255,255,255,.06); }
    .table{ --bs-table-bg: transparent; }
    .badge{ font-weight:600; }
    /* Responsive sidebar */
    @media (max-width: 992px){
      .layout{ grid-template-columns: 0 1fr; }
      .layout.show-sidebar{ grid-template-columns: var(--sidebar-width) 1fr; }
      .sidebar{ position: fixed; left: -100%; width: var(--sidebar-width); z-index: 20;}
      .layout.show-sidebar .sidebar{ left: 0; }
      .content{ position: relative; z-index: 10;}
    }
  </style>
</head>
<body>

<div id="appLayout" class="layout">
  {{-- Sidebar --}}
  <aside class="sidebar">
    <div class="brand">
      <i class="bi bi-boxes text-primary"></i>
      <div class="fw-semibold">Administrador</div>
    </div>
    <nav class="nav flex-column nav-sidebar">
      <a href="#resumen" class="nav-link active"><i class="bi bi-speedometer"></i> Resumen</a>
      <a href="#subir" class="nav-link"><i class="bi bi-upload"></i> Subir archivos</a>
      <a href="#historial" class="nav-link"><i class="bi bi-table"></i> Historial</a>
      <a href="#ayuda" class="nav-link"><i class="bi bi-question-circle"></i> Ayuda</a>
    </nav>
  </aside>

  {{-- Content --}}
  <main class="content">
    <div class="topbar">
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-light d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
        <span class="fw-semibold">Panel de Cargas</span>
      </div>
      <div class="small text-muted">Livewire • Cola database • Excel</div>
    </div>

    {{-- AQUÍ SE MONTA TU PÁGINA ÚNICA --}}
    @yield('content')
  </main>
</div>

{{-- Bootstrap JS --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

@livewireScripts

<script>
  // Toggle sidebar en móviles
  const layout = document.getElementById('appLayout');
  const btn = document.getElementById('sidebarToggle');
  btn?.addEventListener('click', ()=> layout.classList.toggle('show-sidebar'));

  // Anclar navegación (resaltar activo simple)
  const links = document.querySelectorAll('.nav-sidebar .nav-link');
  links.forEach(a => a.addEventListener('click', ()=>{
    links.forEach(l => l.classList.remove('active'));
    a.classList.add('active');
    if (window.innerWidth < 992) layout.classList.remove('show-sidebar');
  }));

  // Eventos de modales desde Livewire (v3)
  window.addEventListener('open-modal', (e) => {
    const id = e.detail.id;
    const el = document.getElementById(id);
    if (el) new bootstrap.Modal(el).show();
  });
  window.addEventListener('close-modal', (e) => {
    const id = e.detail.id;
    const el = document.getElementById(id);
    if (el) bootstrap.Modal.getInstance(el)?.hide();
  });
</script>
</body>
</html>
