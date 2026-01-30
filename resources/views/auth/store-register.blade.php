<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register Your Store - Inventory Architect</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white/5 backdrop-blur-xl rounded-2xl shadow-2xl p-8 border border-white/10">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-extrabold text-white mb-2">
                    <span class="bg-gradient-to-r from-purple-400 via-pink-400 to-purple-400 bg-clip-text text-transparent">
                        Register Your Store
                    </span>
                </h1>
                <p class="text-slate-300 text-sm">
                    Create your account and set up your store
                </p>
            </div>

            <form method="POST" action="{{ route('store.register') }}" class="space-y-6">
                @csrf

                <!-- User Information -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-white mb-3">Account Information</h3>
                    
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-300 mb-1">
                            Your Name
                        </label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               value="{{ old('name') }}" 
                               required 
                               autofocus
                               class="w-full px-4 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        @error('name')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-300 mb-1">
                            Email Address
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="{{ old('email') }}" 
                               required 
                               autocomplete="email"
                               class="w-full px-4 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        @error('email')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-300 mb-1">
                            Password
                        </label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required 
                               autocomplete="new-password"
                               class="w-full px-4 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        @error('password')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-slate-300 mb-1">
                            Confirm Password
                        </label>
                        <input type="password" 
                               id="password_confirmation" 
                               name="password_confirmation" 
                               required 
                               autocomplete="new-password"
                               class="w-full px-4 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                </div>

                <!-- Store Information -->
                <div class="space-y-4 pt-4 border-t border-slate-700">
                    <h3 class="text-lg font-semibold text-white mb-3">Store Information</h3>
                    
                    <div>
                        <label for="store_name" class="block text-sm font-medium text-slate-300 mb-1">
                            Store Name <span class="text-red-400">*</span>
                        </label>
                        <input type="text" 
                               id="store_name" 
                               name="store_name" 
                               value="{{ old('store_name') }}" 
                               required
                               placeholder="My Card Shop"
                               class="w-full px-4 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        @error('store_name')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="store_address" class="block text-sm font-medium text-slate-300 mb-1">
                            Store Address (Optional)
                        </label>
                        <textarea id="store_address" 
                                  name="store_address" 
                                  rows="2"
                                  placeholder="123 Main St, City, State ZIP"
                                  class="w-full px-4 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">{{ old('store_address') }}</textarea>
                        @error('store_address')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="store_phone" class="block text-sm font-medium text-slate-300 mb-1">
                            Phone Number (Optional)
                        </label>
                        <input type="tel" 
                               id="store_phone" 
                               name="store_phone" 
                               value="{{ old('store_phone') }}" 
                               placeholder="(555) 123-4567"
                               class="w-full px-4 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        @error('store_phone')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white font-semibold py-3 px-4 rounded-lg shadow-lg transition-all duration-200 transform hover:scale-[1.02]">
                        Create Account & Store
                    </button>
                </div>

                <div class="text-center pt-4">
                    <p class="text-sm text-slate-400">
                        Already have an account?
                        <a href="{{ route('filament.store.auth.login') }}" class="text-purple-400 hover:text-purple-300 font-medium">
                            Sign in
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

