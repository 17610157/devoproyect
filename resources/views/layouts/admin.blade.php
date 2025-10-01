<!doctype html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Panel</title>

  {{-- Bootstrap 5 + Icons (opcional) --}}
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  @livewireStyles
</head>
<body>
  <div class="container py-3">
    @yield('content')   {{-- si usas Blade con @extends --}}
    {{ $slot ?? '' }}   {{-- si usas Livewire #[Layout('layouts.admin')] --}}
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  @livewireScripts
</body>
</html>
