<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Inventory Architect</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #1b1b18;
            }
            .container {
                max-width: 600px;
                text-align: center;
                padding: 2rem;
            }
            .logo {
                font-size: 3rem;
                font-weight: 700;
                color: white;
                margin-bottom: 1rem;
                letter-spacing: -0.02em;
            }
            .tagline {
                font-size: 1.25rem;
                color: rgba(255, 255, 255, 0.9);
                margin-bottom: 3rem;
                font-weight: 400;
            }
            .actions {
                display: flex;
                gap: 1rem;
                justify-content: center;
                flex-wrap: wrap;
            }
            .btn {
                display: inline-block;
                padding: 0.75rem 2rem;
                background: white;
                color: #6366f1;
                text-decoration: none;
                border-radius: 0.5rem;
                font-weight: 600;
                transition: all 0.3s ease;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            }
            .btn-secondary {
                background: rgba(255, 255, 255, 0.2);
                color: white;
                backdrop-filter: blur(10px);
            }
            .btn-secondary:hover {
                background: rgba(255, 255, 255, 0.3);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1 class="logo">Inventory Architect</h1>
            <p class="tagline">Your complete card inventory management system</p>
            <div class="actions">
                @auth
                    <a href="{{ url('/admin') }}" class="btn">Go to Dashboard</a>
                @else
                    <a href="{{ url('/admin/login') }}" class="btn">Log In</a>
                @endauth
            </div>
        </div>
    </body>
</html>
