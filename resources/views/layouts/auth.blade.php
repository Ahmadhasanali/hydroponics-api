<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Auth') - Hydroponic Farm Management System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/auth.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-[radial-gradient(circle_at_top_left,_#1a1c1e_0%,_#0d0e10_55%,_#040506_100%)] text-white antialiased">
    @yield('content')
</body>
</html>
