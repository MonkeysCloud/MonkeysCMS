<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - MonkeysCMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                            950: '#082f49',
                        }
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #0f172a;
            background-image: 
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
        }
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .input-group input {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        .input-group input:focus {
            background: rgba(15, 23, 42, 0.8);
            border-color: #38bdf8;
            box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
            outline: none;
        }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4 selection:bg-brand-500 selection:text-white overflow-hidden relative">

    <!-- Decorative Elements -->
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden -z-10">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-purple-500/10 rounded-full blur-[100px] animate-pulse-slow"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-blue-500/10 rounded-full blur-[100px] animate-pulse-slow" style="animation-delay: 2s;"></div>
    </div>

    <!-- Main Container -->
    <div class="w-full max-w-md animate-float">
        <!-- Logo Area -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-brand-600 to-purple-600 shadow-lg shadow-brand-500/30 mb-4 ring-1 ring-white/20">
                <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-white tracking-tight">MonkeysCMS</h1>
            <p class="text-slate-400 mt-2 text-sm font-light">Welcome back, creator.</p>
        </div>

        <!-- Glass Card -->
        <div class="glass-panel rounded-2xl p-8 relative overflow-hidden group">
            <!-- Top Light Effect -->
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-1/2 h-[1px] bg-gradient-to-r from-transparent via-blue-400 to-transparent opacity-50"></div>

            <?php if (!empty($error)): ?>
                <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm flex items-center shadow-[0_0_15px_rgba(239,68,68,0.1)] backdrop-blur-sm">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php elseif (!empty($success)): ?>
                <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center shadow-[0_0_15px_rgba(16,185,129,0.1)] backdrop-blur-sm">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/login" class="space-y-6">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                
                <div class="space-y-4">
                    <div class="input-group">
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1 uppercase tracking-wider">Email Address</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path></svg>
                            </span>
                            <input type="email" name="email" required autocomplete="email" placeholder="name@company.com" 
                                class="w-full pl-11 pr-4 py-3 rounded-xl text-slate-200 placeholder-slate-600 focus:text-white">
                        </div>
                    </div>

                    <div class="input-group">
                        <div class="flex items-center justify-between mb-1.5 ml-1">
                            <label class="block text-xs font-medium text-slate-400 uppercase tracking-wider">Password</label>
                            <a href="/password/forgot" class="text-xs text-brand-400 hover:text-brand-300 transition-colors">Forgot password?</a>
                        </div>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            </span>
                            <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••" 
                                class="w-full pl-11 pr-4 py-3 rounded-xl text-slate-200 placeholder-slate-600 focus:text-white">
                        </div>
                    </div>
                </div>

                <div class="flex items-center">
                    <label class="flex items-center cursor-pointer group">
                        <div class="relative flex items-center">
                            <input type="checkbox" name="remember" class="peer sr-only">
                            <div class="w-5 h-5 border border-slate-600 rounded bg-slate-800 peer-checked:bg-brand-500 peer-checked:border-brand-500 transition-all shadow-inner"></div>
                            <svg class="absolute w-3 h-3 text-white left-1 opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <span class="ml-2 text-sm text-slate-400 group-hover:text-slate-300 transition-colors">Remember device</span>
                    </label>
                </div>

                <button type="submit" 
                    class="w-full bg-gradient-to-r from-brand-600 to-blue-600 hover:from-brand-500 hover:to-blue-500 text-white font-semibold py-3.5 px-4 rounded-xl shadow-lg shadow-brand-500/20 active:scale-[0.98] transition-all duration-200 border border-transparent hover:border-white/10 flex items-center justify-center group">
                    Sign In
                    <svg class="w-5 h-5 ml-2 -mr-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                </button>
            </form>

            <div class="mt-8 text-center border-t border-slate-700/50 pt-6">
                <p class="text-sm text-slate-400">
                    Don't have an account? 
                    <a href="/register" class="font-medium text-brand-400 hover:text-brand-300 transition-colors relative inline-block after:content-[''] after:absolute after:w-full after:scale-x-0 after:h-px after:bottom-0 after:left-0 after:bg-brand-400 after:origin-bottom-right hover:after:scale-x-100 hover:after:origin-bottom-left after:transition-transform after:duration-300">Create one now</a>
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <p class="text-center text-xs text-slate-600 mt-8 font-light">
            &copy; 2025 MonkeysCloud &bull; <a href="#" class="hover:text-slate-500 transition-colors">Privacy</a> &bull; <a href="#" class="hover:text-slate-500 transition-colors">Terms</a>
        </p>
    </div>
</body>
</html>
