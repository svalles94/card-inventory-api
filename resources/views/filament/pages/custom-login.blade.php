<div class="flex items-center justify-center bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 relative overflow-hidden" style="min-height: auto; padding: 1.5rem 0;">
    <!-- Animated background -->
    <div class="absolute inset-0">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-purple-500/20 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-purple-500/10 rounded-full blur-3xl"></div>
    </div>

    <!-- Grid pattern overlay -->
    <div class="absolute inset-0 bg-[linear-gradient(to_right,#80808012_1px,transparent_1px),linear-gradient(to_bottom,#80808012_1px,transparent_1px)] bg-[size:24px_24px]"></div>

    <div class="relative z-10 w-full max-w-sm mx-auto px-6">
        <div class="bg-white/5 backdrop-blur-xl rounded-2xl shadow-2xl p-4 border-0">
            <div class="mb-3 text-center">
                <h1 class="text-2xl font-extrabold text-white">
                    <span class="bg-gradient-to-r from-purple-400 via-pink-400 to-purple-400 bg-clip-text text-transparent">
                        Inventory Architect
                    </span>
                </h1>
            </div>
            
            <form wire:submit="authenticate" class="space-y-2.5">
                <div class="space-y-2.5">
                    {{ $this->form }}
                </div>

                <div class="flex items-center justify-between text-xs pt-0">
                    <label class="flex items-center gap-1.5 cursor-pointer group">
                        <input type="checkbox" class="w-3 h-3 rounded border-slate-600 bg-slate-800 text-purple-600 focus:ring-purple-500 focus:ring-offset-slate-900">
                        <span class="text-slate-300 group-hover:text-white transition-colors">Remember me</span>
                    </label>
                    <a href="#" class="text-purple-400 hover:text-purple-300 transition-colors font-medium">
                        Forgot password?
                    </a>
                </div>

                <button wire:click="authenticate" type="button" class="sign-in-btn w-full text-white font-semibold py-2 px-4 rounded-xl shadow-lg text-sm mt-2" style="background: linear-gradient(to right, #38bdf8, #3b82f6) !important; border: none !important; color: white !important; transition: all 0.3s ease !important; cursor: pointer !important;">
                    Sign In
                </button>
            </form>
        </div>
    </div>

    <style>
        @keyframes pulse {
            0%, 100% { 
                opacity: 0.4;
                transform: scale(1);
            }
            50% { 
                opacity: 0.6;
                transform: scale(1.1);
            }
        }
        .animate-pulse {
            animation: pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        .delay-1000 {
            animation-delay: 2s;
        }
        
        /* Custom input styling - compact and borderless */
        input[type="email"],
        input[type="password"] {
            background: rgba(15, 23, 42, 0.4) !important;
            border: none !important;
            border-radius: 0.5rem !important;
            padding: 0.5rem 0.75rem !important;
            color: white !important;
            transition: all 0.2s ease !important;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1) !important;
            font-size: 0.875rem !important;
        }
        input[type="email"]:focus,
        input[type="password"]:focus {
            background: rgba(15, 23, 42, 0.6) !important;
            box-shadow: 0 0 0 2px rgba(147, 51, 234, 0.3), inset 0 1px 2px rgba(0, 0, 0, 0.1) !important;
            outline: none !important;
        }
        input[type="email"]::placeholder,
        input[type="password"]::placeholder {
            color: rgba(148, 163, 184, 0.5) !important;
        }
        label {
            color: rgba(226, 232, 240, 0.8) !important;
            font-weight: 500 !important;
            font-size: 0.75rem !important;
            margin-bottom: 0.25rem !important;
        }
        
        /* Reduce form spacing */
        .space-y-2\.5 > * + * {
            margin-top: 0.5rem !important;
        }
        .space-y-3 > * + * {
            margin-top: 0.5rem !important;
        }
        
        /* Custom button hover effect */
        .sign-in-btn:hover {
            background: linear-gradient(to right, #0ea5e9, #2563eb) !important;
            background-image: linear-gradient(to right, #0ea5e9, #2563eb) !important;
            box-shadow: 0 4px 12px rgba(56, 189, 248, 0.3) !important;
            transform: translateY(-1px) scale(1.01) !important;
        }
        
        /* Force button styling - target all possible button selectors */
        button[type="submit"],
        button[wire\\:click="authenticate"],
        button[type="button"],
        .fi-btn,
        .fi-btn-primary,
        [type="submit"],
        form button,
        .fi-ac-action {
            background: linear-gradient(to right, #38bdf8, #3b82f6) !important;
            background-image: linear-gradient(to right, #38bdf8, #3b82f6) !important;
            border: none !important;
            color: white !important;
            transition: all 0.3s ease !important;
            cursor: pointer !important;
        }
        button[type="submit"]:hover,
        button[wire\\:click="authenticate"]:hover,
        button[type="button"]:hover,
        .fi-btn:hover,
        .fi-btn-primary:hover,
        [type="submit"]:hover,
        form button:hover,
        .fi-ac-action:hover {
            background: linear-gradient(to right, #0ea5e9, #2563eb) !important;
            background-image: linear-gradient(to right, #0ea5e9, #2563eb) !important;
            box-shadow: 0 4px 12px rgba(56, 189, 248, 0.3) !important;
            transform: translateY(-1px) scale(1.01) !important;
            transition: all 0.3s ease !important;
        }
    </style>
</div>
