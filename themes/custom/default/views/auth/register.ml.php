<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - MonkeysCMS</title>
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
    <div class="w-full max-w-lg animate-float">
        <!-- Logo Area -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white tracking-tight">Join monkeysCMS</h1>
            <p class="text-slate-400 mt-2 text-sm font-light">Start building your digital empire today.</p>
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
            <?php endif; ?>
            
            <form method="POST" action="/register" class="space-y-5">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                
                <div class="space-y-4">
                    <div class="input-group">
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1 uppercase tracking-wider">Email Address</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" required 
                               class="w-full px-4 py-3 rounded-xl text-slate-200 placeholder-slate-600 focus:text-white"
                               placeholder="name@company.com">
                        <?php if (isset($errors['email'])): ?>
                            <p class="text-red-400 text-xs mt-1.5 flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <?= htmlspecialchars($errors['email']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                
                    <div class="grid grid-cols-2 gap-4">
                        <div class="input-group">
                            <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1 uppercase tracking-wider">Username</label>
                            <input type="text" name="username" value="<?= htmlspecialchars($old['username'] ?? '') ?>" required 
                                   class="w-full px-4 py-3 rounded-xl text-slate-200 placeholder-slate-600 focus:text-white"
                                   placeholder="johndoe">
                            <?php if (isset($errors['username'])): ?>
                                <p class="text-red-400 text-xs mt-1.5 flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <?= htmlspecialchars($errors['username']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="input-group">
                            <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1 uppercase tracking-wider">Display Name</label>
                            <input type="text" name="display_name" value="<?= htmlspecialchars($old['display_name'] ?? '') ?>" 
                                   class="w-full px-4 py-3 rounded-xl text-slate-200 placeholder-slate-600 focus:text-white"
                                   placeholder="John Doe">
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1 uppercase tracking-wider">Password</label>
                        <input type="password" name="password" required 
                               class="w-full px-4 py-3 rounded-xl text-slate-200 placeholder-slate-600 focus:text-white"
                               placeholder="••••••••">
                        <p class="text-[10px] text-slate-500 mt-1 ml-1">Min 8 chars, 1 uppercase, 1 number</p>
                        <?php if (isset($errors['password'])): ?>
                            <p class="text-red-400 text-xs mt-1.5 flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <?= htmlspecialchars($errors['password']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="input-group">
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1 uppercase tracking-wider">Confirm Password</label>
                        <input type="password" name="password_confirmation" required 
                               class="w-full px-4 py-3 rounded-xl text-slate-200 placeholder-slate-600 focus:text-white"
                               placeholder="••••••••">
                        <?php if (isset($errors['password_confirmation'])): ?>
                            <p class="text-red-400 text-xs mt-1.5 flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <?= htmlspecialchars($errors['password_confirmation']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-brand-600 to-blue-600 hover:from-brand-500 hover:to-blue-500 text-white font-semibold py-3.5 px-4 rounded-xl shadow-lg shadow-brand-500/20 active:scale-[0.98] transition-all duration-200 border border-transparent hover:border-white/10 flex items-center justify-center group">
                        Create Account
                        <svg class="w-5 h-5 ml-2 -mr-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                    </button>
                </div>
            </form>

            <div class="mt-8 text-center border-t border-slate-700/50 pt-6">
                <p class="text-sm text-slate-400">
                    Already have an account? 
                    <a href="/login" class="font-medium text-brand-400 hover:text-brand-300 transition-colors relative inline-block after:content-[''] after:absolute after:w-full after:scale-x-0 after:h-px after:bottom-0 after:left-0 after:bg-brand-400 after:origin-bottom-right hover:after:scale-x-100 hover:after:origin-bottom-left after:transition-transform after:duration-300">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
