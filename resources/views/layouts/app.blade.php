<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', '幸运跳棋大冒险')</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
  <header class="topbar">
    <a class="brand" href="{{ route('game.index') }}">🎲 LUCKY JUMP</a>
    @auth
    <nav>
      <span class="user-tag">{{ auth()->user()->name }}</span>
      <a class="admin-link" href="{{ route('experience.center') }}">✦ 幸运中心</a>
      @if(auth()->user()->is_admin)
        <a class="admin-link" href="{{ route('admin.index') }}">⚙ 管理</a>
      @endif
      <form method="post" action="{{ route('logout') }}">
        @csrf
        <button class="link-btn">退出</button>
      </form>
    </nav>
    @endauth
  </header>

  <main>
    @if(session('success'))
      <div class="flash success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="flash error">{{ session('error') }}</div>
    @endif
    @if($errors->any())
      <div class="flash error">{{ $errors->first() }}</div>
    @endif
    @yield('content')
  </main>

  @stack('scripts')
</body>
</html>
