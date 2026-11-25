<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Access Restricted — Laleen</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: radial-gradient(circle at top, #281b1e 0%, #140d0f 65%, #0e090a 100%);
            color: #f8efe6;
            font-family: 'Poppins', sans-serif;
        }
        .card {
            width: 100%;
            max-width: 440px;
            background: linear-gradient(180deg, rgba(34, 22, 27, 0.94) 0%, rgba(21, 14, 18, 0.98) 100%);
            border: 1px solid rgba(229, 184, 168, 0.18);
            border-radius: 18px;
            padding: 32px 28px;
            text-align: center;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.55);
        }
        .code {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.1em;
            color: #fda4af;
            background: rgba(253, 164, 175, 0.12);
            border: 1px solid rgba(253, 164, 175, 0.3);
            border-radius: 999px;
            padding: 4px 12px;
            margin-bottom: 16px;
        }
        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 700;
            color: #f8efe6;
            margin-bottom: 10px;
        }
        p {
            font-size: 13.5px;
            line-height: 1.6;
            color: #b8a29a;
            margin-bottom: 24px;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: 999px;
            background: linear-gradient(135deg, #d98f83, #c19a8c);
            color: #1c110f;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: filter 0.15s ease;
        }
        .btn-primary:hover { filter: brightness(1.07); }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">403 &middot; ACCESS RESTRICTED</div>
        <h1>Permission Required</h1>
        <p>{{ $exception->getMessage() ?: 'You do not have permission to view this section.' }}</p>
        @auth
            @if (auth()->user()->isSalonStaff())
                <a href="{{ route('portal.profile') }}" class="btn-primary">Return to My Profile</a>
            @else
                <a href="{{ auth()->user()->defaultLandingUrl() }}" class="btn-primary">Return to Workspace</a>
            @endif
        @else
            <a href="{{ route('login') }}" class="btn-primary">Sign In</a>
        @endauth
    </div>
</body>
</html>
